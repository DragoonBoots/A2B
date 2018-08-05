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
    protected $name;

    /**
     * The type of the id field.
     *
     * Valid types are:
     * - int
     * - string
     *
     * Defaults to "int".
     *
     * @var string
     * @Annotation\Enum({"int", "string"})
     */
    protected $type;

    /**
     * IdField constructor.
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->name = $values['name'] ?? null;
        $this->type = $values['type'] ?? 'int';
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
