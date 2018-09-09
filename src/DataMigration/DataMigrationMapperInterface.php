<?php

namespace DragoonBoots\A2B\DataMigration;

use Doctrine\DBAL\Schema\SchemaException;
use DragoonBoots\A2B\Annotations\DataMigration;
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
     * @param array  $sourceIds
     * @param array  $destIds
     * @param int    $status
     *
     * @throws NonexistentMigrationException
     */
    public function addMapping($migrationId, array $sourceIds, array $destIds, int $status = DataMigrationMapper::STATUS_MIGRATED);

    /**
     * @param string $migrationId
     * @param array  $sourceIds
     *
     * @return array
     *
     * @throws NonexistentMigrationException
     * @throws NoMappingForIdsException
     */
    public function getDestIdsFromSourceIds(string $migrationId, array $sourceIds);

    /**
     * @param string $migrationId
     * @param array  $destIds
     *
     * @return array
     *
     * @throws NonexistentMigrationException
     * @throws NoMappingForIdsException
     */
    public function getSourceIdsFromDestIds(string $migrationId, array $destIds);

    /**
     * Create a stub for an entity that does not yet exist.
     *
     * @param string $migrationId
     * @param array  $sourceIds
     *
     * @return object
     */
    public function createStub(string $migrationId, array $sourceIds);

    /**
     * Get the stubs that have been created and forget about them.
     *
     * @return array
     */
    public function getAndPurgeStubs(): array;
}
