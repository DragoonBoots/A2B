<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;

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
    protected $entities = [];

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
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $migrationId, array $sourceIds, bool $stub = false)
    {
        $key = serialize([$migrationId, $sourceIds]);

        if (!array_key_exists($key, $this->entities)) {
            $stubbed = false;

            $migrationDefinition = $this->migrationManager->getMigration($migrationId)
                ->getDefinition();
            $destinationDriver = clone ($this->driverManager->getDestinationDriver($migrationDefinition->getDestinationDriver()));
            $destinationDriver->configure($migrationDefinition);
            try {
                $destIds = $this->mapper->getDestIdsFromSourceIds($migrationId, $sourceIds);
                $entity = $destinationDriver->read($destIds);
            } catch (NoMappingForIdsException $e) {
                if ($stub) {
                    $entity = $this->mapper->createStub($migrationId, $sourceIds);
                    $stubbed = true;
                } else {
                    throw $e;
                }
            }

            if (is_null($entity)) {
                if ($stub) {
                    $entity = $this->mapper->createStub($migrationId, $sourceIds);
                    $stubbed = true;
                } else {
                    throw new NoMappingForIdsException($sourceIds, $migrationId);
                }
            }

            if (!$stubbed) {
                $this->entities[$key] = $entity;
            }

            unset($destinationDriver);
        } else {
            $entity = $this->entities[$key];
        }

        return $entity;
    }
}
