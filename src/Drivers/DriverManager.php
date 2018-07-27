<?php


namespace DragoonBoots\A2B\Drivers;


use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Exception\NoDriverForSchemeException;
use DragoonBoots\A2B\Exception\NonexistentDriverException;
use DragoonBoots\A2B\Exception\UnclearDriverException;

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
     * @var Collection|Driver[]
     */
    protected $sourceDriverDefinitions;

    /**
     * @var Collection|Driver[]
     */
    protected $destinationDriverDefinitions;

    /**
     * DriverManager constructor.
     *
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;

        $this->sourceDrivers = new ArrayCollection();
        $this->sourceDriverDefinitions = new ArrayCollection();
        $this->destinationDrivers = new ArrayCollection();
        $this->destinationDriverDefinitions = new ArrayCollection();
    }

    /**
     * Add source driver
     *
     * @internal
     *
     * @param SourceDriverInterface $sourceDriver
     *
     * @throws \ReflectionException
     */
    public function addSourceDriver(SourceDriverInterface $sourceDriver)
    {
        $this->sourceDrivers[get_class($sourceDriver)] = $sourceDriver;
        $reflClass = new \ReflectionClass($sourceDriver);
        $this->sourceDriverDefinitions[get_class($sourceDriver)] = $this->annotationReader->getClassAnnotation($reflClass, Driver::class);
    }

    /**
     * Add destination driver
     *
     * @internal
     *
     * @param DestinationDriverInterface $destinationDriver
     *
     * @throws \ReflectionException
     */
    public function addDestinationDriver(DestinationDriverInterface $destinationDriver)
    {
        $this->destinationDrivers[get_class($destinationDriver)] = $destinationDriver;
        $reflClass = new \ReflectionClass($destinationDriver);
        $this->destinationDriverDefinitions[get_class($destinationDriver)] = $this->annotationReader->getClassAnnotation($reflClass, Driver::class);
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

    public function getSourceDriverDefinition(string $driverName): Driver
    {
        return $this->getDriverDefinition($driverName, $this->sourceDriverDefinitions);
    }

    /**
     * Get the driver definition from the given list of definitions.
     *
     * @param string     $driverName
     * @param Collection $driverDefinitions
     *
     * @return Driver
     *
     * @throws NonexistentDriverException
     */
    protected function getDriverDefinition(string $driverName, Collection $driverDefinitions): Driver
    {
        if (!$driverDefinitions->containsKey($driverName)) {
            throw new NonexistentDriverException($driverName);
        }

        return $driverDefinitions[$driverName];
    }

    public function getSourceDriverForScheme(string $scheme): SourceDriverInterface
    {
        return $this->getDriverForScheme($scheme, $this->sourceDrivers, $this->sourceDriverDefinitions);
    }

    /**
     * Get the driver from the given list that implements the given scheme.
     *
     * @param string     $scheme
     * @param Collection $drivers
     * @param Collection $driverDefinitions
     *
     * @return SourceDriverInterface|DestinationDriverInterface
     *
     * @throws NoDriverForSchemeException
     * @throws UnclearDriverException
     *   Thrown when more than one driver matches the given scheme.
     */
    protected function getDriverForScheme(string $scheme, Collection $drivers, Collection $driverDefinitions)
    {
        $useDrivers = $drivers->filter(
          function ($driver) use ($scheme, $driverDefinitions) {
              /** @var SourceDriverInterface|DestinationDriverInterface $driver */
              return in_array($scheme, $driverDefinitions[get_class($driver)]->value);
          }
        );

        if ($useDrivers->isEmpty()) {
            throw new NoDriverForSchemeException($scheme);
        }
        if ($useDrivers->count() > 1) {
            $driverNames = [];
            foreach ($useDrivers as $driver) {
                $driverNames[] = get_class($driver);
            }
            throw new UnclearDriverException($scheme, $driverNames);
        }

        return $useDrivers->first();
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

    public function getDestinationDriverDefinition(string $driverName): Driver
    {
        return $this->getDriverDefinition($driverName, $this->destinationDriverDefinitions);
    }

    public function getDestinationDriverForScheme(string $scheme): DestinationDriverInterface
    {
        return $this->getDriverForScheme($scheme, $this->destinationDrivers, $this->destinationDriverDefinitions);
    }

    public function getSourceDriverDefinitions(): Collection
    {
        return $this->sourceDriverDefinitions;
    }

    public function getDestinationDriverDefinitions(): Collection
    {
        return $this->destinationDriverDefinitions;
    }
}
