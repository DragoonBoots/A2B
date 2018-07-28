<?php


namespace DragoonBoots\A2B\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * Id field annotation
 *
 * @Annotation
 * @Annotation\Target("ANNOTATION")
 */
class IdField
{

    /**
     * The name of the id field
     *
     * @var string
     * @Annotation\Required
     */
    public $name;

    /**
     * The type of the id field.
     *
     * Valid types are:
     * - int
     * - string
     *
     * @var string
     * @Annotation\Required
     * @Annotation\Enum({"int", "string"})
     */
    public $type;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
