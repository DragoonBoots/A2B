<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Event\DataMigrationEvents;
use DragoonBoots\A2B\Event\PostFetchSourceRow;
use DragoonBoots\A2B\Event\PostTransformRow;
use DragoonBoots\A2B\Event\PostWriteDestinationRow;
use DragoonBoots\A2B\Exception\NoIdSetException;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DataMigrationExecutor implements DataMigrationExecutorInterface
{

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var DataMigrationMapperInterface
     */
    protected $mapper;

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
     * @var IdField[]
     */
    protected $sourceIds;

    /**
     * @var IdField[]
     */
    protected $destinationIds;

    /**
     * DataMigrationExecutor constructor.
     *
     * @param EventDispatcherInterface     $eventDispatcher
     * @param DataMigrationMapperInterface $mapper
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, DataMigrationMapperInterface $mapper)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->mapper = $mapper;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(
      DataMigrationInterface $migration,
      DataMigration $definition,
      SourceDriverInterface $sourceDriver,
      DestinationDriverInterface $destinationDriver
    )
    {
        $this->migration = $migration;
        $this->definition = $definition;
        $this->sourceIds = $definition->sourceIds;
        $this->sourceDriver = $sourceDriver;
        $this->destinationIds = $definition->destinationIds;
        $this->destinationDriver = $destinationDriver;

        foreach ($sourceDriver->getIterator() as $row) {
            $this->executeRow($row);
        }

        // Cleanup
        $this->destinationDriver->flush();
        unset(
          $this->migration,
          $this->definition,
          $this->sourceIds,
          $this->sourceDriver,
          $this->destinationIds,
          $this->destinationDriver
        );
    }

    /**
     * @param array $sourceRow
     *
     * @throws NoIdSetException
     *   Thrown when there is no value set for an id in this row.
     * @throws \DragoonBoots\A2B\Exception\NonexistentMigrationException
     * @throws \DragoonBoots\A2B\Exception\NoDestinationException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function executeRow(array $sourceRow)
    {
        $sourceIds = $this->getSourceIds($sourceRow);

        $postFetchSourceRowEvent = new PostFetchSourceRow($this->migration, $this->definition, $sourceRow);
        $this->eventDispatcher->dispatch(DataMigrationEvents::EVENT_POST_FETCH_SOURCE_ROW, $postFetchSourceRowEvent);

        try {
            $destIds = $this->mapper->getDestIdsFromSourceIds(get_class($this->migration), $sourceIds);
            $entity = $this->destinationDriver->getCurrentEntity($destIds);
            if (is_null($entity)) {
                $entity = $this->migration->defaultResult();
            }
        } catch (NoMappingForIdsException $e) {
            $entity = $this->migration->defaultResult();
        }
        $this->migration->transform($sourceRow, $entity);
        $postTransformRowEvent = new PostTransformRow($this->migration, $this->definition, $entity);
        $this->eventDispatcher->dispatch(DataMigrationEvents::EVENT_POST_TRANSFORM_ROW, $postTransformRowEvent);

        $destIds = $this->destinationDriver->write($entity);
        $this->mapper->addMapping(get_class($this->migration), $this->definition, $sourceIds, $destIds);
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
        foreach ($this->sourceIds as $idField) {
            if (!isset($sourceRow[$idField->name])) {
                throw new NoIdSetException($idField, $sourceRow);
            }

            $value = $sourceRow[$idField->name];
            if ($idField->type == 'int') {
                $value = (int)$value;
            }
            $sourceIds[$idField->name] = $value;
        }

        return $sourceIds;
    }
}
