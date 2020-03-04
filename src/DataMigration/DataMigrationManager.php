<?php


namespace DragoonBoots\A2B\DataMigration;


use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Exception\MigrationException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use MJS\TopSort\Implementations\FixedArraySort;
use MJS\TopSort\TopSortInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DataMigrationManager implements DataMigrationManagerInterface
{

    /**
     * @var Reader
     */
    protected $annotationReader;

    /**
     * @var DriverManagerInterface
     */
    protected $driverManager;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var Collection|DataMigrationInterface[]
     */
    protected $migrations;

    /**
     * @var Collection|string[]
     */
    protected $groups;

    /**
     * @var string[]
     */
    protected $sources;

    /**
     * @var string[]
     */
    protected $destinations;

    /**
     * DataMigrationManager constructor.
     *
     * @param Reader                 $annotationReader
     * @param DriverManagerInterface $driverManager
     * @param ParameterBagInterface  $parameterBag
     */
    public function __construct(
        Reader $annotationReader,
        DriverManagerInterface $driverManager,
        ParameterBagInterface $parameterBag
    ) {
        $this->annotationReader = $annotationReader;
        $this->driverManager = $driverManager;
        $this->parameterBag = $parameterBag;

        $this->migrations = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

    /**
     * Add a source key
     *
     * @param string $name
     * @param string $uri
     * @param string $driver
     *
     * @internal
     */
    public function addSource(string $name, string $uri, string $driver)
    {
        if (!isset($this->sources[$name])) {
            $this->sources[$name] = ['uri' => $uri, 'driver' => $driver];
        }
    }

    /**
     * Add a destination key
     *
     * @param string $name
     * @param string $uri
     * @param string $driver
     *
     * @internal
     */
    public function addDestination(string $name, string $uri, string $driver)
    {
        if (!isset($this->destinations[$name])) {
            $this->destinations[$name] = ['uri' => $uri, 'driver' => $driver];
        }
    }

    /**
     * Add a new migration
     *
     * @param DataMigrationInterface $migration
     *
     * @throws \DragoonBoots\A2B\Exception\NoDriverForSchemeException
     * @throws \DragoonBoots\A2B\Exception\UnclearDriverException
     * @throws \ReflectionException
     * @internal
     *
     */
    public function addMigration(DataMigrationInterface $migration)
    {
        $reflClass = new \ReflectionClass($migration);
        /** @var DataMigration $definition */
        $definition = $this->annotationReader->getClassAnnotation($reflClass, DataMigration::class);
        // Lookup the source/destination key if applicable
        foreach (['source', 'destination'] as $propertyName) {
            $this->resolveDefinitionProperty($definition, $propertyName);
        }

        // Canonicalize the driver names
        $sourceDriver = $this->driverManager->getSourceDriver($definition->getSourceDriver());
        $definition->setSourceDriver(get_class($sourceDriver));
        $destinationDriver = $this->driverManager->getDestinationDriver($definition->getDestinationDriver());
        $definition->setDestinationDriver(get_class($destinationDriver));

        $migration->setDefinition($definition);
        $this->migrations[get_class($migration)] = $migration;
        $this->groups[$definition->getGroup()] = $definition->getGroup();
    }

    /**
     * Resolve the migration definition property into something usable.
     *
     * This will
     * - Lookup the property in the appropriate key map and substitute the found
     *   value if it exists
     * - Resolve parameters contained within
     *
     * @param DataMigration $definition
     * @param string        $propertyName
     *
     * @throws \ReflectionException
     */
    private function resolveDefinitionProperty(DataMigration $definition, string $propertyName)
    {
        $valueGetters = [
            'source' => [$definition, 'getSource'],
            'destination' => [$definition, 'getDestination'],
        ];
        $driverGetters = [
            'source' => [$definition, 'getSourceDriver'],
            'destination' => [$definition, 'getDestinationDriver'],
        ];
        $keyMaps = [
            'source' => $this->sources,
            'destination' => $this->destinations,
        ];

        $value = call_user_func($valueGetters[$propertyName]);
        $driver = call_user_func($driverGetters[$propertyName]);
        $keyMap = $keyMaps[$propertyName];

        if (isset($keyMap[$value])) {
            $key = $value;
            $value = $keyMap[$key]['uri'];
            $driver = $keyMap[$key]['driver'];
        }
        $value = $this->parameterBag->resolveValue($value);

        $this->injectProperty($definition, $propertyName, $value);
        $this->injectProperty($definition, $propertyName.'Driver', $driver);
    }

    /**
     * Inject a value into an object property with reflection.
     *
     * @param object $object
     * @param string $propertyName
     * @param mixed  $value
     *
     * @throws \ReflectionException
     */
    private function injectProperty(object $object, string $propertyName, $value)
    {
        $refl = new \ReflectionClass($object);
        $property = $refl->getProperty($propertyName);
        $accessible = $property->isPublic();
        $property->setAccessible(true);
        $property->setValue($object, $value);
        $property->setAccessible($accessible);
    }

    public function getMigrations(): Collection
    {
        return $this->migrations;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    /**
     * @param string $migrationName
     *
     * @return DataMigrationInterface|mixed
     * @throws MigrationException
     * @throws NonexistentMigrationException
     * @throws \ReflectionException
     */
    public function getMigration(string $migrationName)
    {
        if (!$this->migrations->containsKey($migrationName)) {
            throw new NonexistentMigrationException($migrationName);
        }

        $migration = $this->migrations[$migrationName];
        $definition = $migration->getDefinition();
        if (!is_null($definition->getExtends()) && !is_a($definition->getExtends(), DataMigrationInterface::class)) {
            // Inject the migration into the definition.  This is done here
            // to better guarantee the referenced migration is known by the
            // manager already.
            $extendsMigration = $this->getMigration($definition->getExtends());
            $extendsMigrationDefinition = $extendsMigration->getDefinition();
            $isCompatibleMigrationExtension = $definition->getSource() === $extendsMigrationDefinition->getSource()
                && $definition->getSourceIds() == $extendsMigrationDefinition->getSourceIds()
                && $definition->getDestination() === $extendsMigrationDefinition->getDestination()
                && $definition->getDestinationIds() == $extendsMigrationDefinition->getDestinationIds();
            if ($isCompatibleMigrationExtension) {
                $this->injectProperty($definition, 'extends', $extendsMigration);
            } else {
                throw new MigrationException(sprintf('"%s" cannot extend "%s" because their definitions do not match.', get_class($migration), get_class($extendsMigration)));
            }
        }

        return $migration;
    }

    public function getMigrationsInGroup(string $groupName)
    {
        $migrations = $this->migrations->filter(
            function (DataMigrationInterface $migration) use ($groupName) {
                $definition = $this->getMigration(get_class($migration))
                    ->getDefinition();

                return $definition->getGroup() == $groupName;
            }
        );

        return $migrations;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveDependencies(iterable $migrations, ?array &$extrasAdded = null): Collection
    {
        if (!isset($extrasAdded)) {
            $extrasAdded = [];
        }

        // Track requested migrations so these don't appear as extras added to
        // satisfy dependencies.
        $requestedMigrations = [];
        foreach ($migrations as $migration) {
            $requestedMigrations[] = get_class($migration);
        }

        $sorter = new FixedArraySort();
        $this->buildDependencyList($migrations, $extrasAdded, $sorter);
        $runList = $sorter->sort();

        $runMigrations = new ArrayCollection();
        foreach ($runList as $migrationId) {
            $runMigrations->add($this->getMigration($migrationId));
        }

        $extrasAdded = array_unique($extrasAdded);
        $extrasAdded = array_diff($extrasAdded, $requestedMigrations);
        $extrasAdded = array_values($extrasAdded);

        return $runMigrations;
    }

    /**
     * Recursively build the dependency list.
     *
     * @param iterable              $migrations
     * @param array                 $extrasAdded
     * @param TopSortInterface|null $sorter
     *
     * @throws NonexistentMigrationException
     */
    protected function buildDependencyList(iterable $migrations, array &$extrasAdded, TopSortInterface &$sorter)
    {
        foreach ($migrations as $migration) {
            if (!is_a($migration, DataMigrationInterface::class)) {
                if (is_string($migration)) {
                    $migration = $this->getMigration($migration);
                } else {
                    throw new \UnexpectedValueException("$migration is not a DataMigration instance.");
                }
            }

            $definition = $migration->getDefinition();
            $dependencies = $definition->getDepends();
            $this->buildDependencyList($dependencies, $extrasAdded, $sorter);
            foreach ($dependencies as $dependency) {
                $extrasAdded[] = $dependency;
            }
            $sorter->add(get_class($migration), $dependencies);
        }
    }
}
