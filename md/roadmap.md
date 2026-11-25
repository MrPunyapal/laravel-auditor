---
title: Development roadmap
description: Planned milestones for evidence-based Laravel change review, verification, and security intelligence.
order: 15
slug: roadmap
---

# Development roadmap

Last reviewed: 2026-11-25. Starting point: [v0.6.0](https://github.com/MrPunyapal/laravel-auditor/releases/tag/v0.6.0).

Our goal is to help developers answer: **What changed in my Laravel application, what could break, and what evidence supports that conclusion?**

The milestones below are proposals, not release commitments or delivery dates. Version numbers may change based on compatibility, feedback, and implementation evidence. Existing coding agents remain responsible for application-code reasoning; Auditor provides deterministic evidence and a repeatable review workflow.

## Already shipped

- [x] v0.3.0: Composer advisory exports, optional lockfile and production-only auditing, npm audit context, and additional evidence-first security rules.
- [x] v0.4.0: Route and authorization snapshots, optional schema snapshots, structural comparisons, and GitHub Actions annotations.
- [x] v0.5.0: Migration risk indicators, opt-in migration source review, conditional database-driver guidance, and historical findings baselines.
- [x] v0.6.0: Read-only `auditor:review` combining saved snapshots, optional schema risks, and supplied findings/baselines in JSON. Verified PHP 8.6 compatibility with Laravel 13.

Current commands and limitations are documented in [Change review](change-review.md), [Findings and reports](findings.md), [Context snapshots](snapshots.md), and [Migration risk indicators](migration-risk.md).

## v0.7.0 — Practical pull request change review

The following features are on the default branch and are listed under Unreleased in the changelog.

- [x] **Regression-aware baselines.** Version 2 records lifecycle status. Opt in to CI checks for increases in severity and reopened findings, in addition to new findings. Existing baselines and the default policy remain supported.
- [x] **Human-readable reports.** Change review supports Markdown and terminal text alongside its versioned JSON report.
- [x] **Snapshot provenance.** Opt-in Git, route cache, and runtime metadata helps compare the conditions under which snapshots were captured. The metadata is unsigned.
- [x] **Unified migration review.** Explicitly select registered migration source and request database guidance when both snapshots include schema evidence.
- [x] **Git-aware artifact review.** Associate explicitly supplied clean snapshots with the local merge base and HEAD. Changed paths are reported separately from complete findings.
- [x] **GitHub Actions example.** Consume trusted artifacts and run separate structural review and findings policy steps. Annotations are optional.

See the [change review guide](change-review.md) for how to capture evidence, review differences, and configure the CI gate.

## Proposed v0.8.0 — Deeper Laravel-specific verification

Extend the existing review contract incrementally rather than introduce unrelated scanners.

- [ ] Configuration compatibility changes using allowlisted, non-secret metadata; no environment values or credentials.
- [ ] Dependency changes using committed lockfile data, including direct/transitive version changes and available advisory context; do not confuse outdated versions with vulnerabilities.
- [ ] Queue, event/listener, and scheduler metadata comparisons, with explicit limits for dynamic registration and runtime behavior.
- [ ] Authorization regression evidence beyond route metadata where it is safe and reproducible; distinguish changed policies/middleware from a demonstrated authorization bypass.
- [ ] Regression fixtures for supported Laravel patterns and database drivers, with coverage boundaries documented.

Exit criteria: new sections are optional, deterministic, versioned where necessary, and distinguish unavailable or unsupported data from unchanged data.

## Proposed v0.9.0 — Optional security intelligence

Build on existing Composer auditing instead of duplicating Composer or npm scanners.

- [ ] Evaluate advisory enrichment using GitHub Security Advisories and OSV; match and deduplicate CVE, GHSA, and ecosystem advisory identifiers.
- [ ] Enrich applicable findings with CISA Known Exploited Vulnerabilities (KEV) and FIRST EPSS metadata. Keep exploitation probability and known exploitation separate from severity and application-specific exploitability.
- [ ] Verify affected and patched version ranges, including transitive dependencies; offer actionable upgrade guidance without installing or updating packages.
- [ ] Explore Laravel-specific applicability checks grounded in actual code and configuration.

Before implementing any external provider, verify current terms, licensing/attribution, API limits, freshness, caching, timeout/failure behavior, and privacy implications. Network access must be explicit for new integrations, optional, and free of required paid services. Unavailable or stale intelligence must not imply a clean audit.

## Proposed v1.0.0 — Mature verification workflow

Treat 1.0 as a quality and compatibility milestone, not a feature-count target.

- [ ] Stabilize and document public command, config, MCP, finding, snapshot, baseline, and review contracts with a migration policy.
- [ ] Provide controlled, opt-in runtime verification in isolated test environments; no production mutations, no automatic execution of untrusted code, and no claims beyond observed behavior.
- [ ] Evaluate representative Laravel applications using reproducible fixtures and independent expected outcomes; measure false positives, missed findings, coverage, and limitations without inventing benchmarks.
- [ ] Validate Laravel Boost and standalone agent integrations, supported PHP/Laravel combinations, and database-driver behavior.
- [ ] Publish practical end-to-end examples and contributor guidance.

Automatic remediation remains a separate, approval-based future investigation, not a prerequisite for 1.0. See [Future scope](future.md) for other deferred ideas.

## Engineering principles

- Evidence over guesses; severity and confidence are distinct.
- Read-only auditing by default. Report structural observations as observations, not confirmed vulnerabilities or deployment guarantees.
- Agent-agnostic, with optional Laravel Boost integration. Deterministic review features should not require an AI service.
- Free and open-source core; no mandatory paid APIs, hosted service, or telemetry.
- Prefer existing abstractions, small atomic PRs, explicit unknown states, and backward-compatible additions.
- Never execute untrusted migration source or silently run historical application revisions.
- Every feature needs focused regression tests, updated source documentation, and passing applicable CI checks (PHPStan, Pint, Pest, type coverage, supported PHP/Laravel/OS combinations). Rebuild generated documentation via the existing workflow.
- Reassess the roadmap after each milestone using real developer feedback and verified evidence.
