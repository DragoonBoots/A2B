<?php

namespace DragoonBoots\A2B\Tests\Drivers\Destination;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Drivers\Destination\DebugDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use League\Uri\Parser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class DebugDestinationDriverTest extends TestCase
{

    use VarDumperTestTrait;

    public function testGetCurrentEntity()
    {
        $uriParser = $this->createMock(Parser::class);
        $dumper = $this->createMock(AbstractDumper::class);
        $cloner = $this->createMock(ClonerInterface::class);
        $driver = new DebugDestinationDriver($uriParser, $dumper, $cloner);

        // The debug destination never has a current entity.
        $this->assertNull($driver->getCurrentEntity(['id' => 1]));
    }

    /**
     * @param string $destination
     * @param string $path
     *
     * @dataProvider pathDataProvider
     */
    public function testWrite(string $destination, string $path)
    {
        $uriParser = $this->createMock(Parser::class);
        $uriParser->method('parse')
            ->with($destination)
            ->willReturn(['path' => $path]);
        $data = [
            'field0' => 'DebugDestinationDriver',
            'field1' => 'Test',
            'field2' => 'Case',
        ];
        $definition = new DataMigration();
        $definition->destination = $destination;
        $driver = new DebugDestinationDriver($uriParser, new CliDumper(), new VarCloner());
        $driver->configure($definition);

        $driver->write($data);
        $this->assertDumpEquals($data, $data);
    }

    public function pathDataProvider()
    {
        return [
            'stdout' => [
                'debug:stdout',
                'stdout',
                STDOUT,
            ],
            'stderr' => [
                'debug:stderr',
                'stderr',
                STDERR,
            ],
        ];
    }

    /**
     * @param string   $destination
     * @param string   $path
     *
     * @param resource $stream
     *
     * @dataProvider pathDataProvider
     */
    public function testConfigure(string $destination, string $path, $stream)
    {
        $this->setupDriver($destination, $path, $stream, $driver, $definition);

        $driver->configure($definition);
    }

    /**
     * @param string                          $destination
     * @param string                          $path
     * @param resource                        $stream
     * @param DestinationDriverInterface|null $driver
     * @param DataMigration|null              $definition
     */
    protected function setupDriver(string $destination, string $path, $stream, ?DestinationDriverInterface &$driver = null, ?DataMigration &$definition = null): void
    {
        $uriParser = $this->createMock(Parser::class);
        $uriParser->method('parse')
            ->with($destination)
            ->willReturn(['path' => $path]);
        $dumper = $this->createMock(AbstractDumper::class);
        $dumper->expects($stream ? $this->once() : $this->never())
            ->method('setOutput')
            ->with($stream);
        $cloner = $this->createMock(ClonerInterface::class);
        $definition = new DataMigration();
        $definition->destination = $destination;
        $driver = new DebugDestinationDriver($uriParser, $dumper, $cloner);
    }

    public function testConfigureBad()
    {
        $this->setupDriver('debug:badstream', 'badstream', null, $driver, $definition);

        $this->expectException(BadUriException::class);
        $driver->configure($definition);
    }
}
