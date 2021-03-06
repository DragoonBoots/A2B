<?php


namespace DragoonBoots\A2B\Annotations;

use Doctrine\Common\Annotations\Annotation;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;

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
     * - An absolute path, or a path is relative to the project directory.
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
     * @var string
     * @Annotation\Required
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
     * @Annotation\Required
     */
    protected $destinationDriver;

    /**
     * The source unique ids
     *
     * @var IdField[]
     * @Annotation\Required
     */
    protected $sourceIds;

    /**
     * The destination unique ids
     *
     * @var IdField[]
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
     * Entities will be flushed after being written.
     *
     * This can solve problems with self-referencing entities.
     *
     * Use this with care, as it can cause massive performance issues.
     *
     * @var bool
     */
    protected $flush = false;

    /**
     * This migration extends a different one (e.g. a second pass over the same
     * data set).
     *
     * @var string|null
     */
    protected $extends;

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
        $this->flush = $values['flush'] ?? false;
        $this->extends = $values['extends'] ?? null;

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

    /**
     * @return bool
     */
    public function getFlush(): bool
    {
        return $this->flush;
    }

    /**
     * @return string|DataMigrationInterface|null
     */
    public function getExtends()
    {
        return $this->extends;
    }
}
