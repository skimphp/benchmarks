<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new Leaf\App();

$app->get('/', function () {
    response()->plain('Hello World');
});

$app->get('/json', function () {
    response()->json(['time' => microtime(true)]);
});

$app->get('/db', function () {
    $pdo = new PDO('pgsql:host=postgres;dbname=bench', 'bench', 'bench');
    $pdo->query('SELECT 1');
    response()->plain('ok');
});

$app->run();
