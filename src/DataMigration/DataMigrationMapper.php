<?php


namespace DragoonBoots\A2B\DataMigration;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use DragoonBoots\A2B\Types\MapRow;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class DataMigrationMapper implements DataMigrationMapperInterface
{

    public const MAPPING_SOURCE = 'source';

    public const MAPPING_DEST = 'dest';

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Inflector
     */
    protected $inflector;

    /**
     * @var DataMigrationManagerInterface
     */
    protected $dataMigrationManager;

    /**
     * A list of migrations for which the mapping tables have been conformed.
     *
     * The key is the migration class name, the values is true or false
     * depending on its status.
     *
     * @var bool[]
     */
    protected $mappingConformed = [];

    /**
     * The UUID for this migration process.
     *
     * This is used to determine which records were checked/updated by this
     * process.
     *
     * @var UuidInterface
     */
    protected $migrationUuid;

    /**
     * DataMigrationMapper constructor.
     *
     * @param string                        $dbConfig
     * @param ConnectionFactory             $connectionFactory
     * @param Inflector                     $inflector
     * @param DataMigrationManagerInterface $dataMigrationManager
     */
    public function __construct(
        string $dbConfig,
        ConnectionFactory $connectionFactory,
        Inflector $inflector,
        DataMigrationManagerInterface $dataMigrationManager
    ) {
        $this->connection = $connectionFactory->createConnection(['url' => $dbConfig]);

        $this->inflector = $inflector;
        $this->dataMigrationManager = $dataMigrationManager;
    }

    /**
     * @param UuidInterface $migrationUuid
     *
     * @return self
     */
    public function setMigrationUuid(UuidInterface $migrationUuid): self
    {
        $this->migrationUuid = $migrationUuid;

        return $this;
    }

    /**
     * Generates a uuid if one has not been defined already.
     *
     * @return UuidInterface
     * @throws \Exception
     */
    protected function getMigrationUuid(): UuidInterface
    {
        if (is_null($this->migrationUuid)) {
            $this->migrationUuid = Uuid::uuid4();
        }

        return $this->migrationUuid;
    }

    /**
     * {@inheritdoc}
     */
    public function addMapping($migrationId, DataMigration $migrationDefinition, array $sourceIds, array $destIds)
    {
        $tableName = $this->getMappingTableName($migrationId);
        $mappingConformed = $this->mappingConformed[$migrationId] ?? false;
        if (!$mappingConformed) {
            $this->conformMappingTable($migrationId, $migrationDefinition);
            $this->mappingConformed[$migrationId] = true;
        }

        $q = $this->connection->createQueryBuilder();
        if (!$this->rowMigratedPreviously($migrationId, $sourceIds, $destIds)) {
            // Create a new mapping.
            $q->insert($tableName);
            foreach ($sourceIds as $sourceId => $value) {
                $columnName = $this->getMappingColumnName($sourceId, self::MAPPING_SOURCE);
                $q->setValue($columnName, $q->createNamedParameter($value));
            }
            $setFunction = 'setValue';
            foreach ($destIds as $destId => $value) {
                $columnName = $this->getMappingColumnName($destId, self::MAPPING_DEST);
                $q->$setFunction($columnName, $q->createNamedParameter($value));
            }
        } else {
            // Update existing mapping
            $q->update($tableName);
            foreach ($sourceIds as $sourceId => $value) {
                $columnName = $this->getMappingColumnName($sourceId, self::MAPPING_SOURCE);
                $q->andWhere($this->createKeyComparisonSql($q, $columnName, $value));
            }
            foreach ($destIds as $destId => $value) {
                $columnName = $this->getMappingColumnName($destId, self::MAPPING_DEST);
                $q->andWhere($this->createKeyComparisonSql($q, $columnName, $value));

            }
            $setFunction = 'set';
        }
        $q->$setFunction(
            'migration', $q->createNamedParameter(
            $this->getMigrationUuid()
                ->getBytes()
        )
        );
        $q->$setFunction('updated', $q->createNamedParameter(date('c')));
        $q->execute();
    }

    /**
     * @param $migrationId
     *
     * @return string
     */
    protected function getMappingTableName(string $migrationId): string
    {
        static $tableNames = [];
        if (!isset($tableNames[$migrationId])) {
            $tableNames[$migrationId] = $this->inflector::tableize(
              str_replace(['/', '\\'], '_', $migrationId)
            );
        }

        return $tableNames[$migrationId];
    }

    /**
     * @param string        $migrationId
     * @param DataMigration $migrationDefinition
     *
     * @throws SchemaException
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function conformMappingTable(string $migrationId, DataMigration $migrationDefinition): void
    {
        // Ensure the data structures are in place to create the mapping.
        $tableName = $this->getMappingTableName($migrationId);
        $sm = $this->connection->getSchemaManager();
        $fromSchema = $sm->createSchema();
        $toSchema = clone $fromSchema;
        try {
            $table = $toSchema->getTable($tableName);
        } catch (SchemaException $e) {
            if ($e->getCode() == SchemaException::TABLE_DOESNT_EXIST) {
                // Create the table.
                $table = $toSchema->createTable($tableName);
                $table->addOption('comment', sprintf('Data migration map for "%s"', $migrationId));
                $table->addColumn('id', Type::INTEGER);
                $table->addColumn('migration', Type::BLOB);
                $table->addColumn('updated', Type::DATETIMETZ_IMMUTABLE);
                $table->setPrimaryKey(['id']);
                $table->addIndex(['migration'], 'ix_migration');
                $table->addIndex(['updated'], 'ix_updated');
            } else {
                throw $e;
            }
        }

        // Ensure mapping columns are present.
        $sourceIdFields = $migrationDefinition->sourceIds;
        $destIdFields = $migrationDefinition->destinationIds;
        foreach ($sourceIdFields as $sourceIdField) {
            $this->conformMappingColumn($table, $sourceIdField, self::MAPPING_SOURCE);
        }
        foreach ($destIdFields as $destIdField) {
            $this->conformMappingColumn($table, $destIdField, self::MAPPING_DEST);
        }

        // Create indexes
        $this->conformMappingIndexes($table, $sourceIdFields, self::MAPPING_SOURCE);
        $this->conformMappingIndexes($table, $destIdFields, self::MAPPING_DEST);
        $allIdColumnNames = [];
        foreach ($sourceIdFields as $sourceIdField) {
            $allIdColumnNames[] = $this->getMappingColumnName($sourceIdField, self::MAPPING_SOURCE);
        }
        foreach ($destIdFields as $destIdField) {
            $allIdColumnNames[] = $this->getMappingColumnName($destIdField, self::MAPPING_DEST);
        }
        $allIdIndexName = 'ix_mapping';
        try {
            $index = $table->getIndex($allIdIndexName);
            // Ensure index columns match.
            if ($index->getUnquotedColumns() !== $allIdColumnNames) {
                $table->dropIndex($allIdIndexName);
                $table->addUniqueIndex($allIdColumnNames, $allIdIndexName);
            }
        } catch (SchemaException $e) {
            // Create the index
            if ($e->getCode() == SchemaException::INDEX_DOESNT_EXIST) {
                $table->addUniqueIndex($allIdColumnNames, $allIdIndexName);
            } else {
                throw $e;
            }
        }

        // Apply schema
        $sql = $fromSchema->getMigrateToSql($toSchema, $this->connection->getDatabasePlatform());
        foreach ($sql as $q) {
            $this->connection->exec($q);
        }
    }

    /**
     * Add a column to the mapping table.
     *
     * @param Table   $table
     *   The table to add the column to, passed by reference.
     * @param IdField $idField
     *   The id field containing the column info.
     * @param string  $type
     *   One of 'source' or 'dest'.
     *
     * @throws SchemaException
     */
    protected function conformMappingColumn(Table &$table, IdField $idField, string $type)
    {
        $comment = sprintf(
          '%s field "%s"',
          ucfirst($type),
          $idField->name
        );
        $columnName = $this->getMappingColumnName($idField, $type);
        if ($idField->type == 'int') {
            $colType = Type::INTEGER;
        } else {
            $colType = Type::STRING;
        }
        try {
            $column = $table->getColumn($columnName);
        } catch (SchemaException $e) {
            // Create the column.
            if ($e->getCode() == SchemaException::COLUMN_DOESNT_EXIST) {
                $column = $table->addColumn(
                  $columnName,
                  $colType
                );
            } else {
                throw $e;
            }
        }
        $column->setComment($comment);
        $column->setType(Type::getType($colType));
        $column->setNotnull(false);
    }

    /**
     * @param IdField|string $idField
     *   The id field containing the column info.
     * @param string         $type
     *   One of 'source' or 'dest'.
     *
     * @return string
     */
    protected function getMappingColumnName($idField, string $type): string
    {
        if ($idField instanceof IdField) {
            $idField = $idField->name;
        }
        $type = strtolower($type);

        static $columnNames = [];
        if (!isset($columnNames[$type][$idField])) {
            $columnNames[$type][$idField] = sprintf('%s_%s', $type, $this->inflector::tableize($idField));
        }

        return $columnNames[$type][$idField];
    }

    /**
     * @param Table     $table
     *   The table to add the column to, passed by reference.
     * @param IdField[] $idFields
     *   A list of id fields that should be part of this index.
     * @param string    $type
     *   One of 'source' or 'dest'.
     *
     * @throws SchemaException
     */
    protected function conformMappingIndexes(Table &$table, array $idFields, string $type)
    {
        $indexName = sprintf('ix_%s', strtolower($type));
        $indexCols = [];
        foreach ($idFields as $idField) {
            $indexCols[] = $this->getMappingColumnName($idField, $type);
        }
        try {
            $index = $table->getIndex($indexName);
            // Ensure index columns match.
            if ($index->getUnquotedColumns() !== $indexCols) {
                $table->dropIndex($indexName);
                $table->addIndex($indexCols, $indexName);
            }
        } catch (SchemaException $e) {
            // Create the index
            if ($e->getCode() == SchemaException::INDEX_DOESNT_EXIST) {
                $table->addIndex($indexCols, $indexName);
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param $migrationId
     *
     * @return bool
     */
    protected function isUpdateMigration($migrationId): bool
    {
        $tableName = $this->getMappingTableName($migrationId);

        static $updateMigrations = [];
        if (!isset($updateMigrations[$migrationId])) {
            $q = $this->connection->createQueryBuilder();
            $q->select('COUNT(*)')
                ->from($tableName);
            $count = (int)($q->execute()->fetch(FetchMode::COLUMN));
            $updateMigrations[$migrationId] = (bool)($count > 0);
        }

        return $updateMigrations[$migrationId];
    }

    /**
     * @param string $migrationId
     * @param array  $sourceIds
     * @param array  $destIds
     *
     * @return bool
     */
    protected function rowMigratedPreviously(string $migrationId, array $sourceIds, array $destIds): bool
    {
        $tableName = $this->getMappingTableName($migrationId);
        $q = $this->connection->createQueryBuilder();
        $q->select('COUNT(*)')
            ->from($tableName);
        foreach ($sourceIds as $sourceId => $value) {
            $columnName = $this->getMappingColumnName($sourceId, self::MAPPING_SOURCE);
            $q->andWhere($this->createKeyComparisonSql($q, $columnName, $value));
        }
        foreach ($destIds as $destId => $value) {
            $columnName = $this->getMappingColumnName($destId, self::MAPPING_DEST);
            $q->andWhere($this->createKeyComparisonSql($q, $columnName, $value));
        }
        $count = (int)($q->execute()->fetch(FetchMode::COLUMN));

        return $count > 0;
    }

    /**
     * Create a SQL comparison string that handles NULL values properly.
     *
     * @param QueryBuilder $q
     * @param string       $column
     * @param mixed|null   $value
     *
     * @return string
     */
    private function createKeyComparisonSql(QueryBuilder $q, string $column, $value)
    {
        if (is_null($value)) {
            return sprintf('"%s" IS NULL', $column);
        } else {
            return sprintf('"%s" = %s', $column, $q->createNamedParameter($value));
        }
    }

    /**
     * @param string $migrationId
     * @param array  $sourceIds
     *
     * @return array
     *
     * @throws NoMappingForIdsException
     * @throws NonexistentMigrationException
     */
    public function getDestIdsFromSourceIds(string $migrationId, array $sourceIds)
    {
        $definition = $this->dataMigrationManager->getMigrationDefinition($migrationId);

        return $this->getMatchingIds($migrationId, $sourceIds, $definition->destinationIds, self::MAPPING_DEST);
    }

    /**
     * @param           $migrationId
     * @param array     $sourceIds
     * @param IdField[] $destIdFields
     * @param string    $type
     *   One of MAPPING_SOURCE or MAPPING_DEST.
     *
     * @return array
     *   An array with the resulting ids.
     * @throws NoMappingForIdsException
     */
    protected function getMatchingIds($migrationId, array $sourceIds, array $destIdFields, string $type): array
    {
        // Important thing to remember in this function: "Source" and "Dest"
        // don't mean what you think they do.  It really means "querying ids"
        // and "resulting ids" respectively.
        $tableName = $this->getMappingTableName($migrationId);

        $q = $this->connection->createQueryBuilder();
        $q->from($tableName);

        foreach ($destIdFields as $destIdField) {
            $q->addSelect($this->getMappingColumnName($destIdField, self::MAPPING_DEST));
        }

        foreach ($sourceIds as $sourceId => $value) {
            $columnName = $this->getMappingColumnName($sourceId, self::MAPPING_SOURCE);
            $q->andWhere(
              sprintf(
                '"%s" = %s',
                $columnName,
                $q->createNamedParameter($value)
              )
            );
        }

        try {
            $result = $q->execute()->fetch();
        } catch (TableNotFoundException $e) {
            throw new NoMappingForIdsException($sourceIds, 0, $e);
        }
        if (!$result) {
            throw new NoMappingForIdsException($sourceIds);
        }

        // Need to remove the column prefix from the keys for use elsewhere.
        $destIds = [];
        foreach ($result as $key => $value) {
            $newKey = str_replace($this->getMappingColumnName('', $type), '', $key);
            $destIds[$newKey] = $value;
        }

        return $destIds;
    }

    /**
     * @param string $migrationId
     * @param array  $destIds
     *
     * @return array
     *
     * @throws NoMappingForIdsException
     * @throws NonexistentMigrationException
     */
    public function getSourceIdsFromDestIds(string $migrationId, array $destIds)
    {
        $definition = $this->dataMigrationManager->getMigrationDefinition($migrationId);

        return $this->getMatchingIds($migrationId, $destIds, $definition->sourceIds, self::MAPPING_SOURCE);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrphans(string $migrationId): array
    {
        $q = $this->connection->createQueryBuilder();
        $q->select('*')
            ->from($this->getMappingTableName($migrationId))
            ->where(
                sprintf(
                    'migration <> "%s"',
                    $this->getMigrationUuid()->getBytes()
                )
            );
        $results = $q->execute()
            ->fetchAll(FetchMode::CUSTOM_OBJECT, MapRow::class);

        return $results;
    }
}
