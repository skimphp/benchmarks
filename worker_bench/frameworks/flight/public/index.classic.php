<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new flight\Engine();

$app->route('GET /', function () {
    echo 'Hello World';
});

$app->route('GET /json', function () use ($app) {
    $app->json(['time' => microtime(true)]);
});

$app->route('GET /db', function () {
    $pdo = new PDO('pgsql:host=postgres;dbname=bench', 'bench', 'bench');
    $pdo->query('SELECT 1');
    echo 'ok';
});

$app->start();
