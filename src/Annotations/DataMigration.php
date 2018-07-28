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
     * @var string|null
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
     * @var string|null
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
     * @var string|null
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
     * @return null|string
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @param null|string $group
     *
     * @return self
     */
    public function setGroup(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
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
     * @param null|string $sourceDriver
     *
     * @return self
     */
    public function setSourceDriver(?string $sourceDriver): self
    {
        $this->sourceDriver = $sourceDriver;

        return $this;
    }

    /**
     * @return string
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
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
     * @param null|string $destinationDriver
     *
     * @return self
     */
    public function setDestinationDriver(?string $destinationDriver): self
    {
        $this->destinationDriver = $destinationDriver;

        return $this;
    }

    /**
     * @return IdField[]
     */
    public function getSourceIds(): array
    {
        return $this->sourceIds;
    }

    /**
     * @param IdField[] $sourceIds
     *
     * @return self
     */
    public function setSourceIds(array $sourceIds): self
    {
        $this->sourceIds = $sourceIds;

        return $this;
    }

    /**
     * @return IdField[]
     */
    public function getDestinationIds(): array
    {
        return $this->destinationIds;
    }

    /**
     * @param IdField[] $destinationIds
     *
     * @return self
     */
    public function setDestinationIds(array $destinationIds): self
    {
        $this->destinationIds = $destinationIds;

        return $this;
    }
}
