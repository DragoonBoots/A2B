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
     * A list of source/destination url schemes this driver can handle.
     *
     * @var string[]
     * @Annotation\Required
     */
    protected $schemes;

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
        $schemes = $values['value'] ?? $values['schemes'] ?? [];
        if (!is_array($schemes)) {
            $schemes = [$schemes];
        }
        $this->schemes = $schemes;
        $this->supportsStubs = $values['supportsStubs'] ?? false;
    }

    /**
     * @return string[]
     */
    public function getSchemes(): array
    {
        return $this->schemes;
    }

    /**
     * @return bool
     */
    public function supportsStubs(): bool
    {
        return $this->supportsStubs;
    }
}
