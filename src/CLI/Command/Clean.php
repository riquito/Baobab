<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Clean extends BaobabCommand
{
    protected function configure()
    {
        $this
            ->setName('clean')
            ->setDescription('Delete a tree from database')
            ->addOption(
                'forestName',
                '-f',
                InputOption::VALUE_REQUIRED,
                'Specify forest name (table to be used)'
            )
            ->addOption(
                'treeId',
                '-t',
                InputOption::VALUE_REQUIRED,
                'Specify treeId'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $input->getOption('forestName');
        $output->writeln($text);
    }
}