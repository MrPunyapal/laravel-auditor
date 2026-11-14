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

The comparison identifies added/removed routes, route metadata modifications, route registration reorderings, gate/alias changes, and changed policy mappings. It rejects invalid or incompatible snapshots and ambiguous duplicate route identities rather than reporting a clean result.

This is a structural comparison, **not** proof of an authorization vulnerability. A missing middleware may have equivalent protection inside a controller. An agent or reviewer must verify the actual authorization path before reporting security findings.

Keep snapshots private if route names or policy classes reveal internal application structure.

Route registration order is captured independently of sorted route details. An order change is not itself a verified authorization problem, but may affect matching when patterns overlap.

Routes include both declared middleware and the resolved route middleware stack, including group expansion and exclusions. Global HTTP middleware and changes inside middleware, gate, or policy implementations are outside this metadata snapshot. Policy mappings include explicitly registered mappings; automatic policy discovery is not enumerated.

Capture both snapshots with the same route-cache state. Cached routes represent the cached application configuration; rebuild the route cache after changing routes. Laravel can generate names for unnamed routes during caching, so cached and uncached snapshots may differ even without a source change.
