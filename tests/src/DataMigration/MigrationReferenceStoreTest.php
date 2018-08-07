<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManagerInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationMapperInterface;
use DragoonBoots\A2B\DataMigration\MigrationReferenceStore;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MigrationReferenceStoreTest extends TestCase
{

    public function testGet()
    {
        $sourceIds = [
            'id' => 1,
        ];
        $expectedReferencedEntity = [
            'identifier' => 'test',
            'field' => 'test',
        ];
        $destIds = [
            'identifier' => 'test',
        ];

        $driver = $this->createMock(DestinationDriverInterface::class);
        $driver->expects($this->once())
            ->method('read')
            ->with($destIds)
            ->willReturn($expectedReferencedEntity);
        $driverManager = $this->createMock(DriverManagerInterface::class);
        $driverManager->expects($this->once())
            ->method('getDestinationDriver')
            ->with(get_class($driver))
            ->willReturn($driver);

        $definition = new DataMigration(
            [
                'destinationDriver' => get_class($driver),
            ]
        );
        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $migration->method('getDefinition')
            ->willReturn($definition);
        $migrationManager = $this->createMock(DataMigrationManagerInterface::class);
        $migrationManager->expects($this->once())
            ->method('getMigration')
            ->with(get_class($migration))
            ->willReturn($migration);

        $mapper = $this->createMock(DataMigrationMapperInterface::class);
        $mapper->expects($this->once())
            ->method('getDestIdsFromSourceIds')
            ->with(get_class($migration), $sourceIds)
            ->willReturn($destIds);

        $referenceStore = new MigrationReferenceStore($mapper, $migrationManager, $driverManager);
        $this->assertEquals($expectedReferencedEntity, $referenceStore->get(get_class($migration), $sourceIds));

        // Call a second time to ensure the cache is used instead of fetching again.
        $this->assertEquals($expectedReferencedEntity, $referenceStore->get(get_class($migration), $sourceIds), 'Not using cached value');
    }
}
