# FrankenPHP Worker Mode Benchmark

Isolated benchmark suite for PHP frameworks running in FrankenPHP worker mode.

## Scope

| Framework     | Mode                        | Port (host) | Service |
|---------------|-----------------------------|-------------|---------|
| Skim          | Native FrankenPHP worker    | 9101        | `skim`    |
| Symfony       | Native FrankenPHP worker    | 9102        | `symfony` |
| Laravel       | Octane + FrankenPHP         | 9103        | `laravel` |
| CodeIgniter 4 | Native FrankenPHP worker    | 9104        | `ci4`     |

## Endpoints

All frameworks expose two endpoints for parity:
- `GET /` — returns `Hello World`
- `GET /json` — returns `{"time":0.0}`

## Prerequisites

Docker and Docker Compose must be installed.

## Usage

```bash
# Build and start all worker containers
docker compose -f worker_bench/docker-compose.worker.yml up -d --build

# Manual smoke test
curl http://localhost:9101/        # Skim hello
curl http://localhost:9101/json  # Skim json
curl http://localhost:9102/        # Symfony hello
curl http://localhost:9102/json    # Symfony json
curl http://localhost:9103/        # Laravel hello
curl http://localhost:9103/json    # Laravel json
curl http://localhost:9104/        # CI4 hello
curl http://localhost:9104/json    # CI4 json

# Full benchmark run (hello + json, c=1,10,50,100)
docker compose -f worker_bench/docker-compose.worker.yml run --rm bench

# Quick run (only Skim vs Symfony, c=1,10)
docker compose -f worker_bench/docker-compose.worker.yml run --rm bench \
  php /app/worker_bench/wrk_worker_run.php --variants=skim,symfony --levels=1,10

# Stop everything
docker compose -f worker_bench/docker-compose.worker.yml down
```

## Results

Results are written to `worker_bench/results/<timestamp>/`:
- `hello.json` — Hello World benchmark results per concurrency level
- `json.json` — JSON endpoint benchmark results per concurrency level
- `meta.json` — run metadata (frameworks, URLs, timestamps)

## Runner flags

- `--variants=skim,symfony` — filter which frameworks to benchmark
- `--levels=1,10,50,100` — concurrency levels to test
- `--threads=N` — number of wrk threads (default: 2)
- `--duration=30s` — wrk duration per test (default: 30s)

## Isolation guarantees

- Compose project name: `skim_worker` (not `skim_framework_self_usage`)
- Docker network: `worker_bench` (not `bench`)
- Host ports: 9101–9104 (does not conflict with existing 9001–9009/5434)
- Results directory: `worker_bench/results/` (separate from `benchmarks/results/`)
- Existing suite (`benchmarks/`, root `docker-compose.yml`) is **not modified**
