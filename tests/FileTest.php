<?php

declare(strict_types=1);

namespace PhpFsTest;

use PhpFs\Directory;
use PhpFs\Exception\FileException;
use PhpFs\Exception\FileNotFoundException;
use PhpFs\Exception\LinkException;
use PhpFs\Exception\PermissionException;
use PhpFs\File;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileTest extends TestCase
{
    private const ARTIFACTS = './tests/artifacts';
    private const FIXTURES = './tests/fixtures';

    public function testCanCreateFile(): void
    {
        File::create($file = self::ARTIFACTS . '/cool-story-bro.txt');

        $this->assertFileExists($file);
    }

    public function testThrowsExceptionOnCreateError(): void
    {
        $this->expectException(FileException::class);
        File::create(self::ARTIFACTS . '/dir-does-not-exist/cool-story-bro.txt');
    }

    public function testDeterminesIfFileExists(): void
    {
        $this->assertTrue(File::exists(self::FIXTURES . '/content/first-level.txt'));
        $this->assertFalse(File::exists(self::FIXTURES . '/content/second-level.txt'));
    }

    public function testGetsFileInformation(): void
    {
        $file = self::ARTIFACTS . '/test.txt';
        File::create($file, 0644);
        File::write($file, 'test');

        $info = File::info($file);

        $this->assertEquals(self::ARTIFACTS, $info['dirname']);
        $this->assertEquals('test.txt', $info['basename']);
        $this->assertEquals('test', $info['filename']);
        $this->assertEquals('txt', $info['extension']);
        $this->assertEquals('text/plain', $info['mime_type']);
        $this->assertEquals('us-ascii', $info['mime_encoding']);
        $this->assertEquals('0644', $info['permissions']);
    }

    public function testCanWriteToNewFile(): void
    {
        $file = self::ARTIFACTS . '/cool-story-bro.txt';
        File::create($file);

        $this->assertEquals(20, File::write($file, 'Tell me more, lol x3'));
    }

    public function testThrowsExceptionOnWriteError(): void
    {
        $file = self::ARTIFACTS . '/cool-story-bro.txt';
        File::create($file, 0000);

        $this->expectException(PermissionException::class);
        File::write($file, 'Tell me more, lol x3');
    }

    public function testCanAppendToExistingFile(): void
    {
        $file = self::ARTIFACTS . '/test.txt';
        File::create($file);
        File::write($file, 'Test');

        // append now returns bytes of appended content only (not total)
        $this->assertEquals(3, File::append($file, ' Me'));
        $this->assertEquals('Test Me', File::read($file));
    }

    public function testCanPrependToExistingFile(): void
    {
        $file = self::ARTIFACTS . '/test.txt';
        File::create($file);
        File::write($file, 'Me');

        $this->assertEquals(7, File::prepend($file, 'Test '));
        $this->assertEquals('Test Me', File::read($file));
    }

    public function testCanReadFromExistingFile(): void
    {
        $file = self::ARTIFACTS . '/cool-story-bro.txt';
        File::create($file);
        File::write($file, 'Tell me more, lol x3');

        $this->assertEquals(
            'Tell me more, lol x3',
            File::read($file)
        );
    }

    public function testThrowsExceptionOnReadError(): void
    {
        $file = self::ARTIFACTS . '/cool-story-bro.txt';
        File::create($file, 0000);

        $this->expectException(PermissionException::class);
        File::read($file);
    }

    public function testCanRemoveFile(): void
    {
        $file = self::ARTIFACTS . '/to-be-removed.txt';
        File::create($file);

        $this->assertFileExists($file);
        File::remove($file);
        $this->assertFileDoesNotExist($file);
    }

    public function testCanCopyFileToExistingDirectory(): void
    {
        $file = self::FIXTURES . '/content/first-level.txt';
        $target = self::ARTIFACTS . '/first-level.txt';

        File::copy($file, $target);

        $this->assertFileExists($target);
    }

    public function testCanMoveFileToExistingDirectory(): void
    {
        $file = self::ARTIFACTS . '/mover.txt';
        $target = self::ARTIFACTS . '/moved.txt';

        File::create($file);
        File::move($file, $target);

        $this->assertFileExists($target);
    }

    public function testFileNotFoundExceptionType(): void
    {
        $this->expectException(FileNotFoundException::class);
        File::read(self::ARTIFACTS . '/non-existent.txt');
    }

    public function testCanTouchFile(): void
    {
        $file = self::ARTIFACTS . '/touched.txt';

        $this->assertFileDoesNotExist($file);
        File::touch($file);
        $this->assertFileExists($file);
    }

    public function testCanTouchExistingFileWithTimestamp(): void
    {
        $file = self::ARTIFACTS . '/touched.txt';
        File::create($file);

        $mtime = 1000000000;
        File::touch($file, $mtime);

        $this->assertEquals($mtime, filemtime($file));
    }

    public function testCanTruncateFile(): void
    {
        $file = self::ARTIFACTS . '/truncate.txt';
        File::create($file);
        File::write($file, 'Hello World');

        $this->assertEquals('Hello World', File::read($file));
        File::truncate($file);
        $this->assertEquals('', File::read($file));
    }

    public function testCanTruncateFileToSize(): void
    {
        $file = self::ARTIFACTS . '/truncate.txt';
        File::create($file);
        File::write($file, 'Hello World');

        File::truncate($file, 5);
        $this->assertEquals('Hello', File::read($file));
    }

    public function testCanWriteWithLock(): void
    {
        $file = self::ARTIFACTS . '/locked.txt';
        File::create($file);

        $bytes = File::writeWithLock($file, 'Locked content');

        $this->assertEquals(14, $bytes);
        $this->assertEquals('Locked content', File::read($file));
    }

    public function testCanReadWithLock(): void
    {
        $file = self::ARTIFACTS . '/locked.txt';
        File::create($file);
        File::write($file, 'Test content');

        $content = File::readWithLock($file);

        $this->assertEquals('Test content', $content);
    }

    public function testCanReadFileLines(): void
    {
        $file = self::ARTIFACTS . '/lines.txt';
        File::create($file);
        File::write($file, "Line 1\nLine 2\nLine 3");

        $lines = [];
        foreach (File::lines($file) as $lineNumber => $line) {
            $lines[$lineNumber] = $line;
        }

        $this->assertCount(3, $lines);
        $this->assertEquals("Line 1\n", $lines[1]);
        $this->assertEquals("Line 2\n", $lines[2]);
        $this->assertEquals("Line 3", $lines[3]);
    }

    public function testCanReadFileChunks(): void
    {
        $file = self::ARTIFACTS . '/chunks.txt';
        File::create($file);
        File::write($file, str_repeat('A', 100));

        $chunks = [];
        foreach (File::chunks($file, 30) as $index => $chunk) {
            $chunks[$index] = $chunk;
        }

        $this->assertCount(4, $chunks);
        $this->assertEquals(30, strlen($chunks[0]));
        $this->assertEquals(10, strlen($chunks[3]));
    }

    public function testCanStreamCopyFile(): void
    {
        $source = self::ARTIFACTS . '/source.txt';
        $target = self::ARTIFACTS . '/target.txt';

        File::create($source);
        File::write($source, 'Stream copy content');

        $bytes = File::copyStream($source, $target);

        $this->assertEquals(19, $bytes);
        $this->assertEquals('Stream copy content', File::read($target));
    }

    public function testCanWriteAtomically(): void
    {
        $file = self::ARTIFACTS . '/atomic.txt';

        $bytes = File::writeAtomic($file, 'Atomic content');

        $this->assertEquals(14, $bytes);
        $this->assertFileExists($file);
        $this->assertEquals('Atomic content', File::read($file));
    }

    public function testCanSwapFiles(): void
    {
        $file1 = self::ARTIFACTS . '/swap1.txt';
        $file2 = self::ARTIFACTS . '/swap2.txt';

        File::create($file1);
        File::write($file1, 'Content 1');
        File::create($file2);
        File::write($file2, 'Content 2');

        File::swap($file1, $file2);

        $this->assertEquals('Content 2', File::read($file1));
        $this->assertEquals('Content 1', File::read($file2));
    }

    public function testCanCreateSymlink(): void
    {
        $file = self::ARTIFACTS . '/symlink-original.txt';
        $link = self::ARTIFACTS . '/symlink-link.txt';

        File::create($file);
        File::write($file, 'Original content');

        // Use absolute path for symlink target
        $absoluteFile = realpath($file);
        File::link($absoluteFile, $link);

        $this->assertTrue(File::isLink($link));
        // Read the content through the symlink
        $content = file_get_contents($link);
        $this->assertEquals('Original content', $content);
    }

    public function testCanReadSymlinkTarget(): void
    {
        $file = self::ARTIFACTS . '/symlink-original2.txt';
        $link = self::ARTIFACTS . '/symlink-link2.txt';

        File::create($file);
        $absoluteFile = realpath($file);
        File::link($absoluteFile, $link);

        $target = File::readLink($link);

        $this->assertEquals($absoluteFile, $target);
    }

    public function testReadLinkThrowsForNonLink(): void
    {
        $file = self::ARTIFACTS . '/not-a-link.txt';
        File::create($file);

        $this->expectException(LinkException::class);
        File::readLink($file);
    }

    public function testCanRemoveWithDryRun(): void
    {
        $file = self::ARTIFACTS . '/dry-run.txt';
        File::create($file);

        $result = File::remove($file, true);

        $this->assertEquals($file, $result);
        $this->assertFileExists($file);
    }

    public function testDefaultFileMode(): void
    {
        $this->assertEquals(0644, File::DEFAULT_FILE_MODE);
    }

    protected function setUp(): void
    {
        Directory::create(self::ARTIFACTS);
    }

    protected function tearDown(): void
    {
        Directory::remove(self::ARTIFACTS);
    }
}
