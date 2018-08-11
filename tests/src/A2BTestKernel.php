<?php


namespace DragoonBoots\A2B\Tests;


use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use DragoonBoots\A2B\A2BBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Kernel for Tests
 */
class A2BTestKernel extends Kernel
{

    protected const USE_BUNDLES = [
        FrameworkBundle::class,
        DoctrineBundle::class,
        A2BBundle::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        foreach (self::USE_BUNDLES as $bundleName) {
            yield new $bundleName;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(
            function (ContainerBuilder $container) {
                foreach ($this->getParameters() as $parameter => $value) {
                    $container->setParameter($parameter, $value);
                }
                foreach ($this->getConfiguration() as $extension => $config) {
                    $container->loadFromExtension($extension, $config);
                }
            }
        );
    }

    /**
     * Get a map of parameters to use in the test.
     *
     * Override this per test if different parameters are needed.
     *
     * @return array
     */
    protected function getParameters(): array
    {
        return [
            'kernel.secret' => 'SECRETSECRET',
        ];
    }

    /**
     * Get the configuration map to use in the test.
     *
     * Override this per test if different parameters are needed.
     *
     * The key is the extension name, the value is a list of configuration
     * for that extension.
     *
     * @return array
     */
    protected function getConfiguration(): array
    {
        return [
            'doctrine' => [
                'dbal' => [],
            ],
        ];
    }
}
