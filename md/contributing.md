---
title: Contributing
description: How to work on Laravel Auditor locally, including documentation.
og_title: Contribute to Laravel Auditor
og_description: How to work on Laravel Auditor locally — tests, lint, static analysis, and docs.
order: 10
slug: contributing
---

Please also read [CONTRIBUTING.md](https://github.com/mrpunyapal/laravel-auditor/blob/main/.github/CONTRIBUTING.md).

## Setup

```bash
composer install
composer test
```

## Quality

```bash
composer lint
composer analyse
composer test:unit
```

## Documentation

Markdown source lives in `md/`. The static site is generated into `docs/` with [Docsmith](https://github.com/MrPunyapal/docsmith).

```bash
composer docs:build
```

Add a page as `md/your-page.md` with frontmatter (`title`, `description`, `order`, `slug`). Then rebuild.

Open Graph images are generated per page. They need Node.js, Playwright, and capturist installed once:

```bash
npm install
npx playwright install chromium
```

Set `DOCS_CAPTURE_OG=0` to skip capture (for example, without Node.js). Page-specific social titles and descriptions use `og_title` and `og_description` frontmatter; `og_image` overrides the card image.

If Docsmith is missing a feature this package needs, contribute it upstream in `mrpunyapal/docsmith` rather than forking the builder here.
