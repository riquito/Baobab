<?php
namespace Baobab\CLI;

use Baobab\CLI\Command;
use Symfony\Component\Console\Application as BaseApplication;
use Pimple\Container as Pimple;

class Forest extends BaseApplication {
    /**
     * Calculator constructor.
     */
    public function __construct(Pimple $container) {
        parent::__construct('
Welcome to BaobabPDO CLI Interface
 _                 _  _  _ 
|_) _  _ |_  _ |_ |_)| \/ \
|_)(_|(_)|_)(_||_)|  |_/\_/
', '2.0.0');
 		
        $this->addCommands(array(
            new Command\Build($container),
            new Command\Destroy($container),
            new Command\Clean($container),
            new Command\CleanAll($container),
            new Command\Import($container),
            new Command\Export($container),
            new Command\ListTrees($container),
            new Command\DisplayTree($container)
        ));
    }
}