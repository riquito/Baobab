<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Export extends BaobabCommand
{
    protected function configure()
    {
        $this
            ->setName('export')
            ->setDescription('Export a tree from database')
            ->addOption(
                'forestName',
                '-f',
                InputOption::VALUE_REQUIRED,
                'Specify forest name (table to be used)'
            )
            ->addOption(
                'treeId',
                '-t',
                InputOption::VALUE_OPTIONAL,
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