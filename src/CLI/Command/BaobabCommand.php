<?php
namespace Baobab\CLI\Command;

use Pimple\Container as Pimple;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BaobabCommand extends BaseCommand
{
    protected $container;
    
    function __construct(Pimple $container){
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(){
    	$this
    	    ->addArgument(
    	        'forest-name',
    	        InputArgument::REQUIRED,
    	        'Specify forest name (table to be used by a command)'
    	    );
    }
}