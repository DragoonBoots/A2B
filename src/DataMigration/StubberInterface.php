<?php

namespace DragoonBoots\A2B\DataMigration;

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
