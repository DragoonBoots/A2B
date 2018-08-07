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
     * A list of migration FQCNs this depends on
     *
     * @var string[]
     */
    protected $depends = [];

    /**
     * DataMigration constructor.
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->name = $values['name'] ?? null;
        $this->group = $values['group'] ?? 'default';
        $this->source = $values['source'] ?? null;
        $this->sourceDriver = $values['sourceDriver'] ?? null;
        $this->destination = $values['destination'] ?? null;
        $this->destinationDriver = $values['destinationDriver'] ?? null;
        $this->sourceIds = $values['sourceIds'] ?? [];
        $this->destinationIds = $values['destinationIds'] ?? [];

        // Normalize dependency list to remove leading backslash
        foreach ($values['depends'] ?? [] as $dependency) {
            $this->depends[] = ltrim($dependency, '\\');
        }
    }

    /**
     * @return string
     *
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return null|string
     *
     * @codeCoverageIgnore
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @return string
     *
     * @codeCoverageIgnore
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
     *
     * @codeCoverageIgnore
     */
    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return null|string
     *
     * @codeCoverageIgnore
     */
    public function getSourceDriver(): ?string
    {
        return $this->sourceDriver;
    }

    /**
     * @internal
     *
     * @param string $sourceDriver
     *
     * @return self
     */
    public function setSourceDriver(string $sourceDriver): self
    {
        $this->sourceDriver = $sourceDriver;

        return $this;
    }

    /**
     * @return string
     *
     * @codeCoverageIgnore
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
     *
     * @codeCoverageIgnore
     */
    public function setDestination(string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * @return null|string
     *
     * @codeCoverageIgnore
     */
    public function getDestinationDriver(): ?string
    {
        return $this->destinationDriver;
    }

    /**
     * @internal
     *
     * @param string $destinationDriver
     *
     * @return self
     */
    public function setDestinationDriver(string $destinationDriver): self
    {
        $this->destinationDriver = $destinationDriver;

        return $this;
    }

    /**
     * @return IdField[]
     *
     * @codeCoverageIgnore
     */
    public function getSourceIds(): array
    {
        return $this->sourceIds;
    }

    /**
     * @return IdField[]
     *
     * @codeCoverageIgnore
     */
    public function getDestinationIds(): array
    {
        return $this->destinationIds;
    }

    /**
     * @return string[]
     *
     * @codeCoverageIgnore
     */
    public function getDepends(): array
    {
        return $this->depends;
    }
}
