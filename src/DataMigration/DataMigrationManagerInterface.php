<?php

namespace DragoonBoots\A2B\DataMigration;

use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;


/**
 * Manage Data migrations
 */
interface DataMigrationManagerInterface
{

    /**
     * @return Collection|DataMigrationInterface[]
     */
    public function getMigrations(): Collection;

    /**
     * @param string $migrationName
     *
     * @return DataMigrationInterface
     *
     * @throws NonexistentMigrationException
     */
    public function getMigration(string $migrationName);

    /**
     * @param string $groupName
     *
     * @return Collection|DataMigrationInterface[]
     */
    public function getMigrationsInGroup(string $groupName);
}
