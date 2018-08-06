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
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use League\Uri\Parser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DataMigrationMapperTest extends TestCase
{

    /**
     * @var Connection
     */
    protected $connection;

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
        /** @var DataMigrationInterface[] $migrations */
        /** @var DataMigration[] $annotations */
        /** @var DataMigrationMapper $mapper */
        $this->setupMapper($migrations, $annotations, $mapper);

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $mapper->addMapping(get_class($migrations['TestMigration1']), $annotations['TestMigration1'], $sourceIds, $destIds);

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
     * @param DataMigrationInterface[]|null $migrations
     * @param DataMigration[]|null          $definitions
     * @param DataMigrationMapper|null      $mapper
     *
     * @throws \ReflectionException
     */
    protected function setupMapper(?array &$migrations, ?array &$definitions, ?DataMigrationMapper &$mapper)
    {
        /** @var DataMigrationInterface[]|MockObject[] $migrations */
        $migrations = [
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
        $definitions = [
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
        foreach ($migrations as $migrationId => $migration) {
            $migration->method('getDefinition')
                ->willReturn($definitions[$migrationId]);
        }

        // Test with a real inflector and migration manager as their output can
        // cause very real problems in the mapping database.
        $inflector = new Inflector();
        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->method('getClassAnnotation')
            ->willReturnCallback(
                function (\ReflectionClass $reflectionClass, string $annotationName) use ($migrations, $definitions) {
                    return $definitions[$reflectionClass->getName()];
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
        $dataMigrationManager = new DataMigrationManager($annotationReader, $uriParser, $driverManager);
        foreach ($migrations as $migration) {
            $dataMigrationManager->addMigration($migration);
        }

        $mapper = new DataMigrationMapper($this->connection, $inflector, $dataMigrationManager);
    }

    public function testAddMappingSecondRun()
    {
        /** @var DataMigrationInterface[] $migrations */
        /** @var DataMigration[] $annotations */
        /** @var DataMigrationMapper $mapper */
        $this->setupMapper($migrations, $annotations, $mapper);

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $mapper->addMapping(get_class($migrations['TestMigration1']), $annotations['TestMigration1'], $sourceIds, $destIds);

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
        $mapper->addMapping(get_class($migrations['TestMigration1']), $annotations['TestMigration1'], $sourceIds, $destIds);
        $mappings = $this->connection->query('SELECT * FROM "test_migration1"')
            ->fetchAll();
        $this->assertCount(1, $mappings);
        $mapping = array_pop($mappings);
        $secondUpdated = new \DateTime($mapping['updated']);
        $this->assertGreaterThan($firstUpdated, $secondUpdated);
    }

    public function testGetDestIdsFromSourceIds()
    {
        /** @var DataMigrationInterface[] $migrations */
        /** @var DataMigration[] $annotations */
        /** @var DataMigrationMapper $mapper */
        $this->setupMapper($migrations, $annotations, $mapper);

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $mapper->addMapping(get_class($migrations['TestMigration1']), $annotations['TestMigration1'], $sourceIds, $destIds);
        $this->assertEquals($destIds, $mapper->getDestIdsFromSourceIds(get_class($migrations['TestMigration1']), $sourceIds));
    }

    public function testGetDestIdsFromSourceIdsBad()
    {
        /** @var DataMigrationInterface[] $migrations */
        /** @var DataMigration[] $annotations */
        /** @var DataMigrationMapper $mapper */
        $this->setupMapper($migrations, $annotations, $mapper);

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $mapper->addMapping(get_class($migrations['TestMigration1']), $annotations['TestMigration1'], $sourceIds, $destIds);
        $this->expectException(NoMappingForIdsException::class);
        $mapper->getDestIdsFromSourceIds(get_class($migrations['TestMigration1']), ['identifier' => 'nope']);
    }

    public function testGetSourceIdsFromDestIds()
    {
        /** @var DataMigrationInterface[] $migrations */
        /** @var DataMigration[] $annotations */
        /** @var DataMigrationMapper $mapper */
        $this->setupMapper($migrations, $annotations, $mapper);

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $mapper->addMapping(get_class($migrations['TestMigration1']), $annotations['TestMigration1'], $sourceIds, $destIds);
        $this->assertEquals($sourceIds, $mapper->getSourceIdsFromDestIds(get_class($migrations['TestMigration1']), $destIds));
    }

    public function testGetSourceIdsFromDestIdsBad()
    {
        /** @var DataMigrationInterface[] $migrations */
        /** @var DataMigration[] $annotations */
        /** @var DataMigrationMapper $mapper */
        $this->setupMapper($migrations, $annotations, $mapper);

        // Add the mapping
        $sourceIds = [
            'identifier' => 'test',
        ];
        $destIds = [
            'id' => 1,
        ];
        $mapper->addMapping(get_class($migrations['TestMigration1']), $annotations['TestMigration1'], $sourceIds, $destIds);
        $this->expectException(NoMappingForIdsException::class);
        $mapper->getSourceIdsFromDestIds(get_class($migrations['TestMigration1']), ['id' => 12]);
    }
}
