<?php
namespace Ens\JobeetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ens\JobeetBundle\Entity\Job;

class JobeetCleanupCommand {

    protected function configure()
    {
        $this
            ->setName('ens.jobbet.cleanup')
            ->setDescription('Cleanup Jobeet database')
            ->addArgument('days', InputArgument::OPTIONAL, 'The eamil', 90)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $days = $input->getArgument('days');

        $em = $this->getContainer()->get('doctrine')->getManager();
        $nb = $em->getRepository('EnsJobeetBundle:Job')->cleanup($days);

        $output->writeln(sprintf('Removed %s stale jobs', $nb));
    }
}
?>