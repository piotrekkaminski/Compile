<?php
namespace M2t\Console\Command;

use League\Csv\Reader;
use League\Csv\Writer;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use M2t\Data\Branches;
use M2t\Data\Language;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Translate extends Command
{
    protected $progressBar      = null;
    protected $sourceStrings;
    protected $translationFiles = [];
    protected $destStrings      = [];
    protected $ismaster         = false;
    protected $newStrings;
    protected $zip;
    protected $buildDir;
    protected $output;
    protected $branches         = [];
    protected $currentBranch;
    protected $languages        = [];

    protected function configure()
    {
        $this->setName('translate')
            ->setDescription('Translate the source with the zip contents')
            ->addArgument('lang', InputArgument::IS_ARRAY, 'What Languages need to be translated?')
            ->addOption('download', 'd', InputOption::VALUE_NONE, 'Download the translation first')
            ->addOption('branch', 'b', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'What branch(es) need to be translated?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->languages = Language::languageCodes();
        $this->branches  = Branches::branches();

        //Branch
        $branchChoice = $input->getOption('branch');
        if ($branchChoice) {
            foreach ($branchChoice as $key => $inputBranch) {
                $branchChoice[$key] = $inputBranch;
                if (!in_array($branchChoice[$key], $this->branches)) {
                    $output->writeln('<error>Branch not valid ' . $inputBranch . '</error>');
                    unset($branchChoice[$key]);
                }
            }
            if (count(array_values($branchChoice)) <= 0) {
                $output->writeln('<error>No valid branch found in your --branch input, exiting!</error>');
                exit(1);
            }
            $this->branches = array_values($branchChoice);
        }

        // run download first
        if ($input->getOption('download')) {
            $command = $this->getApplication()->find('download');

            $arguments = [
                'command'  => 'download',
                'lang'     => $input->getArgument('lang'),
                '--branch' => $this->branches
            ];

            $downloadInput = new ArrayInput($arguments);
            $returnCode    = $command->run($downloadInput, $output);
        }

        $this->output = $output;

        //check arguments
        $langChoice = $input->getArgument('lang');
        if ($langChoice) {
            foreach ($langChoice as $key => $inputLang) {
                $langChoice[$key] = $inputLang;
                if (!in_array($langChoice[$key], $this->languages)) {
                    $output->writeln('<comment>Input not found ' . $inputLang . '</comment>');
                    unset($langChoice[$key]);
                }
            }
            $langChoice = array_values($langChoice);
        }

        // ask question
        if (!$langChoice || count($langChoice) < 1) {
            $helper   = $this->getHelper('question');
            $question = new ChoiceQuestion('What Language do you want to translate?', $this->languages, 0);
            $question->setMultiselect(true);
            $question->setErrorMessage('Input %s is invalid.');

            $langChoice = $helper->ask($input, $output, $question);
        }

        if (in_array('all', $langChoice)) {
            unset($this->languages[0]);
            $langChoice = array_values($this->languages);
        }


        $output->writeln('<info></info>');
        $output->writeln('<info></info>');
        $output->writeln('<info>Translating ' . implode(', ', $langChoice) . ' for ' . implode(', ', $this->branches) . '</info>');


        foreach ($this->branches as $branch) {
            if (null == $this->buildDir) {
                $this->buildDir = new Filesystem(new Local(BUILD_DIR));
            }
            $this->buildDir->createDir($branch);
            $this->currentBranch = $branch;

            $output->writeln('<info></info>');
            $output->writeln('<info>Switch branch ' . $branch . '</info>');
            $this->readSourceTranslation();

            $output->writeln('<info>Translate & save for: ' . implode(', ', $langChoice) . '</info>');
            foreach ($langChoice as $key => $lang) {
                $output->writeln('<info>Start - read translated files ' . $lang . '</info>');
                $this->processTranslationFiles($lang);
            }
        }

        foreach ($this->branches as $branch) {
            $this->currentBranch = $branch;

            $output->writeln('<info></info>');
            $output->writeln('<info>Switch branch ' . $branch . '</info>');

            $output->writeln('<info>Translate & save for: ' . implode(', ', $langChoice) . '</info>');
            foreach ($langChoice as $key => $lang) {
                $output->writeln('<comment>Translate ' . $lang . '</comment>');
                $this->combine($lang);
                $output->writeln('<comment>Save ' . $lang . '</comment>');
                $this->save($lang);
            }
        }

        $this->currentBranch = 'master';
        $output->writeln('<info></info>');
        $output->writeln('<info>Switch branch master</info>');
        $output->writeln('<info>Translate & save for: ' . implode(', ', $langChoice) . '</info>');
        foreach ($langChoice as $key => $lang) {
            $output->writeln('<comment>Translate ' . $lang . '</comment>');
            $this->combine($lang);
            $output->writeln('<comment>Save ' . $lang . '</comment>');
            $this->save($lang);
        }
    }


    protected function processTranslationFiles($lang)
    {
        $csv = Reader::createFromPath(TRANSLATIONS_DIR . DIRECTORY_SEPARATOR . $this->currentBranch . DIRECTORY_SEPARATOR . $lang . '.csv');
        if (!isset($this->destStrings[$lang])) {
            $this->destStrings[$lang] = [];
        }
        $this->destStrings[$lang][$this->currentBranch] = [];
        foreach ($csv->fetchAll() as $line) {
            if (!isset($this->destStrings[$lang][$this->currentBranch][$line[2]])) {
                $this->destStrings[$lang][$this->currentBranch][$line[2]] = [];
            }
            if (!isset($this->destStrings[$lang][$this->currentBranch][$line[2]][$line[3]])) {
                $this->destStrings[$lang][$this->currentBranch][$line[2]][$line[3]] = [];
            }
            $this->destStrings[$lang][$this->currentBranch][$line[2]][$line[3]][] = $line;
        }
    }

    protected function readSourceTranslation()
    {
        $csv                                                  = Reader::createFromPath(SOURCE_DIR . DIRECTORY_SEPARATOR . $this->currentBranch . '.csv');
        $this->sourceTranslationStrings[$this->currentBranch] = $csv->fetchAll();
    }

    protected function combine($lang)
    {
        $this->newStrings = [];
        if ($this->currentBranch == 'master') {
            foreach ($this->sourceTranslationStrings['Head'] as $source) {
                foreach ($this->branches as $branch) {
                    $source = $this->search($branch, $lang, $source);
                    if ($source != null) {
                        $this->newStrings[] = $source;
                        break;
                    }
                }
            }
        }
        else {
            foreach ($this->sourceTranslationStrings[$this->currentBranch] as $source) {
                $source = $this->search($this->currentBranch, $lang, $source);
                if ($source != null) {
                    $this->newStrings[] = $source;
                }
            }
        }
    }

    protected function search($branch, $lang, $source)
    {
        if (isset($this->destStrings[$lang][$branch][$source[2]][$source[3]])) {
            $dest = $this->destStrings[$lang][$branch][$source[2]][$source[3]];
        }
        elseif (isset($this->destStrings[$lang][$branch][$source[2]][0])) {
            $dest = $this->destStrings[$lang][$branch][$source[2]][0];
        }
        else {
            return null;
        }
        foreach ($dest as $str) {
            if ($source[0] == $str[0] && $str[0] != $str[1]) {
                $source[1] = $str[1];

                return $source;
            }
        }

        return null;
    }

    protected function save($lang)
    {
        if (null == $this->buildDir) {
            $this->buildDir = new Filesystem(new Local(BUILD_DIR));
        }
        if ($lang == 'zh_CN') {
            $lang = 'zh_Hans_CN';
        }
        $this->buildDir->createDir($this->currentBranch . DIRECTORY_SEPARATOR . $lang);
        $writer = Writer::createFromPath(new \SplFileObject(BUILD_DIR . DIRECTORY_SEPARATOR . $this->currentBranch . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $lang . '.csv', 'a+'), 'w');
        $writer->insertAll($this->newStrings);
    }
}
