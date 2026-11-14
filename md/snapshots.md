---
title: Context snapshots
description: Capture reproducible Laravel route and authorization state before changing an application.
order: 11
slug: snapshots
---

# Context snapshots

Use the versioned, deterministic snapshot to record an application's routes and authorization registry before a change:

~~~bash
php artisan auditor:snapshot --output=before.json
~~~

After the change, run the command again with a different output path. Snapshots have no generated timestamp and contain only route metadata, registered gates, policy mappings, policy file names, and middleware aliases. They do not collect environment variables, credentials, database records, or application source.

The snapshot format is version 1. It does not prove that a route is authorized: a missing middleware alias may have an equivalent authorization check inside a controller. Verification still requires reading the relevant Laravel code.

This feature only captures state. Comparison and risk assessment are separate follow-up features. Keep snapshots private if route names or policy classes reveal internal application structure.
