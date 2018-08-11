<?php


namespace DragoonBoots\A2B\Tests;


use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class A2BKernelTestCase extends KernelTestCase
{

    protected function tearDown()
    {
        self::tearDownTest();
    }

    /**
     * Recursively remove the contents of a directory.
     *
     * @param string $path
     *
     * @return bool
     */
    private static function rmDirContents(string $path)
    {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $contentsPath = sprintf('%s/%s', $path, $file);
            (is_dir($contentsPath)) ? self::rmDirContents($contentsPath) : unlink($contentsPath);
        }

        return rmdir($path);
    }

    protected static function tearDownTest(): void
    {
        $cacheDir = self::$kernel->getCacheDir();
        self::rmDirContents($cacheDir);
    }

}
