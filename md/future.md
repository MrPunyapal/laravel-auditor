---
title: Future scope
description: Features that may influence architecture but are not part of V1.
og_title: Laravel Auditor future scope
og_description: Planned capabilities that may shape the architecture but are not part of V1.
order: 9
slug: future
---

These features may influence the architecture. They are **not** required to ship V1 and are not promises.

## Legacy and standalone support

- **F1: Legacy runner** — Audit older Laravel applications without forcing PHP or Laravel upgrades.
- **F2: Standalone CLI** — A package-runner style command for projects whose runtime is too old for the current package.

## Agent and runtime improvements

- **F8: Runtime verification** — Safe, opt-in checks that turn likely findings into confirmed ones by observing actual behavior.

## Remediation

- **F3: Auto-fix / remediation** — Turn a verified finding into a proposed fix, tests, and a patch. Any implementation must require explicit user approval and stay read-only by default.

## CI and baselines

- **F4: CI policies** — Ignored findings, historical baselines, new-vs-existing detection, trend tracking. `auditor:ci` already fails on severity.

## Audit depth

- **F5: More domains** — Deployment, observability, API design domains — only when they stay evidence-first.
- **F6: Deeper ecosystem packs** — Tailwind, Passport, PHPUnit-specific packs — only when they stay evidence-first.

## Reporting

- **F7: Richer reporting** — HTML output, GitHub annotations, PR comments, TUI, IDE integration. SARIF is already available.

## Broader scope

- **F9: Organization policy packs** — Multi-tenant or organization-level rule configuration.
- **F10: Agent profiler** — Understand which agents produce the best findings.
- **F11: Other-framework auditors** — Separate products for non-Laravel frameworks.

## Constraints that survive

These principles apply regardless of which features ship:

- Agent-agnostic core
- Boost optional
- Evidence over guesses
- Read-only by default
- Few high-quality rules
- Never claim more certainty than the evidence supports
