<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;

/**
 * Base class for source drivers.
 */
abstract class AbstractSourceDriver extends AbstractDriver implements SourceDriverInterface
{

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
     * @inheritDoc
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);
        $this->ids = $definition->getSourceIds();
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
    public function setDefinition(Driver $definition)
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
