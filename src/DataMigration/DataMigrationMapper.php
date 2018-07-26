<?php


namespace DragoonBoots\A2B\DataMigration;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Exception\NoMappingForIdsException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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
     * DataMigrationMapper constructor.
     *
     * @param ConnectionFactory             $connectionFactory
     * @param ParameterBagInterface         $parameterBag
     * @param Inflector                     $inflector
     * @param DataMigrationManagerInterface $dataMigrationManager
     */
    public function __construct(
      ConnectionFactory $connectionFactory,
      ParameterBagInterface $parameterBag,
      Inflector $inflector,
      DataMigrationManagerInterface $dataMigrationManager
    ) {
        $dbPath = $parameterBag->resolveValue(['url' => 'sqlite:///%kernel.project_dir%/var/data_migration_map.sqlite']);
        $this->connection = $connectionFactory->createConnection($dbPath);

        $this->inflector = $inflector;
        $this->dataMigrationManager = $dataMigrationManager;
    }

    public function addMapping($migrationId, DataMigration $migrationDefinition, array $sourceIds, array $destIds)
    {
        $tableName = $this->getMappingTableName($migrationId);
        $mappingConformed = $this->mappingConformed[$migrationId] ?? false;
        if (!$mappingConformed) {
            $this->conformMappingTable($migrationId, $migrationDefinition);
            $this->mappingConformed[$migrationId] = true;
        }

        $q = $this->connection->createQueryBuilder();
        $q->insert($tableName);
        foreach ($sourceIds as $sourceId => $value) {
            $columnName = $this->getMappingColumnName($sourceId, self::MAPPING_SOURCE);
            $q->setValue($columnName, $q->createNamedParameter($value));
        }
        foreach ($destIds as $destId => $value) {
            $columnName = $this->getMappingColumnName($destId, self::MAPPING_DEST);
            $q->setValue($columnName, $q->createNamedParameter($value));
        }
        $q->execute();
    }

    /**
     * @param $migrationId
     *
     * @return string
     */
    protected function getMappingTableName($migrationId): string
    {
        return $this->inflector::tableize(
          str_replace(['/', '\\'], '_', $migrationId)
        );
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
                $table->setPrimaryKey(['id']);
            } else {
                throw $e;
            }
        }

        $sourceIdFields = $migrationDefinition->sourceIds;
        $destIdFields = $migrationDefinition->destinationIds;
        foreach ($sourceIdFields as $sourceIdField) {
            $this->conformMappingColumn($table, $sourceIdField, self::MAPPING_SOURCE);
        }
        foreach ($destIdFields as $destIdField) {
            $this->conformMappingColumn($table, $destIdField, self::MAPPING_DEST);
        }
        $this->conformMappingIndexes($table, $sourceIdFields, self::MAPPING_SOURCE);
        $this->conformMappingIndexes($table, $destIdFields, self::MAPPING_DEST);
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

        return sprintf('%s_%s', strtolower($type), $this->inflector::tableize($idField));
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
                $table->addUniqueIndex($indexCols, $indexName);
            }
        } catch (SchemaException $e) {
            // Create the index
            if ($e->getCode() == SchemaException::INDEX_DOESNT_EXIST) {
                $table->addUniqueIndex($indexCols, $indexName);
            } else {
                throw $e;
            }
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

        return $this->getMatchingIds($migrationId, $sourceIds, $definition->destinationIds);
    }

    /**
     * @param           $migrationId
     * @param array     $sourceIds
     * @param IdField[] $destIdFields
     *
     * @return array
     *   An array with the resulting ids.
     * @throws NoMappingForIdsException
     */
    protected function getMatchingIds($migrationId, array $sourceIds, array $destIdFields): array
    {
        // Important thing to remember in this function: "Source" and "Dest"
        // don't mean what you think they do.  It really means "querying ids"
        // and "resulting ids" respectively.
        $tableName = $this->getMappingTableName($migrationId);

        $q = $this->connection->createQueryBuilder();
        $q->from($tableName);

        foreach ($destIdFields as $destIdField) {
            $q->addSelect($q->createNamedParameter($this->getMappingColumnName($destIdField, self::MAPPING_DEST)));
        }

        foreach ($sourceIds as $sourceId => $value) {
            $columnName = $this->getMappingColumnName($sourceId, self::MAPPING_SOURCE);
            $q->andWhere(
              sprintf(
                '%s = %s',
                $q->createNamedParameter($columnName),
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

        return $result;
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

        return $this->getMatchingIds($migrationId, $destIds, $definition->sourceIds);
    }
}
