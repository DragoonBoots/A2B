<?php


namespace DragoonBoots\A2B\Types;


use Ramsey\Uuid\UuidInterface;

class MapRow
{

    /**
     * @var int
     */
    protected $id;

    /**
     * The migration uuid that last processed this row
     *
     * @var UuidInterface
     */
    protected $migration;

    /**
     * When this was last updated.
     *
     * @var \DateTimeImmutable
     */
    protected $updated;
}
