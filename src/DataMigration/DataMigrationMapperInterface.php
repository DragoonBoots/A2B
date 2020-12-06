<?php

namespace DragoonBoots\A2B\DataMigration;

use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;


/**
 * Map source ids and destination ids.
 */
interface DataMigrationMapperInterface
{

    /**
     * @param string $migrationId
     *   The class name of the migration being run.
     * @param array $sourceIds
     * @param array $destIds
     * @param int $status
     *
     * @throws NonexistentMigrationException
     */
    public function addMapping(
        string $migrationId,
        array $sourceIds,
        array $destIds,
        int $status = DataMigrationMapper::STATUS_MIGRATED
    );

    /**
     * @param string $migrationId
     * @param array $sourceIds
     *
     * @return array
     *
     * @throws NonexistentMigrationException
     * @throws NoMappingForIdsException
     */
    public function getDestIdsFromSourceIds(string $migrationId, array $sourceIds): array;

    /**
     * @param string $migrationId
     * @param array $destIds
     *
     * @return array
     *
     * @throws NonexistentMigrationException
     * @throws NoMappingForIdsException
     */
    public function getSourceIdsFromDestIds(string $migrationId, array $destIds): array;

    /**
     * Create a stub for an entity that does not yet exist.
     *
     * @param DataMigrationInterface $migration
     * @param array $sourceIds
     *
     * @return object
     */
    public function createStub(DataMigrationInterface $migration, array $sourceIds): object;

    /**
     * Get the stubs that have been created and forget about them.
     *
     * @return array
     */
    public function getAndPurgeStubs(): array;
}
