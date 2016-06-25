<?php
require 'vendor/autoload.php';
define('WORK_DIR', __DIR__);
define('CACHE_DIR', WORK_DIR . DIRECTORY_SEPARATOR . 'cache');
define('BUILD_DIR', WORK_DIR . DIRECTORY_SEPARATOR . 'build');
define('TEMPLATE_DIR', WORK_DIR . DIRECTORY_SEPARATOR . 'template');
define('MAGENTO_DIR', WORK_DIR . DIRECTORY_SEPARATOR . 'magento');
define('ZIP_FILE', WORK_DIR . DIRECTORY_SEPARATOR . 'translations.zip');
define('DOWNLOADS_DIR', WORK_DIR . DIRECTORY_SEPARATOR . 'downloads');
define('SOURCE_DIR', WORK_DIR . DIRECTORY_SEPARATOR . 'source');

use M2t\Console\Command\Download;
use M2t\Console\Command\Package;
use M2t\Console\Command\Translate;
use M2t\Console\Command\Full;
use Symfony\Component\Console\Application;

try {
    $application = new Application();
    $application->add(new Download());
    $application->add(new Translate());
    $application->add(new Package());
    $application->add(new Full());
    $application->run();

} catch (\Exception $e) {
    while ($e) {
        echo $e->getMessage();
        echo $e->getTraceAsString();
        echo "\n\n";
        $e = $e->getPrevious();
    }
    exit(1);
}