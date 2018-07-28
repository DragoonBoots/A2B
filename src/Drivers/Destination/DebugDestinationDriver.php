<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use League\Uri\Parser;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;

/**
 * Destination driver to print result to a stream.
 *
 * @Driver("debug")
 */
class DebugDestinationDriver extends AbstractDestinationDriver implements DestinationDriverInterface
{

    /**
     * @var AbstractDumper
     */
    protected $dumper;

    /**
     * @var ClonerInterface
     */
    protected $cloner;

    public function __construct(Parser $uriParser, AbstractDumper $dumper, ClonerInterface $cloner)
    {
        parent::__construct($uriParser);

        $this->dumper = $dumper;
        $this->cloner = $cloner;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        $destination = $definition->destination;

        $uri = $this->uriParser->parse($destination);
        switch ($uri['path']) {
            case 'stdout':
                $this->dumper->setOutput(STDOUT);

                return;
            case 'stderr':
                $this->dumper->setOutput(STDERR);

                return;
        }

        throw new BadUriException($destination);
    }

    /**
     * {@inheritdoc}
     */
    public function write($data)
    {
        $this->dumper->dump($this->cloner->cloneVar($data));

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
