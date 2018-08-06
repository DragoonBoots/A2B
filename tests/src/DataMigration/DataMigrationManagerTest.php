<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManager;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DataMigrationManagerTest extends TestCase
{

    public function testAddMigration()
    {
        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $annotation = new DataMigration();
        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->expects($this->once())
            ->method('getClassAnnotation')
            ->with(new \ReflectionClass($migration), DataMigration::class)
            ->willReturn($annotation);

        $dataMigrationManager = new DataMigrationManager($annotationReader);
        $dataMigrationManager->addMigration($migration);
        $this->assertEquals(new ArrayCollection(['TestMigration' => $migration]), $dataMigrationManager->getMigrations());
        $this->assertSame($migration, $dataMigrationManager->getMigration(get_class($migration)));
    }

    public function testGetMigrationBad()
    {
        $annotationReader = $this->createMock(Reader::class);

        $dataMigrationManager = new DataMigrationManager($annotationReader);
        $this->expectException(NonexistentMigrationException::class);
        $dataMigrationManager->getMigration('NonexistantMigration');
    }

    public function testGetMigrationsInGroup()
    {
        /** @var DataMigrationInterface[]|MockObject[] $migrations */
        $migrations = [
            'Group1' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('Group1Migration')
                ->getMock(),
            'Group2' => $this->getMockBuilder(DataMigrationInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('Group2Migration')
                ->getMock(),
        ];
        /** @var DataMigration[] $definitions */
        $definitions = [
            'Group1' => new DataMigration(['group' => 'Group1']),
            'Group2' => new DataMigration(['group' => 'Group2']),
        ];
        foreach ($migrations as $group => $migration) {
            $migration->method('getDefinition')
                ->willReturn($definitions[$group]);
        }
        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->method('getClassAnnotation')
            ->willReturnCallback(
                function (\ReflectionClass $reflectionClass, string $annotationName) use ($definitions) {
                    if ($reflectionClass->getName() == 'Group1Migration') {
                        return $definitions['Group1'];
                    } elseif ($reflectionClass->getName() == 'Group2Migration') {
                        return $definitions['Group2'];
                    }

                    return null;
                }
            );

        $dataMigrationManager = new DataMigrationManager($annotationReader);
        foreach ($migrations as $migration) {
            $dataMigrationManager->addMigration($migration);
        }

        $expected = new ArrayCollection(['Group1Migration' => $migrations['Group1']]);
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

        $dataMigrationManager = new DataMigrationManager($annotationReader);
        foreach ($migrations as $migration) {
            $dataMigrationManager->addMigration($migration);
        }

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
