<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Annotations\IdField;
use League\Uri\Parser;

/**
 * Base class for source drivers.
 */
abstract class AbstractSourceDriver implements SourceDriverInterface
{

    /**
     * @var Driver|null
     */
    protected $definition;

    /**
     * @var Parser
     */
    protected $uriParser;

    /**
     * @var DataMigration
     */
    protected $migrationDefinition;

    /**
     * @var array
     *
     * @see \League\Uri\Parser::parse()
     */
    protected $sourceUri;

    /**
     * @var IdField[]
     */
    protected $sourceIds;

    /**
     * AbstractSourceDriver constructor.
     *
     * @param Parser $uriParser
     */
    public function __construct(Parser $uriParser)
    {
        $this->uriParser = $uriParser;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        $this->migrationDefinition = $definition;
        $this->sourceUri = $this->uriParser->parse($definition->getSource());
        $this->sourceIds = $definition->getSourceIds();
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
