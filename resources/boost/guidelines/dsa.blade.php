## Laravel Auditor DSA

When asked for a DSA, data-structure, ownership, or subsystem audit, use the `laravel-audit-dsa` skill.

- Read-only. Do not implement fixes during the audit.
- Inventory subsystems first (`php artisan auditor:context subsystems`).
- One worker per ownership boundary. At most two findings per subsystem.
- Skip when the local code is already clear.
- Verify every finding yourself before it enters the report.
- Rank P0–P3. Keep P3 small. Set `metadata.priority` to `p0`–`p3` and `metadata.subsystem` to the inventory id.
- Prefer `AUD-DSA-*` rules when they fit. Otherwise use the six core domains.

Do not recommend a new type just to hide existing branching.
