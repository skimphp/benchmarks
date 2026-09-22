<?php declare(strict_types=1);

use CodeIgniter\Boot;
use Config\Paths;

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

require $paths->systemDirectory . '/Boot.php';

$app = Boot::bootWorker($paths);

$handler = function () use (&$app): void {
    $app->run();
};

$max_requests = (int) ($_ENV['WORKER_MAX_REQUESTS'] ?? 0);
$count = 0;

while (($count < $max_requests || $max_requests === 0) && frankenphp_handle_request($handler)) {
    $count++;
    \Config\Services::reset(false);
    $app = Boot::bootWorker($paths);
}
