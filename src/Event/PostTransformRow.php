<?php


namespace DragoonBoots\A2B\Event;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Fired after transforming the row.
 */
class PostTransformRow extends Event
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
     * @var mixed
     */
    protected $destinationEntity;

    /**
     * PreFetchSourceRow constructor.
     *
     * @param DataMigrationInterface $migration
     * @param DataMigration          $definition
     * @param                        $destinationEntity
     */
    public function __construct(DataMigrationInterface $migration, DataMigration $definition, $destinationEntity)
    {
        $this->migration = $migration;
        $this->definition = $definition;
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
     * @return DataMigration
     */
    public function getDefinition(): DataMigration
    {
        return $this->definition;
    }

    /**
     * @return mixed
     */
    public function getDestinationEntity()
    {
        return $this->destinationEntity;
    }
}
