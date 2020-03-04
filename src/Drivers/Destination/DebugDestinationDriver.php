<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;

/**
 * Destination driver to print result to a stream.
 *
 * @Driver()
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

    /**
     * DebugDestinationDriver constructor.
     *
     * @param AbstractDumper  $dumper
     * @param ClonerInterface $cloner
     */
    public function __construct(AbstractDumper $dumper, ClonerInterface $cloner)
    {
        parent::__construct();

        $this->dumper = $dumper;
        $this->cloner = $cloner;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);

        switch ($this->migrationDefinition->getDestination()) {
            case 'stdout':
                $this->dumper->setOutput(STDOUT);

                return;
            case 'stderr':
                $this->dumper->setOutput(STDERR);

                return;
        }

        throw new BadUriException($definition->getDestination());
    }

    /**
     * {@inheritdoc}
     */
    public function getExistingIds(): array
    {
        return [];
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
    public function read(array $destIds)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $destIdSet)
    {
        return [];
    }
}
