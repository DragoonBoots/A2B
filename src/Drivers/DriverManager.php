<?php


namespace DragoonBoots\A2B\Drivers;


use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Exception\NonexistentDriverException;
use ReflectionClass;
use ReflectionException;

class DriverManager implements DriverManagerInterface
{

    /**
     * @var Reader
     */
    protected $annotationReader;

    /**
     * @var Collection|SourceDriverInterface[]
     */
    protected $sourceDrivers;

    /**
     * @var Collection|DestinationDriverInterface[]
     */
    protected $destinationDrivers;

    /**
     * DriverManager constructor.
     *
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;

        $this->sourceDrivers = new ArrayCollection();
        $this->destinationDrivers = new ArrayCollection();
    }

    /**
     * Add source driver
     *
     * @param SourceDriverInterface $sourceDriver
     *
     * @throws ReflectionException
     * @internal
     *
     */
    public function addSourceDriver(SourceDriverInterface $sourceDriver)
    {
        $reflClass = new ReflectionClass($sourceDriver);
        /** @var Driver $definition */
        $definition = $this->annotationReader->getClassAnnotation($reflClass, Driver::class);
        $sourceDriver->setDefinition($definition);
        $this->sourceDrivers[get_class($sourceDriver)] = $sourceDriver;
    }

    /**
     * Add destination driver
     *
     * @param DestinationDriverInterface $destinationDriver
     *
     * @throws ReflectionException
     * @internal
     *
     */
    public function addDestinationDriver(DestinationDriverInterface $destinationDriver)
    {
        $reflClass = new ReflectionClass($destinationDriver);
        /** @var Driver $definition */
        $definition = $this->annotationReader->getClassAnnotation($reflClass, Driver::class);
        $destinationDriver->setDefinition($definition);
        $this->destinationDrivers[get_class($destinationDriver)] = $destinationDriver;
    }

    public function getSourceDrivers(): Collection
    {
        return $this->sourceDrivers;
    }

    public function getSourceDriver(string $driverName): SourceDriverInterface
    {
        if (!$this->sourceDrivers->containsKey($driverName)) {
            throw new NonexistentDriverException($driverName);
        }

        return $this->sourceDrivers[$driverName];
    }

    public function getDestinationDrivers(): Collection
    {
        return $this->destinationDrivers;
    }

    public function getDestinationDriver(string $driverName): DestinationDriverInterface
    {
        if (!$this->destinationDrivers->containsKey($driverName)) {
            throw new NonexistentDriverException($driverName);
        }

        return $this->destinationDrivers[$driverName];
    }
}
