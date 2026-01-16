# PHP FS (Filesystem Helpers)

A lightweight, production-ready PHP library for filesystem operations with proper validation, error handling, and comprehensive features.

## Features

- **Static utility classes** - Clean, simple API with no instantiation required
- **Proper exception hierarchy** - Granular exception types for precise error handling
- **Memory-efficient operations** - Stream-based operations for large files
- **File locking** - Concurrent access protection
- **Atomic writes** - Prevent partial writes on failure
- **Symbolic link support** - Create, read, and manipulate symlinks
- **Path utilities** - Safe path manipulation and traversal prevention
- **Dry-run mode** - Preview destructive operations before executing

## Requirements

- PHP 8.0+
- ext-fileinfo

## Install

```shell
composer require likeajohny/php-fs
```

## Quick Start

```php
use PhpFs\File;
use PhpFs\Directory;
use PhpFs\Path;

// File operations
File::create('/path/to/file.txt');
File::write('/path/to/file.txt', 'Hello World');
$content = File::read('/path/to/file.txt');

// Directory operations
Directory::create('/path/to/dir');
$files = Directory::files('/path/to/dir');
Directory::remove('/path/to/dir');

// Path utilities
$normalized = Path::normalize('/foo/../bar/./baz'); // '/bar/baz'
$joined = Path::join('/foo', 'bar', 'baz.txt');     // '/foo/bar/baz.txt'
```

## API Reference

### File Class

#### Constants
```php
File::DEFAULT_FILE_MODE  // 0644 - Default permissions for new files
```

#### Basic Operations

```php
// Create a new file (directory must exist)
File::create(string $file, int $mode = 0644): mixed

// Check if file exists
File::exists(string $file): bool

// Get file information (dirname, basename, extension, permissions, mime_type, mime_encoding)
File::info(string $file): array

// Read file contents
File::read(string $file): string

// Write content to file (overwrites existing content)
File::write(string $file, string $content, bool $lock = false): int

// Append content to file
File::append(string $file, string $content): int

// Prepend content to file
File::prepend(string $file, string $content): int

// Copy file
File::copy(string $file, string $targetFile): bool

// Move/rename file
File::move(string $file, string $targetFile): bool

// Remove file (with optional dry-run)
File::remove(string $file, bool $dryRun = false): bool|string
```

#### Advanced Operations

```php
// Touch file (create if not exists, update timestamps)
File::touch(string $file, ?int $mtime = null, ?int $atime = null): bool

// Truncate file to specified size
File::truncate(string $file, int $size = 0): bool

// Write with exclusive lock
File::writeWithLock(string $file, string $content, int $lockType = LOCK_EX): int

// Read with shared lock
File::readWithLock(string $file): string

// Atomic write (write to temp, then rename)
File::writeAtomic(string $file, string $content): int

// Swap two files atomically
File::swap(string $file1, string $file2): bool
```

#### Streaming Operations

```php
// Read file line by line (memory-efficient)
foreach (File::lines($file) as $lineNumber => $line) {
    echo "Line $lineNumber: $line";
}

// Read file in chunks
foreach (File::chunks($file, 8192) as $chunkNumber => $chunk) {
    process($chunk);
}

// Stream copy (for large files)
File::copyStream(string $source, string $target, ?int $length = null): int
```

#### Symbolic Links

```php
// Check if path is a symlink
File::isLink(string $path): bool

// Read symlink target
File::readLink(string $link): string

// Create symlink
File::link(string $target, string $link): bool
```

### Directory Class

#### Constants
```php
Directory::DEFAULT_DIR_MODE  // 0755 - Default permissions for new directories
```

#### Basic Operations

```php
// Create directory (recursive by default)
Directory::create(string $dir, int $permissions = 0755, bool $recursive = true): void

// Check if directory exists
Directory::exists(string $dir): bool

// Get directory information
Directory::info(string $dir): array
// Returns: path, basename, permissions, owner, group, mtime, atime

// Get total size of directory contents (recursive)
Directory::size(string $dir): int

// Count files and directories
Directory::count(string $dir, bool $recursive = true): array
// Returns: ['files' => 42, 'directories' => 7]

// Copy directory recursively
Directory::copy(string $dir, string $targetDir): bool

// Move/rename directory
Directory::move(string $dir, string $targetDir): bool

// Empty directory (keep directory, remove contents)
Directory::empty(string $dir, bool $dryRun = false): bool|array

// Remove directory recursively (with optional dry-run)
Directory::remove(string $dir, bool $dryRun = false): bool|array
```

#### Listing Operations

```php
// List directory contents (nested structure)
Directory::list(string $dir, bool $recursive = true, bool $flatten = false): array

// List only files
Directory::files(string $dir, bool $recursive = true): array

// List only subdirectories
Directory::directories(string $dir, bool $recursive = true): array

// Glob pattern matching
Directory::glob(string $pattern, int $flags = 0): array

// Find with custom filter
Directory::find(string $dir, callable $filter, bool $recursive = true): array
```

#### Symbolic Links

```php
// Check if path is a symlink
Directory::isLink(string $path): bool

// Read symlink target
Directory::readLink(string $link): string

// Create symlink to directory
Directory::link(string $target, string $link): bool
```

### Path Class

```php
use PhpFs\Path;

// Normalize path (resolve . and .., normalize separators)
Path::normalize('/foo/../bar/./baz');  // '/bar/baz'

// Join path segments
Path::join('/foo', 'bar', 'baz.txt');  // '/foo/bar/baz.txt'

// Check if path is absolute
Path::isAbsolute('/foo/bar');  // true
Path::isAbsolute('foo/bar');   // false

// Check if path is relative
Path::isRelative('foo/bar');   // true

// Get directory name
Path::dirname('/foo/bar/baz.txt');  // '/foo/bar'

// Get base name
Path::basename('/foo/bar.txt');         // 'bar.txt'
Path::basename('/foo/bar.txt', '.txt'); // 'bar'

// Get extension
Path::extension('/foo/bar.txt');  // 'txt'

// Compare paths (after normalization)
Path::equals('/foo/./bar', '/foo/bar');  // true

// Check if path is inside directory (prevents traversal)
Path::isInside('/foo/bar/baz', '/foo/bar');  // true
Path::isInside('/foo/baz', '/foo/bar');      // false

// Get relative path
Path::relative('/foo/bar', '/foo/bar/baz');  // 'baz'
Path::relative('/foo/bar', '/foo/baz');      // '../baz'
```

## Exception Hierarchy

The library provides a granular exception hierarchy for precise error handling:

```
RuntimeException
├── FileException
│   └── FileNotFoundException
├── DirectoryException
│   └── DirectoryNotFoundException
├── PermissionException
└── LinkException
```

### Exception Factory Methods

```php
// File exceptions
FileException::createError($file, $error)
FileException::noFile($file)                    // Returns FileNotFoundException
FileException::isDirectory($path)
FileException::notReadable($file)               // Returns PermissionException
FileException::notWriteable($file)              // Returns PermissionException
FileException::readError($file, $error)
FileException::writeError($file, $error)
FileException::streamError($file, $operation)
FileException::lockFailed($file)
FileException::touchError($file)
FileException::truncateError($file)

// Directory exceptions
DirectoryException::directoryNotCreated($dir)
DirectoryException::noDirectory($dir)           // Returns DirectoryNotFoundException
DirectoryException::isFile($path)
DirectoryException::globError($pattern)
DirectoryException::scanError($dir)

// Permission exceptions
PermissionException::notReadable($path)
PermissionException::notWriteable($path)
PermissionException::notExecutable($path)

// Link exceptions
LinkException::createFailed($target, $link)
LinkException::notALink($path)
LinkException::targetNotFound($link, $target)
LinkException::readFailed($link)
```

### Catching Specific Exceptions

```php
use PhpFs\File;
use PhpFs\Exception\FileNotFoundException;
use PhpFs\Exception\PermissionException;

try {
    $content = File::read('/path/to/file.txt');
} catch (FileNotFoundException $e) {
    // Handle missing file
} catch (PermissionException $e) {
    // Handle permission error
}
```

## Dry-Run Mode

Preview destructive operations before executing them:

```php
// Preview files that would be deleted
$paths = File::remove('/path/to/file.txt', dryRun: true);
// Returns: '/path/to/file.txt'

// Preview directory contents that would be deleted
$paths = Directory::remove('/path/to/dir', dryRun: true);
// Returns: ['/path/to/dir/file1.txt', '/path/to/dir/subdir', '/path/to/dir']

// Preview what emptying would delete (excludes directory itself)
$paths = Directory::empty('/path/to/dir', dryRun: true);
// Returns: ['/path/to/dir/file1.txt', '/path/to/dir/subdir']
```

## Examples

### Memory-Efficient Large File Processing

```php
use PhpFs\File;

// Process large log file line by line
foreach (File::lines('/var/log/large.log') as $lineNum => $line) {
    if (str_contains($line, 'ERROR')) {
        echo "Error on line $lineNum: $line";
    }
}

// Copy large file using streams
File::copyStream('/path/to/large.zip', '/backup/large.zip');
```

### Concurrent File Access

```php
use PhpFs\File;

// Write with exclusive lock (prevents race conditions)
File::writeWithLock('/path/to/config.json', json_encode($config));

// Read with shared lock (allows concurrent reads)
$config = json_decode(File::readWithLock('/path/to/config.json'), true);
```

### Atomic Configuration Updates

```php
use PhpFs\File;

// Atomic write - prevents partial writes if interrupted
File::writeAtomic('/path/to/config.json', json_encode($newConfig));
```

### Safe Path Handling

```php
use PhpFs\Path;

// Prevent directory traversal attacks
$userPath = $_GET['file'];
$basePath = '/var/www/uploads';

if (Path::isInside(Path::join($basePath, $userPath), $basePath)) {
    // Safe to access
    $content = File::read(Path::join($basePath, $userPath));
}
```

### Finding Files with Filters

```php
use PhpFs\Directory;

// Find all PHP files
$phpFiles = Directory::find('/src', fn($path) => str_ends_with($path, '.php'));

// Find files modified in last 24 hours
$recentFiles = Directory::find('/logs', fn($path) =>
    is_file($path) && filemtime($path) > time() - 86400
);

// Using glob patterns
$configFiles = Directory::glob('/etc/**/*.conf');
```

## Migration Guide

### Permission Defaults Change

Version 3.0 changes default file permissions from `0766` to `0644` and directory permissions from `0766` to `0755`. This is the standard Unix convention.

If you rely on the old defaults, explicitly pass the permissions:

```php
// Old behavior (not recommended)
File::create('/path/to/file.txt', 0766);
Directory::create('/path/to/dir', 0766);

// New defaults (recommended)
File::create('/path/to/file.txt');     // 0644
Directory::create('/path/to/dir');     // 0755
```

### Return Types

- `Directory::copy()` now returns `bool` (was `void`)
- `Directory::empty()` now returns `bool` (was `void`)
- `File::append()` now returns only bytes written (not total file size)

## Testing

```bash
# Run tests
composer test

# Run specific test
vendor/bin/phpunit --filter testMethodName
```

## Contributing

Issues and pull requests are welcome at https://github.com/likeajohny/php-fs

## License

MIT
