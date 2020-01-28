<?php

namespace RokkaCli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestTestCommand extends BaseRokkaCliCommand
{
    protected function configure()
    {
        $this
            ->setName('test:test')
            ->setDescription('Empty test command')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return 0;
    }
}
