<?php

namespace DragoonBoots\A2B\DataMigration;

use DragoonBoots\A2B\DataMigration\OutputFormatter\OutputFormatterInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\NoIdSetException;

interface DataMigrationExecutorInterface
{

    /**
     * Set the output formatter to use for all output.
     *
     * @param OutputFormatterInterface $outputFormatter
     */
    public function setOutputFormatter(OutputFormatterInterface $outputFormatter);

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

    /**
     * Handle orphans from the migration.
     *
     * @param array                      $orphans
     * @param DataMigrationInterface     $migration
     * @param DestinationDriverInterface $destinationDriver
     */
    public function askAboutOrphans(array $orphans, DataMigrationInterface $migration, DestinationDriverInterface $destinationDriver);

    /**
     * Write orphans to the destination and mapping table.
     *
     * @param array                      $orphans
     * @param DataMigrationInterface     $migration
     * @param DestinationDriverInterface $destinationDriver
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws \DragoonBoots\A2B\Exception\NoDestinationException
     */
    public function writeOrphans(array $orphans, DataMigrationInterface $migration, DestinationDriverInterface $destinationDriver): void;
}
