<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;

/**
 * Destination driver to print result to a stream.
 *
 * @Driver("debug")
 */
class DebugDestinationDriver extends AbstractDestinationDriver implements DestinationDriverInterface
{

    /**
     * Output stream
     *
     * @var resource
     */
    protected $stream;

    /**
     * {@inheritdoc}
     */
    public function setDestination(string $destination)
    {
        $uri = $this->uriParser->parse($destination);
        switch ($uri['path']) {
            case 'stdout':
                $this->stream = STDOUT;

                return;
            case 'stderr':
                $this->stream = STDERR;

                return;
        }

        throw new BadUriException($destination);
    }

    /**
     * {@inheritdoc}
     */
    public function write($data)
    {
        $printable = var_export($data, true)."\n\n";
        fwrite($this->stream, $printable);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentEntity(array $ids)
    {
        return null;
    }
}
