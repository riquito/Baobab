<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Baobab\Forest as Forest;

class Seed extends BaobabCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('seed')
            ->setDescription('Add a new table (forest) and all required procedures to a database')
            ->setHelp('
Create a new table (forest) and add all required procedures to a database.
Helps deploy features based on BaobabPDO to production servers 
where standard PDO database connection don\'t have privilages to create 
new tables or stored procedures that are required by BaobabPDO to work.

Example of use: <info>bin/forest seed Folders</info>
You can reverse this command by: <info>bin/forest burn Folders -f</info>
');
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $c = $this->container;
        $forestName = $input->getArgument('forest-name');
        
        //New forest - forest - seeded:
        // -- Forest structure
        // Procedures added:
        // -- List

        if(Forest::build($c['pdoInstance'], $forestName)){
            $output->writeln('Forest '.$input->getArgument('forest-name').' builded correctly');
        } else {
            $output->writeln('Forest '.$input->getArgument('forest-name').' already exist, build skipped');
        }
    }
}