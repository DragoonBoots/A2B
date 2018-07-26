<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Exception\BadUriException;

/**
 * Destination driver interface.
 */
interface DestinationDriverInterface
{

    /**
     * Set the destination of this driver.
     *
     * @param string $destination
     *   A destination URI.
     *
     * @throws BadUriException
     *   Thrown when the given URI is not valid.
     */
    public function setDestination(string $destination);

    /**
     * Write the transformed data.
     *
     * @param $data
     */
    public function write($data);

    /**
     * Get the entity as last migrated from the destination for updating.
     *
     * @param array $ids
     *   A list of key-value pairs where the key is the source id field and the
     *   value is source id value.
     *
     * @return mixed
     */
    public function getCurrentEntity(array $ids);
}
