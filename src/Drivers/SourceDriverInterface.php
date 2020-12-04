<?php


namespace DragoonBoots\A2B\Drivers;

use Countable;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Exception\BadUriException;
use IteratorAggregate;

/**
 * Source driver interface
 *
 * Source drivers should implement this interface and be annotated with
 * DragoonBoots\A2B\Annotations\Driver.
 */
interface SourceDriverInterface extends IteratorAggregate, Countable
{

    /**
     * Set the source of this driver.
     *
     * @param DataMigration $definition
     *   The migration definition.
     *
     * @throws BadUriException
     *   Thrown when the given URI is not valid.
     */
    public function configure(DataMigration $definition);

    /**
     * Get the total number of rows in the source.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Get the settings defined for this driver.
     *
     * This will only be set for drivers retrieved from the DriverManager.
     *
     * @return Driver|null
     */
    public function getDefinition(): ?Driver;

    /**
     * Used by the manager to inject the definition
     *
     * @param Driver $definition
     *
     * @return self
     * @internal
     *
     */
    public function setDefinition(Driver $definition);

    /**
     * Called when the system needs to free memory before crashing.
     */
    public function freeMemory(): void;
}
