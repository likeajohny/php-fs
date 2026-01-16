# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP-FS is a lightweight utility library providing static helper classes for filesystem operations (file and directory manipulation) in PHP 8.0+ with proper validation and error handling.

## Commands

```bash
# Install dependencies
composer install

# Run tests
composer test
# or
vendor/bin/phpunit --testdox

# Run a single test
vendor/bin/phpunit --filter testMethodName
```

## Architecture

### Static Utility Pattern

Both `File` and `Directory` classes use exclusively static methods - they are utility classes, not instantiable objects.

### Class Structure

- **`PhpFs\File`** - File operations (create, read, write, append, prepend, copy, move, remove, info)
- **`PhpFs\Directory`** - Directory operations (create, exists, remove, empty, move, copy, list)
- **`PhpFs\Exception\FileException`** - Exception factory with static methods for file errors
- **`PhpFs\Exception\DirectoryException`** - Exception factory with static methods for directory errors

### Cross-Class Dependencies

`Directory::remove()` and `Directory::empty()` internally call `File::remove()` for file deletion during recursive operations.

### Validation Pattern

Both classes use private validation methods (`validateRead`, `validateWrite`, `validateFile`, `validate`) that throw exceptions before operations execute. Validation happens before the actual filesystem operation.

### Error Handling

Exception classes use static factory methods (e.g., `FileException::noFile()`, `DirectoryException::noDirectory()`) for creating specific error scenarios with descriptive messages.

## Testing

Tests are in `tests/` with fixtures in `tests/fixtures/content/`. Test artifacts are created in `tests/artifacts/` and cleaned up via `tearDown()`.

Key test files:
- `tests/FileTest.php` - 15 test methods covering all File operations
- `tests/DirectoryTest.php` - 6+ test methods covering recursive directory operations
