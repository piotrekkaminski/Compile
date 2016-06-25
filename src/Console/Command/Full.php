<?php
namespace M2t\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Full extends Command
{
    protected function configure()
    {
        $this->setName('full')
             ->setDescription('Download, translate & package')
             ->addArgument('lang', InputArgument::IS_ARRAY, 'What Language(s) need to be processed?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $langChoice = $input->getArgument('lang');
        if (count($langChoice) == 0) {
            $langChoice = ['all'];
        }

        $command = $this->getApplication()->find('package');

        $arguments = array(
            'command'     => 'package',
            'lang'        => $langChoice,
            '--translate' => true,
            '--download'  => true,
        );

        $downloadInput = new ArrayInput($arguments);
        $returnCode    = $command->run($downloadInput, $output);
    }
}
