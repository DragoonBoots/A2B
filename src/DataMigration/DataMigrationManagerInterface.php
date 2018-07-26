<?php

namespace DragoonBoots\A2B\DataMigration;

use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\DataMigration;
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
     * @return Collection|DataMigration[]
     */
    public function getMigrationDefinitions(): Collection;

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

    /**
     * @param string $migrationName
     *
     * @return DataMigration
     *
     * @throws NonexistentMigrationException
     */
    public function getMigrationDefinition(string $migrationName);

    /**
     * @param string $groupName
     *
     * @return Collection|DataMigration[]
     */
    public function getMigrationDefinitionsInGroup(string $groupName);
}
