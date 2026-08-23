---
name: laravel-audit-performance
description: >
  Audit a Laravel application's performance deeply and safely: unnecessary
  queries, N+1, collection vs database work, memory amplification, repeated
  computation, I/O, queues, and rendering cost — verifying that every proposed
  optimization preserves behavior before reporting it. Use when auditing
  performance or investigating a slow path.
metadata:
  agent: any
---

# Laravel Performance Audit

Audit how the application spends time, memory, queries, and I/O — and report only optimizations that are **semantically verified** and **worth acting on**.

List applicable rules first:

```bash
php artisan auditor:rules --domain=performance --applicable
```

Ecosystem rules (`AUD-LW-003`, `AUD-FIL-003`, `AUD-IN-003`) appear only when those packages are installed.

## The methodology

Work every candidate through this pipeline. A pattern that stops at any stage is not a finding.

```text
Signal        A suspicious shape (query in a loop, get()->count(), unbounded all())
  ↓
Context       Who calls this? What route/job/command? What data volume? What else uses the result?
  ↓
Behavior      What does the code actually do — accessors, casts, custom collections, callbacks?
  ↓
Verification  Is the proposed replacement semantically equivalent FOR THIS USAGE?
  ↓
Impact        Which resource does it save (queries, rows transferred, memory, CPU, I/O)? Is it meaningful?
  ↓
Finding       Evidence-backed report with mechanism-based impact and justified severity
```

**A suspicious pattern is not automatically a performance bug.**

### Worked example — when it IS a finding

```php
$total = User::where('active', true)->get()->count();
```

The collection is materialized only to be collapsed into one number. Every matching row is transferred and hydrated to produce what `User::where('active', true)->count()` returns as a single value. Verified: `$total` is a scalar; no other code uses the collection. Report via `AUD-PER-008`.

### Counter-example — same shape, NOT a finding

```php
$users = User::where('active', true)->get();

$count = $users->count();

return view('users.index', compact('users', 'count'));
```

Do **not** recommend `->count()` here. The collection is rendered by the view; replacing the aggregate would issue a second query or force a refactor. Deriving the count from already-loaded data is the cheapest correct option. This rule applies to every "obvious" optimization in this skill: check what else consumes the value first.

## What to investigate

Work through the areas relevant to this application. Use context tools (`routes`, `models`, `database_schema`, `jobs_events_schedules`) to find hot paths and data-volume sources before reading code.

### Database and Eloquent

- Materialized aggregates: `get()->count()/sum()/avg()/min()/max()`, `get()->isEmpty()/isNotEmpty()`, `get()->first()` where the collection has no other consumer (`AUD-PER-008`).
- PHP doing database work: filtering, sorting, slicing, or deduplication of freshly queried rows that SQL could do (`AUD-PER-009`).
- Relationship counts/existence answered by loading rows: `$post->comments->count()`, `->isNotEmpty()` vs `withCount()`/`withExists()`/`->relation()->count()` (`AUD-PER-010`).
- Queries inside loops: per-item lookups, counts, writes without bulk forms (`AUD-PER-011`).
- N+1 relationship access in loops, views, resources, jobs — verify the relationship is actually loaded lazily before reporting (`AUD-PER-001`).
- Over-selection: full hydration of wide tables where few columns are consumed (`AUD-PER-013`).
- Repeated identical queries within one lifecycle that can share one result (`AUD-PER-004`).

### Collections

- Chained transformations creating avoidable intermediates on large sets: `map()->filter()`, `filter()->first()` where `first(callable)` is equivalent (`AUD-PER-018`).
- Multiple separate passes over one large collection where a single pass or a keyed lookup would do.
- Semantic traps: `all()` returns the underlying items; `toArray()` recursively converts Arrayable items. They are interchangeable **only** when consumers tolerate the difference. Never swap them blindly.
- Lazy vs eager: long chains over large datasets may suit `lazy()` collections — but only propose this when the chain is genuinely hot.

### Application flow

- Expensive synchronous work in request paths where a queue is clearly idiomatic (`AUD-PER-002`, `AUD-PER-005`).
- Repeated expensive stable computations without a justified cache (`AUD-PER-007`).
- Hot paths first: high-traffic routes, scheduled commands, queue workers. A slow admin page once a month is rarely worth the same attention.

### Data volume

- Unbounded retrieval on growth-prone paths: APIs, exports, reports, commands, jobs (`AUD-PER-012`). Match the mechanism to the workload: paginate/cursorPaginate for HTTP, chunkById/lazyById for read-modify-write, lazy/cursor for single-pass streaming.
- Jobs processing unbounded datasets or carrying oversized serialized payloads (`AUD-PER-016`).

### I/O

- External HTTP calls inside loops, repeated identical requests, sequential independent calls that could batch (`AUD-PER-014`). Respect call dependencies and rate limits.
- Storage/filesystem work repeated per item, or whole-file reads where streaming fits (`AUD-PER-015`). Remote disks make every call network I/O.

### Rendering

- Queries inside Blade loops/components, expensive helpers recomputed per iteration, stable values recomputed per render without caching (`AUD-PER-017`).
- Livewire: per-render/update queries, computed properties that re-query on each subsequent update (they are memoized within a single request), oversized public properties serialized every cycle (`AUD-LW-003`).
- Filament: select options loading whole tables, per-row closures querying, counts without `withCount` (`AUD-FIL-003`).
- Inertia: shared props computed eagerly on every response including pages that never use them (`AUD-IN-003`).

Only investigate ecosystem areas when the package is installed.

## Verify semantic equivalence before recommending

Every recommendation must answer: **does the replacement produce identical observable results in this exact usage?** Check specifically:

- **Collection reuse** — is the materialized collection used anywhere else (view, return value, later loop)? If yes, most query-side rewrites do not apply.
- **Accessors, casts, appended attributes** — PHP-side operations may see transformed values SQL never produces. Aggregate comparisons can differ.
- **Comparison strictness** — Collection `where()`/`contains()` compare loosely by default; SQL compares differently. Null handling differs too.
- **Ordering semantics** — collation, locale-aware sort callbacks, stability of sorts.
- **Custom collection classes** — overridden methods (`count()`, `contains()`, `first()`) may have non-standard behavior.
- **Model requirements** — narrowed selects still need primary keys, foreign keys, and anything accessors/casts touch.
- **Side effects** — bulk writes skip model events unless explicitly handled; lazy loading timing changes when queries execute.

If equivalence cannot be established, either lower confidence and say so, or do not report.

## Evidence requirements

Every performance finding needs at least:

- The specific site (file + line/range) and the enclosing method/route/job.
- The cost driver with a source: iteration count origin, table growth expectation, call frequency — from routes/schema/jobs context, not vibes.
- Why the current form performs the unnecessary work (what is transferred/computed and then discarded or repeated).
- The verification note: what you checked to confirm the replacement is safe *here*.

Do not invent measurements. Describe mechanisms ("avoids transferring every matching row into PHP") rather than fabricating multipliers. Cite runtime evidence only if it actually exists.

## Severity guidance

Justify severity from reach × frequency × amplification:

- `high`: query explosion or heavy amplification on hot/high-volume paths (per-row queries over user-driven loops, unbounded retrieval on public endpoints, external calls multiplied by dataset size) — `AUD-PER-011` findings typically land here when loops are data-driven.
- `medium`: clear unnecessary database work, avoidable materialization, repeated queries, oversized payloads, rendering-path queries with real traffic.
- `low`: real but small inefficiencies — narrow-column wins, single-model count forms, pass-collapsing on modest collections.
- `info`: observations worth noting without action.

A correct micro-optimization on a tiny dataset is not a finding at all.

## False positives — keep the report trustworthy

- The collection/result is reused after the suspicious call (the counter-example above).
- Semantics depend on PHP-only behavior the replacement cannot reproduce.
- Loops bounded by small fixed sets (config constants, enum values).
- Inherently bounded tables (countries, settings) need no pagination.
- Already-loaded relationships queried again would be *slower*, not faster.
- Caching without a key/invalidation story; concurrency for dependent requests; eager loading everything "just in case" (over-eager loading is itself waste).

## Performance checklist

Run this checklist over each scoped area. Every checked box needs evidence before it becomes a finding.

```text
□ Are queries executed unnecessarily?
□ Are queries executed inside loops?
□ Are relationships causing N+1 queries?
□ Is data loaded that is never needed?
□ Is a collection materialized unnecessarily?
□ Is SQL work being performed in PHP?
□ Is PHP work being performed where SQL is more appropriate?
□ Are aggregates done in PHP unnecessarily?
□ Are large datasets loaded into memory?
□ Is pagination/chunking/lazy iteration appropriate?
□ Are unnecessary columns selected?
□ Are identical queries repeated?
□ Are external HTTP calls repeated?
□ Are Storage/filesystem operations repeated?
□ Are jobs processing too much data?
□ Is rendering causing repeated queries?
□ Is Livewire state unnecessarily large?
□ Is Filament performing repeated database work?
□ Is the suggested optimization semantically equivalent?
□ Is the optimization meaningful enough to report?
```

The last two boxes gate everything above them.
