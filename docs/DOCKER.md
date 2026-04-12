# Docker & PHP Memory Reference

This document explains how PHP memory limits are configured across the
development and production containers, and how to override them for heavy
artisan tasks without touching the Dockerfiles.

## PHP `memory_limit` — aligned at 512M

Both `Dockerfile` (development) and `Dockerfile.prod` set
`memory_limit = 512M`. This satisfies the CLAUDE.md rule that Dockerfile PHP
settings must match, and the `dockerfile-drift` CI job enforces it.

| Setting | Dev (`Dockerfile`) | Prod (`Dockerfile.prod`) |
|---------|--------------------|--------------------------|
| `memory_limit` | `512M` | `512M` |
| `upload_max_filesize` | `50M` | `50M` |
| `post_max_size` | `55M` | `55M` |
| `max_execution_time` | `60` | `60` |

512M comfortably handles HTTP requests, PHPUnit runs, most artisan commands,
and typical seed operations. It also leaves headroom inside a 768M–1536M
container allocation for Apache workers, opcache, and OS overhead.

## Container memory allocations

| Environment | File | Container limit | Rationale |
|-------------|------|-----------------|-----------|
| Development | `compose.yml` | `1536m` (mem_limit) | Generous headroom for Vite, PHPUnit, composer, schema dumps |
| Production | `compose.prod.yml` | see that file | Tuned to the Azure VM sizing |

The container limit must always be **strictly greater** than the PHP
`memory_limit`, because each Apache worker can allocate up to `memory_limit`
independently and opcache/JIT buffers live outside that budget.

## Overriding `memory_limit` for heavy artisan tasks

Some tasks — PHPStan level ≥ 5, full Meilisearch reindex, bulk imports — need
more than 512M for a single process. **Do not raise the baseline** (it would
cascade to every Apache worker and risk OOM). Use a per-invocation override:

```bash
# Unlimited (recommended for PHPStan, large reindexes)
docker exec nexus-php-app php -d memory_limit=-1 artisan <command>

# Specific cap (recommended for seeders where you want a safety net)
docker exec nexus-php-app php -d memory_limit=2G artisan db:seed

# Via environment variable (picked up by the PHP CLI ini scanner)
docker exec -e PHP_MEMORY_LIMIT=2G nexus-php-app php artisan <command>
```

### Common recipes

```bash
# PHPStan full scan
docker exec nexus-php-app php -d memory_limit=-1 vendor/bin/phpstan analyse --memory-limit=-1

# Meilisearch reindex
docker exec nexus-php-app php -d memory_limit=-1 artisan scout:import "App\\Models\\Listing"

# Large data migration
docker exec nexus-php-app php -d memory_limit=2G artisan migrate --force
```

## Why not just set `memory_limit = 2G` everywhere?

- **Security / DoS surface**: any runaway request in production could allocate
  up to 2G, and with multiple Apache workers you'd exhaust the VM.
- **Noise in failure modes**: a high ceiling hides real memory leaks behind
  "enough slack to finish the request", and the leak lands in production.
- **Docker drift CI check**: both Dockerfiles must match exactly, so raising
  one means raising both, which we don't want for production.

The per-invocation override pattern gives CLI tasks the headroom they need
without polluting the web request surface.

## Related files

- `Dockerfile` — dev PHP image, `memory_limit = 512M`
- `Dockerfile.prod` — prod PHP image, `memory_limit = 512M`
- `compose.yml` — dev container limits (`mem_limit`, `mem_reservation`)
- `compose.prod.yml` — prod container limits
- `.github/workflows/ci.yml` — `dockerfile-drift` job enforces sync
