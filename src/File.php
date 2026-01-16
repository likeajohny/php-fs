<?php

declare(strict_types=1);

namespace PhpFs;

use Generator;
use PhpFs\Exception\FileException;

use const FILE_APPEND;
use const LOCK_EX;
use const LOCK_SH;
use const LOCK_UN;

use function chmod;
use function clearstatcache;
use function error_get_last;
use function fclose;
use function feof;
use function fflush;
use function fgets;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function filesize;
use function finfo_close;
use function finfo_file;
use function finfo_open;
use function finfo_set_flags;
use function flock;
use function fopen;
use function fread;
use function ftruncate;
use function fwrite;
use function is_file;
use function is_link;
use function is_readable;
use function is_writeable;
use function pathinfo;
use function readlink;
use function rename;
use function stream_copy_to_stream;
use function sys_get_temp_dir;
use function tempnam;
use function symlink;
use function touch;
use function unlink;

class File
{
    public const DEFAULT_FILE_MODE = 0644;

    public static function create(string $file, int $mode = self::DEFAULT_FILE_MODE): mixed
    {
        $resource = fopen($file, 'wb');
        if ($resource === false || !chmod($file, $mode)) {
            throw FileException::createError($file, error_get_last());
        }

        return $resource;
    }

    public static function exists(string $file): bool
    {
        return file_exists($file);
    }

    public static function info(string $file): array
    {
        self::validateRead($file);

        $info = [...pathinfo($file)];
        $info['permissions'] = substr(sprintf('%o', fileperms($file)), -4);

        $handle = finfo_open(FILEINFO_MIME_TYPE);
        $info['mime_type'] = finfo_file($handle, $file);
        finfo_set_flags($handle, FILEINFO_MIME_ENCODING);
        $info['mime_encoding'] = finfo_file($handle, $file);
        finfo_close($handle);

        return $info;
    }

    public static function write(string $file, string $content, bool $lock = false): bool|int
    {
        self::validateWrite($file);

        $flags = $lock ? LOCK_EX : 0;
        $bytes = file_put_contents($file, $content, $flags);
        if ($bytes === false) {
            throw FileException::writeError($file, error_get_last());
        }

        return $bytes;
    }

    public static function writeWithLock(string $file, string $content, int $lockType = LOCK_EX): bool|int
    {
        self::validateWrite($file);

        $handle = fopen($file, 'cb');
        if ($handle === false) {
            throw FileException::streamError($file, 'open for write', error_get_last());
        }

        if (!flock($handle, $lockType)) {
            fclose($handle);
            throw FileException::lockFailed($file);
        }

        if (!ftruncate($handle, 0)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw FileException::writeError($file, error_get_last());
        }

        $bytes = fwrite($handle, $content);
        if ($bytes === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw FileException::writeError($file, error_get_last());
        }

        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $bytes;
    }

    public static function readWithLock(string $file): string
    {
        self::validateRead($file);

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw FileException::streamError($file, 'open for read', error_get_last());
        }

        if (!flock($handle, LOCK_SH)) {
            fclose($handle);
            throw FileException::lockFailed($file);
        }

        clearstatcache(true, $file);
        $size = filesize($file);

        $content = $size > 0 ? fread($handle, $size) : '';
        if ($content === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw FileException::readError($file, error_get_last());
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        return $content;
    }

    public static function append(string $file, string $content): bool|int
    {
        self::validateWrite($file);

        $bytes = file_put_contents($file, $content, FILE_APPEND);
        if ($bytes === false) {
            throw FileException::writeError($file, error_get_last());
        }

        return $bytes;
    }

    public static function prepend(string $file, string $content): bool|int
    {
        self::validateWrite($file);

        $tempFile = tempnam(sys_get_temp_dir(), 'phpfs_prepend_');
        if ($tempFile === false) {
            throw FileException::streamError($file, 'prepend (temp file creation)', error_get_last());
        }

        $tempHandle = fopen($tempFile, 'wb');
        if ($tempHandle === false) {
            @unlink($tempFile);
            throw FileException::streamError($file, 'prepend (temp file open)', error_get_last());
        }

        $sourceHandle = fopen($file, 'rb');
        if ($sourceHandle === false) {
            fclose($tempHandle);
            @unlink($tempFile);
            throw FileException::streamError($file, 'prepend (source file open)', error_get_last());
        }

        $contentBytes = fwrite($tempHandle, $content);
        if ($contentBytes === false) {
            fclose($sourceHandle);
            fclose($tempHandle);
            @unlink($tempFile);
            throw FileException::writeError($file, error_get_last());
        }

        $streamBytes = stream_copy_to_stream($sourceHandle, $tempHandle);
        if ($streamBytes === false) {
            fclose($sourceHandle);
            fclose($tempHandle);
            @unlink($tempFile);
            throw FileException::streamError($file, 'prepend (stream copy)', error_get_last());
        }

        fclose($sourceHandle);
        fclose($tempHandle);

        if (!rename($tempFile, $file)) {
            @unlink($tempFile);
            throw FileException::streamError($file, 'prepend (rename)', error_get_last());
        }

        return $contentBytes + $streamBytes;
    }

    public static function read(string $file): string
    {
        self::validateRead($file);

        $content = file_get_contents($file);
        if ($content === false) {
            throw FileException::readError($file, error_get_last());
        }

        return $content;
    }

    public static function copy(string $file, string $targetFile): bool
    {
        self::validateFile($file);

        return copy($file, $targetFile);
    }

    public static function move(string $file, string $targetFile): bool
    {
        self::validateFile($file);

        return rename($file, $targetFile);
    }

    public static function remove(string $file, bool $dryRun = false): bool|string
    {
        self::validateFile($file);

        if ($dryRun) {
            return $file;
        }

        return unlink($file);
    }

    public static function touch(string $file, ?int $mtime = null, ?int $atime = null): bool
    {
        $result = touch($file, $mtime, $atime);
        if ($result === false) {
            throw FileException::touchError($file, error_get_last());
        }

        return $result;
    }

    public static function truncate(string $file, int $size = 0): bool
    {
        self::validateWrite($file);

        $handle = fopen($file, 'r+b');
        if ($handle === false) {
            throw FileException::streamError($file, 'open for truncate', error_get_last());
        }

        $result = ftruncate($handle, $size);
        fclose($handle);

        if ($result === false) {
            throw FileException::truncateError($file, error_get_last());
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
            throw \PhpFs\Exception\LinkException::notALink($link);
        }

        $target = readlink($link);
        if ($target === false) {
            throw \PhpFs\Exception\LinkException::readFailed($link, error_get_last());
        }

        return $target;
    }

    public static function link(string $target, string $link): bool
    {
        $result = @symlink($target, $link);
        if ($result === false) {
            throw \PhpFs\Exception\LinkException::createFailed($target, $link, error_get_last());
        }

        return $result;
    }

    /**
     * @return Generator<int, string>
     */
    public static function lines(string $file): Generator
    {
        self::validateRead($file);

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw FileException::streamError($file, 'open for lines', error_get_last());
        }

        $lineNumber = 1;
        while (($line = fgets($handle)) !== false) {
            yield $lineNumber => $line;
            $lineNumber++;
        }

        fclose($handle);
    }

    /**
     * @return Generator<int, string>
     */
    public static function chunks(string $file, int $chunkSize = 8192): Generator
    {
        self::validateRead($file);

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw FileException::streamError($file, 'open for chunks', error_get_last());
        }

        $chunkNumber = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                fclose($handle);
                throw FileException::readError($file, error_get_last());
            }
            if ($chunk !== '') {
                yield $chunkNumber => $chunk;
                $chunkNumber++;
            }
        }

        fclose($handle);
    }

    public static function copyStream(string $source, string $target, ?int $length = null): int
    {
        self::validateRead($source);

        $sourceHandle = fopen($source, 'rb');
        if ($sourceHandle === false) {
            throw FileException::streamError($source, 'open source for stream copy', error_get_last());
        }

        $targetHandle = fopen($target, 'wb');
        if ($targetHandle === false) {
            fclose($sourceHandle);
            throw FileException::streamError($target, 'open target for stream copy', error_get_last());
        }

        if ($length !== null) {
            $bytes = stream_copy_to_stream($sourceHandle, $targetHandle, $length);
        } else {
            $bytes = stream_copy_to_stream($sourceHandle, $targetHandle);
        }

        fclose($sourceHandle);
        fclose($targetHandle);

        if ($bytes === false) {
            throw FileException::streamError($source, 'stream copy', error_get_last());
        }

        return $bytes;
    }

    public static function writeAtomic(string $file, string $content): bool|int
    {
        $directory = \dirname($file);
        $tempFile = tempnam($directory !== '' ? $directory : sys_get_temp_dir(), 'phpfs_atomic_');

        if ($tempFile === false) {
            throw FileException::streamError($file, 'atomic write (temp file creation)', error_get_last());
        }

        $bytes = file_put_contents($tempFile, $content);
        if ($bytes === false) {
            @unlink($tempFile);
            throw FileException::writeError($file, error_get_last());
        }

        if (!rename($tempFile, $file)) {
            @unlink($tempFile);
            throw FileException::streamError($file, 'atomic write (rename)', error_get_last());
        }

        return $bytes;
    }

    public static function swap(string $file1, string $file2): bool
    {
        self::validateFile($file1);
        self::validateFile($file2);

        $directory = \dirname($file1);
        $tempFile = tempnam($directory !== '' ? $directory : sys_get_temp_dir(), 'phpfs_swap_');

        if ($tempFile === false) {
            throw FileException::streamError($file1, 'swap (temp file creation)', error_get_last());
        }

        // file1 -> temp
        if (!rename($file1, $tempFile)) {
            @unlink($tempFile);
            throw FileException::streamError($file1, 'swap (move file1 to temp)', error_get_last());
        }

        // file2 -> file1
        if (!rename($file2, $file1)) {
            // Rollback: temp -> file1
            rename($tempFile, $file1);
            throw FileException::streamError($file2, 'swap (move file2 to file1)', error_get_last());
        }

        // temp -> file2
        if (!rename($tempFile, $file2)) {
            // Rollback: file1 -> file2, temp -> file1
            rename($file1, $file2);
            rename($tempFile, $file1);
            throw FileException::streamError($tempFile, 'swap (move temp to file2)', error_get_last());
        }

        return true;
    }

    private static function validateRead(string $file): void
    {
        self::validateFile($file);

        if (!is_readable($file)) {
            throw FileException::notReadable($file);
        }
    }

    private static function validateWrite(string $file): void
    {
        self::validateFile($file);

        if (!is_writeable($file)) {
            throw FileException::notWriteable($file);
        }
    }

    private static function validateFile(string $file): void
    {
        if (!is_file($file)) {
            throw FileException::noFile($file);
        }
    }
}
