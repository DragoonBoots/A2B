<?php

namespace DragoonBoots\A2B\Tests\DependencyInjection;

use DragoonBoots\A2B\DataMigration\DataMigrationExecutorInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManagerInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationMapperInterface;
use DragoonBoots\A2B\DataMigration\MigrationReferenceStoreInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Tests\A2BKernelTestCase;
use ProxyManager\Proxy\LazyLoadingInterface;

class A2BExtensionTest extends A2BKernelTestCase
{

    /**
     * @param string $serviceId
     * @param string $serviceClass
     * @param bool   $isLazy
     *
     * @dataProvider servicesDataProvider
     */
    public function testServices(string $serviceId, string $serviceClass, bool $isLazy = false)
    {
        $service = self::$container->get($serviceId);
        $this->assertInstanceOf($serviceClass, $service);
        if ($isLazy) {
            $this->assertInstanceOf(LazyLoadingInterface::class, $service);
        }
    }

    public function servicesDataProvider()
    {
        // service id, service class/type hint
        return [
            // @formatter:off
            ['a2b.data_migration_manager', DataMigrationManagerInterface::class, true],
            ['a2b.driver_manager', DriverManagerInterface::class, true],
            ['a2b.executor', DataMigrationExecutorInterface::class, true],
            ['a2b.mapper', DataMigrationMapperInterface::class, true],
            ['a2b.reference_store', MigrationReferenceStoreInterface::class, true],
            // @formatter:on
        ];
    }

    protected function setUp()
    {
        parent::bootKernel();
    }
}
