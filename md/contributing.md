---
title: Contributing
description: How to work on Laravel Auditor locally, including documentation.
og_title: Contribute to Laravel Auditor
og_description: How to work on Laravel Auditor locally — tests, lint, static analysis, and docs.
order: 10
slug: contributing
---

Thank you for considering contributing to Laravel Auditor. Please also read [CONTRIBUTING.md](https://github.com/mrpunyapal/laravel-auditor/blob/main/.github/CONTRIBUTING.md).

## Local setup

```bash
composer install
composer build
```

The `build` command sets up the workbench with a SQLite database and runs migrations.

## Run the full validation suite

```bash
composer test
```

This runs PHPStan, Pint, type coverage, and Pest in sequence.

## Individual checks

```bash
composer lint          # Fix code style with Pint
composer lint:check    # Check code style without modifying
composer analyse       # Static analysis with PHPStan
composer test:unit     # Run Pest tests in parallel
composer test:types    # Type coverage (must be 100%)
```

## Documentation

Markdown source lives in `md/`. The static site is generated into `docs/` with [Docsmith](https://github.com/MrPunyapal/docsmith).

```bash
composer docs:build
```

**Do not edit `docs/` directly.** Edit the Markdown source in `md/` and rebuild.

### Adding a page

1. Create `md/your-page.md` with frontmatter:
   ```yaml
   ---
   title: Your Page
   description: What this page covers.
   og_title: Social title for the page
   og_description: Social description for the page
   order: 11
   slug: your-page
   ---
   ```
2. Run `composer docs:build`.
3. The page appears in the navigation based on its `order` value.

### Open Graph images

OG images are generated per page. They need Node.js, Playwright, and capturist installed once:

```bash
npm install
npx playwright install chromium
```

Set `DOCS_CAPTURE_OG=0` to skip capture (for example, in environments without Node.js).

Page-specific social titles and descriptions use `og_title` and `og_description` frontmatter. The `og_image` frontmatter key overrides the generated card image.

### Docsmith

If Docsmith is missing a feature this package needs, contribute it upstream in [mrpunyapal/docsmith](https://github.com/MrPunyapal/docsmith) rather than forking the builder.
