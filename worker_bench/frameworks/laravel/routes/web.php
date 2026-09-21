<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Hello World';
});

Route::get('/json', function () {
    return response()->json(['time' => microtime(true)]);
});

Route::get('/debug', function () {
    return response()->json([
        'env' => app()->environment(),
        'debug' => config('app.debug'),
        'opcache_scripts' => opcache_get_status()['opcache_statistics']['num_cached_scripts'] ?? -1,
    ]);
});

Route::get('/bench', function () {
    $start = hrtime(true);
    $result = 'Hello World';
    $elapsed = (hrtime(true) - $start) / 1e6;
    return response()->json(['elapsed_ms' => $elapsed, 'result' => $result]);
});
