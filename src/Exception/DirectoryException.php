<?php

declare(strict_types=1);

namespace PhpFs\Exception;

use RuntimeException;

use function sprintf;

class DirectoryException extends RuntimeException
{
    public static function directoryNotCreated(string $dir): self
    {
        return new self(
            sprintf('The directory "%s" could not be created.', $dir)
        );
    }

    public static function noDirectory(string $noDir): DirectoryNotFoundException
    {
        return new DirectoryNotFoundException(
            sprintf('Given value "%s" doesn\'t appear to be a directory.', $noDir)
        );
    }

    public static function isFile(string $path): self
    {
        return new self(
            sprintf('Expected directory but got file: "%s".', $path)
        );
    }

    public static function globError(string $pattern, ?array $error = null): self
    {
        return new self(
            sprintf('Glob pattern "%s" failed. %s', $pattern, $error['message'] ?? '')
        );
    }

    public static function scanError(string $dir, ?array $error = null): self
    {
        return new self(
            sprintf('Could not scan directory "%s". %s', $dir, $error['message'] ?? '')
        );
    }
}
