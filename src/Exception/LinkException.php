<?php

declare(strict_types=1);

namespace PhpFs\Exception;

use RuntimeException;

use function sprintf;

class LinkException extends RuntimeException
{
    public static function createFailed(string $target, string $link, ?array $error = null): self
    {
        return new self(
            sprintf('Could not create symbolic link "%s" pointing to "%s". %s', $link, $target, $error['message'] ?? '')
        );
    }

    public static function notALink(string $path): self
    {
        return new self(
            sprintf('Path "%s" is not a symbolic link.', $path)
        );
    }

    public static function targetNotFound(string $link, string $target): self
    {
        return new self(
            sprintf('Symbolic link "%s" target "%s" does not exist.', $link, $target)
        );
    }

    public static function readFailed(string $link, ?array $error = null): self
    {
        return new self(
            sprintf('Could not read symbolic link "%s". %s', $link, $error['message'] ?? '')
        );
    }
}
