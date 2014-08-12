<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Baobab\Forest as Forest;

class Burn extends BaobabCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('burn')
            ->setDescription('Delete stored procedures and/or forest (table) from a database')
            ->setHelp('
This command allows you to delete all procedures attached to a forest.
As an option you can delete also a whole forest (table) with all stored data.
Deleting procedures and a forest is reverse of using command <info>seed</info>.
If you want to delete only trees form a table you can use command <info>cut</info>.

Example of use: <info>bin/forest burn Folders -f</info>
')
            ->addOption(
                'forest',
                '-f',
                InputOption::VALUE_NONE,
                'Specyfy if you want to delete a forest (table)'
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $c = $this->container;
        $forestName = $input->getArgument('forest-name');
        $helper = $this->getHelper('question');

        if (!Forest::forestExists($c['pdoInstance'], $forestName)){
            $output->writeln('You can not burn unexistend forest!');
        } else {
            $question = new ConfirmationQuestion(
                PHP_EOL."Are you sure to burn $forestName ? <comment>[yes/no]</comment>".PHP_EOL,
                false
                );

            if (!$helper->ask($input, $output, $question)) {
                return;
            }
            Forest::Destroy($c['pdoInstance'], $forestName, $input->getOption('forest'));
            if ($input->getOption('forest')) {
                $output->writeln('Forest and procedures '.$forestName.' burned!');
            } else {
                $output->writeln('Procedures of forest '.$forestName.' burned!');
            }
        }
    }
}