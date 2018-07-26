<?php


namespace DragoonBoots\A2B\DataMigration;


/**
 * Base class for Data Migrations
 */
abstract class AbstractDataMigration implements DataMigrationInterface
{

    /**
     * {@inheritdoc}
     */
    public function defaultResult()
    {
        return [];
    }

}
