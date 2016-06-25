<?php
namespace M2t\Console\Command;

use League\Csv\Reader;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use M2t\Data\Language;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Intl\Intl;
use Twig_Environment;
use Twig_Loader_Filesystem;

class Package extends Command
{
    protected $buildDir;
    protected $languages         = [];
    protected $branches          = ["master", "Head", "2.0.7", "2.0.2"];
    protected $mageVersions      = [];
    protected $sourceStringCount = [];
    protected $currentBranch;
    protected $twig;

    protected function configure()
    {
        $this->setName('package')
            ->setDescription('create translation packages')
            ->addArgument('lang', InputArgument::IS_ARRAY, 'What Language(s) need to be packaged?')
            ->addOption('download', null, InputOption::VALUE_NONE, 'Download first')
            ->addOption('translate', null, InputOption::VALUE_NONE, 'Translate first');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->languages = Language::languageCodes();
        if ($input->getOption('translate')) {
            $command = $this->getApplication()->find('translate');

            $arguments = [
                'command' => 'translate',
                'lang'    => $input->getArgument('lang')
            ];

            if ($input->getOption('download')) {
                $arguments['--download'] = true;
            }

            $translateInput = new ArrayInput($arguments);
            $returnCode     = $command->run($translateInput, $output);
        }

        $this->buildDir = new Filesystem(new Local(BUILD_DIR));
        $paths          = $this->buildDir->listContents('');

        foreach ($paths as $path) {
            if ($path['type'] == 'dir') {
                //$this->languages[] = $path['filename'];
            }
        }

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
            $question = new ChoiceQuestion('What Language(s) do you want to package?', $this->languages, 0);
            $question->setMultiselect(true);
            $question->setErrorMessage('Input %s is invalid.');

            $langChoice = $helper->ask($input, $output, $question);
        }
        if (in_array('all', $langChoice)) {
            unset($this->languages[0]);
            $langChoice = array_values($this->languages);
        }

        $loader     = new Twig_Loader_Filesystem(TEMPLATE_DIR);
        $this->twig = new Twig_Environment($loader);

        $output->writeln('<info>Generate packages for: ' . implode(', ', $langChoice) . '</info>');

        foreach ($this->branches as $branch) {
            if ($branch == "master") {
                continue;
            }
            $string = file_get_contents(MAGENTO_DIR . DIRECTORY_SEPARATOR . $branch . DIRECTORY_SEPARATOR . 'composer.lock');
            $json_a = json_decode($string, true);
            foreach ($json_a['packages'] as $pck) {
                if ($pck['name'] == 'magento/magento2-base') {
                    $this->mageVersions[$branch] = $pck['version'];
                    break;
                }
            }

            $csv                              = Reader::createFromPath(SOURCE_DIR . DIRECTORY_SEPARATOR . $branch . '.csv');
            $this->sourceStringCount[$branch] = $csv->each(function ($row) {
                return true;
            });
        }
        $this->mageVersions['master']      = $this->mageVersions['Head'];
        $this->sourceStringCount['master'] = $this->sourceStringCount['Head'];

        foreach ($this->branches as $branch) {

            $output->writeln('<info></info>');
            $output->writeln('<info></info>');
            $output->writeln('<info>Switch branch ' . $branch . '</info>');
            foreach ($langChoice as $key => $lang) {
                if ($lang == 'zh_CN') {
                    $lang = 'zh_Hans_CN';
                }
                $strings                     = [
                    'language'    => $lang,
                    'branch'      => ($branch == 'master' ? 'Head' : $branch),
                    'mageVersion' => $this->mageVersions[$branch],
                    'sourceCount' => $this->sourceStringCount[$branch]
                ];
                $csv                         = Reader::createFromPath(BUILD_DIR . DIRECTORY_SEPARATOR . $branch . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $lang . '.csv');
                $strings['translationCount'] = $csv->each(function ($row) {
                    return true;
                });
                $strings['progress']         = intval(round((intval($strings['translationCount']) / intval($strings['sourceCount'])) * 100));

                $langArr = explode('_', $lang);

                \Locale::setDefault('en');
                $strings['languageCode'] = $langArr[0];
                $strings['countryCode']  = $langArr[1];
                if ($lang == 'zh_Hans_CN') {
                    $strings['countryCode'] = $langArr[2];
                }
                $strings['year']           = date('Y');
                $strings['link']           = $langArr[0];
                $strings['languageNameEn'] = Intl::getLanguageBundle()->getLanguageName($strings['languageCode'], $strings['countryCode']);
                $strings['countryNameEn']  = Intl::getRegionBundle()->getCountryName($strings['countryCode']);
                \Locale::setDefault($lang);
                $strings['languageNameLocal'] = Intl::getLanguageBundle()->getLanguageName($strings['languageCode'], $strings['countryCode']);
                $strings['countryNameLocal']  = Intl::getRegionBundle()->getCountryName($strings['countryCode']);

                switch ($lang) {
                    case 'en_PT':
                        $strings['link']             = 'en-PT';
                        $strings['countryNameEn']    = 'international waters';
                        $strings['countryNameLocal'] = 'Anywhere';
                        break;
                    case 'zh_Hans_CN':
                        $strings['link'] = 'zh-CN';
                        break;
                    case 'pt_PT':
                        $strings['link'] = 'pt-PT';
                        break;
                    case 'pt_BR':
                        $strings['link'] = 'pt-BR';
                        break;
                    case 'es_ES':
                        $strings['link'] = 'es-ES';
                        break;
                    case 'es_AR':
                        $strings['link'] = 'es-AR';
                        break;
                }

                $output->writeln('<info>Start - generating ' . $lang . '</info>');
                $this->writeFile($branch, 'composer.json', $lang, $strings);
                $this->writeFile($branch, 'language.xml', $lang, $strings);
                $this->writeFile($branch, 'registration.php', $lang, $strings);
                $this->writeFile($branch, 'README.md', $lang, $strings);
                $this->writeFile($branch, 'LICENSE.md', $lang, $strings);
                $output->writeln('<comment>Done - generating ' . $lang . '</comment>');
            }
        }
    }

    protected function writeFile($branch, $file, $lang, $strings)
    {
        $this->buildDir->put($branch . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $file, $this->twig->render($file, $strings));
    }
}
