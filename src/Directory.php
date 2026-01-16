<?php

declare(strict_types=1);

namespace PhpFs;

use PhpFs\Exception\DirectoryException;
use PhpFs\Exception\LinkException;

use function array_diff;
use function basename;
use function error_get_last;
use function fileatime;
use function filegroup;
use function filemtime;
use function fileowner;
use function fileperms;
use function filesize;
use function glob;
use function is_callable;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function readlink;
use function realpath;
use function rename;
use function rmdir;
use function scandir;
use function sprintf;
use function substr;
use function symlink;
use function unlink;

class Directory
{
    public const DEFAULT_DIR_MODE = 0755;

    public static function create(string $dir, int $permissions = self::DEFAULT_DIR_MODE, bool $recursive = true): void
    {
        // https://github.com/kalessil/phpinspectionsea/blob/master/docs/probable-bugs.md#mkdir-race-condition
        if (!is_dir($dir) && !mkdir($dir, $permissions, $recursive) && !is_dir($dir)) {
            throw DirectoryException::directoryNotCreated($dir);
        }
    }

    public static function exists(string $dir): bool
    {
        return is_dir($dir);
    }

    public static function remove(string $dir, bool $dryRun = false): bool|array
    {
        self::validate($dir);

        if ($dryRun) {
            return self::collectRemovePaths($dir);
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_link($source)) {
                // Handle symlinks directly (works for broken symlinks too)
                unlink($source);
            } elseif (is_dir($source)) {
                self::remove($source);
            } else {
                File::remove($source);
            }
        }

        return rmdir($dir);
    }

    public static function empty(string $dir, bool $dryRun = false): bool|array
    {
        self::validate($dir);

        if ($dryRun) {
            $paths = self::collectRemovePaths($dir);
            // Remove the directory itself from the list (we're only emptying, not removing)
            return array_filter($paths, fn($path) => $path !== $dir);
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_link($source)) {
                // Handle symlinks directly (works for broken symlinks too)
                unlink($source);
            } elseif (is_dir($source)) {
                self::remove($source);
            } else {
                File::remove($source);
            }
        }

        return true;
    }

    private static function collectRemovePaths(string $dir): array
    {
        $paths = [];
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_dir($source)) {
                $paths = [...$paths, ...self::collectRemovePaths($source)];
            } else {
                $paths[] = $source;
            }
        }

        $paths[] = $dir;

        return $paths;
    }

    public static function move(string $dir, string $targetDir): bool
    {
        self::create($targetDir);
        self::validate($dir);
        self::validate($targetDir);

        return rename($dir, $targetDir);
    }

    public static function copy(string $dir, string $targetDir): bool
    {
        self::create($targetDir);
        self::validate($dir);
        self::validate($targetDir);

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";
            $destination = "$targetDir/$file";

            is_dir("$source")
                ? self::copy("$source", "$destination")
                : File::copy("$source", "$destination");
        }

        return true;
    }

    public static function list(string $dir, bool $recursive = true, bool $flatten = false): array
    {
        self::validate($dir);

        if ($flatten) {
            return self::listFlattened($dir, $recursive);
        }

        $list = [];
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_dir($source) && $recursive) {
                $list[$dir][$source] = self::list($source)[$source];
            } elseif (is_file($source)) {
                $list[$dir][] = $file;
            }
        }

        return $list;
    }

    public static function info(string $dir): array
    {
        self::validate($dir);

        $realPath = realpath($dir);

        return [
            'path' => $realPath !== false ? $realPath : $dir,
            'basename' => basename($dir),
            'permissions' => substr(sprintf('%o', fileperms($dir)), -4),
            'owner' => fileowner($dir),
            'group' => filegroup($dir),
            'mtime' => filemtime($dir),
            'atime' => fileatime($dir),
        ];
    }

    public static function size(string $dir): int
    {
        self::validate($dir);

        $totalSize = 0;
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_dir($source)) {
                $totalSize += self::size($source);
            } else {
                $totalSize += filesize($source);
            }
        }

        return $totalSize;
    }

    public static function count(string $dir, bool $recursive = true): array
    {
        self::validate($dir);

        $counts = ['files' => 0, 'directories' => 0];
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_dir($source)) {
                $counts['directories']++;
                if ($recursive) {
                    $subCounts = self::count($source, true);
                    $counts['files'] += $subCounts['files'];
                    $counts['directories'] += $subCounts['directories'];
                }
            } else {
                $counts['files']++;
            }
        }

        return $counts;
    }

    public static function files(string $dir, bool $recursive = true): array
    {
        self::validate($dir);

        $result = [];
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_dir($source)) {
                if ($recursive) {
                    $result = [...$result, ...self::files($source, true)];
                }
            } else {
                $result[] = $source;
            }
        }

        return $result;
    }

    public static function directories(string $dir, bool $recursive = true): array
    {
        self::validate($dir);

        $result = [];
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_dir($source)) {
                $result[] = $source;
                if ($recursive) {
                    $result = [...$result, ...self::directories($source, true)];
                }
            }
        }

        return $result;
    }

    public static function glob(string $pattern, int $flags = 0): array
    {
        $result = @glob($pattern, $flags);
        if ($result === false) {
            throw DirectoryException::globError($pattern, error_get_last());
        }

        return $result;
    }

    public static function find(string $dir, callable $filter, bool $recursive = true): array
    {
        self::validate($dir);

        $result = [];
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if ($filter($source)) {
                $result[] = $source;
            }

            if (is_dir($source) && $recursive) {
                $result = [...$result, ...self::find($source, $filter, true)];
            }
        }

        return $result;
    }

    public static function isLink(string $path): bool
    {
        return is_link($path);
    }

    public static function readLink(string $link): string
    {
        if (!is_link($link)) {
            throw LinkException::notALink($link);
        }

        $target = readlink($link);
        if ($target === false) {
            throw LinkException::readFailed($link, error_get_last());
        }

        return $target;
    }

    public static function link(string $target, string $link): bool
    {
        $result = @symlink($target, $link);
        if ($result === false) {
            throw LinkException::createFailed($target, $link, error_get_last());
        }

        return $result;
    }

    private static function listFlattened(string $dir, bool $recursive): array
    {
        $result = [];
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $source = "$dir/$file";

            if (is_dir($source)) {
                $result[] = $source;
                if ($recursive) {
                    $result = [...$result, ...self::listFlattened($source, true)];
                }
            } else {
                $result[] = $source;
            }
        }

        return $result;
    }

    private static function validate(string $dir): void
    {
        if (!is_dir($dir)) {
            throw DirectoryException::noDirectory($dir);
        }
    }
}
