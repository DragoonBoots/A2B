<?php

namespace DragoonBoots\A2B\DataMigration;

/**
 * Create stubs filled with data when needed for a migration reference
 */
interface StubberInterface
{

    /**
     * Create a stub for the given migration.
     *
     * @param DataMigrationInterface $migration
     *
     * @return object
     */
    public function createStub(DataMigrationInterface $migration): object ;
}
