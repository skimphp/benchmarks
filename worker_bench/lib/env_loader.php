<?php declare(strict_types=1);

if (!function_exists('load_worker_env')) {
function load_worker_env(string $path): array {
    if (!is_file($path)) {
        return [];
    }

    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last  = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }
        $env[$key] = $val;
    }
    return $env;
}
}

if (!function_exists('discover_worker_variants')) {
function discover_worker_variants(string $benchDir): array {
    $variants = [];
    foreach (glob($benchDir . '/*.env') as $envFile) {
        $env = load_worker_env($envFile);
        if (!isset($env['SKIM_VERSION'], $env['SKIM_LABEL'], $env['SKIM_PORT'])) {
            continue;
        }
        $dir = $benchDir . '/' . $env['SKIM_LABEL'];
        $variants[$env['SKIM_LABEL']] = [
            'env'     => $env,
            'envFile' => $envFile,
            'dir'     => $dir,
        ];
    }
    ksort($variants);
    return $variants;
}
}

if (!function_exists('normalize_worker_version')) {
function normalize_worker_version(?string $version): string {
    return $version === null ? '' : ltrim($version, 'v');
}
}

if (!function_exists('resolve_worker_version')) {
function resolve_worker_version(array $variantInfo): ?string {
    $envVersion = $variantInfo['env']['SKIM_VERSION'] ?? null;
    $sidecar = $variantInfo['dir'] . '/.skim_version';
    $composerJson = $variantInfo['dir'] . '/vendor/skim/framework/composer.json';
    $installedJson = $variantInfo['dir'] . '/vendor/composer/installed.json';

    $version = null;
    if (is_file($sidecar)) {
        $version = trim((string)file_get_contents($sidecar)) ?: null;
    }
    if ($version === null && is_file($composerJson)) {
        $j = json_decode((string)file_get_contents($composerJson), true);
        if (is_array($j) && !empty($j['version'])) {
            $version = (string)$j['version'];
        }
    }
    if ($version === null && is_file($installedJson)) {
        $j = json_decode((string)file_get_contents($installedJson), true);
        if (is_array($j)) {
            foreach ($j['packages'] ?? [] as $package) {
                if (($package['name'] ?? null) === 'skim/framework' && !empty($package['version'])) {
                    $version = (string)$package['version'];
                    break;
                }
            }
        }
    }
    if ($version === null) {
        $version = $envVersion;
    }

    if (
        $envVersion !== null
        && $version !== null
        && normalize_worker_version($envVersion) !== normalize_worker_version($version)
    ) {
        $label = $variantInfo['env']['SKIM_LABEL'] ?? '?';
        fwrite(STDERR, "WARNING: $label env pins $envVersion but vendor has $version\n");
    }

    return $version;
}
}

if (!function_exists('build_worker_meta')) {
function build_worker_meta(array $frameworks, array $frameworkLabels, array $variants): array {
    $frameworksMeta = [];
    foreach ($frameworks as $name => $url) {
        $label = $frameworkLabels[$name] ?? null;
        $version = null;
        if ($label !== null && isset($variants[$label])) {
            $version = resolve_worker_version($variants[$label]);
        }
        $frameworksMeta[$name] = [
            'label'   => $label,
            'url'     => $url,
            'version' => $version,
        ];
    }

    return [
        'generated_at' => date('c'),
        'frameworks'   => $frameworksMeta,
    ];
}
}

if (!function_exists('parse_worker_variants_filter')) {
function parse_worker_variants_filter(?array $argv): ?array {
    if ($argv === null) {
        return null;
    }
    foreach ($argv as $a) {
        if (is_string($a) && str_starts_with($a, '--variants=')) {
            $raw = substr($a, 11);
            $out = [];
            foreach (explode(',', $raw) as $v) {
                $v = trim($v);
                if ($v !== '') {
                    $out[] = $v;
                }
            }
            return $out ?: null;
        }
    }
    return null;
}
}

if (!function_exists('filter_worker_frameworks_by_variants')) {
function filter_worker_frameworks_by_variants(array $frameworks, array $frameworkLabels, array $allowedLabels): array {
    $out = [];
    foreach ($frameworks as $name => $url) {
        $label = $frameworkLabels[$name] ?? null;
        if ($label !== null && in_array($label, $allowedLabels, true)) {
            $out[$name] = $url;
        }
    }
    return $out;
}
}

if (!function_exists('parse_worker_int_flag')) {
function parse_worker_int_flag(?array $argv, string $flag, int $default): int {
    if ($argv === null) {
        return $default;
    }
    $prefix = $flag . '=';
    foreach ($argv as $a) {
        if (is_string($a) && str_starts_with($a, $prefix)) {
            $val = filter_var(substr($a, strlen($prefix)), FILTER_VALIDATE_INT);
            if ($val !== false && $val > 0) {
                return $val;
            }
            fwrite(STDERR, "WARNING: $flag expects a positive integer, got '$a'\n");
        }
    }
    return $default;
}
}

if (!function_exists('parse_worker_duration_flag')) {
function parse_worker_duration_flag(?array $argv, string $flag, string $default): string {
    if ($argv === null) {
        return $default;
    }
    $prefix = $flag . '=';
    foreach ($argv as $a) {
        if (is_string($a) && str_starts_with($a, $prefix)) {
            $val = trim(substr($a, strlen($prefix)));
            if (preg_match('/^\d+s$/', $val)) {
                return $val;
            }
            fwrite(STDERR, "WARNING: $flag expects a duration like 30s, got '$a'\n");
        }
    }
    return $default;
}
}

if (!function_exists('resolve_worker_results_dir')) {
function resolve_worker_results_dir(string $benchDir): string {
    $envTs = getenv('BENCH_TIMESTAMP');
    $timestamp = ($envTs !== false && preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $envTs))
        ? $envTs
        : date('Y-m-d_H-i-s');
    $dir = $benchDir . '/results/' . $timestamp;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}
}
