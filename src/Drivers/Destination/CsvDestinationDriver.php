<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\MigrationException;
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
     * The temporary file the results are written to.
     *
     * @var resource
     */
    protected $tempFile;

    /**
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);

        // Ensure the destination exists.
        if (!is_dir(dirname($this->destUri['path']))) {
            mkdir(dirname($this->destUri['path']), 0755, true);
        }

        $this->reader = CsvReader::createFromPath($this->destUri['path'], 'c+');

        // The file is new if it is entirely empty or only includes a header.
        $this->newFile = $this->reader->count() <= 1;
        if (!$this->newFile) {
            $this->reader->setHeaderOffset(0);
        }

        $this->tempFile = tmpfile();
        $this->writer = CsvWriter::createFromStream($this->tempFile);
        $this->writer->addValidator(new ColumnConsistency(), 'column_consistency');

        $this->headerWritten = false;
    }

    /**
     * {@inheritdoc}
     */
    public function getExistingIds(): array
    {
        $ids = [];
        foreach ($this->reader->getIterator() as $row) {
            $id = [];
            foreach ($this->destIds as $destId) {
                $id[$destId->getName()] = $row[$destId->getName()];
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * {@inheritdoc}
     * @throws \League\Csv\CannotInsertRecord
     * @throws NoIdSetException
     */
    public function write($data)
    {
        if (!$this->headerWritten) {
            $this->writer->insertOne(array_keys($data));
            $this->headerWritten = true;
        }
        $this->writer->insertOne($data);

        $destIds = [];
        foreach ($this->destIds as $destId) {
            if (!isset($data[$destId->getName()])) {
                throw new NoIdSetException($destId->getName(), $data);
            }
            $destIds[$destId->getName()] = $this->resolveDestId($destId, $data[$destId->getName()]);
        }

        return $destIds;
    }

    /**
     * {@inheritdoc}
     * @throws \League\Csv\Exception
     */
    public function read(array $destIds)
    {
        if (!$this->newFile) {
            $results = $this->findEntities([$destIds]);
            $count = $results->count();
            if ($count > 1) {
                throw new \RangeException(sprintf("More than one row matched the ids:\n%s\n", var_export($destIds, true)));
            } elseif ($count == 1) {
                return $results->fetchOne();
            }
        }

        return null;
    }

    /**
     * Query the destination results
     *
     * @param array $destIdSet
     *   An array of of dest id arrays.  Each dest id array is a set of
     *   key/value pairs.
     *
     * @return \League\Csv\ResultSet
     */
    protected function findEntities(array $destIdSet)
    {
        $constraint = (new Statement())->where(
            function ($record) use ($destIdSet) {
                foreach ($destIdSet as $destIds) {
                    $found = true;
                    foreach ($destIds as $key => $value) {
                        $found = $found && ($record[$key] == $value);
                    }
                    if ($found) {
                        return true;
                    }
                }

                return false;
            }
        );
        $results = $constraint->process($this->reader);

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $destIdSet)
    {
        if ($this->newFile) {
            return [];
        }

        $results = $this->findEntities($destIdSet);
        $entities = [];
        foreach ($results as $result) {
            $entities[] = $result;
        }

        return $entities;
    }

    /**
     * {@inheritdoc}
     * @throws MigrationException
     *   Thrown when the destination file could not be written.
     */
    public function flush()
    {
        $tempFile = stream_get_meta_data($this->tempFile)['uri'];
        copy($tempFile, $this->destUri['path']);
        unlink($tempFile);
    }
}
