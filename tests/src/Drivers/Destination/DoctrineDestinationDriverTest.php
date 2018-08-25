<?php

namespace DragoonBoots\A2B\Tests\Drivers\Destination;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\Destination\DoctrineDestinationDriver;
use DragoonBoots\A2B\Exception\BadUriException;
use League\Uri\Parser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class DoctrineDestinationDriverTest extends TestCase
{

    /**
     * @var Parser|MockObject
     */
    protected $uriParser;

    /**
     * @var EntityManagerInterface|MockObject
     */
    protected $em;

    /**
     * @var ObjectRepository|MockObject
     */
    protected $repo;

    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccess;

    /**
     * @var DataMigration
     */
    protected $definition;

    /**
     * @var DoctrineDestinationDriver
     */
    protected $driver;

    public function testConfigure()
    {
        $this->setupDriver();

        $this->driver->configure($this->definition);

        $this->assertSame($this->em, $this->driver->getEm());
    }

    protected function setupDriver(string $destination = '/DragoonBoots/A2B/Tests/Drivers/Destination/TestEntity')
    {
        $destinationUrl = 'doctrine://'.$destination;
        $this->definition = new DataMigration(
            [
                'destination' => $destinationUrl,
                'destinationIds' => [new IdField(['name' => 'id'])],
            ]
        );

        $this->uriParser = $this->createMock(Parser::class);
        $this->uriParser->expects($this->once())
            ->method('parse')
            ->with($destinationUrl)
            ->willReturn(['path' => $destination]);

        $this->repo = $this->createMock(ObjectRepository::class);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('getRepository')
            ->with('\\'.TestEntity::class)
            ->willReturn($this->repo);

        $this->propertyAccess = PropertyAccess::createPropertyAccessor();

        $this->driver = new DoctrineDestinationDriver($this->uriParser, $this->em, $this->propertyAccess);
    }

    public function testConfigureBad()
    {
        $this->setupDriver('/Nonexistent/Entity');
        $this->expectException(BadUriException::class);
        $this->driver->configure($this->definition);
    }

    public function testGetExistingIds()
    {
        $this->setupDriver();

        $expected = [
            ['id' => 1],
            ['id' => 2],
        ];
        $entities = [
            (new TestEntity())
                ->setId(1),
            (new TestEntity())
                ->setId(2),
        ];
        $this->repo->expects($this->once())
            ->method('findAll')
            ->willReturn($entities);

        $this->driver->configure($this->definition);
        $this->assertEquals($expected, $this->driver->getExistingIds());
    }

    public function testGetRepo()
    {
        $this->setupDriver();
        $this->driver->configure($this->definition);

        $this->em->expects($this->once())
            ->method('getRepository')
            ->with('\\'.TestEntity::class)
            ->willReturn($this->repo);

        $this->assertSame($this->repo, $this->driver->getRepo());
    }

    /**
     * @param int         $id
     * @param null|object $expected
     *
     * @dataProvider readDataProvider
     */
    public function testRead(int $id, ?object $expected)
    {
        $this->setupDriver();
        $this->driver->configure($this->definition);

        $this->repo->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $id])
            ->willReturn($expected);

        $this->assertEquals($expected, $this->driver->read(['id' => $id]));
    }

    public function readDataProvider()
    {
        return [
            'exists' => [1, (new TestEntity())->setId(1)],
            'nonexistent' => [99, null],
        ];
    }

    public function testWrite()
    {
        $expected = [
            'id' => 1,
        ];

        $this->setupDriver();
        $this->driver->configure($this->definition);

        $entity = (new TestEntity())
            ->setField1('Test')
            ->setField2('Data');

        $this->em->expects($this->once())
            ->method('persist')
            ->with($entity)
            ->willReturnCallback(
                function (TestEntity $entity) {
                    $entity->setId(1);
                }
            );

        $this->assertEquals($expected, $this->driver->write($entity));
    }

    public function testFlush()
    {
        $this->setupDriver();
        $this->driver->configure($this->definition);

        $this->em->expects($this->once())
            ->method('flush');

        $this->driver->flush();
    }

    public function testSetEm()
    {
        $this->setupDriver();
        $this->driver->configure($this->definition);
        $this->assertSame($this->em, $this->driver->getEm());

        $newEm = $this->createMock(EntityManagerInterface::class);
        $newRepo = $this->createMock(ObjectRepository::class);
        $newEm->expects($this->once())
            ->method('getRepository')
            ->with('\\'.TestEntity::class)
            ->willReturn($newRepo);

        $this->driver->setEm($newEm);
        $this->assertSame($newEm, $this->driver->getEm());
        $this->assertSame($newRepo, $this->driver->getRepo());
    }
}

class TestEntity
{

    protected $id;

    protected $field1;

    protected $field2;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getField1()
    {
        return $this->field1;
    }

    /**
     * @param mixed $field1
     *
     * @return self
     */
    public function setField1($field1)
    {
        $this->field1 = $field1;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getField2()
    {
        return $this->field2;
    }

    /**
     * @param mixed $field2
     *
     * @return self
     */
    public function setField2($field2)
    {
        $this->field2 = $field2;

        return $this;
    }

}
