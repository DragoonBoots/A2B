<?php


namespace DragoonBoots\A2B\DependencyInjection;


use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class A2BExtension extends Extension implements CompilerPassInterface
{

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
          $container,
          new FileLocator(__DIR__.'/../../Resources/config')
        );
        $loader->load('services.yaml');

        $container->registerForAutoconfiguration(DataMigrationInterface::class)
          ->addTag('a2b.data_migration');
        $container->registerForAutoconfiguration(SourceDriverInterface::class)
          ->addTag('a2b.driver.source')
          ->setParent('a2b.source.abstract_source_driver');
        $container->registerForAutoconfiguration(DestinationDriverInterface::class)
          ->addTag('a2b.driver.destination')
          ->setParent('a2b.destination.abstract_destination_driver');
    }

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->has('a2b.data_migration_manager')) {
            $this->configureDataMigrationManager($container);
        }
        if ($container->has('a2b.driver_manager')) {
            $this->configureDriverManager($container);
        }
    }

    /**
     * Add migrations to the migration manager.
     *
     * @param ContainerBuilder $container
     */
    protected function configureDataMigrationManager(ContainerBuilder $container)
    {
        $definition = $container->findDefinition('a2b.data_migration_manager');

        $migrations = $container->findTaggedServiceIds('a2b.data_migration');
        foreach ($migrations as $id => $tags) {
            $definition->addMethodCall('addMigration', [new Reference($id)]);
        }
    }

    /**
     * Add drivers to the driver manager.
     *
     * @param ContainerBuilder $container
     */
    protected function configureDriverManager(ContainerBuilder $container)
    {
        $definition = $container->findDefinition('a2b.driver_manager');

        $sourceDrivers = $container->findTaggedServiceIds('a2b.driver.source');
        foreach ($sourceDrivers as $id => $tags) {
            $definition->addMethodCall('addSourceDriver', [new Reference($id)]);
        }

        $destinationDrivers = $container->findTaggedServiceIds('a2b.driver.destination');
        foreach ($destinationDrivers as $id => $tags) {
            $definition->addMethodCall('addDestinationDriver', [new Reference($id)]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'a2b';
    }
}
