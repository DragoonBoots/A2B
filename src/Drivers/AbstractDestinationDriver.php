<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\DataMigration;
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
     * @var DataMigration
     */
    protected $migrationDefinition;

    /**
     * @var array
     *
     * @see \League\Uri\Parser::parse()
     */
    protected $destUri;

    /**
     * @var IdField[]
     */
    protected $destIds;

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
        $this->definition = $definition;
        $this->destUri = $this->uriParser->parse($definition->getDestination());
        $this->destIds = $definition->getDestinationIds();
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
        $idType = $idField->getType();
        if ($idType == 'int') {
            $value = (int)$value;
        } elseif ($idType == 'string') {
            $value = (string)$value;
        }

        return $value;
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
}
