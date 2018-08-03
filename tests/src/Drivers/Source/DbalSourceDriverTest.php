<?php

namespace DragoonBoots\A2B\Tests\Drivers;


use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDOSqlite;
use Doctrine\DBAL\Driver\Statement;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\Source\DbalSourceDriver;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use League\Uri\Parser;
use PHPUnit\Framework\TestCase;

class DbalSourceDriverTest extends TestCase
{

    protected const SOURCE_PATH = TEST_RESOURCES_ROOT.'/Drivers/Source/DbalSourceDriverTest/source.sqlite';

    protected const SOURCE_URL = 'sqlite://'.self::SOURCE_PATH;

    public function testConfigure()
    {
        /** @var DataMigration $definition */
        /** @var Connection $connection */
        /** @var SourceDriverInterface $driver */
        $this->setupDriver($definition, $connection, $driver);
        $driver->configure($definition);
    }

    /**
     * @param DataMigration|null         $definition
     * @param Connection|null            $connection
     * @param SourceDriverInterface|null $driver
     */
    protected function setupDriver(?DataMigration &$definition = null, ?Connection &$connection = null, ?SourceDriverInterface &$driver = null): void
    {
        if (!isset($definition)) {
            $definition = new DataMigration();
            $definition->setSource(self::SOURCE_URL)
                ->setSourceIds(
                    [
                        (new IdField())
                            ->setName('id')
                            ->setType('int'),
                    ]
                );
        }
        if (!isset($connection)) {
            $connection = $this->createMock(Connection::class);
        }
        $uriParser = $this->createMock(Parser::class);
        $connectionFactory = $this->createMock(ConnectionFactory::class);
        $connectionFactory->expects($this->once())
            ->method('createConnection')
            ->withAnyParameters()
            ->willReturnCallback(
                function ($params) use ($connection) {
                    if ($params == ['url' => self::SOURCE_URL]) {
                        return $connection;
                    } else {
                        throw new DBALException();
                    }
                }
            );

        $driver = new DbalSourceDriver($uriParser, $connectionFactory);
    }

    public function testConfigureBad()
    {
        $definition = new DataMigration();
        $definition->setSource('sqlite://this/db/is/fake.sqlite')
            ->setSourceIds(
                [
                    (new IdField())
                        ->setName('id')
                        ->setType('int'),
                ]
            );
        /** @var DataMigration $definition */
        /** @var Connection $connection */
        /** @var SourceDriverInterface $driver */
        $this->setupDriver($definition, $connection, $driver);
        $this->expectException(BadUriException::class);
        $driver->configure($definition);
    }

    public function testSetStatement()
    {
        /** @var DataMigration $definition */
        /** @var Connection $connection */
        /** @var SourceDriverInterface $driver */
        $this->setupDriver($definition, $connection, $driver);
        $driver->configure($definition);

        $statement = $this->createMock(Statement::class);
        $statement->expects($this->once())->method('execute');
        $driver->setStatement($statement);

        $this->assertSame($statement, $driver->getIterator());
    }

    public function testGetConnection()
    {
        /** @var DataMigration $definition */
        /** @var Connection $connection */
        /** @var DbalSourceDriver $driver */
        $this->setupDriver($definition, $connection, $driver);
        $driver->configure($definition);

        $this->assertSame($connection, $driver->getConnection());
    }

    /**
     * Functional test involving actual connections and data
     */
    public function testGetIterator()
    {
        $expected = [
            [
                'id' => 1,
                'description' => 'Test row 1',
            ],
        ];

        $connection = new \Doctrine\DBAL\Connection(
            ['path' => self::SOURCE_PATH],
            new PdoSqlite\Driver()
        );
        $connection->connect();
        /** @var DataMigration $definition */
        /** @var Connection $connection */
        /** @var DbalSourceDriver $driver */
        $this->setupDriver($definition, $connection, $driver);
        $driver->configure($definition);
        $statement = $connection->prepare(
            <<< SQL
SELECT *
FROM "test_table";
SQL
        );
        $driver->setStatement($statement);
        $this->assertEquals($expected, $driver->getIterator()->fetchAll());
    }
}
