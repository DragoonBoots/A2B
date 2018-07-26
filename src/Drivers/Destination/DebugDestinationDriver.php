<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Exception\NoDestinationException;

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
    public function configure(DataMigration $definition)
    {
        $destination = $definition->destination;

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
        if (!isset($this->stream)) {
            throw new NoDestinationException();
        }
        $printable = var_export($data, true)."\n\n";
        fwrite($this->stream, $printable);

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentEntity(array $destIds)
    {
        return null;
    }
}
