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
}
