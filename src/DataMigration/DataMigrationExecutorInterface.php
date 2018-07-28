<?php

namespace DragoonBoots\A2B\DataMigration;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoIdSetException;

interface DataMigrationExecutorInterface
{

    /**
     * Run the given migration
     *
     * @param DataMigrationInterface     $migration
     * @param DataMigration              $definition
     * @param SourceDriverInterface      $sourceDriver
     * @param DestinationDriverInterface $destinationDriver
     *
     * @throws NoIdSetException
     */
    public function execute(DataMigrationInterface $migration, DataMigration $definition, SourceDriverInterface $sourceDriver, DestinationDriverInterface $destinationDriver);
}