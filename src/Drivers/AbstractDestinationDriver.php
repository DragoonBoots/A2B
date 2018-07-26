<?php


namespace DragoonBoots\A2B\Drivers;

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
}
