<?php

declare(strict_types=1);

namespace PhpFs\Exception;

use RuntimeException;

use function sprintf;

class PermissionException extends RuntimeException
{
    public static function notReadable(string $path): self
    {
        return new self(
            sprintf('Path "%s" is not readable.', $path)
        );
    }

    public static function notWriteable(string $path): self
    {
        return new self(
            sprintf('Path "%s" is not writeable.', $path)
        );
    }

    public static function notExecutable(string $path): self
    {
        return new self(
            sprintf('Path "%s" is not executable.', $path)
        );
    }
}
