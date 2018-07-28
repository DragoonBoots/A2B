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
    public $value;

    /**
     * Driver constructor.
     *
     * @param array $values
     */
    public function __construct(array $values)
    {
        $schemes = $values['value'];
        if (!is_array($schemes)) {
            $schemes = [$schemes];
        }
        $this->value = $schemes;
    }

    /**
     * @return string[]
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @param string[] $value
     *
     * @return self
     */
    public function setValue(array $value): self
    {
        $this->value = $value;

        return $this;
    }
}
