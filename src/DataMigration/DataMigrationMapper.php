<?php


namespace DragoonBoots\A2B\DataMigration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use UnexpectedValueException;

class DataMigrationMapper implements DataMigrationMapperInterface
{

    public const MAPPING_SOURCE = 'source';

    public const MAPPING_DEST = 'dest';

    public const STATUS_MIGRATED = 0;

    public const STATUS_STUB = 1;

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
     * @var StubberInterface
     */
    protected $stubber;

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
     * A list of stubs created for this migration.
     *
     * @var array
     */
    protected $stubs = [];

    /**
     * DataMigrationMapper constructor.
     *
     * @param Connection $connection
     * @param DataMigrationManagerInterface $dataMigrationManager
     * @param StubberInterface $stubber
     */
    public function __construct(
        Connection $connection,
        DataMigrationManagerInterface $dataMigrationManager,
        StubberInterface $stubber
    ) {
        $this->connection = $connection;
        $this->dataMigrationManager = $dataMigrationManager;
        $this->stubber = $stubber;
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * {@inheritdoc}
     */
    public function addMapping(
        string $migrationId,
        array $sourceIds,
        array $destIds,
        int $status = self::STATUS_MIGRATED
    ) {
        $migrationDefinition = $this->dataMigrationManager->getMigration($migrationId)
            ->getDefinition();
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
        $q->$setFunction('updated', $q->createNamedParameter(date('c')));
        $q->$setFunction('status', $q->createNamedParameter($status));
        $q->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function createStub(DataMigrationInterface $migration, array $sourceIds): object
    {
        ksort($sourceIds);
        $key = [
            'migrationId' => get_class($migration),
            'sourceIds' => $sourceIds,
        ];
        $key = serialize($key);
        if (!isset($this->stubs[$key])) {
            $this->stubs[$key] = $this->stubber->createStub($migration);
        }

        return $this->stubs[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function getAndPurgeStubs(): array
    {
        $stubs = $this->stubs;
        $this->stubs = [];

        return $stubs;
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
            $tableNames[$migrationId] = $this->inflector->tableize(
                str_replace(['/', '\\'], '_', $migrationId)
            );
        }

        return $tableNames[$migrationId];
    }

    /**
     * @param string $migrationId
     * @param DataMigration $migrationDefinition
     *
     * @throws SchemaException
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
            $createPrimaryKey = false;
        } catch (SchemaException $e) {
            if ($e->getCode() == SchemaException::TABLE_DOESNT_EXIST) {
                // Create the table.
                $table = $toSchema->createTable($tableName);
                $table->addOption('comment', sprintf('Data migration map for "%s"', $migrationId));
                $table->addColumn('updated', Types::DATETIMETZ_IMMUTABLE);
                $table->addIndex(['updated'], sprintf('ix_%s_updated', $tableName));
                $table->addColumn('status', Types::SMALLINT);
                $createPrimaryKey = true;
            } else {
                throw $e;
            }
        }

        // Ensure mapping columns are present.
        $sourceIdFields = $migrationDefinition->getSourceIds();
        $destIdFields = $migrationDefinition->getDestinationIds();
        $sourceIdColumnNames = [];
        foreach ($sourceIdFields as $sourceIdField) {
            $columnName = $this->getMappingColumnName($sourceIdField, self::MAPPING_SOURCE);
            $sourceIdColumnNames[] = $columnName;
            $this->conformMappingColumn($table, $sourceIdField, self::MAPPING_SOURCE);
        }
        $destIdColumnNames = [];
        foreach ($destIdFields as $destIdField) {
            $columnName = $this->getMappingColumnName($destIdField, self::MAPPING_DEST);
            $destIdColumnNames[] = $columnName;
            $this->conformMappingColumn($table, $destIdField, self::MAPPING_DEST);
        }
        if ($createPrimaryKey) {
            $table->setPrimaryKey($destIdColumnNames);
        }

        // Create indexes
        $this->conformMappingIndexes($table, $sourceIdFields, self::MAPPING_SOURCE);
        $allIdColumnNames = array_merge($sourceIdColumnNames, $destIdColumnNames);
        $allIdIndexName = sprintf('ix_%s_mapping', $tableName);
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
            $this->connection->executeStatement($q);
        }
    }

    /**
     * Add a column to the mapping table.
     *
     * @param Table $table
     *   The table to add the column to, passed by reference.
     * @param IdField $idField
     *   The id field containing the column info.
     * @param string $type
     *   One of 'source' or 'dest'.
     *
     * @throws SchemaException
     */
    protected function conformMappingColumn(Table &$table, IdField $idField, string $type)
    {
        $comment = sprintf(
            '%s field "%s"',
            ucfirst($type),
            $idField->getName()
        );
        $columnName = $this->getMappingColumnName($idField, $type);
        if ($idField->getType() == 'int') {
            $colType = Types::INTEGER;
        } else {
            $colType = Types::STRING;
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
     * @param string $type
     *   One of 'source' or 'dest'.
     *
     * @return string
     */
    protected function getMappingColumnName($idField, string $type): string
    {
        if ($idField instanceof IdField) {
            $idField = $idField->getName();
        }
        $type = strtolower($type);

        static $columnNames = [];
        if (!isset($columnNames[$type][$idField])) {
            $columnNames[$type][$idField] = sprintf('%s_%s', $type, $this->inflector->tableize($idField));
        }

        return $columnNames[$type][$idField];
    }

    /**
     * @param Table $table
     *   The table to add the column to, passed by reference.
     * @param IdField[] $idFields
     *   A list of id fields that should be part of this index.
     * @param string $type
     *   One of 'source' or 'dest'.
     *
     * @throws SchemaException
     */
    protected function conformMappingIndexes(Table &$table, array $idFields, string $type)
    {
        $indexName = sprintf('ix_%s_%s', $table->getName(), strtolower($type));
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
     * @param string $migrationId
     * @param array $sourceIds
     * @param array $destIds
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
        $count = (int)($q->execute()->fetchOne());

        return $count > 0;
    }

    /**
     * Create a SQL comparison string that handles NULL values properly.
     *
     * @param QueryBuilder $q
     * @param string $column
     * @param mixed|null $value
     *
     * @return string
     */
    private function createKeyComparisonSql(QueryBuilder $q, string $column, $value): string
    {
        if (is_null($value)) {
            return sprintf('"%s" IS NULL', $column);
        } else {
            return sprintf('"%s" = %s', $column, $q->createNamedParameter($value));
        }
    }

    /**
     * @param string $migrationId
     * @param array $sourceIds
     *
     * @return array
     *
     * @throws NoMappingForIdsException
     * @throws NonexistentMigrationException
     */
    public function getDestIdsFromSourceIds(string $migrationId, array $sourceIds): array
    {
        $definition = $this->dataMigrationManager->getMigration($migrationId)
            ->getDefinition();

        return $this->getMatchingIds($migrationId, $sourceIds, $definition->getDestinationIds(), self::MAPPING_DEST);
    }

    /**
     * @param           $migrationId
     * @param array $sourceIds
     *   The querying ids, as a list of key/value items.
     * @param IdField[] $destIdFields
     *   The resulting id fields.
     * @param string $type
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
            $q->addSelect($this->getMappingColumnName($destIdField, $type));
        }

        foreach ($sourceIds as $sourceId => $value) {
            $columnName = $this->getMappingColumnName($sourceId, $this->flipMappingType($type));
            $q->andWhere(
                sprintf(
                    '"%s" = %s',
                    $columnName,
                    $q->createNamedParameter($value)
                )
            );
        }

        try {
            $result = $q->execute()->fetchAssociative();
        } catch (TableNotFoundException $e) {
            throw new NoMappingForIdsException($sourceIds, $migrationId, $e->getCode(), $e);
        }
        if (!$result) {
            throw new NoMappingForIdsException($sourceIds, $migrationId);
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
     * @param $direction
     *
     * @return string
     */
    protected function flipMappingType($direction): string
    {
        if ($direction == self::MAPPING_SOURCE) {
            return self::MAPPING_DEST;
        } elseif ($direction == self::MAPPING_DEST) {
            return self::MAPPING_SOURCE;
        } else {
            throw new UnexpectedValueException(sprintf('"%s" is not a valid mapping direction.', $direction));
        }
    }

    /**
     * @param string $migrationId
     * @param array $destIds
     *
     * @return array
     *
     * @throws NoMappingForIdsException
     * @throws NonexistentMigrationException
     */
    public function getSourceIdsFromDestIds(string $migrationId, array $destIds): array
    {
        $definition = $this->dataMigrationManager->getMigration($migrationId)
            ->getDefinition();

        return $this->getMatchingIds($migrationId, $destIds, $definition->getSourceIds(), self::MAPPING_SOURCE);
    }
}
