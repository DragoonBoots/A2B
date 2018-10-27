<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManager;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\MigrationException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use League\Uri\Parser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataMigrationManagerTest extends TestCase
{

    /**
     * @param DataMigration      $definition
     * @param DataMigration|null $resolvedDefinition
     * @param string[]           $sources
     * @param string[]           $destinations
     *
     * @throws NonexistentMigrationException
     * @throws \DragoonBoots\A2B\Exception\NoDriverForSchemeException
     * @throws \DragoonBoots\A2B\Exception\UnclearDriverException
     * @throws \ReflectionException
     * @dataProvider addMigrationDataProvider
     */
    public function testAddMigration(DataMigration $definition, DataMigration $resolvedDefinition = null, array $sources = [], array $destinations = [])
    {
        if (is_null($resolvedDefinition)) {
            $resolvedDefinition = clone $definition;
        }

        $uriParser = $this->createMock(Parser::class);
        $uriParser->expects($this->exactly(2))
            ->method('parse')
            ->willReturnMap(
                [
                    [
                        $resolvedDefinition->getSource(),
                        ['scheme' => 'testSource'],
                    ],
                    [
                        $resolvedDefinition->getDestination(),
                        ['scheme' => 'testDestination'],
                    ],
                ]
            );

        $sourceDriver = $this->createMock(SourceDriverInterface::class);
        $resolvedDefinition->setSourceDriver(get_class($sourceDriver));
        $destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $resolvedDefinition->setDestinationDriver(get_class($destinationDriver));
        $driverManager = $this->createMock(DriverManagerInterface::class);
        $driverManager->expects($this->once())
            ->method('getSourceDriverForScheme')
            ->with('testSource')
            ->willReturn($sourceDriver);
        $driverManager->expects($this->once())
            ->method('getDestinationDriverForScheme')
            ->with('testDestination')
            ->willReturn($destinationDriver);

        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $migration->expects($this->once())
            ->method('setDefinition')
            ->with($resolvedDefinition);
        $migration->method('getDefinition')
            ->willReturn($resolvedDefinition);

        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->with(new \ReflectionClass($migration), DataMigration::class)
            ->willReturn($definition);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->exactly(2))
            ->method('resolveValue')
            ->willReturnArgument(0);

        $dataMigrationManager = new DataMigrationManager($annotationReader, $uriParser, $driverManager, $parameterBag);
        foreach ($sources as $key => $value) {
            $dataMigrationManager->addSource($key, $value);
        }
        foreach ($destinations as $key => $value) {
            $dataMigrationManager->addDestination($key, $value);
        }
        $dataMigrationManager->addMigration($migration);
        $this->assertEquals(new ArrayCollection(['TestMigration' => $migration]), $dataMigrationManager->getMigrations());
        $this->assertSame($migration, $dataMigrationManager->getMigration(get_class($migration)));

        return [$migration, $dataMigrationManager];
    }

    public function addMigrationDataProvider()
    {
        return [
            'standard' => [
                new DataMigration(
                    [
                        'source' => 'testSource://test',
                        'destination' => 'testDestination://test',
                    ]
                ),
            ],
            'with keys' => [
                new DataMigration(
                    [
                        'source' => 'test_source',
                        'destination' => 'test_destination',
                    ]
                ),
                new DataMigration(
                    [
                        'source' => 'testSource://test',
                        'destination' => 'testDestination://test',
                    ]
                ),
                ['test_source' => 'testSource://test'],
                ['test_destination' => 'testDestination://test'],
            ],
        ];
    }

    /**
     * @dataProvider addMigrationExtendsDataProvider
     *
     * @param DataMigration $extendsDefinition
     * @param string|null   $exception
     *
     * @throws MigrationException
     * @throws NonexistentMigrationException
     * @throws \DragoonBoots\A2B\Exception\NoDriverForSchemeException
     * @throws \DragoonBoots\A2B\Exception\UnclearDriverException
     * @throws \ReflectionException
     */
    public function testAddMigrationExtends(DataMigration $extendsDefinition, ?string $exception = null)
    {
        $definition = new DataMigration(
            [
                'source' => 'testSource://test',
                'sourceIds' => [new IdField(['name' => 'id'])],
                'destination' => 'testDestination://test',
                'destinationIds' => [new IdField(['name' => 'id'])],
            ]
        );
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $migration->method('getDefinition')
            ->willReturn($definition);
        $extendsMigration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestExtendsMigration')
            ->getMock();
        $extendsMigration->method('getDefinition')
            ->willReturn($extendsDefinition);

        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->method('getClassAnnotation')
            ->willReturnCallback(
                function (\ReflectionClass $refl, string $annotationName) use ($migration, $extendsMigration, $definition, $extendsDefinition) {
                    if ($annotationName == DataMigration::class) {
                        switch ($refl->getName()) {
                            case get_class($migration):
                                return $definition;
                            case get_class($extendsMigration):
                                return $extendsDefinition;
                        }
                    }

                    return null;
                }
            );

        $uriParser = $this->createMock(Parser::class);
        $uriParser->method('parse')
            ->willReturnMap(
                [
                    [
                        $definition->getSource(),
                        ['scheme' => 'testSource'],
                    ],
                    [
                        $definition->getDestination(),
                        ['scheme' => 'testDestination'],
                    ],
                ]
            );

        $sourceDriver = $this->createMock(SourceDriverInterface::class);
        $definition->setSourceDriver(get_class($sourceDriver));
        $extendsDefinition->setSourceDriver(get_class($sourceDriver));
        $destinationDriver = $this->createMock(DestinationDriverInterface::class);
        $definition->setDestinationDriver(get_class($destinationDriver));
        $extendsDefinition->setDestinationDriver(get_class($destinationDriver));
        $driverManager = $this->createMock(DriverManagerInterface::class);
        $driverManager->method('getSourceDriverForScheme')
            ->with('testSource')
            ->willReturn($sourceDriver);
        $driverManager->method('getDestinationDriverForScheme')
            ->with('testDestination')
            ->willReturn($destinationDriver);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('resolveValue')
            ->willReturnArgument(0);

        $dataMigrationManager = new DataMigrationManager($annotationReader, $uriParser, $driverManager, $parameterBag);

        foreach ([$migration, $extendsMigration] as $item) {
            $dataMigrationManager->addMigration($item);
        }

        if (!is_null($exception)) {
            $this->expectException($exception);
        }
        self::assertSame(
            $migration, $dataMigrationManager->getMigration('TestExtendsMigration')
            ->getDefinition()
            ->getExtends()
        );

    }

    public function addMigrationExtendsDataProvider()
    {
        return [
            'good' => [
                new DataMigration(
                    [
                        'source' => 'testSource://test',
                        'sourceIds' => [new IdField(['name' => 'id'])],
                        'destination' => 'testDestination://test',
                        'destinationIds' => [new IdField(['name' => 'id'])],
                        'extends' => 'TestMigration',
                    ]
                ),
            ],
            'non-matching source' => [
                new DataMigration(
                    [
                        'source' => 'testSource://other',
                        'sourceIds' => [new IdField(['name' => 'id'])],
                        'destination' => 'testDestination://test',
                        'destinationIds' => [new IdField(['name' => 'id'])],
                        'extends' => 'TestMigration',
                    ]
                ),
                MigrationException::class,
            ],
            'non-matching destination' => [
                new DataMigration(
                    [
                        'source' => 'testSource://test',
                        'sourceIds' => [new IdField(['name' => 'id'])],
                        'destination' => 'testDestination://other',
                        'destinationIds' => [new IdField(['name' => 'id'])],
                        'extends' => 'TestMigration',
                    ]
                ),
                MigrationException::class,
            ],
            'non-matching source ids' => [
                new DataMigration(
                    [
                        'source' => 'testSource://test',
                        'sourceIds' => [
                            new IdField(
                                [
                                    'name' => 'identifier',
                                    'type' => 'string',
                                ]
                            ),
                        ],
                        'destination' => 'testDestination://test',
                        'destinationIds' => [new IdField(['name' => 'id'])],
                        'extends' => 'TestMigration',
                    ]
                ),
                MigrationException::class,
            ],
            'non-matching destination ids' => [
                new DataMigration(
                    [
                        'source' => 'testSource://test',
                        'sourceIds' => [new IdField(['name' => 'id'])],
                        'destination' => 'testDestination://test',
                        'destinationIds' => [
                            new IdField(
                                [
                                    'name' => 'identifier',
                                    'type' => 'string',
                                ]
                            ),
                        ],
                        'extends' => 'TestMigration',
                    ]
                ),
                MigrationException::class,
            ],
        ];
    }

    public function testGetMigrationBad()
    {
        $annotationReader = $this->createMock(Reader::class);
        $uriParser = $this->createMock(Parser::class);
        $driverManager = $this->createMock(DriverManagerInterface::class);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('resolveValue')
            ->willReturnArgument(0);

        $dataMigrationManager = new DataMigrationManager($annotationReader, $uriParser, $driverManager, $parameterBag);
        $this->expectException(NonexistentMigrationException::class);
        $dataMigrationManager->getMigration('NonexistantMigration');
    }

    public function testGetMigrationsInGroup()
    {
        /** @var DataMigrationInterface[]|MockObject[] $migrations */
        $migrations = [
            'Group1Migration' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('Group1Migration')
                ->getMock(),
            'Group2Migration' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('Group2Migration')
                ->getMock(),
        ];
        /** @var DataMigration[] $definitions */
        $definitions = [
            'Group1Migration' => new DataMigration(['group' => 'Group1']),
            'Group2Migration' => new DataMigration(['group' => 'Group2']),
        ];
        foreach ($migrations as $id => $migration) {
            $migration->method('getDefinition')
                ->willReturn($definitions[$id]);
        }
        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->method('getClassAnnotation')
            ->willReturnCallback(
                function (\ReflectionClass $reflectionClass, string $annotationName) use ($definitions) {
                    return $definitions[$reflectionClass->getName()] ?? null;
                }
            );
        $uriParser = $this->createMock(Parser::class);
        $driverManager = $this->createMock(DriverManagerInterface::class);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('resolveValue')
            ->willReturnArgument(0);

        $dataMigrationManager = new DataMigrationManager($annotationReader, $uriParser, $driverManager, $parameterBag);

        // Inject the migrations
        $refl = new \ReflectionClass($dataMigrationManager);
        $migrationsProperty = $refl->getProperty('migrations');
        $migrationsProperty->setAccessible(true);
        $migrationsProperty->setValue($dataMigrationManager, new ArrayCollection($migrations));

        $expected = new ArrayCollection(['Group1Migration' => $migrations['Group1Migration']]);
        $this->assertEquals(
            $expected, $dataMigrationManager->getMigrationsInGroup('Group1')
        );
    }

    /**
     * @param DataMigrationInterface[]|MockObject[] $migrations
     * @param DataMigration[]                       $definitions
     * @param DataMigration[]                       $requested
     * @param DataMigration[]|Collection            $expectedRunList
     * @param string[]                              $expectedExtrasAdded
     *
     * @dataProvider dependencyResolutionDataProvider
     */
    public function testResolveDependencies($migrations, $definitions, $requested, $expectedRunList, $expectedExtrasAdded)
    {
        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->method('getClassAnnotation')
            ->willReturnCallback(
                function (\ReflectionClass $reflectionClass, string $annotationName) use ($definitions) {
                    return $definitions[$reflectionClass->getName()] ?? null;
                }
            );
        $uriParser = $this->createMock(Parser::class);
        $driverManager = $this->createMock(DriverManagerInterface::class);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('resolveValue')
            ->willReturnArgument(0);

        $dataMigrationManager = new DataMigrationManager($annotationReader, $uriParser, $driverManager, $parameterBag);

        // Inject the migrations
        $refl = new \ReflectionClass($dataMigrationManager);
        $migrationsProperty = $refl->getProperty('migrations');
        $migrationsProperty->setAccessible(true);
        $migrationsProperty->setValue($dataMigrationManager, new ArrayCollection($migrations));

        $runList = $dataMigrationManager->resolveDependencies($requested, $extrasAdded);
        $this->assertEquals($expectedRunList, $runList);
        $this->assertEquals($expectedExtrasAdded, $extrasAdded);
    }

    public function dependencyResolutionDataProvider()
    {
        /** @var DataMigrationInterface[]|MockObject[] $migrations */
        $migrations = [
            'FirstMigration' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('FirstMigration')
                ->getMock(),
            'DependentMigration' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('DependentMigration')
                ->getMock(),
        ];
        /** @var DataMigration[] $definitions */
        $definitions = [
            'FirstMigration' => new DataMigration(['depends' => [get_class($migrations['DependentMigration'])]]),
            'DependentMigration' => new DataMigration([]),
        ];
        foreach ($migrations as $group => $migration) {
            $migration->method('getDefinition')
                ->willReturn($definitions[$group]);
        }

        return [
            'all migrations' => [
                $migrations,
                $definitions,
                // Requested list
                $migrations,
                // Run list
                new ArrayCollection(array_values(array_reverse($migrations))),
                // Extras added list
                [],
            ],
            'dependency not specified' => [
                $migrations,
                $definitions,
                // Requested list
                [$migrations['FirstMigration']],
                // Run list
                new ArrayCollection(array_values(array_reverse($migrations))),
                // Extras added list
                [get_class($migrations['DependentMigration'])],
            ],
        ];
    }
}
