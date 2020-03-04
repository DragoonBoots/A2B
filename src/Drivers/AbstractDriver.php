<?php


namespace DragoonBoots\A2B\Drivers;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;

/**
 * Base class for drivers
 */
abstract class AbstractDriver
{

    /**
     * @var IdField[]
     */
    protected $ids;

    /**
     * Setup the driver to run the migration
     *
     * @param DataMigration $definition
     */
    public function configure(DataMigration $definition)
    {
        $this->migrationDefinition = $definition;
    }
}
