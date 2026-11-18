<?php declare(strict_types=1);

/**
 * Cross-framework HTTP comparison — runs http_hello + http_json against the
 * worker_bench framework containers and prints/writes a comparison table.
 *
 * Usage (inside bench container — service names):
 *   php benchmarks/framework/compare_http.php
 *   php benchmarks/framework/compare_http.php skim,leaf,flight
 *
 * From the host (mapped ports 9101-9107):
 *   php benchmarks/framework/compare_http.php --host
 */

$targets = [
    'skim'    => 'skim',
    'symfony' => 'symfony',
    'laravel' => 'laravel',
    'ci4'     => 'ci4',
    'slim'    => 'slim',
    'leaf'    => 'leaf',
    'flight'  => 'flight',
];

$hostPorts = [
    'skim' => 9101, 'symfony' => 9102, 'laravel' => 9103, 'ci4' => 9104,
    'slim' => 9105, 'leaf' => 9106, 'flight' => 9107,
];

$host = in_array('--host', $argv, true);
$only = [];
foreach ($argv as $a) {
    if (is_string($a) && !str_starts_with($a, '-') && str_contains($a, ',')) {
        $only = explode(',', $a);
    }
}
$requests = (int) ($argv[2] ?? 300);
$concurrent = (int) ($argv[3] ?? 10);
if (isset($argv[1]) && is_numeric($argv[1])) {
    $requests = (int) $argv[1];
    $concurrent = (int) ($argv[2] ?? 10);
}

$dir = __DIR__;
$results = [];

foreach ($targets as $name => $svc) {
    if ($only !== [] && !in_array($name, $only, true)) {
        continue;
    }
    $base = $host ? "http://localhost:{$hostPorts[$name]}" : "http://{$svc}";

    foreach (['http_hello' => '/', 'http_json' => '/json'] as $script => $path) {
        $cmd = sprintf(
            'BENCH_JSON=1 php %s %s %d %d 2>&1',
            escapeshellarg("$dir/$script.php"),
            escapeshellarg($base . $path),
            $requests,
            $concurrent
        );
        $out = [];
        exec($cmd, $out, $rc);

        $row = ['error' => 'no_json_line', 'exit' => $rc];
        foreach ($out as $line) {
            if (str_starts_with($line, '__JSON__ ')) {
                $row = json_decode(substr($line, 9), true) ?? $row;
            }
        }
        $results[$name][$script] = $row;
        printf("%-8s %-10s avg=%sms p95=%sms rps=%s\n",
            $name, $script,
            $row['avg_ms'] ?? '?', $row['p95_ms'] ?? '?', $row['rps'] ?? '?');
    }
}

$outDir = $dir . '/results';
if (!is_dir($outDir)) {
    mkdir($outDir, 0o755, true);
}
$path = $outDir . '/compare-' . gmdate('Y-m-d\TH-i-s\Z') . '.json';
file_put_contents($path, json_encode(['generated_at' => date('c'), 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "\nResults: $path\n";
