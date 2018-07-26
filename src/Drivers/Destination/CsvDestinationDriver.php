<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\NoDestinationException;
use DragoonBoots\A2B\Exception\NoIdSetException;
use League\Csv\ColumnConsistency;
use League\Csv\Reader as CsvReader;
use League\Csv\Statement;
use League\Csv\Writer as CsvWriter;

/**
 * CSV Destination driver
 *
 * @Driver("csv")
 */
class CsvDestinationDriver extends AbstractDestinationDriver implements DestinationDriverInterface
{

    /**
     * @var CsvWriter
     */
    protected $writer;

    /**
     * @var CsvReader
     */
    protected $reader;

    /**
     * @var IdField[]
     */
    protected $destIds;

    /**
     * @var bool
     */
    protected $headerWritten = false;

    /**
     * Is the file being worked on a newly created file?
     *
     * @var bool
     */
    protected $newFile = false;

    /**
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        $destination = $definition->destination;
        $this->destIds = $definition->destinationIds;

        $uri = $this->uriParser->parse($destination);

        // Ensure the destination exists.
        if (!is_dir(dirname($uri['path']))) {
            mkdir(dirname($uri['path']), 0755, true);
        }

        $this->writer = CsvWriter::createFromPath($uri['path'], 'w+');
        $this->writer->addValidator(new ColumnConsistency(), 'column_consistency');

        $this->reader = CsvReader::createFromPath($uri['path']);

        $this->headerWritten = false;
        // The file is new if it is entirely empty or only includes a header.
        $this->newFile = $this->reader->count() <= 1;
        if (!$this->newFile) {
            $this->reader->setHeaderOffset(0);
        }
    }

    /**
     * {@inheritdoc}
     * @throws \League\Csv\CannotInsertRecord
     * @throws NoIdSetException
     */
    public function write($data)
    {
        if (!isset($this->writer)) {
            throw new NoDestinationException();
        }

        if (!$this->headerWritten) {
            $this->writer->insertOne(array_keys($data));
            $this->headerWritten = true;
        }
        $this->writer->insertOne($data);

        $destIds = [];
        foreach ($this->destIds as $destId) {
            if (!isset($data[$destId->name])) {
                throw new NoIdSetException($destId->name, $data);
            }
            $destIds[$destId->name] = $this->resolveDestId($destId, $data[$destId->name]);
        }

        return $destIds;
    }

    /**
     * {@inheritdoc}
     * @throws \League\Csv\Exception
     */
    public function getCurrentEntity(array $destIds)
    {
        if (!$this->newFile) {
            $constraint = new Statement();
            $constraint->where(
              function ($record) use ($destIds) {
                  $found = true;
                  foreach ($destIds as $key => $value) {
                      $found = $found && ($record[$key] == $value);
                  }

                  return $found;
              }
            );
            $results = $constraint->process($this->reader);
            $count = $results->count();
            if ($count > 1) {
                throw new \RangeException(sprintf("More than one row matched the ids:\n%s\n", var_export($destIds, true)));
            } elseif ($count == 1) {
                return $results->fetchOne();
            }
        }

        return null;
    }
}
