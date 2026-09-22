<?php declare(strict_types=1);

function parseWrkOutput(array $lines): array {
    $result = [
        'rps' => 0,
        'avg' => 0,
        'p50' => 0,
        'p90' => 0,
        'p95' => 0,
        'p99' => 0,
        'max' => 0,
        'errors' => 0,
    ];

    $inLatencyDist = false;

    foreach ($lines as $line) {
        if (strpos($line, 'Latency Distribution') !== false) {
            $inLatencyDist = true;
            continue;
        }

        if (preg_match('/Requests\/sec:\s+([\d.]+)/', $line, $m)) {
            $result['rps'] = (float)$m[1];
        }

        if (preg_match('/Latency\s+([\d.]+)(ms|us|s)\s+([\d.]+)(ms|us|s)\s+([\d.]+)(ms|us|s)/', $line, $m)) {
            $result['avg'] = toMs((float)$m[1], $m[2]);
            $result['max'] = toMs((float)$m[5], $m[6]);
        }

        if ($inLatencyDist) {
            if (preg_match('/50%\s+([\d.]+)(ms|us|s)/', $line, $m)) {
                $result['p50'] = toMs((float)$m[1], $m[2]);
            }
            if (preg_match('/90%\s+([\d.]+)(ms|us|s)/', $line, $m)) {
                $result['p90'] = toMs((float)$m[1], $m[2]);
            }
            if (preg_match('/95%\s+([\d.]+)(ms|us|s)/', $line, $m)) {
                $result['p95'] = toMs((float)$m[1], $m[2]);
            }
            if (preg_match('/99%\s+([\d.]+)(ms|us|s)/', $line, $m)) {
                $result['p99'] = toMs((float)$m[1], $m[2]);
            }
        }

        if (preg_match('/Non-2xx or 3xx responses:\s+(\d+)/', $line, $m)) {
            $result['errors'] = (int)$m[1];
        }
    }

    return $result;
}

function toMs(float $value, string $unit): float {
    return match($unit) {
        'us' => $value / 1000,
        'ms' => $value,
        's' => $value * 1000,
        default => $value,
    };
}

function parse_worker_duration_flag(?array $argv, string $flag, string $default): string {
    if ($argv === null) {
        return $default;
    }
    $prefix = $flag . '=';
    foreach ($argv as $a) {
        if (is_string($a) && str_starts_with($a, $prefix)) {
            $val = substr($a, strlen($prefix));
            if ($val !== '' && preg_match('/^\d+[smh]?$/', $val)) {
                return $val;
            }
            fwrite(STDERR, "WARNING: $flag expects a duration like 30s, 1m, got '$a'\n");
        }
    }
    return $default;
}

function printResults(array $data): void {
    printf("  RPS:          %.2f\n", $data['rps']);
    printf("  Avg latency:  %.2f ms\n", $data['avg']);
    printf("  p50:          %.2f ms\n", $data['p50']);
    printf("  p90:          %.2f ms\n", $data['p90']);
    printf("  p95:          %.2f ms\n", $data['p95']);
    printf("  p99:          %.2f ms\n", $data['p99']);
    printf("  Max:          %.2f ms\n", $data['max']);
    printf("  Errors:       %d\n", $data['errors']);
    echo "\n";
}
