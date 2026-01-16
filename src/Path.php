<?php

declare(strict_types=1);

namespace PhpFs;

use function array_filter;
use function array_pop;
use function array_shift;
use function count;
use function dirname;
use function explode;
use function implode;
use function ltrim;
use function pathinfo;
use function preg_replace;
use function realpath;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

class Path
{
    public static function normalize(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Normalize directory separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Check if absolute path
        $isAbsolute = self::isAbsolute($path);
        $prefix = '';

        if ($isAbsolute) {
            if (DIRECTORY_SEPARATOR === '\\' && strlen($path) >= 2 && $path[1] === ':') {
                // Windows drive letter (e.g., C:\)
                $prefix = substr($path, 0, 2) . DIRECTORY_SEPARATOR;
                $path = substr($path, 3);
            } else {
                $prefix = DIRECTORY_SEPARATOR;
                $path = ltrim($path, DIRECTORY_SEPARATOR);
            }
        }

        // Split into segments and process . and ..
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (count($result) > 0 && $result[count($result) - 1] !== '..') {
                    array_pop($result);
                } elseif (!$isAbsolute) {
                    $result[] = '..';
                }
            } else {
                $result[] = $segment;
            }
        }

        $normalized = $prefix . implode(DIRECTORY_SEPARATOR, $result);

        return $normalized !== '' ? $normalized : ($isAbsolute ? DIRECTORY_SEPARATOR : '.');
    }

    public static function join(string ...$segments): string
    {
        if (count($segments) === 0) {
            return '';
        }

        $filteredSegments = array_filter($segments, fn($s) => $s !== '');
        if (count($filteredSegments) === 0) {
            return '';
        }

        // Process each segment
        $parts = [];
        $first = true;

        foreach ($filteredSegments as $segment) {
            if ($first) {
                // Keep leading slash for first absolute segment
                $parts[] = rtrim($segment, '/\\');
                $first = false;
            } else {
                $parts[] = trim($segment, '/\\');
            }
        }

        $joined = implode(DIRECTORY_SEPARATOR, $parts);

        return self::normalize($joined);
    }

    public static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        // Unix absolute path
        if ($path[0] === '/') {
            return true;
        }

        // Windows absolute path (e.g., C:\, D:/)
        if (strlen($path) >= 2 && $path[1] === ':') {
            return true;
        }

        // Windows UNC path (e.g., \\server\share)
        if (strlen($path) >= 2 && ($path[0] === '\\' && $path[1] === '\\')) {
            return true;
        }

        return false;
    }

    public static function isRelative(string $path): bool
    {
        return !self::isAbsolute($path);
    }

    public static function dirname(string $path): string
    {
        return dirname($path);
    }

    public static function basename(string $path, ?string $suffix = null): string
    {
        if ($suffix !== null) {
            return \basename($path, $suffix);
        }

        return \basename($path);
    }

    public static function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function equals(string $path1, string $path2): bool
    {
        return self::normalize($path1) === self::normalize($path2);
    }

    public static function isInside(string $path, string $directory): bool
    {
        $normalizedPath = self::normalize($path);
        $normalizedDir = self::normalize($directory);

        // Try to resolve to real paths if they exist
        $realPath = realpath($normalizedPath);
        $realDir = realpath($normalizedDir);

        if ($realPath !== false && $realDir !== false) {
            $normalizedPath = $realPath;
            $normalizedDir = $realDir;
        }

        // Ensure directory ends with separator for proper prefix matching
        if (!str_ends_with($normalizedDir, DIRECTORY_SEPARATOR)) {
            $normalizedDir .= DIRECTORY_SEPARATOR;
        }

        // Path should not be the directory itself
        if (rtrim($normalizedPath, DIRECTORY_SEPARATOR) === rtrim($normalizedDir, DIRECTORY_SEPARATOR)) {
            return false;
        }

        return str_starts_with($normalizedPath, $normalizedDir);
    }

    public static function relative(string $from, string $to): string
    {
        $fromParts = explode(DIRECTORY_SEPARATOR, self::normalize($from));
        $toParts = explode(DIRECTORY_SEPARATOR, self::normalize($to));

        // Remove empty parts
        $fromParts = array_filter($fromParts, fn($p) => $p !== '');
        $toParts = array_filter($toParts, fn($p) => $p !== '');

        // Re-index arrays
        $fromParts = [...$fromParts];
        $toParts = [...$toParts];

        // Find common prefix
        $commonLength = 0;
        $maxLength = min(count($fromParts), count($toParts));

        for ($i = 0; $i < $maxLength; $i++) {
            if ($fromParts[$i] !== $toParts[$i]) {
                break;
            }
            $commonLength++;
        }

        // Calculate relative path
        $upCount = count($fromParts) - $commonLength;
        $relativeParts = [];

        for ($i = 0; $i < $upCount; $i++) {
            $relativeParts[] = '..';
        }

        for ($i = $commonLength; $i < count($toParts); $i++) {
            $relativeParts[] = $toParts[$i];
        }

        if (count($relativeParts) === 0) {
            return '.';
        }

        return implode(DIRECTORY_SEPARATOR, $relativeParts);
    }
}
