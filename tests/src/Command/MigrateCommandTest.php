<?php

namespace DragoonBoots\A2B\Tests\Command;

use Doctrine\Common\Collections\ArrayCollection;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Command\MigrateCommand;
use DragoonBoots\A2B\DataMigration\DataMigrationExecutorInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManagerInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationMapperInterface;
use DragoonBoots\A2B\Drivers\Destination\DebugDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use League\Uri\Parser;
use PHPUnit\Framework\MockObject\Matcher\InvokedRecorder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;

class MigrateCommandTest extends TestCase
{

    /**
     * @var DataMigrationManagerInterface|MockObject
     */
    protected $dataMigrationManager;

    /**
     * @var DriverManagerInterface|MockObject
     */
    protected $driverManager;

    /**
     * @var DataMigrationExecutorInterface|MockObject
     */
    protected $executor;

    /**
     * @var DataMigrationMapperInterface|MockObject
     */
    protected $mapper;

    /**
     * @var Parser|MockObject
     */
    protected $uriParser;

    /**
     * @var ParameterBagInterface|MockObject
     */
    protected $parameterBag;

    /**
     * @var AbstractDumper|MockObject
     */
    protected $varDumper;

    /**
     * @var ClonerInterface|MockObject
     */
    protected $varCloner;

    /**
     * Migration with group "default"
     *
     * @var DataMigrationInterface|MockObject
     */
    protected $migration1;

    /**
     * Migration with group "special"
     *
     * @var DataMigrationInterface|MockObject
     */
    protected $migration2;

    /**
     * @var SourceDriverInterface|MockObject
     */
    protected $sourceDriver;

    /**
     * @var DestinationDriverInterface|MockObject
     */
    protected $destinationDriver;

    /**
     * @var MigrateCommand
     */
    protected $command;

    public function testExecute()
    {
        $this->setUpCommand();
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getSourceDriver')
            ->with('InlineSourceDriver')
            ->willReturn($this->sourceDriver);
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getDestinationDriver')
            ->with('TestDestinationDriver')
            ->willReturn($this->destinationDriver);
        $this->dataMigrationManager->expects($this->once())
            ->method('resolveDependencies')
            ->willReturnCallback(
                function ($migrations) {
                    return new ArrayCollection(array_values($migrations));
                }
            );

        $this->executor->expects($this->exactly(1))
            ->method('execute')
            ->with($this->migration1, $this->sourceDriver, $this->destinationDriver);
        $tester = new CommandTester($this->command);
        $tester->execute([]);
    }

    /**
     * @param DataMigration|null $definition
     *   The definition to use for $migration2
     */
    public function setUpCommand($definition = null)
    {
        $data = [
            'id' => 1,
            'field' => 'test',
        ];
        $dataMigrationManager = $this->setupDataMigrationManager($data, $definition);
        $this->dataMigrationManager = $dataMigrationManager;

        $this->sourceDriver = $this->createMock(SourceDriverInterface::class);
        $this->destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $driverManager = $this->createMock(DriverManagerInterface::class);
        $this->driverManager = $driverManager;

        $executor = $this->createMock(DataMigrationExecutorInterface::class);
        $this->executor = $executor;

        $mapper = $this->createMock(DataMigrationMapperInterface::class);
        $this->mapper = $mapper;

        $uriParser = $this->createMock(Parser::class);
        $dataPath = urlencode(serialize($data));
        $uriParser->method('parse')
            ->willReturnMap(
                [
                    [
                        'inline://'.$dataPath,
                        [
                            'scheme' => 'inline',
                            'path' => $dataPath,
                        ],
                    ],
                    [
                        'test:stdout',
                        [
                            'scheme' => 'test',
                            'path' => 'stdout',
                        ],
                    ],
                    [
                        'debug:stderr',
                        [
                            'scheme' => 'debug',
                            'path' => 'stdout',
                        ],
                    ],
                ]
            );
        $this->uriParser = $uriParser;

        $varDumper = $this->createMock(AbstractDumper::class);
        $this->varDumper = $varDumper;

        $varCloner = $this->createMock(ClonerInterface::class);
        $this->varCloner = $varCloner;

        $command = new MigrateCommand(
            $this->dataMigrationManager,
            $this->driverManager,
            $this->executor,
            $this->mapper,
            $this->uriParser,
            $this->varDumper,
            $this->varCloner
        );
        $this->command = $command;
    }

    /**
     * @param array The data contained in the source $data
     * @param DataMigration|null $definition
     *   The definition to use for $migration2
     *
     * @return DataMigrationManagerInterface|MockObject
     */
    protected function setupDataMigrationManager($data, $definition = null)
    {
        $definition1 = new DataMigration(
            [
                'name' => 'Test Migration 1',
                'source' => 'inline://'.urlencode(serialize($data)),
                'sourceDriver' => 'InlineSourceDriver',
                'sourceIds' => [
                    new IdField(
                        [
                            'name' => 'id',
                        ]
                    ),
                ],
                'destination' => 'test:stdout',
                'destinationDriver' => 'TestDestinationDriver',
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
        if (!is_null($definition)) {
            $definition2 = $definition;
        } else {
            $definition2 = new DataMigration(
                [
                    'name' => 'Test Migration 2',
                    'group' => 'special',
                    'source' => 'inline://'.urlencode(serialize($data)),
                    'sourceDriver' => 'InlineSourceDriver',
                    'sourceIds' => [
                        new IdField(
                            [
                                'name' => 'id',
                            ]
                        ),
                    ],
                    'destination' => 'test:stdout',
                    'destinationDriver' => 'TestDestinationDriver',
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
        }
        $this->migration1 = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration1')
            ->getMock();
        $this->migration1->method('getDefinition')
            ->willReturn($definition1);
        $this->migration2 = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration2')
            ->getMock();
        $this->migration2->method('getDefinition')
            ->willReturn($definition2);
        $dataMigrationManager = $this->createMock(DataMigrationManagerInterface::class);
        $dataMigrationManager->method('getMigrations')
            ->willReturn(
                new ArrayCollection(
                    [
                        $this->migration1,
                        $this->migration2,
                    ]
                )
            );
        $dataMigrationManager->method('getMigration')
            ->willReturnMap(
                [
                    [get_class($this->migration1), $this->migration1],
                    [get_class($this->migration2), $this->migration2],
                ]
            );
        $dataMigrationManager->method('getMigrationsInGroup')
            ->willReturnMap(
                [
                    ['default', new ArrayCollection([$this->migration1])],
                    ['special', new ArrayCollection([$this->migration2])],
                ]
            );

        return $dataMigrationManager;
    }

    public function testExecuteGroup()
    {
        $this->setUpCommand();
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getSourceDriver')
            ->with('InlineSourceDriver')
            ->willReturn($this->sourceDriver);
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getDestinationDriver')
            ->with('TestDestinationDriver')
            ->willReturn($this->destinationDriver);
        $this->dataMigrationManager->expects($this->once())
            ->method('resolveDependencies')
            ->willReturnCallback(
                function ($migrations) {
                    return new ArrayCollection(array_values($migrations));
                }
            );

        $this->executor->expects($this->exactly(1))
            ->method('execute')
            ->with($this->migration2, $this->sourceDriver, $this->destinationDriver);
        $tester = new CommandTester($this->command);
        $tester->execute(
            [
                '--group' => ['special'],
            ]
        );
    }

    public function testExecuteSpecificMigrations()
    {
        $this->setUpCommand();
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getSourceDriver')
            ->with('InlineSourceDriver')
            ->willReturn($this->sourceDriver);
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getDestinationDriver')
            ->with('TestDestinationDriver')
            ->willReturn($this->destinationDriver);
        $this->dataMigrationManager->expects($this->once())
            ->method('resolveDependencies')
            ->willReturnCallback(
                function ($migrations) {
                    return new ArrayCollection(array_values($migrations));
                }
            );

        $this->executor->expects($this->exactly(1))
            ->method('execute')
            ->with($this->migration2, $this->sourceDriver, $this->destinationDriver);
        $tester = new CommandTester($this->command);
        $tester->execute(
            [
                'migrations' => [get_class($this->migration2)],
            ]
        );
    }

    /**
     * @param array   $input
     * @param Matcher $askAboutOrphansCount
     * @param Matcher $writeOrphansCount
     *
     * @dataProvider orphanOptionDataProvider
     */
    public function testExecuteWithOrphans(array $input, InvokedRecorder $askAboutOrphansCount, InvokedRecorder $writeOrphansCount)
    {
        $this->setUpCommand();
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getSourceDriver')
            ->with('InlineSourceDriver')
            ->willReturn($this->sourceDriver);
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getDestinationDriver')
            ->with('TestDestinationDriver')
            ->willReturn($this->destinationDriver);
        $this->dataMigrationManager->expects($this->once())
            ->method('resolveDependencies')
            ->willReturnCallback(
                function ($migrations) {
                    return new ArrayCollection(array_values($migrations));
                }
            );

        $orphans = [['identifier' => 'orphan']];
        $this->executor->expects($this->exactly(1))
            ->method('execute')
            ->with($this->migration1, $this->sourceDriver, $this->destinationDriver)
            ->willReturn($orphans);
        $this->executor->expects($askAboutOrphansCount)
            ->method('askAboutOrphans')
            ->with($orphans, $this->migration1, $this->destinationDriver);
        $this->executor->expects($writeOrphansCount)
            ->method('writeOrphans')
            ->with($orphans, $this->migration1, $this->destinationDriver);
        $tester = new CommandTester($this->command);
        $tester->execute($input);
    }

    public function orphanOptionDataProvider()
    {
        return [
            'default' => [
                // Input
                [],
                // askAboutOrphans count
                $this->atLeastOnce(),
                // writeOrphans count
                $this->never(),
            ],
            'prune' => [
                // Input
                ['--prune' => true],
                // askAboutOrphans count
                $this->never(),
                // writeOrphans count
                $this->never(),
            ],
            'preserve' => [
                // Input
                ['--preserve' => true],
                // askAboutOrphans count
                $this->never(),
                // writeOrphans count
                $this->atLeastOnce(),
            ],
        ];
    }

    public function testExecuteSpecificDrivers()
    {
        $specialSourceDriver = $this->createMock(SourceDriverInterface::class);
        $specialDestinationDriver = $this->createMock(DestinationDriverInterface::class);
        $data = ['id' => 1, 'field' => 'test'];
        $definition = new DataMigration(
            [
                'name' => 'Test Migration 2',
                'group' => 'special',
                'source' => 'inline://'.urlencode(serialize($data)),
                'sourceIds' => [
                    new IdField(
                        [
                            'name' => 'id',
                        ]
                    ),
                ],
                'sourceDriver' => get_class($specialSourceDriver),
                'destination' => 'debug:stdout',
                'destinationIds' => [
                    new IdField(
                        [
                            'name' => 'identifier',
                            'type' => 'string',
                        ]
                    ),
                ],
                'destinationDriver' => get_class($specialDestinationDriver),
            ]
        );
        $this->setUpCommand($definition);
        $this->driverManager->expects($this->once())
            ->method('getSourceDriver')
            ->with(get_class($specialSourceDriver))
            ->willReturn($specialSourceDriver);
        $this->driverManager->expects($this->once())
            ->method('getDestinationDriver')
            ->with(get_class($specialDestinationDriver))
            ->willReturn($specialDestinationDriver);
        $this->dataMigrationManager->expects($this->once())
            ->method('resolveDependencies')
            ->willReturnCallback(
                function ($migrations) {
                    return new ArrayCollection(array_values($migrations));
                }
            );

        $tester = new CommandTester($this->command);
        $tester->execute(['migrations' => [get_class($this->migration2)]]);
    }

    public function testExecuteSimulate()
    {
        $this->setUpCommand();
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getSourceDriver')
            ->with('InlineSourceDriver')
            ->willReturn($this->sourceDriver);
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getDestinationDriver')
            ->with(DebugDestinationDriver::class)
            ->willReturn($this->destinationDriver);
        $this->dataMigrationManager->expects($this->once())
            ->method('resolveDependencies')
            ->willReturnCallback(
                function ($migrations) {
                    return new ArrayCollection(array_values($migrations));
                }
            );

        $tester = new CommandTester($this->command);

        $tester->execute(['--simulate' => true]);
    }

    public function testExecuteConflictingOptions()
    {
        $dataMigrationManager = $this->createMock(DataMigrationManagerInterface::class);
        $dataMigrationManager->expects($this->never())
            ->method('resolveDependencies');
        $driverManager = $this->createMock(DriverManagerInterface::class);
        $executor = $this->createMock(DataMigrationExecutorInterface::class);
        $executor->expects($this->never())
            ->method('execute');
        $mapper = $this->createMock(DataMigrationMapperInterface::class);
        $uriParser = $this->createMock(Parser::class);
        $varDumper = $this->createMock(AbstractDumper::class);
        $varCloner = $this->createMock(ClonerInterface::class);
        $command = new MigrateCommand(
            $dataMigrationManager,
            $driverManager,
            $executor,
            $mapper,
            $uriParser,
            $varDumper,
            $varCloner
        );
        $tester = new CommandTester($command);

        $tester->execute(
            [
                '--prune' => true,
                '--preserve' => true,
            ]
        );

        $this->assertContains(MigrateCommand::ERROR_NO_PRUNE_PRESERVE, $tester->getDisplay());
    }

    public function testNoDependencyResolution()
    {
        $this->setUpCommand();
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getSourceDriver')
            ->with('InlineSourceDriver')
            ->willReturn($this->sourceDriver);
        $this->driverManager->expects($this->atLeastOnce())
            ->method('getDestinationDriver')
            ->with('TestDestinationDriver')
            ->willReturn($this->destinationDriver);
        $this->dataMigrationManager->expects($this->never())
            ->method('resolveDependencies');

        $tester = new CommandTester($this->command);

        $tester->execute(
            [
                '--no-deps' => true,
            ]
        );
    }

}
