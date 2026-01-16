<?php

declare(strict_types=1);

namespace PhpFs\Exception;

use RuntimeException;

use function sprintf;

class FileException extends RuntimeException
{
    public static function createError(string $file, ?array $error): self
    {
        return new self(
            sprintf('Could not create file "%s". %s', $file, $error['message'] ?? '')
        );
    }

    public static function noFile(string $file): FileNotFoundException
    {
        return new FileNotFoundException(
            sprintf('Given value "%s" doesn\'t appear to be a file.', $file)
        );
    }

    public static function isDirectory(string $path): self
    {
        return new self(
            sprintf('Expected file but got directory: "%s".', $path)
        );
    }

    public static function notReadable(string $file): PermissionException
    {
        return PermissionException::notReadable($file);
    }

    public static function readError(string $file, ?array $error): self
    {
        return new self(
            sprintf('Could not read from file "%s". %s', $file, $error['message'] ?? ''),
        );
    }

    public static function notWriteable(string $file): PermissionException
    {
        return PermissionException::notWriteable($file);
    }

    public static function writeError(string $file, ?array $error): self
    {
        return new self(
            sprintf('Could not write to file "%s". %s', $file, $error['message'] ?? '')
        );
    }

    public static function streamError(string $file, string $operation, ?array $error = null): self
    {
        return new self(
            sprintf('Stream %s failed for file "%s". %s', $operation, $file, $error['message'] ?? '')
        );
    }

    public static function lockFailed(string $file): self
    {
        return new self(
            sprintf('Could not acquire lock on file "%s".', $file)
        );
    }

    public static function touchError(string $file, ?array $error = null): self
    {
        return new self(
            sprintf('Could not touch file "%s". %s', $file, $error['message'] ?? '')
        );
    }

    public static function truncateError(string $file, ?array $error = null): self
    {
        return new self(
            sprintf('Could not truncate file "%s". %s', $file, $error['message'] ?? '')
        );
    }
}
