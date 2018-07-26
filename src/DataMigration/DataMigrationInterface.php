<?php


namespace DragoonBoots\A2B\DataMigration;

use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;

/**
 * DataMigration interface
 *
 * Data migrations must implement this interface and be annotated with
 * DragoonBoots\A2B\Annotations\DataMigration.  They will most likely also
 * want to extend AbstractDataMigration.
 */
interface DataMigrationInterface
{

    /**
     * Configure the source driver to fetch data.
     *
     * @param SourceDriverInterface $sourceDriver
     */
    public function configureSource(SourceDriverInterface $sourceDriver);

    /**
     * Transform the source to the destination.
     *
     * @param array $sourceData
     *   The source data returned from the source driver
     * @param mixed $destinationData
     *   The destination data, passed by reference.  This is either an existing
     *   entity known to have been created from the given source keys, or the
     *   result returned by defaultResult().
     */
    public function transform(array $sourceData, &$destinationData);

    /**
     * Configure the destination driver to put data.
     *
     * @param DestinationDriverInterface $destinationDriver
     */
    public function configureDestination(DestinationDriverInterface $destinationDriver);

    /**
     * The default result to use when no result with the same id(s) exist.
     *
     * @return mixed
     */
    public function defaultResult();
}
