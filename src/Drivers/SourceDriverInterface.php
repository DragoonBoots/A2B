<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Exception\BadUriException;

/**
 * Source driver interface
 *
 * Source drivers should implement this interface and be annotated with
 * DragoonBoots\A2B\Annotations\Driver.
 */
interface SourceDriverInterface extends \IteratorAggregate
{

    /**
     * Set the source of this driver.
     *
     * @param string $source
     *   A source URI.
     *
     * @throws BadUriException
     *   Thrown when the given URI is not valid.
     */
    public function setSource(string $source);
}
