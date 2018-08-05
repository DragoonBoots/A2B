<?php

namespace DragoonBoots\A2B\Tests\Drivers;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManager;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoDriverForSchemeException;
use DragoonBoots\A2B\Exception\NonexistentDriverException;
use DragoonBoots\A2B\Exception\UnclearDriverException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DriverManagerTest extends TestCase
{

    const DRIVER_SCHEME = 'testscheme';

    public function testGetSourceDriver()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupSourceDriver($driverManager, $driverStub, $driverId);

        $this->assertEquals(
            $driverStub,
            $driverManager->getSourceDriver($driverId)
        );
    }

    /**
     * Setup the source driver in the manager.
     *
     * All parameters will be filled.
     *
     * @param DriverManagerInterface|null                           $driverManager
     * @param SourceDriverInterface|DestinationDriverInterface|null $driverStub
     * @param string|null                                           $driverId
     * @param Driver|null                                           $definition
     */
    protected function setupSourceDriver(&$driverManager = null, &$driverStub = null, &$driverId = null, &$definition = null): void
    {
        $this->setupDriver(SourceDriverInterface::class, $driverManager, $driverStub, $driverId, $definition);
    }

    /**
     * @param string                                                $driverInterface
     *   Either SourceDriverInterface::class or DestinationDriverInterface::class.
     * @param DriverManagerInterface|null                           $driverManager
     * @param SourceDriverInterface|DestinationDriverInterface|null $driverStub
     * @param string|null                                           $driverId
     * @param Driver|null                                           $definition
     *
     * @throws \Exception
     */
    protected function setupDriver(string $driverInterface, &$driverManager = null, &$driverStub = null, &$driverId = null, &$definition = null): void
    {

        $definition = new Driver(['value' => self::DRIVER_SCHEME]);
        $driverManager = new DriverManager($this->getAnnotationReader($definition));
        switch ($driverInterface) {
            case SourceDriverInterface::class:
                $driverId = 'TestSourceDriver';
                $addCallable = [$driverManager, 'addSourceDriver'];
                break;
            case DestinationDriverInterface::class:
                $driverId = 'TestDestinationDriver';
                $addCallable = [$driverManager, 'addDestinationDriver'];
                break;
            default:
                throw new \Exception('Wrong driver interface used.');
        }
        $driverStub = $this->getMockBuilder($driverInterface)
            ->setMockClassName($driverId)
            ->getMock();
        $driverStub->method('getDefinition')
            ->willReturn($definition);
        $addCallable($driverStub);
    }

    /**
     * Get a stub annotation reader.
     *
     * This reader contains a driver that provides the scheme in the constant
     * DRIVER_SCHEME.
     *
     * @param Driver|null $definition
     *   The driver annotation the reader will return.  If one is not passed, a
     *   default will be used.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|Reader
     */
    protected function getAnnotationReader(?Driver $definition = null)
    {
        if (!isset($definition)) {
            $definition = new Driver(['value' => self::DRIVER_SCHEME]);
        }
        $readerStub = $this->getMockBuilder(AnnotationReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $readerStub->expects($this->once())
            ->method('getClassAnnotation')
            ->willReturn($definition);

        return $readerStub;
    }

    public function testGetSourceDriverBad()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupSourceDriver($driverManager);

        $this->expectException(NonexistentDriverException::class);
        $driverManager->getSourceDriver('NonexistentDriver');
    }

    public function testGetSourceDriverForScheme()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupSourceDriver($driverManager, $driverStub);

        $this->assertEquals(
            $driverStub,
            $driverManager->getSourceDriverForScheme(self::DRIVER_SCHEME)
        );
    }

    public function testGetSourceDriverForSchemeBad()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupSourceDriver($driverManager);

        $this->expectException(NoDriverForSchemeException::class);
        $driverManager->getSourceDriverForScheme('noscheme');
    }

    public function testGetSourceDriverForSchemeMultiple()
    {
        $definition = new Driver(['value' => self::DRIVER_SCHEME]);
        /** @var SourceDriverInterface[]|DestinationDriverInterface[]|MockObject[] $drivers */
        $drivers = [
            $this->getMockBuilder(SourceDriverInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('TestSourceDriver1')
                ->getMock(),
            $this->getMockBuilder(SourceDriverInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('TestSourceDriver2')
                ->getMock(),
        ];
        foreach ($drivers as $driver) {
            $driver->method('getDefinition')
                ->willReturn($definition);
        }
        $annotationReader = $this->createMock(AnnotationReader::class);
        $annotationReader->expects($this->exactly(count($drivers)))
            ->method('getClassAnnotation')
            ->willReturn($definition);
        $driverManager = new DriverManager($annotationReader);
        foreach ($drivers as $driver) {
            $driverManager->addSourceDriver($driver);
        }

        $this->expectException(UnclearDriverException::class);
        $driverManager->getSourceDriverForScheme(self::DRIVER_SCHEME);
    }

    public function testGetDestinationDriverForScheme()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupDestinationDriver($driverManager, $driverStub);

        $this->assertEquals(
            $driverStub,
            $driverManager->getDestinationDriverForScheme(self::DRIVER_SCHEME)
        );
    }

    /**
     * Setup the destination driver in the manager.
     *
     * All parameters will be filled.
     *
     * @param $driverManager
     * @param $driverStub
     * @param $driverId
     * @param $definition
     */
    protected function setupDestinationDriver(&$driverManager = null, &$driverStub = null, &$driverId = null, &$definition = null): void
    {
        $this->setupDriver(DestinationDriverInterface::class, $driverManager, $driverStub, $driverId, $definition);
    }

    public function testGetDestinationDriverForSchemeMultiple()
    {
        $definition = new Driver(['value' => self::DRIVER_SCHEME]);
        /** @var SourceDriverInterface[]|DestinationDriverInterface[]|MockObject[] $drivers */
        $drivers = [
            $this->getMockBuilder(DestinationDriverInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('TestDestinationDriver1')
                ->getMock(),
            $this->getMockBuilder(DestinationDriverInterface::class)
                ->disableOriginalConstructor()
                ->setMockClassName('TestDestinationDriver2')
                ->getMock(),
        ];
        foreach ($drivers as $driver) {
            $driver->method('getDefinition')
                ->willReturn($definition);
        }
        $annotationReader = $this->createMock(AnnotationReader::class);
        $annotationReader->expects($this->exactly(count($drivers)))
            ->method('getClassAnnotation')
            ->willReturn($definition);
        $driverManager = new DriverManager($annotationReader);
        foreach ($drivers as $driver) {
            $driverManager->addDestinationDriver($driver);
        }

        $this->expectException(UnclearDriverException::class);
        $driverManager->getDestinationDriverForScheme(self::DRIVER_SCHEME);
    }

    public function testGetSourceDrivers()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupSourceDriver($driverManager, $driverStub);

        $this->assertContains(
            $driverStub,
            $driverManager->getSourceDrivers()
        );
    }

    public function testAddDestinationDriver()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupDestinationDriver($driverManager, $driverStub);

        $this->assertContains(
            $driverStub,
            $driverManager->getDestinationDrivers()
        );
        $this->assertEmpty($driverManager->getSourceDrivers());
    }

    public function testGetDestinationDriver()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupDestinationDriver($driverManager, $driverStub, $driverId);

        $this->assertEquals(
            $driverStub,
            $driverManager->getDestinationDriver($driverId)
        );
    }

    public function testGetDestinationDriverBad()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupDestinationDriver($driverManager);

        $this->expectException(NonexistentDriverException::class);
        $driverManager->getDestinationDriver('NonexistentDriver');
    }

    public function testAddSourceDriver()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupSourceDriver($driverManager, $driverStub);

        $this->assertContains(
            $driverStub,
            $driverManager->getSourceDrivers()
        );
        $this->assertEmpty($driverManager->getDestinationDrivers());
    }

    public function testGetDestinationDrivers()
    {
        /** @var DriverManagerInterface $driverManager */
        $this->setupDestinationDriver($driverManager, $driverStub);

        $this->assertContains(
            $driverStub,
            $driverManager->getDestinationDrivers()
        );
    }
}
