# Performance finding contract

These rules govern every `performance`-domain finding, on top of the core guidelines. They exist to keep performance reports trustworthy: verified, mechanistic, and free of micro-optimization noise.

## Report the pipeline, not just the pattern

A performance finding must show that you moved through the full investigation pipeline:

```text
Signal → Context → Behavior → Verification → Impact → Finding
```

Findings that skip verification or impact are rejected by this contract even when the underlying pattern is real.

## Semantic equivalence is mandatory

Before recommending any optimization, verify it produces identical observable results **in this exact usage**:

1. Confirm what else consumes the value (collection reuse invalidates most query-side rewrites).
2. Check accessors, casts, and appended attributes for PHP-only behavior.
3. Match comparison strictness and null handling between Collection and SQL semantics.
4. Respect custom collection classes and overridden methods.
5. Keep model requirements intact (primary/foreign keys for narrowed selects).
6. Account for side effects (model events on bulk writes, lazy-load timing).

Record what you checked in `verification_notes`. "This is usually faster" is not verification.

## Impact metadata

For performance findings, describe impact structurally in `metadata.impact` using mechanism wording:

```json
"metadata": {
    "priority": "p1",
    "impact": {
        "resource": "database + memory",
        "mechanism": "Retrieves every matching order into PHP memory to compute one number; the query-level aggregate returns a single row instead.",
        "amplification": "Scales with paid-order count; dashboard is rendered on every admin login."
    }
}
```

- `resource` — which cost category applies: database queries, rows transferred, memory, CPU, network, disk I/O.
- `mechanism` — what the current form wastes and what the replacement avoids.
- `amplification` — what makes the cost grow: loop counts, table growth, request frequency.

Never invent numeric measurements ("10x faster"). Describe mechanisms; cite numbers only from evidence that actually exists (a logged slow-query entry, an N+1 count from a debugbar capture).

## Severity discipline

Severity follows reach × frequency × amplification, not pattern scariness:

| Severity | Typical confirmed instance |
| --- | --- |
| high | Query explosion or heavy amplification on hot/high-volume paths |
| medium | Clear unnecessary work with real traffic or growth behind it |
| low | Real but small inefficiency on modest data |
| info | Worth knowing, not worth acting on |

Do not inflate: a correct but tiny optimization stays out of the report entirely.

## Noise floor

Do not report when any of these hold:

- The materialized collection/result has another consumer.
- The replacement cannot be shown equivalent for this usage.
- The dataset is bounded and small.
- The fix trades real readability or correctness risk for negligible gain.

A developer reading the performance section should believe every line is worth investigating.
