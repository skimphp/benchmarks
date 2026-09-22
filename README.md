# skim-benchmarks

Benchmark harness for SKIM — measurement tooling lives here, correctness
gates stay in the framework repo.

## Layout

- `worker_bench/` — multi-framework FrankenPHP worker-mode comparison
  (skim vs symfony vs laravel-octane vs ci4 vs slim). See `worker_bench/README.md`.
- `framework/` — single-framework measurement scripts (bench_run, cache_hit,
  http_hello/json/warm, worker_mode_benchmark) moved from skim/framework.
- `docker/bench/` — wrk bench container + nginx/php configs.

## Reproducibility

`worker_bench` pins the framework via `skim_worker.env` (SKIM_COMMIT) and
`lib/ensure_worker_version.sh` materialises it into `source/skim_framework_src`
(gitignored — never commit the pinned tree).

## Known debt

- `worker_bench/frameworks/skim/public/index.php` targets the pre-camelCase
  API of its pinned commit — port to `Skim\Core\*` when re-pinning to a
  migrated framework release.
