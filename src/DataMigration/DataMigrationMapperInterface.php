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
     * @param string        $migrationId
     *   The class name of the migration being run.
     * @param DataMigration $migrationDefinition
     * @param array         $sourceIds
     * @param array         $destIds
     *
     * @throws SchemaException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addMapping($migrationId, DataMigration $migrationDefinition, array $sourceIds, array $destIds);

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
}
