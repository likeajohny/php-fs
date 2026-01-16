<?php

declare(strict_types=1);

namespace PhpFsTest;

use PhpFs\Directory;
use PhpFs\Exception\DirectoryNotFoundException;
use PhpFs\Exception\LinkException;
use PhpFs\File;
use PHPUnit\Framework\TestCase;

class DirectoryTest extends TestCase
{
    private const FIXTURES = './tests/fixtures';
    private const ARTIFACTS = './tests/artifacts';

    public function testCanRecursivelyCreateDirectory(): void
    {
        Directory::create(self::ARTIFACTS);

        $this->assertDirectoryExists(self::ARTIFACTS);
    }

    public function testDeterminesIfDirectoryExists(): void
    {
        $this->assertTrue(Directory::exists(self::FIXTURES . '/content/sub-directory'));
        $this->assertFalse(Directory::exists(self::FIXTURES . '/content/sub-sub-directory'));
    }

    /**
     * @depends testCanRecursivelyCreateDirectory
     */
    public function testCanRecursivelyCopyDirectory(): void
    {
        Directory::copy(self::FIXTURES . '/content', self::ARTIFACTS . '/content');

        $this->assertDirectoryExists(self::ARTIFACTS . '/content');
        $this->assertDirectoryExists(self::ARTIFACTS . '/content/sub-directory');
        $this->assertFileExists(self::ARTIFACTS . '/content/first-level.txt');
        $this->assertFileExists(self::ARTIFACTS . '/content/sub-directory/second-level.txt');
    }

    /**
     * @depends testCanRecursivelyCopyDirectory
     */
    public function testCanRecursivelyMoveDirectory(): void
    {
        Directory::move(self::ARTIFACTS . '/content', self::ARTIFACTS . '/moved-content');

        $this->assertDirectoryExists(self::ARTIFACTS . '/moved-content');
        $this->assertDirectoryExists(self::ARTIFACTS . '/moved-content/sub-directory');
        $this->assertFileExists(self::ARTIFACTS . '/moved-content/first-level.txt');
        $this->assertFileExists(self::ARTIFACTS . '/moved-content/sub-directory/second-level.txt');
    }

    /**
     * @depends testCanRecursivelyMoveDirectory
     */
    public function testCanRecursivelyEmptyDirectory(): void
    {
        Directory::empty(self::ARTIFACTS . '/moved-content');

        $this->assertDirectoryExists(self::ARTIFACTS . '/moved-content');
        $this->assertDirectoryDoesNotExist(self::ARTIFACTS . '/moved-content/sub-directory');
        $this->assertFileDoesNotExist(self::ARTIFACTS . '/moved-content/first-level.txt');
        $this->assertFileDoesNotExist(self::ARTIFACTS . '/moved-content/sub-directory/second-level.txt');
    }

    /**
     * @depends testCanRecursivelyEmptyDirectory
     */
    public function testCanRecursivelyRemoveDirectory(): void
    {
        $dir = self::ARTIFACTS;

        Directory::remove(self::ARTIFACTS);

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testCanRecursivelyListDirectoryContentsWithFullPaths(): void
    {
        $dir = self::FIXTURES . '/content';

        $expectedList = [
            $dir => [
                'first-level.txt',
                $dir . '/sub-directory' => [
                    'second-level.txt',
                ],
            ],
        ];

        $this->assertEquals($expectedList, Directory::list($dir));
    }

    public function testCanListFirstLevelDirectoryContentsWithFullPaths(): void
    {
        $dir = self::FIXTURES . '/content';

        $expectedList = [
            $dir => [
                'first-level.txt'
            ],
        ];

        $this->assertEquals($expectedList, Directory::list($dir, false));
    }

    public function testDirectoryNotFoundExceptionType(): void
    {
        $this->expectException(DirectoryNotFoundException::class);
        Directory::info(self::ARTIFACTS . '/non-existent');
    }

    public function testDefaultDirMode(): void
    {
        $this->assertEquals(0755, Directory::DEFAULT_DIR_MODE);
    }

    public function testCanGetDirectoryInfo(): void
    {
        $dir = self::FIXTURES . '/content';
        $info = Directory::info($dir);

        $this->assertArrayHasKey('path', $info);
        $this->assertArrayHasKey('basename', $info);
        $this->assertArrayHasKey('permissions', $info);
        $this->assertArrayHasKey('owner', $info);
        $this->assertArrayHasKey('group', $info);
        $this->assertArrayHasKey('mtime', $info);
        $this->assertArrayHasKey('atime', $info);
        $this->assertEquals('content', $info['basename']);
    }

    public function testCanGetDirectorySize(): void
    {
        $dir = self::FIXTURES . '/content';
        $size = Directory::size($dir);

        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);
    }

    public function testCanCountDirectoryContents(): void
    {
        $dir = self::FIXTURES . '/content';
        $counts = Directory::count($dir);

        $this->assertArrayHasKey('files', $counts);
        $this->assertArrayHasKey('directories', $counts);
        $this->assertEquals(2, $counts['files']);
        $this->assertEquals(1, $counts['directories']);
    }

    public function testCanCountDirectoryContentsNonRecursive(): void
    {
        $dir = self::FIXTURES . '/content';
        $counts = Directory::count($dir, false);

        $this->assertEquals(1, $counts['files']);
        $this->assertEquals(1, $counts['directories']);
    }

    public function testCanListFlattenedDirectory(): void
    {
        $dir = self::FIXTURES . '/content';
        $list = Directory::list($dir, true, true);

        $this->assertIsArray($list);
        $this->assertContains($dir . '/first-level.txt', $list);
        $this->assertContains($dir . '/sub-directory', $list);
        $this->assertContains($dir . '/sub-directory/second-level.txt', $list);
    }

    public function testCanListOnlyFiles(): void
    {
        $dir = self::FIXTURES . '/content';
        $files = Directory::files($dir);

        $this->assertIsArray($files);
        $this->assertContains($dir . '/first-level.txt', $files);
        $this->assertContains($dir . '/sub-directory/second-level.txt', $files);
        $this->assertNotContains($dir . '/sub-directory', $files);
    }

    public function testCanListOnlyDirectories(): void
    {
        $dir = self::FIXTURES . '/content';
        $dirs = Directory::directories($dir);

        $this->assertIsArray($dirs);
        $this->assertContains($dir . '/sub-directory', $dirs);
        $this->assertNotContains($dir . '/first-level.txt', $dirs);
    }

    public function testCanUseGlobPattern(): void
    {
        $pattern = self::FIXTURES . '/content/*.txt';
        $result = Directory::glob($pattern);

        $this->assertIsArray($result);
        $this->assertContains(self::FIXTURES . '/content/first-level.txt', $result);
    }

    public function testCanFindWithFilter(): void
    {
        $dir = self::FIXTURES . '/content';
        $result = Directory::find($dir, fn($path) => str_ends_with($path, '.txt'));

        $this->assertIsArray($result);
        $this->assertContains($dir . '/first-level.txt', $result);
        $this->assertContains($dir . '/sub-directory/second-level.txt', $result);
    }

    public function testCanRemoveWithDryRun(): void
    {
        Directory::create(self::ARTIFACTS . '/dry-run');
        File::touch(self::ARTIFACTS . '/dry-run/file.txt');

        $result = Directory::remove(self::ARTIFACTS . '/dry-run', true);

        $this->assertIsArray($result);
        $this->assertContains(self::ARTIFACTS . '/dry-run/file.txt', $result);
        $this->assertContains(self::ARTIFACTS . '/dry-run', $result);
        $this->assertDirectoryExists(self::ARTIFACTS . '/dry-run');

        // Clean up
        Directory::remove(self::ARTIFACTS . '/dry-run');
    }

    public function testCanEmptyWithDryRun(): void
    {
        Directory::create(self::ARTIFACTS . '/dry-run-empty');
        File::touch(self::ARTIFACTS . '/dry-run-empty/file.txt');

        $result = Directory::empty(self::ARTIFACTS . '/dry-run-empty', true);

        $this->assertIsArray($result);
        $this->assertContains(self::ARTIFACTS . '/dry-run-empty/file.txt', $result);
        $this->assertNotContains(self::ARTIFACTS . '/dry-run-empty', $result);
        $this->assertFileExists(self::ARTIFACTS . '/dry-run-empty/file.txt');

        // Clean up
        Directory::remove(self::ARTIFACTS . '/dry-run-empty');
    }

    public function testCanCreateSymlink(): void
    {
        Directory::create(self::ARTIFACTS . '/original-dir');
        $link = self::ARTIFACTS . '/link-dir';

        Directory::link(self::ARTIFACTS . '/original-dir', $link);

        $this->assertTrue(Directory::isLink($link));

        // Clean up
        unlink($link);
        Directory::remove(self::ARTIFACTS . '/original-dir');
    }

    public function testCanReadSymlinkTarget(): void
    {
        Directory::create(self::ARTIFACTS . '/original-dir2');
        $link = self::ARTIFACTS . '/link-dir2';

        Directory::link(self::ARTIFACTS . '/original-dir2', $link);

        $target = Directory::readLink($link);

        $this->assertEquals(self::ARTIFACTS . '/original-dir2', $target);

        // Clean up
        unlink($link);
        Directory::remove(self::ARTIFACTS . '/original-dir2');
    }

    public function testReadLinkThrowsForNonLink(): void
    {
        Directory::create(self::ARTIFACTS . '/not-a-link');

        $this->expectException(LinkException::class);
        Directory::readLink(self::ARTIFACTS . '/not-a-link');
    }

    protected function tearDown(): void
    {
        // Clean up artifacts if they exist
        if (Directory::exists(self::ARTIFACTS . '/not-a-link')) {
            Directory::remove(self::ARTIFACTS . '/not-a-link');
        }
    }
}
