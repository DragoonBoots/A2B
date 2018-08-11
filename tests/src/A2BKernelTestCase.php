<?php


namespace DragoonBoots\A2B\Tests;


use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class A2BKernelTestCase extends KernelTestCase
{

    protected function tearDown()
    {
        $cacheDir = self::$kernel->getCacheDir();
        parent::tearDown();
        $this->rmDirContents($cacheDir);
    }

    /**
     * Recursively remove the contents of a directory.
     *
     * @param string $path
     *
     * @return bool
     */
    private function rmDirContents(string $path)
    {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $contentsPath = sprintf('%s/%s', $path, $file);
            (is_dir($contentsPath)) ? $this->rmDirContents($contentsPath) : unlink($contentsPath);
        }

        return rmdir($path);
    }

}
