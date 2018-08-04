<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\OutputFormatter\OutputFormatterInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoIdSetException;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;

class DataMigrationExecutor implements DataMigrationExecutorInterface
{

    /**
     * @var DataMigrationMapperInterface
     */
    protected $mapper;

    /**
     * @var OutputFormatterInterface
     */
    protected $outputFormatter;

    /**
     * @var DataMigrationInterface
     */
    protected $migration;

    /**
     * @var SourceDriverInterface
     */
    protected $sourceDriver;

    /**
     * @var DestinationDriverInterface
     */
    protected $destinationDriver;

    /**
     * @var IdField[]
     */
    protected $sourceIds;

    /**
     * @var IdField[]
     */
    protected $destinationIds;

    /**
     * The number of rows migrated
     *
     * @var int
     */
    protected $rowCounter = 0;

    /**
     * DataMigrationExecutor constructor.
     *
     * @param DataMigrationMapperInterface $mapper
     */
    public function __construct(DataMigrationMapperInterface $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutputFormatter(OutputFormatterInterface $outputFormatter)
    {
        $this->outputFormatter = $outputFormatter;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(
        DataMigrationInterface $migration,
        SourceDriverInterface $sourceDriver,
        DestinationDriverInterface $destinationDriver
    ) {
        $definition = $migration->getDefinition();
        $this->migration = $migration;
        $this->sourceIds = $definition->getSourceIds();
        $this->sourceDriver = $sourceDriver;
        $this->destinationIds = $definition->getDestinationIds();
        $this->destinationDriver = $destinationDriver;

        $this->outputFormatter->start($migration, count($sourceDriver));
        $this->rowCounter = 0;
        $existingIds = $this->destinationDriver->getExistingIds();
        $newIds = [];
        foreach ($sourceDriver->getIterator() as $row) {
            $newIds[] = $this->executeRow($row);
        }
        $this->outputFormatter->finish();

        // Handle orphans
        $orphanIds = $this->findOrphans($existingIds, $newIds);
        if (!empty($orphanIds)) {
            $orphans = $this->destinationDriver->readMultiple($orphanIds);
        } else {
            $orphans = [];
        }

        // Cleanup
        unset(
            $this->migration,
            $this->sourceIds,
            $this->sourceDriver,
            $this->destinationIds,
            $this->destinationDriver
        );

        return $orphans;
    }

    /**
     * @param array $sourceRow
     *
     * @return array
     *   The set of ids that identify this row in the destination.
     *
     * @throws NoIdSetException
     *   Thrown when there is no value set for an id in this row.
     * @throws \DragoonBoots\A2B\Exception\NonexistentMigrationException
     * @throws \DragoonBoots\A2B\Exception\NoDestinationException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function executeRow(array $sourceRow): array
    {
        $sourceIds = $this->getSourceIds($sourceRow);

        try {
            $destIds = $this->mapper->getDestIdsFromSourceIds(get_class($this->migration), $sourceIds);
            $entity = $this->destinationDriver->read($destIds);
            if (is_null($entity)) {
                $entity = $this->migration->defaultResult();
            }
        } catch (NoMappingForIdsException $e) {
            $entity = $this->migration->defaultResult();
        }
        $this->migration->transform($sourceRow, $entity);

        $destIds = $this->destinationDriver->write($entity);
        $this->mapper->addMapping(get_class($this->migration), $this->migration->getDefinition(), $sourceIds, $destIds);
        $this->rowCounter++;
        $this->outputFormatter->writeProgress($this->rowCounter, $sourceIds, $destIds);

        return $destIds;
    }

    /**
     * Get the source id values for this row.
     *
     * @param array $sourceRow
     *
     * @return array
     *
     * @throws NoIdSetException
     *   Thrown when there is no value set for the given id.
     */
    protected function getSourceIds(array $sourceRow): array
    {
        $sourceIds = [];
        foreach ($this->sourceIds as $idField) {
            if (!isset($sourceRow[$idField->getName()])) {
                throw new NoIdSetException($idField, $sourceRow);
            }

            $value = $sourceRow[$idField->getName()];
            if ($idField->getType() == 'int') {
                $value = (int)$value;
            }
            $sourceIds[$idField->getName()] = $value;
        }

        return $sourceIds;
    }

    /**
     * Find rows that existed in the destination that don't exist in the source
     *
     * @param array $oldIds
     *   The ids present in the destination at the start of the migration
     * @param array $newIds
     *   The ids migrated from the source
     *
     * @return array
     */
    protected function findOrphans(array $oldIds, array $newIds): array
    {
        $orphans = array_udiff(
            $oldIds, $newIds,
            function ($a, $b) {
                $diff = 0;
                foreach ($a as $key => $aValue) {
                    $diff += strcmp($aValue, $b[$key]);
                }

                return $diff;
            }
        );

        return $orphans;
    }
}
