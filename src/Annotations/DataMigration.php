<?php


namespace DragoonBoots\A2B\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * Annotation for data migrations.
 *
 * @Annotation
 * @Annotation\Target({"CLASS"})
 */
class DataMigration
{

    /**
     * Human-readable name
     *
     * @var string
     * @Annotation\Required
     */
    public $name;

    /**
     * The migration group.
     *
     * Defaults to a group called "default".
     *
     * @var string
     */
    public $group = 'default';

    /**
     * The data source uri
     *
     * Valid values are:
     * - Files should be in the form "file://$PATH", where the path is relative
     *   to the project directory.
     * - Database sources should be specified as a Doctrine DBAL URL
     *   (https://www.doctrine-project.org/projects/doctrine-dbal/en/2.7/reference/configuration.html#connecting-using-a-url)
     * - Doctrine ORM entities should be in the form "entity://$FQCN".
     *
     * @var string
     * @Annotation\Required
     */
    public $source;

    /**
     * The FQCN for the desired source driver.
     *
     * This will usually be determined automatically based on the source uri.
     * You may want to specify a driver manually if more than one driver
     * implements a scheme.
     *
     * @var string
     */
    public $sourceDriver;

    /**
     * The destination uri in the same format as the source URI.
     *
     * @var string
     * @Annotation\Required
     */
    public $destination;

    /**
     * The FQCN for the desired destination driver.
     *
     * @var string
     */
    public $destinationDriver;

    /**
     * The source unique ids
     *
     * @var \DragoonBoots\A2B\Annotations\IdField[]
     * @Annotation\Required
     */
    public $sourceIds;

    /**
     * The destination unique ids
     *
     * @var \DragoonBoots\A2B\Annotations\IdField[]
     * @Annotation\Required
     */
    public $destinationIds;
}
