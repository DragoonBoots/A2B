<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\Driver;
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
    public function count(): int
    {
        $count = 0;
        foreach ($this->getIterator() as $item) {
            $count++;
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition(): ?Driver
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefinition(Driver $definition): self
    {
        $this->definition = $definition;

        return $this;
    }
}
