<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('prod', false);
$kernel->boot();

$handler = function () use ($kernel): void {
    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
};

$max_requests = (int) ($_ENV['WORKER_MAX_REQUESTS'] ?? 0);
$count = 0;

while (($count < $max_requests || $max_requests === 0) && frankenphp_handle_request($handler)) {
    $count++;
}
