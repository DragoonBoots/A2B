<?php


namespace DragoonBoots\A2B\Drivers\Source;


use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractSourceDriver;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;

/**
 * Doctrine DBAL source driver.
 *
 * @Driver()
 */
class DbalSourceDriver extends AbstractSourceDriver implements SourceDriverInterface
{

    /**
     * @var ConnectionFactory
     */
    protected $connectionFactory;

    /**
     * @var Connection|null
     */
    protected $connection;

    /**
     * The main result statement
     *
     * This provides the rows passed to the migration.
     *
     * @var ResultStatement
     */
    protected $resultIterator;

    /**
     * The number of rows in the source.
     *
     * @var int|null
     */
    protected $count = null;

    /**
     * DbalSourceDriver constructor.
     *
     * @param ConnectionFactory $connectionFactory
     */
    public function __construct(ConnectionFactory $connectionFactory)
    {
        parent::__construct();

        $this->connectionFactory = $connectionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        $source = $definition->getSource();
        $this->count = null;

        if (isset($this->connection)) {
            $this->connection->close();
        }

        try {
            $this->connection = $this->connectionFactory->createConnection(['url' => $source]);
        } catch (DBALException $e) {
            // Convert the Doctrine exception into our own.
            throw new BadUriException($source, $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return Connection
     */
    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * Set the statement used for the main result set.
     *
     * @param Statement|string $statement
     */
    public function setStatement($statement)
    {
        if (is_string($statement)) {
            $statement = $this->getConnection()->prepare($statement);
        }

        $statement->execute();
        $this->resultIterator = $statement;
    }

    /**
     * Set the statement use for counting the number of rows in the source.
     *
     * This should select a single field.  The first field in the result
     * will be used as the count.
     *
     * @param Statement|string $statement
     */
    public function setCountStatement($statement)
    {
        if (is_string($statement)) {
            $statement = $this->getConnection()->prepare($statement);
        }

        $statement->execute();
        $this->count = (int)$statement->fetchOne();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return $this->resultIterator;
    }


}
