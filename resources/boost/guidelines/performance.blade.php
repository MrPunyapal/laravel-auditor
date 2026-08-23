## Laravel Auditor performance findings

Performance findings follow the full pipeline: Signal → Context → Behavior → Verification → Impact → Finding. A suspicious pattern is not automatically a performance bug.

### Before reporting

1. Check what else consumes the value. `$users = User::where(...)->get(); $count = $users->count();` with the collection rendered by the view is **correct code** — do not recommend `->count()`. Only materialize-then-reduce with no other consumer is a finding.
2. Verify semantic equivalence for this exact usage: accessors/casts, comparison strictness (Collection `where()` is loose, SQL is not), null handling, custom collection classes, primary/foreign keys for narrowed selects, model events on bulk writes.
3. Describe impact by mechanism — "avoids transferring every matching row into PHP" — never invented multipliers like "10x faster". Cite numbers only from real runtime evidence.
4. Add `metadata.impact` when evidence allows: `resource` (database/memory/network/IO), `mechanism` (what is wasted and what the fix avoids), `amplification` (loop counts, table growth, request frequency).

### Severity discipline

- `high`: query explosion or heavy amplification on hot paths (data-driven per-row queries, unbounded retrieval on public endpoints).
- `medium`: clear unnecessary database work, avoidable materialization, repeated queries, rendering-path queries with real traffic.
- `low`: small inefficiencies on modest data.
- Correct micro-optimizations on tiny datasets are not findings.

### Noise floor

Do not report: reused collections/results, replacements you cannot show equivalent, bounded small datasets, caching without a key/invalidation story, concurrency for dependent requests, or eager loading "just in case" (over-eager loading is itself waste).

Use `php artisan auditor:rules --domain=performance --applicable` to list the rules (`AUD-PER-*`, plus `AUD-LW-003`/`AUD-FIL-003`/`AUD-IN-003` when those packages are installed). The `laravel-audit-performance` skill contains the full methodology and checklist.
