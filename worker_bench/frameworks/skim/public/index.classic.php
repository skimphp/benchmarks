<?php declare(strict_types=1);

define('SKIM_ROOT', dirname(__DIR__));

require SKIM_ROOT . '/vendor/autoload.php';

$app = skim\core\app::instance();

$app->use(skim\middleware\cors::class);

require SKIM_ROOT . '/routes.php';

$app->run();
