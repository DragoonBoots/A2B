<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManagerInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationMapperInterface;
use DragoonBoots\A2B\DataMigration\MigrationReferenceStore;
use DragoonBoots\A2B\DataMigration\MigrationReferenceStoreInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class MigrationReferenceStoreTest extends TestCase
{

    /**
     * @var DestinationDriverInterface|MockObject
     */
    protected $destinationDriver;

    /**
     * @var DataMigrationInterface|MockObject
     */
    protected $migration;

    /**
     * @var DataMigrationMapperInterface|MockObject
     */
    protected $mapper;

    /**
     * @var MigrationReferenceStoreInterface
     */
    protected $referenceStore;

    protected function setupReferenceStore(Driver $driverDefinition = null)
    {
        if (is_null($driverDefinition)) {
            $driverDefinition = new Driver();
        }
        $this->destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $this->destinationDriver->method('getDefinition')
            ->willReturn($driverDefinition);
        $driverManager = $this->createMock(DriverManagerInterface::class);
        $driverManager->expects($this->once())
            ->method('getDestinationDriver')
            ->with(get_class($this->destinationDriver))
            ->willReturn($this->destinationDriver);

        $definition = new DataMigration(
            [
                'destinationDriver' => get_class($this->destinationDriver),
            ]
        );
        /** @var DataMigrationInterface|MockObject $migration */
        $this->migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $this->migration->method('getDefinition')
            ->willReturn($definition);
        $migrationManager = $this->createMock(DataMigrationManagerInterface::class);
        $migrationManager->expects($this->once())
            ->method('getMigration')
            ->with(get_class($this->migration))
            ->willReturn($this->migration);

        $this->mapper = $this->createMock(DataMigrationMapperInterface::class);

        $this->referenceStore = new MigrationReferenceStore($this->mapper, $migrationManager, $driverManager);
    }

    public function testGet()
    {
        $this->setupReferenceStore();

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

        $this->destinationDriver->expects($this->once())
            ->method('read')
            ->with($destIds)
            ->willReturn($expectedReferencedEntity);
        $this->mapper->expects($this->once())
            ->method('getDestIdsFromSourceIds')
            ->with(get_class($this->migration), $sourceIds)
            ->willReturn($destIds);

        $this->assertEquals(
            $expectedReferencedEntity,
            $this->referenceStore->get(get_class($this->migration), $sourceIds)
        );

        // Call a second time to ensure the cache is used instead of fetching again.
        $this->assertEquals(
            $expectedReferencedEntity,
            $this->referenceStore->get(get_class($this->migration), $sourceIds),
            'Not using cached value'
        );
    }

    public function testGetMapperFail()
    {
        $this->setupReferenceStore();

        $sourceIds = [
            'id' => 1,
        ];

        $this->destinationDriver->expects($this->never())
            ->method('read');

        $this->mapper->expects($this->once())
            ->method('getDestIdsFromSourceIds')
            ->with(get_class($this->migration), $sourceIds)
            ->willThrowException(new NoMappingForIdsException($sourceIds, get_class($this->migration)));

        $this->expectException(NoMappingForIdsException::class);
        $this->referenceStore->get(get_class($this->migration), $sourceIds);
    }

    public function testGetDestinationFail()
    {
        $this->setupReferenceStore();

        $sourceIds = [
            'id' => 1,
        ];
        $destIds = [
            'identifier' => 'test',
        ];

        $this->destinationDriver->expects($this->once())
            ->method('read')
            ->with($destIds)
            ->willReturn(null);

        $this->mapper->expects($this->once())
            ->method('getDestIdsFromSourceIds')
            ->with(get_class($this->migration), $sourceIds)
            ->willReturn($destIds);

        $this->expectException(NoMappingForIdsException::class);
        $this->referenceStore->get(get_class($this->migration), $sourceIds);
    }

    public function testGetMapperFailStub()
    {
        $this->setupReferenceStore(new Driver(['supportsStubs' => true]));

        $sourceIds = [
            'id' => 1,
        ];

        $this->destinationDriver->expects($this->never())
            ->method('read');

        $this->mapper->expects($this->once())
            ->method('getDestIdsFromSourceIds')
            ->with(get_class($this->migration), $sourceIds)
            ->willThrowException(new NoMappingForIdsException($sourceIds, get_class($this->migration)));

        $stub = new stdClass();
        $this->mapper->expects($this->once())
            ->method('createStub')
            ->with($this->migration)
            ->willReturn($stub);

        $this->assertSame($this->referenceStore->get(get_class($this->migration), $sourceIds, true), $stub);
    }

    public function testGetDestinationFailStub()
    {
        $this->setupReferenceStore(new Driver(['supportsStubs' => true]));

        $sourceIds = [
            'id' => 1,
        ];
        $destIds = [
            'identifier' => 'test',
        ];

        $this->destinationDriver->expects($this->once())
            ->method('read')
            ->with($destIds)
            ->willReturn(null);

        $this->mapper->expects($this->once())
            ->method('getDestIdsFromSourceIds')
            ->with(get_class($this->migration), $sourceIds)
            ->willReturn($destIds);

        $stub = new stdClass();
        $this->mapper->expects($this->once())
            ->method('createStub')
            ->with($this->migration)
            ->willReturn($stub);

        $this->assertSame($this->referenceStore->get(get_class($this->migration), $sourceIds, true), $stub);
    }

    public function testGetStubUnsupported()
    {
        $this->setupReferenceStore();

        $sourceIds = [
            'id' => 1,
        ];

        $this->destinationDriver->expects($this->never())
            ->method('read');

        $this->mapper->expects($this->once())
            ->method('getDestIdsFromSourceIds')
            ->with(get_class($this->migration), $sourceIds)
            ->willThrowException(new NoMappingForIdsException($sourceIds, get_class($this->migration)));

        $this->expectException(NoMappingForIdsException::class);
        $this->referenceStore->get(get_class($this->migration), $sourceIds, true);
    }
}
