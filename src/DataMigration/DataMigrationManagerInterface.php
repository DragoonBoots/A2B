<?php

namespace DragoonBoots\A2B\DataMigration;

use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use MJS\TopSort\CircularDependencyException;
use MJS\TopSort\ElementNotFoundException;


/**
 * Manage Data migrations
 */
interface DataMigrationManagerInterface
{

    /**
     * Get all migrations
     *
     * @return Collection|DataMigrationInterface[]
     */
    public function getMigrations(): Collection;

    /**
     * Get all group names
     *
     * @return Collection|string[]
     */
    public function getGroups(): Collection;

    /**
     * Get a specific migration
     *
     * @param string $migrationName
     *
     * @return DataMigrationInterface
     *
     * @throws NonexistentMigrationException
     *   Thrown when the specified migration doesn't exist
     */
    public function getMigration(string $migrationName): DataMigrationInterface;

    /**
     * Get the migrations in a specific group
     *
     * @param string $groupName
     *
     * @return Collection|DataMigrationInterface[]
     */
    public function getMigrationsInGroup(string $groupName);

    /**
     * Resolve migration dependencies into a proper run order.
     *
     * @param DataMigrationInterface[]|iterable $migrations
     *   A list of migrations requested for execution.
     * @param array|null $extrasAdded
     *   An array that will be filled with migration ids that are depended upon
     *   but not initially requested for running.
     *
     * @return DataMigrationInterface[]|Collection
     *   A collection of migrations to run in the proper order
     *
     * @throws CircularDependencyException
     * @throws ElementNotFoundException
     * @throws NonexistentMigrationException
     */
    public function resolveDependencies(iterable $migrations, ?array &$extrasAdded = null): Collection;
}
