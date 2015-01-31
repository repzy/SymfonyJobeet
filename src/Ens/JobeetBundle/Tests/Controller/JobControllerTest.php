<?php

namespace Ens\JobeetBundle\Tests\Controller;

use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\CreateSchemaDoctrineCommand;

class JobControllerTest extends WebTestCase
{
    private $em;
    private $application;

    public function setUp()
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();

        $this->application = new Application(static::$kernel);

        //drop the database
        $command = new DropDatabaseDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:database:drop',
            '--force' => true
        ));
        $command->run($input, new NullOutput());

        //we have to close the connection after dropping the database so we dont get "No database selected" error
        $connection = $this->application->getKernel()->getContainer()->get('doctrine')->getConnection();
        if($connection->isConnected()) {
            $connection->close();
        }

        //create the database
        $command = new CreateDatabaseDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:database:create'
        ));
        $command->run($input, new NullOutput());

        //create schema
        $command = new CreateSchemaDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:schema:create'
        ));
        $command->run($input, new NullOutput());

        //get Entity Manager
        $this->em = static::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        //load Fixtures
        $client = static::createClient();
        $loader = new \Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader($client->getContainer());
        $loader->loadFromDirectory(static::$kernel->locateResource('@EnsJobeetBundle/DataFixtures/ORM'));
        $purger = new \Doctrine\Common\DataFixtures\Purger\ORMPurger($this->em);
        $executor = new \Doctrine\Common\DataFixtures\Executor\ORMExecutor($this->em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function GetMostRecentProgramingJob()
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT j FROM IbwJobeetBundle:Job j LEFT JOIN j.category WHERE c.slug = :slug
        AND j.expires_at = :date ORDER BY j.created_at DESC');
        $query->setParameter('slug', 'programing');
        $query->setParameter('date', date('Y-m-d H:i:s', time()));
        $query->setMaxResults(1);

        return $query->getSingleResult();
    }

    public function GetExpiredJob()
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT j FROM EnsJobeetBundle:Job j WHERE j.expires_at = :date');
        $query->setParameter('date', date('Y-m-d H:i:s', time()));
        $query->setMaxResults(1);

        return $query->getSingleResult();
    }

    public function testIndex()
    {
        //get the custom parameters from app config.yml
        $kernel = static::$kernel;
        $kernel->boot();
        $max_jobs_on_homepage = $kernel->getContainer()->getParameter('max_jobs_on_homepage');

        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $this->assertEquals('Ens\JobeetBundle\Controller\JobController::indexAction', $client->getRequest()->attributes->get('_controller'));

        //expired jobs not listed
        $this->assertTrue($crawler->filter('.jobs td.position:contains("Expired")')->count() == 0 );

        //only $max_jobs_on_homepage jobs are listed for a category
        $this->assertTrue($crawler->filter('.category_programing tr')->count() <= $max_jobs_on_homepage);
        $this->assertTrue($crawler->filter('.category_design .more_jobs')->count() == 0);
        $this->assertTrue($crawler->filter('.category_programing .more_jobs')->count() == 1);

        //jobs sorted by date
        $this->assertTrue($crawler->filter('.category_programing tr')->first()->filter(sprintf('a[href*="/%d"]', $this->GetMostRecentProgramingJob()->getId()))->count() == 1);

        //each job on homepage is clickable and give detailed information
        $job = $this->GetMostRecentProgramingJob();
        $link = $crawler->selectLink('Web Developer')->first()->link();
        $crawler = $client->click($link);
        $this->assertEquals('Ibw\JobeetBundle\Controller\JobController::showAction', $client->getRequest()->attributes->get('_controller'));
        $this->assertEquals($job->getCompanySlug(), $client->getRequest()->attributes->get('company'));
        $this->assertEquals($job->getLocationSlug(), $client->getRequest()->attributes->get('location'));
        $this->assertEquals($job->getPositionSlug(), $client->getRequest()->attributes->get('position'));
        $this->assertEquals($job->getId(), $client->getRequest()->attributes->get('id'));

        //a non-existent job forwards user to 404
        $crawler = $client->request('GET', '/job/foo-inc/milano-italy/0/painter');
        $this->assertTrue(404 === $client->getResponse()->getStatusCode());

        //an expired-job page forwards user to a 404
        $crawler = $client->request('GET', sprintf('/job/sensio-labs/paris-france/%d/web-developer', $this->GetExpiredJob()->getId()));
        $this->assertTrue(404 === $client->getResponse()->getStatusCode());
    }

    public function createJob($values = array(), $publish = false)
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/job/new');
        $form = $crawler->selectButton('Preview your job')->form(array(
            'job[company]' => 'Sensio Labs',
            'job[url]' => 'http://www.sensio.com',
            'job[file]' => __DIR__.'/../../../../../web/bundles/ensjobeet/images/sensio-labs.gif',
            'job[position]' => 'Developer',
            'job[location]' => 'Atlanta, USA',
            'job[description]' => 'You will work with symfony to develop web sites for our customers',
            'job[how_to_apply]' => 'Send me an email',
            'job[email]' => 'for_a_job@example.com',
            'job[is_public]' => false,
        ), $values);

        $client->submit($form);
        $client->followRedirect();

        if($publish) {
            $crawler = $client->getCrawler();
            $form = $crawler->selectButton('Publish')->form();
            $client->submit($form);
            $client->followRedirect();
        }

        return $client;
    }

    public function getJobByPosition($position)
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT j FROM EnsJobeetBundle:Job j WHERE j.position =:position');
        $query->setParameter('position', $position);
        $query->setMaxResults(1);

        return $query->getSingleResult();
    }

    public function testJobForm()
    {
        $client = static::createClient();
        $crawler =  $client->request('GET', '/job/new');
        $this->assertEquals('Ens\JobeetBundle\Controller\JobController:newAction', $client->getRequest()->attributes->get('_controller'));
        $form = $crawler->selectButton('Preview your job')->form(array(
            'job[company]' => 'Sensio Labs',
            'job[url]' => 'http://www.sensio.com',
            'job[file]' => __DIR__.'/../../../../../web/bundles/ensjobeet/images/sensio-labs.gif',
            'job[position]' => 'Developer',
            'job[location]' => 'Atlanta, USA',
            'job[description]' => 'You will work with symfony to develop web sites for our customers',
            'job[how_to_apply]' => 'Send me an email',
            'job[email]' => 'for_a_job@example.com',
            'job[is_public]' => false,
        ));

        $client->submit($form);
        $this->assertEquals('Ens\JobeetBundle\Controller\JobController::createAction', $client->getRequest()->attributes->get('_controller'));
        $client->followRedirect();
        $this->assertEquals('Ens\JobeetBundlr\Controller\JobController:previewAction', $client->getRequest()->attributes->get('_controller'));

        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT count(j.id) from EnsJobeetBundle:Job j WHERE j.location = :location AND j.is_activated IS NULL AND j.is_public = 0');
        $query->setParameter('location', 'Atlanta, USA');
        $this->assertTrue(0 < $query->getSingleScalarResult());

        $crawler = $client->request('GET', '/job/new');
        $form = $crawler->selectButton('Preview your job')->form(array(
            'job[company]' => 'Sensio Labs',
            'job[position]' => 'Developer',
            'job[location]' => 'Atlanta, USA',
            'job[email]' => 'not.an.email'
        ));
        $crawler = $client->submit($form);

        //check if we have 3 errors
        $this->assertTrue($crawler->filter('.error_list')->count() == 3 );
        //check if we have error on job_description field
        $this->assertTrue($crawler->filter('#job_description')->siblings()->first()->filter('.error_list')->count() == 1);
        //check if we have error on job_how_to_apply field
        $this->assertTrue($crawler->filter('#job_how_to_apply')->siblings()->first()->filter('.error_list')->count() == 1);
        //check if we have error on job_email field
        $this->assertTrue($crawler->filter('#job_email')->siblings()->first()->filter('.error_list')->count() == 1);
    }

    public function testPublishJob()
    {
        $client = $this->createJob(array('job[position]' => 'F001'));
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Publish')->form();
        $client->submit($form);

        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT count(j.id) FROM EnsJobeetBundle:Job j WHERE j.position =:position AND j.is_activated = 1');
        $query->setParameter('position', 'F001');
        $this->assertTrue(0 < $query->getSingleScalarResult());
    }

    public function testDeleteJob()
    {
        $client = $this->createJob(array('job[position]' => 'F002'));
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Delete')->form();
        $client->submit($form);

        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT count(j.id) FROM EnsJobeetBundle:Job j WHERE j.position =: position');
        $query->setParameter('position', 'F002');
        $this->assertTrue(0 == $query->getSingleScalarResult());
    }

    public function testEditJob()
    {
        $client = $this->createJob(array('job[position]' => 'F003'), true);
        $crawler = $client->getCrawler();
        $crawler = $client->request('GET', sprintf('/job/%s/edit', $this->getJobByPosition('F003')->getToken()));
        $this->assertTrue(404 === $client->getResponse()->getStatusCode());
    }

    public function testExtendJob()
    {
        //A job validity cannot be extendet before the job expires soon
        $client = $this->createJob(array('job[position]' => 'F004'), true);
        $crawler = $client->getCrawler();
        $this->assertTrue($crawler->filter('input[type=submit]:contains("Extend")')->count() == 0);

        //A job validity can be extended when the job expires soon
        //Create a new F005 job
        $client = $this->createJob(array('job[position]' => 'F005'), true);
        //Get the jib and change the expires date to today
        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $job = $em->getRepository('EnsJobeetBundle:Job')->findOneByPosition('F005');
        $job->setExpiresAt(new \DateTime());
        $em->flush();

        //Go to the preview page to extend the job
        $crawler = $client->request('GET', sprintf('/job/%s/%s/%s/%s', $job->getCompanySlug(), $job->getLocationSlug(), $job->getToken(), $job->getPositionSlug()));
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Extend')->form();
        $client->submit($form);

        //Reload the form from db
        $job= $this->getJobByPosition('F005');

        //Check the expiration date
        $this->assertTrue($job->getExpiresAt()->format('y/m/d') == date('y/m/d', time() + 86400 * 30));
    }
}
