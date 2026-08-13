---
name: laravel-audit-testing
description: >
  Audit a Laravel application's test suite: meaningful coverage, authorization
  tests, edge cases, and Pest/PHPUnit conventions. Use when auditing testing.
metadata:
  agent: any
---

# Laravel Testing Audit

Audit the test suite of the Laravel application. **Do not equate line coverage with test quality.**

## What to look for

- Critical business flows with little or no test coverage: purchases, authorization boundaries, data mutations, integrations.
- Tests that do not verify meaningful behavior: tests that only assert the request succeeded, tests that pin implementation details, or tests that mock everything away.
- Missing authorization/security tests around critical boundaries: no test proving an unauthorized user is rejected.
- Important edge cases left untested: boundary values, empty states, duplicates, permissions variations.
- Brittle testing patterns: tests coupled to exact query counts, implementation ordering, or unrelated details.
- Excessive implementation-detail testing: tests that break on any refactor without protecting behavior.
- Inconsistent use of Pest/PHPUnit conventions across the suite.

## Evidence requirements

Every testing finding needs at least:

- The critical flow or boundary (file + route/controller/service).
- The relevant test files that should cover it.
- What behavior is or is not asserted.

## False positives

- Coverage may exist under a different name or location — check before reporting.
- A missing test for an unimportant flow is not a finding.
- Do not report line-coverage numbers as proof of quality (or lack of it).

## Severity guidance

- `high`: a critical boundary (e.g. authorization) has no meaningful tests.
- `medium`: an important flow lacks meaningful coverage or tests are brittle.
- `low`: testing hygiene issues.
- `info`: observations.
