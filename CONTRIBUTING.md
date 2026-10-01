# Contributing to Larascan

Thank you for considering contributing to Larascan!

## Philosophy

Larascan has one core mission: **Measure and analyze Laravel Core native adoption accurately and blisteringly fast without hardcoded rule lists or AST modification risks.**

Everything is dynamically discovered directly from Laravel Core (`vendor/laravel/framework`).

## Development Setup

1. Clone the repository:
```bash
git clone https://github.com/emreyba/larascan.git
cd larascan
```

2. Install dependencies:
```bash
composer install
```

3. Run the CLI on any project:
```bash
bin/larascan path/to/project --used
```

## Pull Request Guidelines

- Ensure your code follows PSR-12 and strict typing (`declare(strict_types=1);`).
- Keep the codebase lightweight, focused, and free of unnecessary dependencies.
- Open an issue before submitting large architectural changes.
