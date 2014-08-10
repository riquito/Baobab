<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListTrees extends BaobabCommand
{
    protected function configure()
    {
        $this
            ->setName('listTrees')
            ->setDescription('List all trees form a forest')
            ->addOption(
                'forestName',
                '-f',
                InputOption::VALUE_REQUIRED,
                'Specify forest name (table to list from)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $input->getOption('forestName');
        $output->writeln($text);
    }
}