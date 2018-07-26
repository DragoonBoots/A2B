<?php


namespace DragoonBoots\A2B\Drivers\Source;


use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractSourceDriver;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use League\Uri\Parser;

/**
 * Doctrine DBAL source driver.
 *
 * @Driver({
 *     "db2",
 *     "ibm_db2",
 *     "mssql",
 *     "pdo_sqlsrv",
 *     "mysql",
 *     "mysql2",
 *     "pdo_mysql",
 *     "pgsql",
 *     "postgres",
 *     "postgresql",
 *     "pdo_pgsql",
 *     "sqlite",
 *     "sqlite3",
 *     "pdo_sqlite"
 * })
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
     * DbalSourceDriver constructor.
     *
     * @param Parser            $uriParser
     * @param ConnectionFactory $connectionFactory
     */
    public function __construct(Parser $uriParser, ConnectionFactory $connectionFactory)
    {
        parent::__construct($uriParser);

        $this->connectionFactory = $connectionFactory;
    }

    /**
     * Set the source of this driver.
     *
     * @param string $source
     *   A source URI.
     *
     * @throws BadUriException
     *   Thrown when the given URI is not valid.
     */
    public function setSource(?string $source)
    {
        if (is_null($source)) {
            unset($this->connection);

            return;
        }

        try {
            $this->connection = $this->connectionFactory->createConnection(['url' => $source]);
        } catch (DBALException $e) {
            // Convert the Doctrine exception into our own.
            throw new BadUriException($source, 0, $e);
        }
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
     * @param Statement $statement
     */
    public function setStatement(Statement $statement)
    {
        $statement->execute();
        $this->resultIterator = $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return $this->resultIterator;
    }


}
