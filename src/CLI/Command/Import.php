<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends BaobabCommand
{
    protected function configure()
    {
        $this
            ->setName('import')
            ->setDescription('Import a tree to a forest')
            ->addOption(
                'forestName',
                '-f',
                InputOption::VALUE_REQUIRED,
                'Specify forest name (table to be used)'
            )
            ->addOption(
                'data',
                '-d',
                InputOption::VALUE_REQUIRED,
                'JSON data to be used'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $input->getOption('forestName');
        $output->writeln($text);
    }
}