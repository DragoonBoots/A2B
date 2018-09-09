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
     * @internal
     *
     * @param SourceDriverInterface $sourceDriver
     *
     * @throws \ReflectionException
     */
    public function addSourceDriver(SourceDriverInterface $sourceDriver)
    {
        $reflClass = new \ReflectionClass($sourceDriver);
        $definition = $this->annotationReader->getClassAnnotation($reflClass, Driver::class);
        $sourceDriver->setDefinition($definition);
        $this->sourceDrivers[get_class($sourceDriver)] = $sourceDriver;
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
        $reflClass = new \ReflectionClass($destinationDriver);
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

    public function getSourceDriverForScheme(string $scheme): SourceDriverInterface
    {
        return $this->getDriverForScheme($scheme, $this->sourceDrivers);
    }

    /**
     * Get the driver from the given list that implements the given scheme.
     *
     * @param string                                                          $scheme
     * @param SourceDriverInterface[]|DestinationDriverInterface[]|Collection $drivers
     *
     * @return SourceDriverInterface|DestinationDriverInterface
     *
     * @throws NoDriverForSchemeException
     * @throws UnclearDriverException
     *   Thrown when more than one driver matches the given scheme.
     */
    protected function getDriverForScheme(string $scheme, Collection $drivers)
    {
        $useDrivers = $drivers->filter(
            function ($driver) use ($scheme) {
                /** @var SourceDriverInterface|DestinationDriverInterface $driver */
                return in_array($scheme, $driver->getDefinition()->getSchemes());
          }
        );

        if ($useDrivers->isEmpty()) {
            throw new NoDriverForSchemeException($scheme);
        }
        if (count($useDrivers) > 1) {
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

    public function getDestinationDriverForScheme(string $scheme): DestinationDriverInterface
    {
        return $this->getDriverForScheme($scheme, $this->destinationDrivers);
    }
}
