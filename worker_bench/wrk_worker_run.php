<?php declare(strict_types=1);

require __DIR__ . '/../benchmarks/lib/env_loader.php';
require __DIR__ . '/lib/wrk.php';

$benchDir = __DIR__;

$frameworks = [
    'Skim'          => 'http://skim/',
    'Symfony'       => 'http://symfony/',
    'Laravel'       => 'http://laravel/',
    'CodeIgniter 4' => 'http://ci4/',
    'Slim'          => 'http://slim/',
];

$frameworkLabels = [
    'Skim'          => 'skim',
    'Symfony'       => 'symfony',
    'Laravel'       => 'laravel',
    'CodeIgniter 4' => 'ci4',
    'Slim'          => 'slim',
];

$jsonEndpoints = [];
foreach ($frameworks as $name => $url) {
    $jsonEndpoints[$name] = rtrim($url, '/') . '/json';
}

$threads = parse_int_flag($argv, '--threads', 2);
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

$variantsFilter = parse_variants_filter($argv);
if ($variantsFilter !== null) {
    $frameworks    = filter_frameworks_by_variants($frameworks, $frameworkLabels, $variantsFilter);
    $jsonEndpoints = filter_frameworks_by_variants($jsonEndpoints, $frameworkLabels, $variantsFilter);
    if ($frameworks === []) {
        fwrite(STDERR, "FATAL: --variants did not match any framework\n");
        exit(1);
    }
}

echo "wrk FrankenPHP Worker Benchmarks\n";
echo "==================================\n";
echo "Threads: $threads, Duration: $duration, Concurrency: " . implode(', ', $concurrencyLevels) . "\n";
if ($variantsFilter !== null) {
    echo "Variants filter: " . implode(', ', $variantsFilter) . "\n";
}
echo "\n";

$meta = [
    'generated_at' => date('c'),
    'frameworks' => [],
];
foreach ($frameworks as $name => $url) {
    $meta['frameworks'][$name] = [
        'label' => $frameworkLabels[$name] ?? null,
        'url'   => $url,
    ];
}

$resultsDir = resolve_results_dir($benchDir);
file_put_contents($resultsDir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT));

$allHelloResults = [];
$allJsonResults = [];

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
}

file_put_contents(
    $resultsDir . '/hello.json',
    json_encode($allHelloResults, JSON_PRETTY_PRINT)
);

file_put_contents(
    $resultsDir . '/json.json',
    json_encode($allJsonResults, JSON_PRETTY_PRINT)
);

echo "\nResults saved to: $resultsDir\n";
