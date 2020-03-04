<?php


namespace DragoonBoots\A2B\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * Annotation for drivers.
 *
 * @Annotation
 * @Annotation\Target({"CLASS"})
 */
class Driver
{
    /**
     * Does this driver support stubs?
     *
     * Most likely this can only be true on drivers that interact with a
     * database.
     *
     * @var bool
     */
    protected $supportsStubs = false;

    /**
     * Driver constructor.
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->supportsStubs = $values['supportsStubs'] ?? false;
    }

    /**
     * @return bool
     */
    public function supportsStubs(): bool
    {
        return $this->supportsStubs;
    }
}
