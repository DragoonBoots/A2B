<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Exception\NoDestinationException;

/**
 * Destination driver interface.
 */
interface DestinationDriverInterface
{

    /**
     * Set the destination of this driver.
     *
     * @param DataMigration $definition
     *   The migration definition.
     *
     * @throws BadUriException
     *   Thrown when the given URI is not valid.
     */
    public function configure(DataMigration $definition);

    /**
     * Write the transformed data.
     *
     * @param $data
     *
     * @return array|null
     *   An associative array with the destination keys.  If no keys can
     *   logically exist (e.g. output only), return null.
     *
     * @throws NoDestinationException
     *   Thrown when the destination is not configured.
     */
    public function write($data);

    /**
     * Get the entity as last migrated from the destination for updating.
     *
     * @param array $destIds
     *   A list of key-value pairs where the key is the destination id field and
     *   the value is destination id value.
     *
     * @return mixed|null
     *   Returns the selected entity, or null if it does not exist in the
     *   destination.
     */
    public function getCurrentEntity(array $destIds);
}
