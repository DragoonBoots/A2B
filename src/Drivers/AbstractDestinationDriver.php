<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;

/**
 * Base class for destination drivers.
 */
abstract class AbstractDestinationDriver extends AbstractDriver implements DestinationDriverInterface
{

    use IdTypeConversionTrait;

    /**
     * @var Driver|null
     */
    protected $definition;

    /**
     * @var DataMigration
     */
    protected $migrationDefinition;

    /**
     * AbstractSourceDriver constructor.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);

        $this->ids = $definition->getDestinationIds();
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $destIdSet)
    {
        $results = [];
        foreach ($destIdSet as $destId) {
            $results[] = $this->read($destId);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function flush()
    {
        // Do nothing, allowing drivers that don't have a buffer to avoid
        // implementing nothing.
        return;
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function getDefinition(): ?Driver
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function setDefinition(Driver $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function freeMemory(): void
    {
    }
}
