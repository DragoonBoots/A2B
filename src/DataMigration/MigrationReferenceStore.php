<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use Ds\Map;

class MigrationReferenceStore implements MigrationReferenceStoreInterface
{

    /**
     * @var DataMigrationMapperInterface
     */
    protected $mapper;

    /**
     * @var DataMigrationManagerInterface
     */
    protected $migrationManager;

    /**
     * @var DriverManagerInterface
     */
    protected $driverManager;

    /**
     * Cache retrieved entities.
     *
     * @var array
     */
    protected $entities;

    /**
     * MigrationReferenceStore constructor.
     *
     * @param DataMigrationMapperInterface  $mapper
     * @param DataMigrationManagerInterface $migrationManager
     * @param DriverManagerInterface        $driverManager
     */
    public function __construct(DataMigrationMapperInterface $mapper, DataMigrationManagerInterface $migrationManager, DriverManagerInterface $driverManager)
    {
        $this->mapper = $mapper;
        $this->migrationManager = $migrationManager;
        $this->driverManager = $driverManager;
        $this->entities = new Map();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $migrationId, array $sourceIds, bool $stub = false)
    {
        $key = [$migrationId, $sourceIds];

        if (!$this->entities->hasKey($key)) {
            $stubbed = false;

            $dataMigration = $this->migrationManager->getMigration($migrationId);
            $migrationDefinition = $dataMigration->getDefinition();
            $destinationDriver = clone ($this->driverManager->getDestinationDriver($migrationDefinition->getDestinationDriver()));
            $destinationDriver->configure($migrationDefinition);

            // If the driver does not support stubbing, disallow it even if
            // it has been requested.
            if (!$destinationDriver->getDefinition()->supportsStubs()) {
                $stub = false;
            }

            try {
                $destIds = $this->mapper->getDestIdsFromSourceIds($migrationId, $sourceIds);
                $entity = $destinationDriver->read($destIds);
            } catch (NoMappingForIdsException $e) {
                if ($stub) {
                    $entity = $this->mapper->createStub($dataMigration, $sourceIds);
                    $stubbed = true;
                } else {
                    throw $e;
                }
            }

            if (is_null($entity)) {
                if ($stub) {
                    $entity = $this->mapper->createStub($dataMigration, $sourceIds);
                    $stubbed = true;
                } else {
                    throw new NoMappingForIdsException($sourceIds, $migrationId);
                }
            }

            if (!$stubbed) {
                $this->entities->put($key, $entity);
            }

            unset($destinationDriver);
        } else {
            $entity = $this->entities->get($key);
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function freeMemory(): void
    {
        // Clear internal entity cache.
        $this->entities->clear();
    }
}
