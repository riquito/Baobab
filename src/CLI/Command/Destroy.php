<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Destroy extends BaobabCommand
{
    protected function configure()
    {
        $this
            ->setName('destroy')
            ->setDescription('Delete procedures (and forest as option) from database')
            ->addArgument(
                'forestName',
                InputArgument::REQUIRED,
                'Specify forest name (table and procedures of table) to be destroyed'
            )
            ->addOption(
                'destroyForest',
                '-d',
                InputOption::VALUE_NONE,
                'Specyfy if you want to delete also a table'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $c = $this->container;
        $c['forestName'] = $input->getArgument('forestName');

        $c['baobab']->destroy($input->getOption('destroyForest'));
        if ($input->getOption('destroyForest')) {
            $output->writeln('Forest and procedures '.$input->getArgument('forestName').' destroyed');
        } else{
            $output->writeln('Procedures of forest '.$input->getArgument('forestName').' destroyed');
        }
    }
}