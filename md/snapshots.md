---
title: Context snapshots
description: Capture and compare reproducible Laravel route and authorization state.
order: 11
slug: snapshots
---

# Context snapshots

Capture an application's routes and authorization registry before and after a change:

~~~bash
php artisan auditor:snapshot --output=before.json
# Make changes, then:
php artisan auditor:snapshot --output=after.json
php artisan auditor:compare before.json after.json
~~~

Snapshots are versioned and deterministic: they contain route metadata, registered gates, policy mappings, policy filenames, and middleware aliases without a generated timestamp. They do not collect environment variables, credentials, database records, or application source.

The comparison identifies added/removed routes, route metadata modifications, gate/alias changes, and changed policy mappings. It rejects invalid or incompatible snapshots and ambiguous duplicate route identities rather than reporting a clean result.

This is a structural comparison, **not** proof of an authorization vulnerability. A missing middleware may have equivalent protection inside a controller. An agent or reviewer must verify the actual authorization path before reporting security findings.

Keep snapshots private if route names or policy classes reveal internal application structure.
