<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Event\DataMigrationEvents;
use DragoonBoots\A2B\Event\PostFetchSourceRow;
use DragoonBoots\A2B\Event\PostTransformRow;
use DragoonBoots\A2B\Event\PostWriteDestinationRow;
use DragoonBoots\A2B\Exception\NoIdSetException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DataMigrationExecutor implements DataMigrationExecutorInterface
{

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var DataMigrationInterface
     */
    protected $migration;

    /**
     * @var DataMigration
     */
    protected $definition;

    /**
     * @var SourceDriverInterface
     */
    protected $sourceDriver;

    /**
     * @var DestinationDriverInterface
     */
    protected $destinationDriver;

    /**
     * @var string[]
     */
    protected $ids;

    /**
     * DataMigrationExecutor constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(DataMigrationInterface $migration, DataMigration $definition, SourceDriverInterface $sourceDriver, DestinationDriverInterface $destinationDriver)
    {
        $this->migration = $migration;
        $this->definition = $definition;
        $this->ids = $definition->ids;
        $this->sourceDriver = $sourceDriver;
        $this->destinationDriver = $destinationDriver;

        foreach ($sourceDriver->getIterator() as $row) {
            $this->executeRow($row);
        }

        // Cleanup
        unset($this->migration, $this->definition, $this->ids, $this->sourceDriver, $this->destinationDriver);
    }

    /**
     * @param array $sourceRow
     *
     * @throws NoIdSetException
     *   Thrown when there is no value set for an id in this row.
     */
    protected function executeRow(array $sourceRow)
    {
        $sourceIds = $this->getSourceIds($sourceRow);

        $postFetchSourceRowEvent = new PostFetchSourceRow($this->migration, $this->definition, $sourceRow);
        $this->eventDispatcher->dispatch(DataMigrationEvents::EVENT_POST_FETCH_SOURCE_ROW, $postFetchSourceRowEvent);

        $entity = $this->destinationDriver->getCurrentEntity($sourceIds);
        if (is_null($entity)) {
            $entity = $this->migration->defaultResult();
        }
        $this->migration->transform($sourceRow, $entity);
        $postTransformRowEvent = new PostTransformRow($this->migration, $this->definition, $entity);
        $this->eventDispatcher->dispatch(DataMigrationEvents::EVENT_POST_TRANSFORM_ROW, $postTransformRowEvent);

        $this->destinationDriver->write($entity);
        $postWriteDestinationRow = new PostWriteDestinationRow($this->migration, $this->definition, $entity);
        $this->eventDispatcher->dispatch(DataMigrationEvents::EVENT_POST_WRITE_DESTINATION_ROW, $postWriteDestinationRow);
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
        foreach ($this->ids as $idField) {
            if (!isset($sourceRow[$idField])) {
                throw new NoIdSetException($idField, $sourceRow);
            }
            $sourceIds[$idField] = $sourceRow[$idField];
        }

        return $sourceIds;
    }
}
