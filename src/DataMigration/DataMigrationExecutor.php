<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\OutputFormatter\OutputFormatterInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoIdSetException;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use RuntimeException;
use Throwable;

class DataMigrationExecutor implements DataMigrationExecutorInterface
{

    protected const ORPHAN_REMOVE = 'n';

    protected const ORPHAN_KEEP = 'y';

    protected const ORPHAN_ASK = 'c';

    /**
     * @var DataMigrationMapperInterface
     */
    protected $mapper;

    /**
     * @var OutputFormatterInterface
     */
    protected $outputFormatter;

    /**
     * @var MigrationReferenceStoreInterface
     */
    protected $referenceStore;

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
     * PHP memory limit (bytes)
     *
     * @var int
     */
    protected $memoryLimit;

    /**
     * DataMigrationExecutor constructor.
     *
     * @param DataMigrationMapperInterface $mapper
     * @param MigrationReferenceStoreInterface $referenceStore
     */
    public function __construct(DataMigrationMapperInterface $mapper, MigrationReferenceStoreInterface $referenceStore)
    {
        $this->mapper = $mapper;
        $this->referenceStore = $referenceStore;
        $this->memoryLimit = $this->phpMemoryLimit();
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
    ): array {
        gc_enable();
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
            $newId = $this->executeRow($row);
            if (!is_null($newId)) {
                $newIds[] = $newId;
            }
        }
        $this->outputFormatter->finish();

        // Handle orphans
        $orphanIds = array_values($this->findOrphans($existingIds, $newIds));
        if (!empty($orphanIds)) {
            $orphans = array_values($this->destinationDriver->readMultiple($orphanIds));
        } else {
            $orphans = [];
        }

        // Cleanup
        $this->freeMemoryIfNeeded(0.5);
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
     * @return array|null
     *   The set of ids that identify this row in the destination, or NULL if
     *   the row is skipped.
     *
     * @throws NoIdSetException Thrown when there is no value set for an id in this row.
     * @throws NoMappingForIdsException
     * @throws NonexistentMigrationException
     * @throws Throwable When an exception occurs inside the transform method,
     * that exception is logged and rethrown
     */
    protected function executeRow(array $sourceRow): ?array
    {
        $this->freeMemoryIfNeeded();

        $migrationDefinition = $this->migration->getDefinition();
        $flush = $migrationDefinition->getFlush();
        $sourceIds = $this->getSourceIds($sourceRow);

        $mapperMigrationKey = get_class($this->migration);
        if (!is_null($migrationDefinition->getExtends())) {
            $mapperMigrationKey = get_class($migrationDefinition->getExtends());
        }
        try {
            $destIds = $this->mapper->getDestIdsFromSourceIds($mapperMigrationKey, $sourceIds);
            $entity = $this->destinationDriver->read($destIds);
            if (is_null($entity)) {
                $entity = $this->migration->defaultResult();
            }
        } catch (NoMappingForIdsException $e) {
            $entity = $this->migration->defaultResult();
        } catch (Throwable $e) {
            $this->outputFormatter->message(
                "Error encountered reading existing data for source ids:\n".var_export($sourceIds, true)
            );
            throw $e;
        }
        $entity = $this->migration->transform($sourceRow, $entity);

        if (!is_null($entity)) {
            try {
                // Write stubs first
                $stubs = $this->mapper->getAndPurgeStubs();
                foreach ($stubs as $serializedKey => $stub) {
                    ['migrationId' => $stubMigrationId, 'sourceIds' => $stubSourceIds] = unserialize($serializedKey);
                    $stubDestIds = $this->destinationDriver->write($stub);
                    $this->mapper->addMapping(
                        $stubMigrationId,
                        $stubSourceIds,
                        $stubDestIds,
                        DataMigrationMapper::STATUS_STUB
                    );
                }
                if (!empty($stubs)) {
                    // Flush the written stubs so they may be retrieved later.
                    $flush = true;
                }

                $destIds = $this->destinationDriver->write($entity);
                if ($flush) {
                    $this->destinationDriver->flush();
                }
            } catch (Throwable $e) {
                $this->outputFormatter->message(
                    "Error encountered writing data for source ids:\n".var_export($sourceIds, true)
                );
                throw $e;
            }
            $this->mapper->addMapping($mapperMigrationKey, $sourceIds, $destIds);
        } else {
            // Purge stubs
            $this->mapper->getAndPurgeStubs();
            $destIds = null;
        }

        $this->rowCounter++;
        $this->outputFormatter->writeProgress($this->rowCounter, $sourceIds, $destIds);

        return $destIds;
    }

    /**
     * Check if memory needs to be freed and attempt to if necessary.
     *
     * @param float $memoryPercentageUsed
     *   When memory usage reaches this percentage of available memory, attempt
     *   to clean up.  Defaults to 0.8 (80%)
     *
     * @throws RuntimeException
     *   Thrown when memory cannot be freed.
     *
     * @codeCoverageIgnore
     */
    protected function freeMemoryIfNeeded(float $memoryPercentageUsed = 0.8): void
    {
        $memoryPreLimit = (int)($this->memoryLimit * $memoryPercentageUsed);
        if (memory_get_usage() >= $memoryPreLimit) {
            $this->outputFormatter->message('Freeing memory...', OutputFormatterInterface::MESSAGE_INFO);
            gc_collect_cycles();
            $this->referenceStore->freeMemory();
            gc_collect_cycles();
            $this->sourceDriver->freeMemory();
            gc_collect_cycles();
            $this->destinationDriver->freeMemory();
            gc_collect_cycles();

            if (memory_get_usage() >= $memoryPreLimit) {
                throw new RuntimeException(
                    sprintf(
                        'Cannot free enough memory to continue. (%s / %s bytes used)',
                        memory_get_usage(),
                        $this->memoryLimit
                    )
                );
            }
            $this->outputFormatter->message('Memory freed, continuing...', OutputFormatterInterface::MESSAGE_INFO);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function askAboutOrphans(
        array $orphans,
        DataMigrationInterface $migration,
        DestinationDriverInterface $destinationDriver
    ) {
        $choices = [
            self::ORPHAN_KEEP => 'Keep all orphans',
            self::ORPHAN_REMOVE => 'Remove all orphans',
            self::ORPHAN_ASK => 'Make a decision for each orphan',
        ];
        $q = sprintf(
            '%d entities existed in the destination but do not exist in the source.  Keep them?',
            count($orphans)
        );
        $decision = $this->outputFormatter->ask($q, $choices, self::ORPHAN_KEEP);

        if ($decision == self::ORPHAN_KEEP) {
            $this->writeOrphans($orphans, $migration, $destinationDriver);
        } elseif ($decision == self::ORPHAN_ASK) {
            $entityChoices = [
                self::ORPHAN_KEEP => 'Keep',
                self::ORPHAN_REMOVE => 'Remove',
            ];
            foreach ($orphans as $orphan) {
                $printableEntity = var_export($orphan, true)."\n";
                $entityQ = sprintf("Keep this entity?\n%s", $printableEntity);
                $entityDecision = $this->outputFormatter->ask($entityQ, $entityChoices, self::ORPHAN_KEEP);
                if ($entityDecision == self::ORPHAN_KEEP) {
                    $this->writeOrphans([$orphan], $migration, $destinationDriver);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeOrphans(
        array $orphans,
        DataMigrationInterface $migration,
        DestinationDriverInterface $destinationDriver
    ): void {
        foreach ($orphans as $orphan) {
            $destIds = $destinationDriver->write($orphan);

            // Make a fake set of source ids for the mapper.
            $sourceIdFields = [];
            foreach ($migration->getDefinition()->getSourceIds() as $sourceId) {
                $sourceIdFields[] = $sourceId->getName();
            }
            $sourceIds = array_fill_keys($sourceIdFields, null);
            $this->mapper->addMapping(get_class($migration), $sourceIds, $destIds);
        }
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
        return array_udiff(
            $oldIds,
            $newIds,
            function ($a, $b) {
                $diff = 0;
                foreach ($a as $key => $aValue) {
                    $diff += strcmp($aValue, $b[$key]);
                }

                return $diff;
            }
        );
    }

    /**
     * Get the PHP memory limit
     *
     * @return int
     * @noinspection PhpMissingBreakStatementInspection
     * @noinspection PhpMissingBreakStatementInspection
     */
    private function phpMemoryLimit(): int
    {
        $val = trim(ini_get('memory_limit'));
        $last = strtolower($val[strlen($val) - 1]);
        if (!is_numeric($last)) {
            $val = (int)substr($val, 0, -1);
        } else {
            $val = (int)$val;
        }

        if ($val == -1) {
            return PHP_INT_MAX;
        }

        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
