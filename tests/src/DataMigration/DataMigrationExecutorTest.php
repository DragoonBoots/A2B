<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\DataMigrationExecutor;
use DragoonBoots\A2B\DataMigration\DataMigrationExecutorInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationMapper;
use DragoonBoots\A2B\DataMigration\DataMigrationMapperInterface;
use DragoonBoots\A2B\DataMigration\OutputFormatter\OutputFormatterInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DataMigrationExecutorTest extends TestCase
{

    protected $orphanKeepDecision;

    protected $orphanRemoveDecision;

    protected $orphanAskDecision;

    /**
     * DataMigrationExecutorTest constructor.
     *
     * @param null|string $name
     * @param array       $data
     * @param string      $dataName
     *
     * @throws \ReflectionException
     */
    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        // Use reflection to get the values of the internal constants.
        $refl = new \ReflectionClass(DataMigrationExecutor::class);
        $this->orphanKeepDecision = $refl->getConstant('ORPHAN_KEEP');
        $this->orphanRemoveDecision = $refl->getConstant('ORPHAN_REMOVE');
        $this->orphanAskDecision = $refl->getConstant('ORPHAN_ASK');
    }

    public function testExecute()
    {
        $testSourceData = [
            [
                'id' => 1,
                'field' => 'data',
            ],
        ];
        $testResultData = [
            [
                'identifier' => 'test',
                'field' => 'migrated',
            ],
        ];
        $testSourceIds = [];
        foreach ($testSourceData as $testSourceRow) {
            $testSourceIds[] = ['id' => $testSourceRow['id']];
        }
        $testResultIds = [];
        foreach ($testResultData as $testResultRow) {
            $testResultIds[] = ['identifier' => $testResultRow['identifier']];
        }

        $definition = new DataMigration(
            [
                'sourceIds' => [
                    new IdField(
                        [
                            'name' => 'id',
                        ]
                    ),
                ],
                'destinationIds' => [
                    new IdField(
                        [
                            'name' => 'identifier',
                            'type' => 'string',
                        ]
                    ),
                ],
            ]
        );

        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $migration->method('getDefinition')->willReturn($definition);
        $migration->method('defaultResult')->willReturn([]);
        $migration->expects($this->exactly(count($testSourceData) * 2))
            ->method('transform')
            ->withConsecutive(
                [$testSourceData[0], []],
                [$testSourceData[0], $testResultData[0]]
            )
            ->willReturn($testResultData[0]);

        $sourceDriver = $this->createMock(SourceDriverInterface::class);
        $sourceDriver->method('getIterator')->willReturn($testSourceData);
        $sourceDriver->method('count')->willReturn(count($testSourceData));

        $destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $destinationDriver->method('getExistingIds')
            ->willReturnOnConsecutiveCalls([], $testResultIds);
        // This method can't be hit the first time, as there are no ids to use.
        $destinationDriver->method('read')
            ->willReturn($testResultData[0]);
        $destinationDriver->method('write')
            ->with($testResultData[0])
            ->willReturn($testResultIds[0]);

        $mapper = $this->createMock(DataMigrationMapperInterface::class);
        $mapper->method('getDestIdsFromSourceIds')->willReturnCallback(
            function ($migrationId, $sourceIds) {
                static $callCount;
                if (!isset($callCount)) {
                    $callCount = 0;
                }

                $callCount++;
                if ($callCount == 1) {
                    throw new NoMappingForIdsException($sourceIds);
                }

                return ['identifier' => 'test'];
            }
        );
        $mapper->expects($this->exactly(count($testSourceData) * 2))
            ->method('addMapping');

        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $outputFormatter->expects($this->exactly(2))
            ->method('start');
        $outputFormatter->expects($this->exactly(2))
            ->method('finish');

        $executor = new DataMigrationExecutor($mapper);
        $executor->setOutputFormatter($outputFormatter);
        // Run twice to test that updating logic works.  The assertions are all
        // in the migration drivers that observe the process.
        $executor->execute($migration, $sourceDriver, $destinationDriver);
        $executor->execute($migration, $sourceDriver, $destinationDriver);
    }

    public function testExecuteWithOrphans()
    {
        $testSourceData = [
            [
                'id' => 1,
                'field' => 'data',
            ],
        ];
        $testResultData = [
            [
                'identifier' => 'test',
                'field' => 'migrated,',
            ],
        ];
        $orphan = [
            'identifier' => 'orphan',
            'field' => 'orphan',
        ];
        $testResultData = array_merge($testResultData, [$orphan]);
        $orphanId = [
            'identifier' => 'orphan',
        ];
        $testSourceIds = [];
        foreach ($testSourceData as $testSourceRow) {
            $testSourceIds[] = ['id' => $testSourceRow['id']];
        }
        $testResultIds = [];
        foreach ($testResultData as $testResultRow) {
            $testResultIds[] = ['identifier' => $testResultRow['identifier']];
        }

        $definition = new DataMigration(
            [
                'sourceIds' => [
                    new IdField(
                        [
                            'name' => 'id',
                        ]
                    ),
                ],
                'destinationIds' => [
                    new IdField(
                        [
                            'name' => 'identifier',
                            'type' => 'string',
                        ]
                    ),
                ],
            ]
        );

        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $migration->method('getDefinition')->willReturn($definition);
        $migration->method('defaultResult')->willReturn([]);
        $migration->method('transform')
            ->willReturn($testResultData[0]);

        $sourceDriver = $this->createMock(SourceDriverInterface::class);
        $sourceDriver->method('getIterator')->willReturn($testSourceData);
        $sourceDriver->method('count')->willReturn(count($testSourceData));

        $destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $destinationDriver->method('getExistingIds')
            ->willReturn($testResultIds);
        $destinationDriver->method('read')
            ->with($testResultIds[0])
            ->willReturn($testResultData[0]);
        $destinationDriver->method('readMultiple')
            ->with([$orphanId])
            ->willReturn([$orphan]);
        $destinationDriver->expects($this->exactly(count($testSourceData)))
            ->method('write')
            ->willReturn($testResultIds[0]);

        $mapper = $this->createMock(DataMigrationMapperInterface::class);
        $mapper->method('getDestIdsFromSourceIds')->willReturnCallback(
            function ($migrationId, $sourceIds) {
                static $callCount;
                if (!isset($callCount)) {
                    $callCount = 0;
                }

                $callCount++;
                if ($callCount == 1) {
                    throw new NoMappingForIdsException($sourceIds);
                }

                return ['identifier' => 'test'];
            }
        );

        $outputFormatter = $this->createMock(OutputFormatterInterface::class);

        $executor = new DataMigrationExecutor($mapper);
        $executor->setOutputFormatter($outputFormatter);
        $resultOrphans = $executor->execute($migration, $sourceDriver, $destinationDriver);
        $this->assertEquals([$orphan], $resultOrphans);

        return [
            'orphans' => $resultOrphans,
            'migration' => $migration,
            'destinationDriver' => $destinationDriver,
            'executor' => $executor,
        ];
    }

    /**
     * @param $context
     *
     * @depends testExecuteWithOrphans
     */
    public function testAskAboutOrphansKeepAll($context)
    {
        /** @var DataMigrationInterface|MockObject $migration */
        [
            'orphans' => $orphans,
            'migration' => $migration,
        ] = $context;

        $orphanDestIds = [];
        foreach ($orphans as $orphan) {
            $orphanDestIds[] = ['identifier' => $orphan['identifier']];
        }
        $mapperAddParams = [];
        foreach ($orphanDestIds as $orphanDestId) {
            $mapperAddParams[] = [
                get_class($migration),
                $migration->getDefinition(),
                array_fill_keys(array_keys($orphanDestId), null),
                $orphanDestId,
            ];
        }

        $mapper = $this->createMock(DataMigrationMapper::class);
        $mapper->expects($this->exactly(count($orphans)))
            ->method('addMapping')
            ->withConsecutive(...$mapperAddParams);
        $executor = new DataMigrationExecutor($mapper);

        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $outputFormatter->expects($this->once())
            ->method('ask')
            ->willReturn($this->orphanKeepDecision);
        $executor->setOutputFormatter($outputFormatter);

        $orphansParams = [];
        foreach ($orphans as $orphan) {
            $orphansParams[] = [$orphan];
        }
        $destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $destinationDriver->expects($this->exactly(count($orphans)))
            ->method('write')
            ->withConsecutive(...$orphansParams)
            ->willReturnOnConsecutiveCalls(...$orphanDestIds);

        $executor->askAboutOrphans($orphans, $migration, $destinationDriver);
    }

    /**
     * @param $context
     *
     * @depends testExecuteWithOrphans
     */
    public function testAskAboutOrphansRemoveAll($context)
    {
        /** @var DataMigrationInterface|MockObject $migration */
        /** @var DestinationDriverInterface|MockObject $destinationDriver */
        /** @var DataMigrationExecutorInterface $executor */
        [
            'orphans' => $orphans,
            'migration' => $migration,
            'destinationDriver' => $destinationDriver,
            'executor' => $executor,
        ] = $context;

        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $outputFormatter->expects($this->once())
            ->method('ask')
            ->willReturn($this->orphanRemoveDecision);
        $executor->setOutputFormatter($outputFormatter);

        $orphansParams = [];
        foreach ($orphans as $orphan) {
            $orphansParams[] = [$orphan];
        }
        $destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $destinationDriver->expects($this->never())
            ->method('write');

        $executor->askAboutOrphans($orphans, $migration, $destinationDriver);
    }

    /**
     * @param string $decision
     * @param bool   $written
     * @param array  $context
     *
     * @dataProvider orphanCheckDataProvider
     * @depends      testExecuteWithOrphans
     */
    public function testAskAboutOrphansCheckEach($decision, $written, $context)
    {
        /** @var DataMigrationInterface|MockObject $migration */
        /** @var DestinationDriverInterface|MockObject $destinationDriver */
        /** @var DataMigrationExecutorInterface $executor */
        [
            'orphans' => $orphans,
            'migration' => $migration,
        ] = $context;
        $writeCount = $written ? count($orphans) : 0;

        $orphanDestIds = [];
        foreach ($orphans as $orphan) {
            $orphanDestIds[] = ['identifier' => $orphan['identifier']];
        }
        $mapperAddParams = [];
        foreach ($orphanDestIds as $orphanDestId) {
            $mapperAddParams[] = [
                get_class($migration),
                $migration->getDefinition(),
                array_fill_keys(array_keys($orphanDestId), null),
                $orphanDestId,
            ];
        }

        $mapper = $this->createMock(DataMigrationMapper::class);
        $mapper->expects($this->exactly($writeCount))
            ->method('addMapping')
            ->withConsecutive(...$mapperAddParams);
        $executor = new DataMigrationExecutor($mapper);

        $outputFormatter = $this->createMock(OutputFormatterInterface::class);
        $outputFormatter->expects($this->exactly(2))
            ->method('ask')
            ->willReturnOnConsecutiveCalls($this->orphanAskDecision, $decision);
        $executor->setOutputFormatter($outputFormatter);

        $orphansParams = [];
        foreach ($orphans as $orphan) {
            $orphansParams[] = [$orphan];
        }
        $destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $destinationDriver->expects($this->exactly($writeCount))
            ->method('write')
            ->withConsecutive(...$orphansParams)
            ->willReturnOnConsecutiveCalls(...$orphanDestIds);

        $executor->askAboutOrphans($orphans, $migration, $destinationDriver);
    }

    public function orphanCheckDataProvider()
    {
        return [
            'keep' => [
                // Decision
                $this->orphanKeepDecision,
                // Written
                true,
            ],
            'remove' => [
                // Decision
                $this->orphanRemoveDecision,
                // Written
                false,
            ],
        ];
    }
}
