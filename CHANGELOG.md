# Changelog

All notable changes to `larascan` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-alpha.1] - 2026-10-01

### Added
- Dynamic Laravel Core inventory loader (`CoreInventoryLoader`) discovering 138+ core Facades, Utilities (`Sleep`, `Benchmark`, `Number`, `Str`, `Arr`), and global Helpers directly from `vendor/laravel/framework`.
- Single-pass AST inventory scanner (`InventoryScanner` & `InventoryVisitor`) traversing entire codebases in milliseconds.
- Laravel 13 Native Adoption Percentage calculation and Termwind visual progress bar.
- Standalone CLI executable `bin/larascan` with `--used`, `--unused`, `--skip-tests`, and `--json` support.
- Laravel auto-discovery ServiceProvider (`LarascanServiceProvider`) registering `native:stats` Artisan command.
- Zero manual rule lists, zero false positives, pure reflection & AST detection.
