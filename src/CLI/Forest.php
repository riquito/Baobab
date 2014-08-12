<?php
namespace Baobab\CLI;

use Baobab\CLI\Command;
use Symfony\Component\Console\Application as BaseApplication;
use Pimple\Container as Pimple;

class Forest extends BaseApplication {
    public function __construct(Pimple $container) {
        parent::__construct('
Welcome to BaobabPDO CLI Interface
 _                 _  _  _ 
|_) _  _ |_  _ |_ |_)| \/ \
|_)(_|(_)|_)(_||_)|  |_/\_/
', '2.0.0');
 		
        $this->addCommands(array(
            new Command\Seed($container),
            new Command\Cut($container),
            new Command\Burn($container),
            // new Command\Import($container),
            // new Command\Export($container),
            // new Command\Show($container)
        ));
    }
}