---
title: Prompt examples
description: Ready-to-use prompts for running Laravel Auditor audits, from a quick discover pass to focused verification and DSA reviews.
og_title: Laravel Auditor prompt examples
og_description: Copy-paste prompts for full audits, quick discovery, domain scoping, filtered verification, and DSA reviews.
order: 5
slug: prompts
---

Pick the prompt that matches what you want out of the run. Every prompt is read-only: the agent gathers evidence and reports, it never modifies application code.

## Full audit

The complete methodology pass: discover, scope, verify, report.

```text
You are auditing the Laravel application in this project using the Laravel Auditor methodology.

1. Use the laravel-audit skill. Follow its Discover -> Scope -> Verify -> Report workflow.
2. Start by calling the context MCP tools to gather deterministic facts BEFORE reading code:
   - project_info — PHP/Laravel versions, database, ecosystem signals
   - routes — the full route surface
   - models — all models with fillable/guarded, casts, relationships
   - migrations — schema changes over time
   - database_schema — actual tables/columns/indexes
   - dependencies — installed packages and versions
   - configuration — config keys in use
   - policies_authorization — gates, policies, auth middleware
   - jobs_events_schedules — queues, events, cron
   - tests — test suite: framework, case counts (feature/unit)
   - subsystems — ownership-bounded inventory for a DSA-style coordinator audit
3. Scope the relevant domains (e.g., security, database, architecture, testing). Do NOT audit everything superficially — pick the domains with the most risk signal and go deep.
4. For every potential finding, verify against actual files, routes, or schema. Never report a guess.
5. Report findings ranked P0–P3, each with: file/route/schema evidence, the rule violated, why it matters, and a concrete fix.
6. Write findings to storage/auditor-findings.json and render the report with auditor:report.
7. Be read-only. Do not modify any application code.
```

## Quick discover pass

A fast, non-exhaustive first look when you only want orientation:

```text
Start with a Discover phase only: run all 11 context tools, summarize what this app is (framework versions, database, route surface, model list, test coverage), and flag any immediate red flags in 3-5 bullets. Do not write findings yet.
```

## Domain-focused audit

When you already know where the risk is, scope hard instead of skimming everything:

```text
Audit this application for security issues only, using the laravel-audit skill and its security rules.

1. Pull project_info, routes, policies_authorization, and dependencies first.
2. Focus on authorization boundaries, mass assignment, dangerous file handling, and dependency advisories.
3. Verify every candidate finding against real code before reporting it.
4. Rank findings P0–P3 with concrete evidence and fixes. Read-only.
```

Swap the domain and tool list for `database`, `architecture`, or `testing` as needed.

## Focused verification with tool filters

The `routes`, `models`, `database_schema`, and `dependencies` tools accept optional read-only filters. Use them when you are verifying one specific slice instead of exploring:

```text
Verify one suspicion end-to-end using filtered context queries, then report:

1. Call routes with { "uri": "api" } and review every API route's middleware.
2. Call database_schema with { "table": "users" } and check the columns against what the code assumes.
3. Call dependencies with { "package": "sanctum" } and check the version against known advisories.
4. For each problem you can prove with this evidence, write a finding with the rule ID, severity, confidence, and the exact query result you relied on.
5. If the filtered result was empty, say so explicitly — an empty result is evidence too.
```

Filtered responses always include `total_count` (the size of the unfiltered inventory), so you know exactly how much was narrowed. See [MCP tools](/mcp/) for the full filter reference.

## Re-audit after fixes

When findings were addressed and you want a delta check:

```text
Re-audit this application against storage/auditor-findings.json from the previous run.

1. Load the previous findings and re-verify each one against the current code.
2. Update each finding's status to a schema-valid value: fixed, open (still present), accepted (known and tolerated), or dismissed (not applicable).
3. If an area changed into a different problem, close the old finding (fixed or dismissed) and add a new finding with a new id instead of inventing a status.
4. Run a fresh Discover pass to catch anything the previous audit missed.
5. Regenerate the report. Read-only.
```

## DSA / subsystem audit

For a bounded data-structure and ownership review:

```text
Use the laravel-audit-dsa skill. Inventory subsystems, review them in bounded read-only lanes, then rank P0–P3.
```

See [DSA audit](/dsa/) for how the coordinator splits the work.
