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
    protected $name;

    /**
     * The migration group.
     *
     * Defaults to a group called "default".
     *
     * @var string
     */
    protected $group = 'default';

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
    protected $source;

    /**
     * The FQCN for the desired source driver.
     *
     * This will usually be determined automatically based on the source uri.
     * You may want to specify a driver manually if more than one driver
     * implements a scheme.
     *
     * @var string
     */
    protected $sourceDriver;

    /**
     * The destination uri in the same format as the source URI.
     *
     * @var string
     * @Annotation\Required
     */
    protected $destination;

    /**
     * The FQCN for the desired destination driver.
     *
     * @var string
     */
    protected $destinationDriver;

    /**
     * The source unique ids
     *
     * @var \DragoonBoots\A2B\Annotations\IdField[]
     * @Annotation\Required
     */
    protected $sourceIds;

    /**
     * The destination unique ids
     *
     * @var \DragoonBoots\A2B\Annotations\IdField[]
     * @Annotation\Required
     */
    protected $destinationIds;

    /**
     * DataMigration constructor.
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->name = $values['name'] ?? null;
        $this->group = $values['group'] ?? null;
        $this->source = $values['source'] ?? null;
        $this->sourceDriver = $values['sourceDriver'] ?? null;
        $this->destination = $values['destination'] ?? null;
        $this->destinationDriver = $values['destinationDriver'] ?? null;
        $this->sourceIds = $values['sourceIds'] ?? null;
        $this->destinationIds = $values['destinationIds'] ?? null;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return null|string
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @internal
     *
     * @param string $source
     *
     * @return self
     */
    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSourceDriver(): ?string
    {
        return $this->sourceDriver;
    }

    /**
     * @return string
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * @internal
     *
     * @param string $destination
     *
     * @return self
     */
    public function setDestination(string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getDestinationDriver(): ?string
    {
        return $this->destinationDriver;
    }

    /**
     * @return IdField[]
     */
    public function getSourceIds(): array
    {
        return $this->sourceIds;
    }

    /**
     * @return IdField[]
     */
    public function getDestinationIds(): array
    {
        return $this->destinationIds;
    }
}
