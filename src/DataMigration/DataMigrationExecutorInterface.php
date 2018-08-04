<?php

namespace DragoonBoots\A2B\DataMigration;

use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoIdSetException;

interface DataMigrationExecutorInterface
{

    /**
     * Run the given migration
     *
     * @param DataMigrationInterface     $migration
     * @param SourceDriverInterface      $sourceDriver
     * @param DestinationDriverInterface $destinationDriver
     *
     * @return mixed
     *   A list of entities that existed in the destination but no longer exist
     *   in the source.
     *
     * @throws NoIdSetException
     */
    public function execute(DataMigrationInterface $migration, SourceDriverInterface $sourceDriver, DestinationDriverInterface $destinationDriver);
}
