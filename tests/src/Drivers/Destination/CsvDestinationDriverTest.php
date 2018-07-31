<?php

namespace DragoonBoots\A2B\Tests\Drivers\Destination;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\Destination\CsvDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\NoIdSetException;
use League\Uri\Parser;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\TestCase;

class CsvDestinationDriverTest extends TestCase
{

    /**
     * @param string $destination
     * @param string $path
     * @param array  $destIds
     * @param mixed  $currentEntity
     *
     * @dataProvider csvDataProvider
     */
    public function testRead(string $destination, string $path, array $destIds, $currentEntity)
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($path, $destination, $driver, $definition);
        $driver->configure($definition);

        $this->assertEquals($currentEntity, $driver->read($destIds));
    }

    /**
     * @param string                          $path
     * @param string                          $destination
     * @param DestinationDriverInterface|null $driver
     * @param DataMigration|null              $definition
     */
    protected function setupDriver(string $path, string $destination, ?DestinationDriverInterface &$driver = null, ?DataMigration &$definition = null): void
    {
        $uriParser = $this->createMock(Parser::class);
        $uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['path' => $path]);
        $definition = new DataMigration();
        $definition->destination = $destination;
        $definition->destinationIds = [
            (new IdField())->setType('int')->setName('id'),
        ];
        $driver = new CsvDestinationDriver($uriParser);
    }

    public function testReadBad()
    {
        $path = vfsStream::url('data/existing_dir/malformed_file.csv');
        $destination = 'csv://'.$path;
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($path, $destination, $driver, $definition);
        $driver->configure($definition);

        $this->expectException(\RangeException::class);
        $driver->read(['id' => 1]);
    }

    /**
     * @param string $destination
     * @param string $path
     * @param array  $destIdSet
     * @param        $entities
     *
     * @dataProvider csvMultipleDataProvider
     */
    public function testReadMultiple(string $destination, string $path, array $destIdSet, $entities)
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($path, $destination, $driver, $definition);
        $driver->configure($definition);

        $this->assertEquals($entities, $driver->readMultiple($destIdSet));
    }

    /**
     * @param string $destination
     * @param string $path
     *
     * @dataProvider csvSourceDataProvider
     */
    public function testConfigure(string $destination, string $path)
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($path, $destination, $driver, $definition);

        $driver->configure($definition);
    }

    /**
     * @param string $destination
     * @param string $path
     * @param array  $existingIds
     *
     * @dataProvider csvIdsDataProvider
     */
    public function testGetCurrentIds(string $destination, string $path, array $existingIds)
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($path, $destination, $driver, $definition);
        $driver->configure($definition);

        $this->assertEquals($existingIds, $driver->getExistingIds());
    }

    public function csvIdsDataProvider()
    {
        // destination and path
        $ret = $this->csvSourceDataProvider();

        $ret['new file'] = array_merge(
            $ret['new file'], [
                // existingIds
                [],
            ]
        );

        $ret['existing file'] = array_merge(
            $ret['existing file'], [
                // existingIds
                [
                    ['id' => 1],
                    ['id' => 2],
                ],
            ]
        );

        return $ret;
    }

    public function csvSourceDataProvider()
    {
        $ret = [];

        $newFilePath = vfsStream::url('data/new_dir/newfile.csv');
        $ret['new file'] = [
            // destination
            'csv://'.$newFilePath,
            // path
            $newFilePath,
        ];

        $existingFilePath = vfsStream::url('data/existing_dir/existing_file.csv');
        $ret['existing file'] = [
            // destination
            'csv://'.$existingFilePath,
            // path
            $existingFilePath,
        ];

        return $ret;
    }

    public function csvDataProvider()
    {
        // destination and path
        $ret = $this->csvSourceDataProvider();

        $ret['new file'] = array_merge(
            $ret['new file'], [
                // destIds
                ['id' => 1],
                // currentEntity
                null,
            ]
        );

        $ret['existing file, new entity'] = array_merge(
            $ret['existing file'], [
                // destIds
                ['id' => 3],
                // currentEntity
                null,
            ]
        );

        $ret['existing file'] = array_merge(
            $ret['existing file'], [
                // destIds
                ['id' => 1],
                // currentEntity
                [
                    'id' => '1',
                    'field0' => 'CsvDestinationDriver',
                    'field1' => 'Test',
                    'field2' => 'Case',
                ],
            ]
        );

        return $ret;
    }

    public function csvMultipleDataProvider()
    {

        // destination and path
        $ret = $this->csvSourceDataProvider();

        $ret['new file'] = array_merge(
            $ret['new file'], [
                // destIdSet
                [['id' => 1], ['id' => 2]],
                // entities
                [],
            ]
        );

        $ret['existing file, new entity'] = array_merge(
            $ret['existing file'], [
                // destIdSet
                [['id' => 3]],
                // entities
                [],
            ]
        );

        $ret['existing file'] = array_merge(
            $ret['existing file'], [
                // destIdSet
                [['id' => 1], ['id' => 2]],
                // entities
                [
                    [
                        'id' => '1',
                        'field0' => 'CsvDestinationDriver',
                        'field1' => 'Test',
                        'field2' => 'Case',
                    ],
                    [
                        'id' => '2',
                        'field0' => 'Yet',
                        'field1' => 'Another',
                        'field2' => 'Row',
                    ],
                ],
            ]
        );

        return $ret;
    }

    /**
     * @param string $destination
     * @param string $path
     * @param array  $newRecord
     * @param string $finalData
     *
     * @dataProvider csvWriteDataProvider
     */
    public function testWrite(string $destination, string $path, array $newRecord, array $finalData)
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($path, $destination, $driver, $definition);

        $driver->configure($definition);

        // Test proper ids are returned for mapping
        $expectedIds = ['id' => $newRecord['id']];
        $this->assertEquals($expectedIds, $driver->write($newRecord));

        // Test file contents are written properly.
        $driver->flush();
        $innerPath = str_replace('vfs://', '', $path);
        /** @var vfsStreamFile|null $file */
        $file = vfsStreamWrapper::getRoot()->getChild($innerPath);
        $this->assertNotNull($file, 'File was not copied to destination.');
        $writtenData = [];
        foreach (explode("\n", rtrim($file->getContent())) as $row) {
            $writtenData[] = str_getcsv($row);
        }
        $this->assertEquals($finalData, $writtenData);
    }

    /**
     * @param string $destination
     * @param string $path
     *
     * @dataProvider csvSourceDataProvider
     */
    public function testWriteBad(string $destination, string $path)
    {
        /** @var DestinationDriverInterface $driver */
        /** @var DataMigration $definition */
        $this->setupDriver($path, $destination, $driver, $definition);

        $driver->configure($definition);

        $newRecord = [
            // Missing id field
            'field0' => 'This is',
            'field1' => 'a new',
            'field2' => 'record',
        ];
        $this->expectException(NoIdSetException::class);
        $driver->write($newRecord);
    }

    public function csvWriteDataProvider()
    {
        // destination and path
        $ret = $this->csvSourceDataProvider();

        $headerRow = ['id', 'field0', 'field1', 'field2'];
        $newRecord = [
            'id' => 11,
            'field0' => 'This is',
            'field1' => 'a new',
            'field2' => 'record',
        ];
        $newRow = array_values($newRecord);

        // New file with all new rows
        array_push(
            $ret['new file'],
            // newRecord
            $newRecord,
            // final data
            [$headerRow, $newRow]
        );

        // Existing file with modified row
        $modifiedRecord = $newRecord;
        $modifiedRecord['id'] = 1;
        $modifiedRow = array_values($modifiedRecord);
        $ret['existing file, modified record'] = $ret['existing file'];
        array_push(
            $ret['existing file, modified record'],
            // newRecord
            $modifiedRecord,
            // final data
            [$headerRow, $modifiedRow]
        );

        unset($ret['existing file']);

        return $ret;
    }

    protected function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('data'));
        vfsStream::copyFromFileSystem(TEST_RESOURCES_ROOT.'/Drivers/Destination/CsvDestinationDriverTest');
    }
}
