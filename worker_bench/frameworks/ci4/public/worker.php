<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Bootstrap CI4
$paths = require __DIR__ . '/../app/Config/Paths.php';
require $paths->systemDirectory . '/bootstrap.php';

$app = \Config\Services::codeigniter();
$app->initialize();

$max_requests = (int) ($_ENV['WORKER_MAX_REQUESTS'] ?? 0);
$count = 0;

while (($count < $max_requests || $max_requests === 0) && frankenphp_handle_request(function () use ($app): void {
    $app->run();
})) {
    $count++;
    // Re-initialize for next request
    $app = \Config\Services::codeigniter();
    $app->initialize();
}
