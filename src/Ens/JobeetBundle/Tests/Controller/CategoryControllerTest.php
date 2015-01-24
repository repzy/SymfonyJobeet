<?php

namespace Ens\JobeetBundle\Tests\Controller;

use \Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use \Symfony\Bundle\FrameworkBundle\Console\Application;
use \Symfony\Component\Console\Output\NullOutput;
use \Symfony\Component\Console\Input\ArrayInput;
use \Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use \Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use \Doctrine\Bundle\DoctrineBundle\Command\Proxy\CreateSchemaDoctrineCommand;

class CategoryControllerTest extends WebTestCase {
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

        //create shema
        $command = new CreateSchemaDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:schema:create'
        ));
        $command->run($input, new NullOutput());

        //get the Entity Manager
        $this->em = static::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        //load fixtures
        $client = static::createClient();
        $loader = new \Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader($client->getContainer());
        $loader->loadFromDirectory(static::$kernel->locateResource('@EnsJobeetBundle/DataFixteres/ORM'));
        $purger = new \Doctrine\Common\DataFixtures\Purger\ORMPurger($this->em);
        $executor = new \Doctrine\Common\DataFixtures\Executor\ORMExecutor($this->em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function tetsShow()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/category/index');
        $this->assertEquals('Ens\JobeetBundle\Controller\CategoryController::showAction', $client->getRequest()->attributes->get('_controller'));
        $this->assertTrue(200 === $client->getResponse()->getStatusCode());

    }
}
