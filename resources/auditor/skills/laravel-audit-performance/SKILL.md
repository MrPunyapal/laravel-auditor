---
name: laravel-audit-performance
description: >
  Audit a Laravel application's performance characteristics: N+1 queries,
  eager loading, request-lifecycle work, queues, caching, and repeated
  computation. Use when auditing performance.
metadata:
  agent: any
---

# Laravel Performance Audit

Audit the performance characteristics of the Laravel application. **Do not treat every loop or every query as a performance problem.** Require evidence of actual cost.

## What to look for

- N+1 query risks: loops over collections that load relationships per iteration without eager loading.
- Unnecessary repeated queries: the same query executed in a loop or multiple times per request.
- Missing eager loading where evidence supports it: relationships used in a view/collection but not eager-loaded.
- Expensive work in the request lifecycle: heavy computation, large external I/O, or bulk processing done synchronously per request.
- Queue opportunities: email, notifications, external calls, or heavy processing that blocks the response.
- Unnecessary synchronous work where a queue is the clear idiomatic fit.
- Cache opportunities when strongly justified: stable, repeatedly computed values.
- Repeated expensive computation: same result computed many times within one request.
- Inefficient collection/database usage: filtering large collections in PHP when a DB query is better, missing pagination on large datasets, `cursor`/`chunk` misuse.

## Evidence requirements

Every performance finding needs at least:

- The specific loop/query site (file + line/range).
- The relationship or computation involved.
- An estimate of cost driver (e.g. collection size source, or the query appearing in a loop).

## False positives

- Small fixed-size loops over a handful of rows are not N+1 problems.
- A query executed once is not a performance problem.
- Do not recommend caching without a strong justification for the cache key and invalidation.
- Do not flag pagination when the dataset is known to be tiny.

## Severity guidance

- `high`: causes a demonstrably significant slowdown under expected load (e.g. N+1 over a large collection in a hot path).
- `medium`: clear inefficiency with likely real-world impact.
- `low`: inefficiency that is real but small or rare.
- `info`: observations worth noting without action.
