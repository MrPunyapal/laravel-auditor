# Findings and reports

Laravel Auditor does not invent findings. The agent produces them; the package stores, validates, and renders them.

## Finding JSON

See `resources/auditor/schema/finding.schema.json` and the example in `resources/auditor/examples/findings.json`.

Required fields: `id`, `rule_id`, `title`, `domain`, `severity`, `confidence`, `summary`, `why_it_matters`.

```bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=markdown
php artisan auditor:report --example
```

## Collecting project facts without MCP

```bash
php artisan auditor:context --list
php artisan auditor:context routes
php artisan auditor:context models --output=storage/auditor-models.json
```

## MCP

```bash
php artisan auditor:mcp
```

A client snippet lives in `resources/auditor/mcp/mcp.json.example`.
