<?php

namespace DragoonBoots\A2B\DataMigration;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use MJS\TopSort\Implementations\FixedArraySort;


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

    /**
     * Resolve migration dependencies into a proper run order.
     *
     * @param DataMigrationInterface[]|\iterable $migrations
     *   A list of migrations requested for execution.
     * @param array                              $extrasAdded
     *   An array that will be filled with migration ids that are depended upon
     *   but not initially requested for running.
     *
     * @return DataMigrationInterface[]|Collection
     *   A collection of migrations to run in the proper order
     *
     * @throws \MJS\TopSort\CircularDependencyException
     * @throws \MJS\TopSort\ElementNotFoundException
     * @throws NonexistentMigrationException
     */
    public function resolveDependencies(iterable $migrations, ?array &$extrasAdded = null): Collection;
}
