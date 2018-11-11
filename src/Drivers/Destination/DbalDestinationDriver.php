<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\FetchMode;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Exception\NoIdSetException;
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
class DbalDestinationDriver extends AbstractDestinationDriver implements DestinationDriverInterface
{

    /**
     * @var ConnectionFactory
     */
    protected $connectionFactory;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * The base table for the current migration
     *
     * @var string|null
     */
    protected $baseTable;

    /**
     * A list of existing ids.  Used to determine UPDATE or INSERT.
     *
     * @var array
     */
    protected $existingIds;

    /**
     * DbalDestinationDriver constructor.
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
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);

        $this->baseTable = $this->destUri['fragment'];
        $destination = $definition->getDestination();
        $destination = str_replace('#'.$this->baseTable, '', $destination);

        try {
            $this->connection = $this->connectionFactory->createConnection(['url' => $destination]);
        } catch (DBALException $e) {
            // Convert the Doctrine exception into our own.
            throw new BadUriException($destination, $e->getCode(), $e);
        }

        $this->connection->beginTransaction();
    }

    /**
     * {@inheritdoc}
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function getExistingIds(): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
            foreach ($this->destIds as $destId) {
                $qb->addSelect($destId->getName());
            }
            $qb->from($this->connection->quoteIdentifier($this->baseTable));
            $results = $qb->execute();

            $this->existingIds = [];
            foreach ($results as $idRow) {
                $id = [];
                foreach ($this->destIds as $destId) {
                    $id[$destId->getName()] = $this->resolveIdType($destId, $idRow[$destId->getName()]);
                }
                $this->existingIds[] = $id;
            }

            return $this->existingIds;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function read(array $destIds)
    {
        try {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('*')
                ->from($this->connection->quoteIdentifier($this->baseTable));
            $k = 0;
            foreach ($destIds as $destId => $value) {
                $idValueParam = ':idValue_'.$k;
                $qb->andWhere(sprintf('%s = %s', $this->connection->quoteIdentifier($destId), $idValueParam));
                $qb->setParameter($idValueParam, $value);
                $k++;
            }
            $qb->setMaxResults(1);

            $results = $qb->execute()->fetch(FetchMode::ASSOCIATIVE);
            if ($results === false) {
                return null;
            } else {
                return $results;
            }
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function write($data)
    {
        try {
            $ids = $this->getIdsFromNewData($data);

            $qb = $this->connection->createQueryBuilder();
            if (in_array($ids, $this->existingIds)) {
                // Data exists already, use UPDATE
                $setMethod = 'set';
                $qb->update($this->connection->quoteIdentifier($this->baseTable));
                $k = 0;
                foreach ($ids as $id => $idValue) {
                    if (is_string($idValue)) {
                        $idValue = $this->connection->quote($idValue);
                    }
                    $idValueParam = ':idValue_'.$k;
                    $qb->andWhere(sprintf('%s = %s', $this->connection->quoteIdentifier($id), $idValueParam));
                    $qb->setParameter($idValueParam, $idValue);
                    $k++;
                }
            } else {
                // Data does not exist already, use INSERT.
                $qb->insert($this->baseTable);
                $setMethod = 'setValue';
            }

            $k = 0;
            foreach ($data as $field => $value) {
                $valueParam = ':value_'.$k;
                if (is_string($value)) {
                    $value = $this->connection->quote($value);
                }
                $qb->$setMethod($this->connection->quoteIdentifier($field), $valueParam);
                $qb->setParameter($valueParam, $value);
                $k++;
            }
            $qb->execute();

            return $ids;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Get id values from the data
     *
     * @param array $data
     *
     * @return array
     */
    protected function getIdsFromNewData(array $data)
    {
        $ids = [];
        foreach ($this->destIds as $destId) {
            if (isset($data[$destId->getName()])) {
                $ids[$destId->getName()] = $data[$destId->getName()];
            } else {
                $ids[$destId->getName()] = null;
            }
        }

        return $ids;
    }

    /**
     * @inheritDoc
     */
    public function readMultiple(array $destIdSet)
    {
        return parent::readMultiple($destIdSet);
    }

    /**
     * {@inheritdoc}
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function flush()
    {
        parent::flush();

        $this->connection->commit();
    }

}
