<?php


namespace DragoonBoots\A2B\Event;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Fired after fetching a new source row and before transforming.
 */
class PostFetchSourceRow extends Event
{

    /**
     * @var DataMigrationInterface
     */
    protected $migration;

    /**
     * @var DataMigration
     */
    protected $definition;

    /**
     * @var array
     */
    protected $sourceRow;

    /**
     * PreFetchSourceRow constructor.
     *
     * @param DataMigrationInterface $migration
     * @param DataMigration          $definition
     * @param array                  $sourceRow
     */
    public function __construct(DataMigrationInterface $migration, DataMigration $definition, array $sourceRow)
    {
        $this->migration = $migration;
        $this->definition = $definition;
        $this->sourceRow = $sourceRow;
    }

    /**
     * @return DataMigrationInterface
     */
    public function getMigration(): DataMigrationInterface
    {
        return $this->migration;
    }

    /**
     * @return DataMigration
     */
    public function getDefinition(): DataMigration
    {
        return $this->definition;
    }

    /**
     * @return array
     */
    public function getSourceRow(): array
    {
        return $this->sourceRow;
    }
}
