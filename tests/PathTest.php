<?php

declare(strict_types=1);

namespace PhpFsTest;

use PhpFs\Path;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    public function testNormalizesPath(): void
    {
        $this->assertEquals('foo/bar', Path::normalize('foo/bar'));
        $this->assertEquals('foo/bar', Path::normalize('foo/./bar'));
        $this->assertEquals('bar', Path::normalize('foo/../bar'));
        $this->assertEquals('foo/bar', Path::normalize('foo//bar'));
    }

    public function testNormalizesAbsolutePath(): void
    {
        $this->assertEquals('/foo/bar', Path::normalize('/foo/bar'));
        $this->assertEquals('/foo/bar', Path::normalize('/foo/./bar'));
        $this->assertEquals('/bar', Path::normalize('/foo/../bar'));
    }

    public function testNormalizesEmptyPath(): void
    {
        $this->assertEquals('', Path::normalize(''));
    }

    public function testJoinsPathSegments(): void
    {
        $this->assertEquals('foo/bar', Path::join('foo', 'bar'));
        $this->assertEquals('foo/bar/baz', Path::join('foo', 'bar', 'baz'));
        $this->assertEquals('/foo/bar', Path::join('/foo', 'bar'));
        $this->assertEquals('foo/bar', Path::join('foo/', 'bar'));
        $this->assertEquals('foo/bar', Path::join('foo', '/bar'));
    }

    public function testJoinsEmptySegments(): void
    {
        $this->assertEquals('', Path::join());
        $this->assertEquals('foo', Path::join('foo', ''));
        $this->assertEquals('bar', Path::join('', 'bar'));
    }

    public function testDetectsAbsolutePath(): void
    {
        $this->assertTrue(Path::isAbsolute('/foo/bar'));
        $this->assertFalse(Path::isAbsolute('foo/bar'));
        $this->assertFalse(Path::isAbsolute('./foo'));
        $this->assertFalse(Path::isAbsolute('../foo'));
    }

    public function testDetectsRelativePath(): void
    {
        $this->assertTrue(Path::isRelative('foo/bar'));
        $this->assertTrue(Path::isRelative('./foo'));
        $this->assertTrue(Path::isRelative('../foo'));
        $this->assertFalse(Path::isRelative('/foo/bar'));
    }

    public function testGetsDirname(): void
    {
        $this->assertEquals('/foo', Path::dirname('/foo/bar'));
        $this->assertEquals('foo', Path::dirname('foo/bar'));
        $this->assertEquals('.', Path::dirname('bar'));
    }

    public function testGetsBasename(): void
    {
        $this->assertEquals('bar', Path::basename('/foo/bar'));
        $this->assertEquals('bar.txt', Path::basename('/foo/bar.txt'));
        $this->assertEquals('bar', Path::basename('/foo/bar.txt', '.txt'));
    }

    public function testGetsExtension(): void
    {
        $this->assertEquals('txt', Path::extension('/foo/bar.txt'));
        $this->assertEquals('php', Path::extension('file.php'));
        $this->assertEquals('', Path::extension('/foo/bar'));
        $this->assertEquals('gz', Path::extension('/foo/bar.tar.gz'));
    }

    public function testComparesPathEquality(): void
    {
        $this->assertTrue(Path::equals('/foo/bar', '/foo/bar'));
        $this->assertTrue(Path::equals('/foo/./bar', '/foo/bar'));
        $this->assertTrue(Path::equals('/foo/../foo/bar', '/foo/bar'));
        $this->assertFalse(Path::equals('/foo/bar', '/foo/baz'));
    }

    public function testChecksIfPathIsInsideDirectory(): void
    {
        $this->assertTrue(Path::isInside('/foo/bar/baz', '/foo/bar'));
        $this->assertTrue(Path::isInside('/foo/bar/baz/file.txt', '/foo/bar'));
        $this->assertFalse(Path::isInside('/foo/bar', '/foo/bar')); // Same path
        $this->assertFalse(Path::isInside('/foo/baz', '/foo/bar'));
        $this->assertFalse(Path::isInside('/foo/bar/../baz', '/foo/bar'));
    }

    public function testCalculatesRelativePath(): void
    {
        $this->assertEquals('bar', Path::relative('/foo', '/foo/bar'));
        $this->assertEquals('..', Path::relative('/foo/bar', '/foo'));
        $this->assertEquals('../baz', Path::relative('/foo/bar', '/foo/baz'));
        $this->assertEquals('.', Path::relative('/foo/bar', '/foo/bar'));
    }
}
