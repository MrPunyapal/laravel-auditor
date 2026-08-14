---
title: Contributing
description: How to work on Laravel Auditor locally, including documentation.
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

If Docsmith is missing a feature this package needs, contribute it upstream in `mrpunyapal/docsmith` rather than forking the builder here.
