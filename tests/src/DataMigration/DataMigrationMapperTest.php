<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOSqlite;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManager;
use DragoonBoots\A2B\DataMigration\DataMigrationMapper;
use DragoonBoots\A2B\DataMigration\DataMigrationMapperInterface;
use DragoonBoots\A2B\DataMigration\StubberInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use League\Uri\Parser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataMigrationMapperTest extends TestCase
{

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var DataMigrationInterface[]|MockObject[]
     */
    protected $migrations;

    /**
     * @var DataMigration[]
     */
    protected $definitions;

    /**
     * @var StubberInterface|MockObject
     */
    protected $stubber;

    /**
     * @var DataMigrationMapperInterface
     */
    protected $mapper;

    public function setUp()
    {
        $this->connection = new Connection(['memory' => true], new PDOSqlite\Driver());
        $this->connection->connect();
    }

    public function tearDown()
    {
        $this->connection->close();
        unset($this->connection);
    }

    public function testAddMappingFirstRun()
    {
        $this->setupMapper();

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $this->mapper->addMapping(get_class($this->migrations['TestMigration1']), $sourceIds, $destIds);

        // Check the results
        $mappings = $this->connection->query('SELECT * FROM "test_migration1"')
            ->fetchAll();
        $this->assertCount(1, $mappings);
        $mapping = array_pop($mappings);
        $this->assertEquals('test', $mapping['source_identifier']);
        $this->assertEquals(1, $mapping['dest_id']);

        // Assert updated times.  To account for race conditions, allow up to 1
        // minute of leeway before failing.
        $updated = new \DateTime($mapping['updated']);
        $updatedDiff = $updated->diff(new \DateTime());
        $this->assertEquals(0, $updatedDiff->i);
    }

    /**
     * @throws \ReflectionException
     */
    protected function setupMapper()
    {
        /** @var DataMigrationInterface[]|MockObject[] $migrations */
        $this->migrations = [
            'TestMigration1' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('TestMigration1')
                ->getMock(),
            'TestMigration2' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('TestMigration2')
                ->getMock(),
        ];
        /** @var DataMigration[] $definitions */
        $this->definitions = [
            'TestMigration1' => new DataMigration(
                [
                    'source' => 'test://test',
                    'sourceIds' => [
                        new IdField(
                            [
                                'name' => 'identifier',
                                'type' => 'string',
                            ]
                        ),
                    ],
                    'destination' => 'test://test',
                    'destinationIds' => [new IdField(['name' => 'id'])],
                ]
            ),
            'TestMigration2' => new DataMigration(
                [
                    'source' => 'test://test',
                    'sourceIds' => [
                        new IdField(
                            [
                                'name' => 'identifier',
                                'type' => 'string',
                            ]
                        ),
                    ],
                    'destination' => 'test://test',
                    'destinationIds' => [new IdField(['name' => 'id'])],
                ]
            ),
        ];
        foreach ($this->migrations as $migrationId => $migration) {
            $migration->method('getDefinition')
                ->willReturn($this->definitions[$migrationId]);
        }

        // Test with a real inflector and migration manager as their output can
        // cause very real problems in the mapping database.
        $inflector = new Inflector();
        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->method('getClassAnnotation')
            ->willReturnCallback(
                function (\ReflectionClass $reflectionClass, string $annotationName) {
                    return $this->definitions[$reflectionClass->getName()];
                }
            );
        $uriParser = $this->createMock(Parser::class);
        $uriParser->method('parse')
            ->willReturn(['scheme' => 'test']);
        $driverManager = $this->createMock(DriverManagerInterface::class);
        $driverManager->method('getSourceDriverForScheme')
            ->willReturn($this->createMock(SourceDriverInterface::class));
        $driverManager->method('getDestinationDriverForScheme')
            ->willReturn($this->createMock(DestinationDriverInterface::class));

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->exactly(count($this->migrations) * 2))
            ->method('resolveValue')
            ->willReturnArgument(0);

        $dataMigrationManager = new DataMigrationManager($annotationReader, $uriParser, $driverManager, $parameterBag);
        foreach ($this->migrations as $migration) {
            $dataMigrationManager->addMigration($migration);
        }

        $this->stubber = $this->createMock(StubberInterface::class);

        $this->mapper = new DataMigrationMapper($this->connection, $inflector, $dataMigrationManager, $this->stubber);
    }

    public function testAddMappingSecondRun()
    {
        $this->setupMapper();

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $this->mapper->addMapping(get_class($this->migrations['TestMigration1']), $sourceIds, $destIds);

        // Check the results
        $mappings = $this->connection->query('SELECT * FROM "test_migration1"')
            ->fetchAll();
        $this->assertCount(1, $mappings);
        $mapping = array_pop($mappings);
        $this->assertEquals('test', $mapping['source_identifier']);
        $this->assertEquals(1, $mapping['dest_id']);

        // Assert updated times.  To account for race conditions, allow up to 1
        // minute of leeway before failing.
        $firstUpdated = new \DateTime($mapping['updated']);
        $updatedDiff = $firstUpdated->diff(new \DateTime());
        $this->assertEquals(0, $updatedDiff->i);

        // Wait one second to ensure the updated time will roll over to a new
        // (greater) value.
        sleep(1);
        $this->mapper->addMapping(get_class($this->migrations['TestMigration1']), $sourceIds, $destIds);
        $mappings = $this->connection->query('SELECT * FROM "test_migration1"')
            ->fetchAll();
        $this->assertCount(1, $mappings);
        $mapping = array_pop($mappings);
        $secondUpdated = new \DateTime($mapping['updated']);
        $this->assertGreaterThan($firstUpdated, $secondUpdated);
    }

    public function testGetDestIdsFromSourceIds()
    {
        $this->setupMapper();

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $this->mapper->addMapping(get_class($this->migrations['TestMigration1']), $sourceIds, $destIds);
        $this->assertEquals($destIds, $this->mapper->getDestIdsFromSourceIds(get_class($this->migrations['TestMigration1']), $sourceIds));
    }

    public function testGetDestIdsFromSourceIdsBad()
    {
        $this->setupMapper();

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $this->mapper->addMapping(get_class($this->migrations['TestMigration1']), $sourceIds, $destIds);
        $this->expectException(NoMappingForIdsException::class);
        $this->mapper->getDestIdsFromSourceIds(get_class($this->migrations['TestMigration1']), ['identifier' => 'nope']);
    }

    public function testGetSourceIdsFromDestIds()
    {
        $this->setupMapper();

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $this->mapper->addMapping(get_class($this->migrations['TestMigration1']), $sourceIds, $destIds);
        $this->assertEquals($sourceIds, $this->mapper->getSourceIdsFromDestIds(get_class($this->migrations['TestMigration1']), $destIds));
    }

    public function testGetSourceIdsFromDestIdsBad()
    {
        $this->setupMapper();

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $this->mapper->addMapping(get_class($this->migrations['TestMigration1']), $sourceIds, $destIds);
        $this->expectException(NoMappingForIdsException::class);
        $this->mapper->getSourceIdsFromDestIds(get_class($this->migrations['TestMigration1']), ['id' => 12]);
    }

    public function testCreateStub()
    {
        $this->setupMapper();

        $defaultResult = new \stdClass();
        $this->stubber->expects($this->once())
            ->method('createStub')
            ->willReturn($defaultResult);

        $stub = $this->mapper->createStub($this->migrations['TestMigration1'], ['identifier' => 'test']);
        $this->assertSame($defaultResult, $stub);

        $stubs = $this->mapper->getAndPurgeStubs();
        $this->assertSame($stub, array_pop($stubs));
    }
}
