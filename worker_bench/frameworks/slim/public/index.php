<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$handler = function (): void {
    $app = AppFactory::create();

    $app->get('/', function (Request $request, Response $response, array $args) {
        $response->getBody()->write('Hello World');
        return $response;
    });

    $app->get('/json', function (Request $request, Response $response, array $args) {
        $response->getBody()->write(json_encode(['time' => microtime(true)]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->run();
};

while (frankenphp_handle_request($handler)) {
}
