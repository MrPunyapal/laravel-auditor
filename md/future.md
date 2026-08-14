---
title: Future scope
description: Features that may influence architecture but are not part of V1.
order: 9
slug: future
---

These features may influence architecture. They are **not** required to ship V1.

## F1: Legacy / standalone runner

Audit older Laravel applications without forcing those apps to upgrade PHP or Laravel just to install Auditor.

## F2: Standalone CLI distribution

A package-runner style command for projects whose runtime is too old for the current package.

## F3: Auto-fix / remediation

Turn a verified finding into a proposed fix, tests, and a patch. Any implementation must require explicit user approval and stay read-only by default.

## F4 / F5: CI policies and baselines

`auditor:ci` already fails on severity. Still open: ignored findings, historical baselines, new-vs-existing, trends.

## F6 / F7: More domains and deeper ecosystem packs

Deployment, observability, API design, Tailwind/Passport/PHPUnit-specific packs — only when they stay evidence-first.

## F8: Runtime verification

Safe, opt-in checks that turn likely findings into confirmed ones.

## F9: Richer reporting

HTML, GitHub annotations, PR comments, TUI, IDE integration. SARIF is already available.

## F10–F12

Organization policy packs, an agent profiler, and other-framework auditors are separate products.

## Constraints that survive

Agent-agnostic core. Boost optional. Evidence over guesses. Read-only by default. Few high-quality rules. Never claim more certainty than the evidence.
