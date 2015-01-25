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
        $this->assertEquals('Ens\JobbetBundle\Controller\JobController::indexAction', $client->getRequest()->attributes->get('_controller'));

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
}
