<?php

namespace DragoonBoots\A2B\Tests\Drivers\Destination;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\Destination\CsvDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\NoDestinationException;
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
        $uriParser = $this->createMock(Parser::class);
        $driver = new CsvDestinationDriver($uriParser);

        $this->expectException(NoDestinationException::class);
        $driver->read(['id' => 1]);
    }

    /**
     * @param string $destination
     * @param string $path
     *
     * @dataProvider csvSourceDataProvider
     */
    public function testConfigure(string $destination, string $path)
    {
        $this->setupDriver($path, $destination, $driver, $definition);

        $driver->configure($definition);
    }

    public function csvdataProvider()
    {
        $ret = $this->csvSourceDataProvider();

        $ret['new file'] = array_merge(
            $ret['new file'], [
                ['id' => 1],
                null,
            ]
        );

        $ret['existing file, new entity'] = array_merge(
            $ret['existing file'], [
                ['id' => 2],
                null,
            ]
        );

        $ret['existing file'] = array_merge(
            $ret['existing file'], [
                ['id' => 1],
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

    public function csvSourceDataProvider()
    {
        $ret = [];

        $newFilePath = vfsStream::url('data/new_dir/newfile.csv');
        $ret['new file'] = [
            'csv://'.$newFilePath,
            $newFilePath,
        ];

        $existingFilePath = vfsStream::url('data/existing_dir/existing_file.csv');
        $ret['existing file'] = [
            'csv://'.$existingFilePath,
            $existingFilePath,
        ];

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

    public function csvWriteDataProvider()
    {
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
            $newRecord,
            [$headerRow, $newRow]
        );

        // Existing file with appended row
        $existingRow = ['1', 'CsvDestinationDriver', 'Test', 'Case'];
        $ret['existing file, appended record'] = $ret['existing file'];
        array_push(
            $ret['existing file, appended record'],
            $newRecord,
            [$headerRow, $existingRow, $newRow]
        );

        // Existing file with modified row
        $modifiedRecord = $newRecord;
        $modifiedRecord['id'] = 1;
        $modifiedRow = array_values($modifiedRecord);
        $ret['existing file, modified record'] = $ret['existing file'];
        array_push(
            $ret['existing file, modified record'],
            $modifiedRecord,
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
