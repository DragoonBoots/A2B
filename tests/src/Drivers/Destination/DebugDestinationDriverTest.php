<?php

namespace DragoonBoots\A2B\Tests\Drivers\Destination;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Drivers\Destination\DebugDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class DebugDestinationDriverTest extends TestCase
{

    use VarDumperTestTrait;

    public function testRead()
    {
        $dumper = $this->createMock(AbstractDumper::class);
        $cloner = $this->createMock(ClonerInterface::class);
        $driver = new DebugDestinationDriver($dumper, $cloner);

        // The debug destination never has a current entity.
        $this->assertNull($driver->read(['id' => 1]));
    }

    public function testReadMultiple()
    {
        $dumper = $this->createMock(AbstractDumper::class);
        $cloner = $this->createMock(ClonerInterface::class);
        $driver = new DebugDestinationDriver($dumper, $cloner);

        $this->assertEquals(
            [],
            $driver->readMultiple(
                [
                    ['id' => 1],
                    ['id' => 2],
                ]
            )
        );
    }

    /**
     * @param string $destination
     *
     * @dataProvider pathDataProvider
     */
    public function testWrite(string $destination)
    {
        $data = [
            'field0' => 'DebugDestinationDriver',
            'field1' => 'Test',
            'field2' => 'Case',
        ];
        $definition = new DataMigration(
            ['destination' => $destination]
        );
        $driver = new DebugDestinationDriver(new CliDumper(), new VarCloner());
        $driver->configure($definition);

        $driver->write($data);
        $this->assertDumpEquals($data, $data);
    }

    public function pathDataProvider()
    {
        return [
            'stdout' => [
                'stdout',
                STDOUT,
            ],
            'stderr' => [
                'stderr',
                STDERR,
            ],
        ];
    }

    /**
     * @param string   $destination
     *
     * @param resource $stream
     *
     * @dataProvider pathDataProvider
     */
    public function testConfigure(string $destination, $stream)
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($destination, $stream, $driver, $definition);

        $driver->configure($definition);
    }

    /**
     * @param string                          $destination
     * @param resource                        $stream
     * @param DestinationDriverInterface|null $driver
     * @param DataMigration|null              $definition
     */
    protected function setupDriver(string $destination, $stream, ?DestinationDriverInterface &$driver = null, ?DataMigration &$definition = null): void
    {
        $dumper = $this->createMock(AbstractDumper::class);
        $dumper->expects($stream ? $this->once() : $this->never())
            ->method('setOutput')
            ->with($stream);
        $cloner = $this->createMock(ClonerInterface::class);
        $definition = new DataMigration(
            ['destination' => $destination]
        );
        $driver = new DebugDestinationDriver($dumper, $cloner);
    }

    public function testGetExistingIds()
    {
        $dumper = $this->createMock(AbstractDumper::class);
        $cloner = $this->createMock(ClonerInterface::class);
        $driver = new DebugDestinationDriver($dumper, $cloner);

        $this->assertEquals([], $driver->getExistingIds());
    }

    public function testConfigureBad()
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver('badstream', null, $driver, $definition);

        $this->expectException(BadUriException::class);
        $driver->configure($definition);
    }

    public function testFlush()
    {
        $dumper = $this->createMock(AbstractDumper::class);
        $cloner = $this->createMock(ClonerInterface::class);
        $preFlushDriver = new DebugDestinationDriver($dumper, $cloner);

        // Smoke test the flush operation; it should do nothing in this driver.
        $postFlushDriver = clone $preFlushDriver;
        $postFlushDriver->flush();
        $this->assertEquals($preFlushDriver, $postFlushDriver);
    }
}
