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
     * @var array
     */
    protected $sourceRow;

    /**
     * PreFetchSourceRow constructor.
     *
     * @param DataMigrationInterface $migration
     * @param array                  $sourceRow
     */
    public function __construct(DataMigrationInterface $migration, array $sourceRow)
    {
        $this->migration = $migration;
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
     * @return array
     */
    public function getSourceRow(): array
    {
        return $this->sourceRow;
    }
}
