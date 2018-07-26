<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\IdField;
use League\Uri\Parser;

/**
 * Base class for destination drivers.
 */
abstract class AbstractDestinationDriver implements DestinationDriverInterface
{

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

    protected function resolveDestId(IdField $idField, $value)
    {
        if ($idField->type == 'int') {
            $value = (int)$value;
        }

        return $value;
    }
}
