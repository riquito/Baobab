<?php
namespace Baobab\CLI\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Baobab\Forest as Forest;

class Cut extends BaobabCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('cut')
            ->setDescription('Delete trees from specyfied forest')
            ->setHelp('
This command allows you to delete trees form a specyfied forest (table).
You can delete one tree, many trees or all trees (default).
You can get list of trees with ids using command <info>show</info>.

Example of use:
<info>bin/forest cut Folders -tree=1</info> // delete tree with id 1
<info>bin/forest cut Folders -tree=2 -tree=3</info> // delete trees with id 1 and 2
<info>bin/forest cut Folders -tree=all</info> // delte ALL trees from a forest (default)
<info>bin/forest cut Folders</info> // shortcut for deleting ALL trees
')
            ->addOption(
                'tree',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Specify tree to cut',
                array('all')
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $c = $this->container;
        $forestName = $input->getArgument('forest-name');

        $helper = $this->getHelper('question');
        if (!Forest::forestExists($c['pdoInstance'], $forestName)){
            $output->writeln('You can not cut trees form unexistend forest!');
        } else {
            if ($input->getOption('tree') === array('all')) {
                $question = new ConfirmationQuestion(
                    PHP_EOL."Are you sure to cut ALL trees from $forestName ? <comment>[yes/no]</comment>".PHP_EOL,
                    false
                    );

                if (!$helper->ask($input, $output, $question)) {
                    return;
                }
                Forest::CleanAll($c['pdoInstance'], $forestName);
                $output->writeln('All trees form a '.$forestName.' deleted!');
            } elseif(is_array($input->getOption('tree'))) {
                foreach ($input->getOption('tree') as $treeId) {
                    $output->writeln('Tree:'.$treeId);
                }
            } else {

            }
        }
    }
}