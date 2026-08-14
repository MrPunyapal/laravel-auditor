---
title: Laravel Auditor
description: Evidence-based, agent-agnostic auditing tools and methodology for Laravel applications.
order: 1
slug: index
sidebar_label: Overview
---

Laravel Auditor equips an existing AI coding agent with a specialized, evidence-based methodology and toolset for auditing Laravel applications.

It is **not** a generic code-review prompt, a replacement for Laravel Boost, or an autonomous AI product. The agent remains the reasoning engine. This package provides the audit workflow, domain knowledge, rules, finding schema, and structured Laravel context tools.

## Why it exists

AI agents can already read a Laravel codebase. They still tend to:

- guess at framework behavior instead of collecting project facts
- report style opinions as high-severity findings
- invent vulnerabilities from uncommon patterns
- skip verification before reporting a serious finding

Laravel Auditor gives the agent a repeatable audit workflow and deterministic project context so findings stay specific, evidenced, and trustworthy.

## First five minutes

1. Install the package as a development dependency
2. Run Boost setup or `auditor:install`
3. Open your preferred AI agent
4. Ask it to audit the project
5. Receive structured findings with evidence

## What V1 covers

Six core domains: **security**, **performance**, **architecture**, **database**, **testing**, and **Laravel conventions**.

Optional ecosystem packs apply only when those packages are installed: Livewire, Filament, Inertia, Sanctum, Pest, and queues.

A separate **DSA / organizing-model** pass inventories subsystems, reviews them in bounded lanes, and ranks findings P0–P3.

## Next

- [Installation](/installation/)
- [Usage](/usage/)
- [Agent setup](/agents/)
