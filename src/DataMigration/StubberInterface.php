<?php

namespace DragoonBoots\A2B\DataMigration;

interface StubberInterface
{

    /**
     * Create a stub for the given migration.
     *
     * @param string $migrationId
     *
     * @return object
     */
    public function createStub(string $migrationId);
}
