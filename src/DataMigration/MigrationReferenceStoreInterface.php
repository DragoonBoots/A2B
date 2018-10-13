<?php

namespace DragoonBoots\A2B\DataMigration;

interface MigrationReferenceStoreInterface
{

    /**
     * Get the migrated destination entity.
     *
     * @param string $migrationId
     *   The migration id that created the desired entity
     * @param array  $sourceIds
     *   The source keys for the desired entity.
     * @param bool $stub
     *   Should a stub object be returned when the requested entity does not
     *   exist?
     *
     * @return mixed
     *
     * @throws \DragoonBoots\A2B\Exception\NoMappingForIdsException
     *   Thrown when the specified ids do not exist
     * @throws \DragoonBoots\A2B\Exception\NonexistentDriverException
     * @throws \DragoonBoots\A2B\Exception\NonexistentMigrationException
     *   Thrown when the specified migration does not exist
     */
    public function get(string $migrationId, array $sourceIds, bool $stub = false);

    /**
     * Called when the system needs to free memory before crashing.
     */
    public function freeMemory(): void;
}
