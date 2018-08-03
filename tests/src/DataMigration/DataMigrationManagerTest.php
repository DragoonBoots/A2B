<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
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
        $annotations = [
            'Group1' => (new DataMigration())
                ->setGroup('Group1'),
            'Group2' => (new DataMigration())
                ->setGroup('Group2'),
        ];
        $annotationReader = $this->createMock(Reader::class);
        $annotationReader->method('getClassAnnotation')
            ->willReturnCallback(
                function (\ReflectionClass $reflectionClass, string $annotationName) use ($annotations) {
                    if ($reflectionClass->getName() == 'Group1Migration') {
                        return $annotations['Group1'];
                    } elseif ($reflectionClass->getName() == 'Group2Migration') {
                        return $annotations['Group2'];
                    }

                    return null;
                }
            );

        $dataMigrationManager = new DataMigrationManager($annotationReader);
        foreach ($migrations as $migration) {
            $dataMigrationManager->addMigration($migration);
        }

        $expected = new ArrayCollection(['Group1Migration' => $migrations['Group1']]);
        $this->assertArraySubset($expected, $dataMigrationManager->getMigrationsInGroup('Group1'));
    }
}
