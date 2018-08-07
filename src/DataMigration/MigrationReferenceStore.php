<?php


namespace DragoonBoots\A2B\DataMigration;


use Doctrine\Common\Collections\ArrayCollection;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;

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
    public function get(string $migrationId, array $sourceIds)
    {
        $key = serialize([$migrationId, $sourceIds]);

        if (!isset($this->entities[$key])) {
            $migrationDefinition = $this->migrationManager->getMigration($migrationId)
                ->getDefinition();
            $destinationDriver = $this->driverManager->getDestinationDriver($migrationDefinition->getDestinationDriver());
            $destIds = $this->mapper->getDestIdsFromSourceIds($migrationId, $sourceIds);
            $entity = $destinationDriver->read($destIds);

            $this->entities[$key] = $entity;
        }

        return $this->entities[$key];
    }
}
