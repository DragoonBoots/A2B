<?php

namespace DragoonBoots\A2B\Tests\DataMigration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\Stubber;
use DragoonBoots\A2B\DataMigration\StubberInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class StubberTest extends TestCase
{

    /**
     * @var PropertyAccessor|MockObject
     */
    protected $propertyAccess;

    /**
     * @var EntityManagerInterface|MockObject
     */
    protected $em;

    /**
     * @var StubberInterface
     */
    protected $stubber;

    /**
     * @var ClassMetadata|MockObject
     */
    protected $classMetadata;

    public function testCreateStub()
    {
        $this->setupStubber();

        $defaultResult = new \stdClass();
        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $migration->expects($this->once())
            ->method('defaultResult')
            ->willReturn($defaultResult);

        $this->classMetadata->expects($this->once())
            ->method('setFieldValue')
            ->with($defaultResult, 'testField', $this->anything());

        $this->stubber->createStub($migration);
    }

    protected function setupStubber()
    {
        $this->propertyAccess = $this->createMock(PropertyAccessor::class);

        $this->classMetadata = $classMetadata = $this->createMock(ClassMetadata::class);
        $this->classMetadata->method('getFieldNames')
            ->willReturn(['id', 'testField', 'nullableField']);
        $this->classMetadata->method('getIdentifierFieldNames')
            ->willReturn(['id']);
        $this->classMetadata->method('getFieldMapping')
            ->willReturnMap(
                [
                    ['testField', ['nullable' => false]],
                    ['nullableField', ['nullable' => true]],
                ]
            );
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($this->classMetadata);

        $this->stubber = new Stubber($this->propertyAccess, $this->em);
    }
}
