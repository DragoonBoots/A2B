<?php


namespace DragoonBoots\A2B\Event;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Fired after writing the destination row.
 */
class PostWriteDestinationRow extends Event
{

    /**
     * @var DataMigrationInterface
     */
    protected $migration;

    /**
     * @var mixed
     */
    protected $destinationEntity;

    /**
     * PreFetchSourceRow constructor.
     *
     * @param DataMigrationInterface $migration
     * @param                        $destinationEntity
     */
    public function __construct(DataMigrationInterface $migration, $destinationEntity)
    {
        $this->migration = $migration;
        $this->destinationEntity = $destinationEntity;
    }

    /**
     * @return DataMigrationInterface
     */
    public function getMigration(): DataMigrationInterface
    {
        return $this->migration;
    }

    /**
     * @return mixed
     */
    public function getDestinationEntity()
    {
        return $this->destinationEntity;
    }
}
