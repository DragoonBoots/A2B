<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Annotations\IdField;
use League\Uri\Parser;

/**
 * Base class for destination drivers.
 */
abstract class AbstractDestinationDriver implements DestinationDriverInterface
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
     * Perform the necessary typecasting on the destination id value.
     *
     * @param IdField $idField
     * @param         $value
     *
     * @return int|mixed
     */
    protected function resolveDestId(IdField $idField, $value)
    {
        if ($idField->getType() == 'int') {
            $value = (int)$value;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        // Do nothing, allowing drivers that don't have a buffer to avoid
        // implementing nothing.
        return;
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
