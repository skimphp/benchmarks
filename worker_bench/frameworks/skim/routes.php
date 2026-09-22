<?php declare(strict_types=1);

/** @var skim\core\app $app */

$app->router->get('/', function (): void {
    echo 'Hello World';
});

$app->router->get('/json', function (): void {
    header('Content-Type: application/json');
    echo json_encode(['time' => microtime(true)]);
});

$app->router->get('/bench', function (): array {
    $sleep = (int) ($_GET['sleep'] ?? 0);
    if ($sleep > 0 && $sleep < 10000) {
        usleep($sleep * 1000);
    }

    return [
        'ok' => true,
        'time' => microtime(true),
        'sleep_ms' => $sleep,
        'id' => $_GET['id'] ?? null,
    ];
});
