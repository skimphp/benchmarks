<?php declare(strict_types=1);

define('SKIM_ROOT', dirname(__DIR__));
define('WORKER_MODE', true);

require SKIM_ROOT . '/vendor/autoload.php';

$app = skim\core\app::instance();

$app->use(skim\middleware\cors::class);

if (\skim\core\config::get('app.debug', false)) {
    $app->use(skim\middleware\toolbar_middleware::class);
}

require SKIM_ROOT . '/routes.php';

$app->boot();
$app->boot_extensions();
$app->freeze();

skim\events\event::capture_boot_snapshot();

$leak_mode = $app->get('app.leak_detection');
if ($leak_mode === null) {
    $leak_mode = (defined('WORKER_MODE') && $app->is_debug_mode()) ? 'warn' : 'off';
}
skim\worker\leak_detector::configure((string) $leak_mode);

set_exception_handler(function (\Throwable $e) use ($app): void {
    $app->handle_exception($e);
});

$shutdown_requested = false;

if (extension_loaded('pcntl')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$shutdown_requested): void {
        $shutdown_requested = true;
    });
    pcntl_signal(SIGINT, function () use (&$shutdown_requested): void {
        $shutdown_requested = true;
    });
}

$handler = function () use ($app): void {
    $app->begin_request();

    $req = skim\core\request::from_globals();
    $res = new skim\core\response();

    try {
        $result = $app->dispatch($req, $res);

        if ($app->is_debug_mode() && $result instanceof skim\core\response) {
            $trace = \skim\dev\request_trace::finish($result->get_status());
        }

        $app->emit($result, $res);
    } catch (\Throwable $e) {
        $app->handle_exception($e);
    } finally {
        $app->end_request();
        gc_collect_cycles();
    }
};

$max_requests = (int) ($_ENV['WORKER_MAX_REQUESTS'] ?? 0);
$count = 0;

while (($count < $max_requests || $max_requests === 0) && !$shutdown_requested) {
    $keepRunning = frankenphp_handle_request($handler);
    if (!$keepRunning) {
        break;
    }
    $count++;
}

$app->shutdown();
