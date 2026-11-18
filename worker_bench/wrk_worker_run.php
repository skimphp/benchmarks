<?php declare(strict_types=1);

require __DIR__ . '/lib/env_loader.php';
require __DIR__ . '/lib/wrk.php';

$benchDir = __DIR__;

$frameworkDefs = [
    'Skim'          => ['skim',    'http://skim/'],
    'Symfony'       => ['symfony', 'http://symfony/'],
    'Laravel'       => ['laravel', 'http://laravel/'],
    'CodeIgniter 4' => ['ci4',     'http://ci4/'],
    'Slim'          => ['slim',    'http://slim/'],
    'Leaf'          => ['leaf',    'http://leaf/'],
    'Flight'        => ['flight',  'http://flight/'],
];

$mode = 'worker';
if (in_array('--classic', $argv, true)) {
    $mode = 'classic';
}
if (in_array('--fpm', $argv, true)) {
    $mode = 'fpm';
}

$fpmPorts = [
    'skim' => 9301, 'symfony' => 9302, 'laravel' => 9303, 'ci4' => 9304,
    'slim' => 9305, 'leaf' => 9306, 'flight' => 9307,
];

$frameworks = $frameworkLabels = [];
foreach ($frameworkDefs as $name => [$label, $url]) {
    $frameworks[$name] = match ($mode) {
        'classic' => "http://{$label}-classic/",
        'fpm'     => "http://nginx:{$fpmPorts[$label]}/",
        default   => $url,
    };
    $frameworkLabels[$name] = $label;
}

$jsonEndpoints = $dbEndpoints = [];
foreach ($frameworks as $name => $url) {
    $jsonEndpoints[$name] = rtrim($url, '/') . '/json';
    $dbEndpoints[$name]   = rtrim($url, '/') . '/db';
}

$threads = parse_worker_int_flag($argv, '--threads', 2);
$duration = parse_worker_duration_flag($argv, '--duration', '30s');
$concurrencyLevels = [1, 10];

$levelsFlag = null;
foreach ($argv as $a) {
    if (is_string($a) && str_starts_with($a, '--levels=')) {
        $levelsFlag = substr($a, 9);
        break;
    }
}
if ($levelsFlag !== null) {
    $parsedLevels = [];
    foreach (explode(',', $levelsFlag) as $level) {
        $level = (int)trim($level);
        if ($level > 0) {
            $parsedLevels[] = $level;
        }
    }
    if ($parsedLevels !== []) {
        $concurrencyLevels = $parsedLevels;
    }
}

$variantsFilter = parse_worker_variants_filter($argv);
if ($variantsFilter !== null) {
    $frameworks    = filter_worker_frameworks_by_variants($frameworks, $frameworkLabels, $variantsFilter);
    $jsonEndpoints = filter_worker_frameworks_by_variants($jsonEndpoints, $frameworkLabels, $variantsFilter);
    $dbEndpoints   = filter_worker_frameworks_by_variants($dbEndpoints, $frameworkLabels, $variantsFilter);
    if ($frameworks === []) {
        fwrite(STDERR, "FATAL: --variants did not match any framework\n");
        exit(1);
    }
}

echo 'wrk ' . ($mode === 'fpm' ? 'nginx + PHP-FPM' : 'FrankenPHP ' . ucfirst($mode)) . " Benchmarks\n";
echo "==================================\n";
echo "Threads: $threads, Duration: $duration, Concurrency: " . implode(', ', $concurrencyLevels) . "\n";
if ($variantsFilter !== null) {
    echo "Variants filter: " . implode(', ', $variantsFilter) . "\n";
}
echo "\n";

$meta = build_worker_meta($frameworks, $frameworkLabels, discover_worker_variants($benchDir));
$meta['mode'] = $mode;

$resultsDir = resolve_worker_results_dir($benchDir);
file_put_contents($resultsDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT));

$allHelloResults = [];
$allJsonResults = [];
$allDbResults = [];

foreach ($concurrencyLevels as $connections) {
    echo str_repeat("=", 60) . "\n";
    echo "HELLO WORLD (c=$connections)\n";
    echo str_repeat("=", 60) . "\n\n";

    $results = [];

    foreach ($frameworks as $name => $url) {
        echo "Testing $name ($url) c=$connections...\n";
        echo str_repeat("-", 60) . "\n";

        exec("wrk -t1 -c1 -d5s $url 2>&1", $warmupOutput);

        $t = min($threads, $connections);
        $output = [];
        $cmd = "wrk -t$t -c$connections -d$duration --latency $url 2>&1";
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            echo "  Error: wrk failed (exit $exitCode)\n";
            echo "  Output: " . implode("\n  ", $output) . "\n\n";
            continue;
        }

        $parsed = parseWrkOutput($output);
        printResults($parsed);

        $results[$name] = $parsed;
        $output = [];
    }

    echo str_repeat("=", 60) . "\n";
    echo "COMPARISON SUMMARY (Hello World c=$connections)\n";
    echo str_repeat("=", 60) . "\n";

    uasort($results, fn($a, $b) => $b['rps'] <=> $a['rps']);

    $rank = 1;
    foreach ($results as $name => $data) {
        printf("%d. %s\n", $rank++, $name);
        printf("   RPS: %.2f | p50: %.2f ms | p99: %.2f ms\n\n",
            $data['rps'], $data['p50'], $data['p99']);
    }

    $allHelloResults["hello_c$connections"] = $results;

    echo str_repeat("=", 60) . "\n";
    echo "JSON ENDPOINTS (c=$connections)\n";
    echo str_repeat("=", 60) . "\n\n";

    $resultsJson = [];

    foreach ($frameworks as $name => $url) {
        $jsonUrl = rtrim($url, '/') . '/json';
        echo "Testing $name ($jsonUrl) c=$connections...\n";
        echo str_repeat("-", 60) . "\n";

        $t = min($threads, $connections);
        $output = [];
        $cmd = "wrk -t$t -c$connections -d$duration --latency $jsonUrl 2>&1";
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            echo "  Error: wrk failed\n\n";
            continue;
        }

        $parsed = parseWrkOutput($output);
        printResults($parsed);

        $resultsJson[$name] = $parsed;
        $output = [];
    }

    $allJsonResults["json_c$connections"] = $resultsJson;

    echo str_repeat("=", 60) . "\n";
    echo "DB ENDPOINTS (SELECT 1, c=$connections)\n";
    echo str_repeat("=", 60) . "\n\n";

    $resultsDb = [];

    foreach ($dbEndpoints as $name => $dbUrl) {
        echo "Testing $name ($dbUrl) c=$connections...\n";
        echo str_repeat("-", 60) . "\n";

        $t = min($threads, $connections);
        $output = [];
        $cmd = "wrk -t$t -c$connections -d$duration --latency $dbUrl 2>&1";
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            echo "  Error: wrk failed\n\n";
            continue;
        }

        $parsed = parseWrkOutput($output);
        printResults($parsed);

        $resultsDb[$name] = $parsed;
        $output = [];
    }

    $allDbResults["db_c$connections"] = $resultsDb;
}

file_put_contents(
    $resultsDir . '/hello.json',
    json_encode($allHelloResults, JSON_PRETTY_PRINT)
);

file_put_contents(
    $resultsDir . '/json.json',
    json_encode($allJsonResults, JSON_PRETTY_PRINT)
);

file_put_contents(
    $resultsDir . '/db.json',
    json_encode($allDbResults, JSON_PRETTY_PRINT)
);

echo "\nResults saved to: $resultsDir\n";
