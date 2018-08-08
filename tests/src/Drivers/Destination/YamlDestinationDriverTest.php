<?php

namespace DragoonBoots\A2B\Tests\Drivers\Destination;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\Destination\YamlDestinationDriver;
use DragoonBoots\A2B\Factory\FinderFactory;
use League\Uri\Parser;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Dumper as YamlDumper;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

class YamlDestinationDriverTest extends TestCase
{

    /**
     * @var Parser|MockObject
     */
    protected $uriParser;

    /**
     * @var YamlDumper
     */
    protected $yamlDumper;

    /**
     * @var YamlParser
     */
    protected $yamlParser;

    /**
     * @var FinderFactory|MockObject
     */
    protected $finderFactory;

    /**
     * @var Finder|MockObject
     */
    protected $finder;

    public function testConfigure()
    {
        $path = vfsStream::url('data/new_dir');
        $definition = new DataMigration(
            [
                'destination' => 'yaml://'.$path,
                'destinationIds' => [
                    new IdField(['name' => 'group', 'type' => 'string']),
                    new IdField(['name' => 'identifier', 'type' => 'string']),
                ],
            ]
        );

        $this->uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['scheme' => 'yaml', 'path' => $path]);

        $driver = new YamlDestinationDriver($this->uriParser, $this->yamlParser, $this->yamlDumper, $this->finderFactory);
        $refl = new \ReflectionClass($driver);
        $driver->configure($definition);

        $this->assertDirectoryIsWritable($path);

        $newInline = 5;
        $driver->setOption('inline', $newInline);
        $optionsProperty = $refl->getProperty('options');
        $optionsProperty->setAccessible(true);
        $this->assertEquals($newInline, $optionsProperty->getValue($driver)['inline']);
        $driver->setFlag(Yaml::DUMP_OBJECT_AS_MAP);
        $driver->unsetFlag(Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $this->assertEquals([Yaml::DUMP_OBJECT_AS_MAP], array_values($optionsProperty->getValue($driver)['flags']));
    }

    public function testRead()
    {
        $destIds = ['group' => 'test', 'identifier' => 'existing_file'];
        $expectedEntity = [
            'group' => 'test',
            'identifier' => 'existing_file',
            'field' => 'value',
            'list' => [
                'item1',
                'item2',
            ],
            'referenced_list' => [
                'item1',
                'item2',
            ],
        ];
        $path = vfsStream::url('data/existing_dir');
        $definition = new DataMigration(
            [
                'destination' => 'yaml://'.$path,
                'destinationIds' => [
                    new IdField(['name' => 'group', 'type' => 'string']),
                    new IdField(['name' => 'identifier', 'type' => 'string']),
                ],
            ]
        );

        $this->uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['scheme' => 'yaml', 'path' => $path]);

        $fileInfo = new SplFileInfo(vfsStream::url('data/existing_dir/test/existing_file.yaml'), 'test', 'test/existing_file.yaml');
        $this->finder->method('count')
            ->willReturn(1);
        $this->finder->method('getIterator')
            ->willReturn(new \ArrayIterator([$fileInfo]));

        $driver = new YamlDestinationDriver($this->uriParser, $this->yamlParser, $this->yamlDumper, $this->finderFactory);
        $driver->configure($definition);
        $foundEntity = $driver->read($destIds);
        $this->assertEquals($expectedEntity, $foundEntity);
    }

    public function testReadNonexistantEntity()
    {
        $destIds = ['group' => 'test', 'identifier' => 'nonexistent_file'];
        $path = vfsStream::url('data/nonexistent_dir');
        $definition = new DataMigration(
            [
                'destination' => 'yaml://'.$path,
                'destinationIds' => [
                    new IdField(['name' => 'group', 'type' => 'string']),
                    new IdField(['name' => 'identifier', 'type' => 'string']),
                ],
            ]
        );

        $this->uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['scheme' => 'yaml', 'path' => $path]);
        $this->finder->method('count')
            ->willReturn(0);
        $this->finder->expects($this->never())
            ->method('getIterator');

        $driver = new YamlDestinationDriver($this->uriParser, $this->yamlParser, $this->yamlDumper, $this->finderFactory);
        $driver->configure($definition);
        $foundEntity = $driver->read($destIds);
        $this->assertNull($foundEntity);
    }

    public function testReadMultipleResults()
    {
        $destIds = ['group' => 'test', 'identifier' => 'multiple_files'];
        $path = vfsStream::url('data/existing_dir');
        $definition = new DataMigration(
            [
                'destination' => 'yaml://'.$path,
                'destinationIds' => [
                    new IdField(['name' => 'group', 'type' => 'string']),
                    new IdField(['name' => 'identifier', 'type' => 'string']),
                ],
            ]
        );

        $this->uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['scheme' => 'yaml', 'path' => $path]);
        $this->finder->method('count')
            ->willReturn(2);
        $this->finder->expects($this->never())
            ->method('getIterator');

        $driver = new YamlDestinationDriver($this->uriParser, $this->yamlParser, $this->yamlDumper, $this->finderFactory);
        $driver->configure($definition);
        $this->expectException(\RangeException::class);
        $driver->read($destIds);
    }

    public function testReadMultiple()
    {
        $destIdSet = [
            ['group' => 'test', 'identifier' => 'existing_file'],
            ['group' => 'test', 'identifier' => 'other_file'],
        ];
        $expectedEntities = [
            [
                'group' => 'test',
                'identifier' => 'existing_file',
                'field' => 'value',
                'list' => [
                    'item1',
                    'item2',
                ],
                'referenced_list' => [
                    'item1',
                    'item2',
                ],
            ],
            [
                'group' => 'test',
                'identifier' => 'other_file',
                'field' => 'value',
                'list' => [
                    'item1',
                    'item2',
                ],
                'referenced_list' => [
                    'item1',
                    'item2',
                ],
            ],
        ];
        $path = vfsStream::url('data/existing_dir');
        $definition = new DataMigration(
            [
                'destination' => 'yaml://'.$path,
                'destinationIds' => [
                    new IdField(['name' => 'group', 'type' => 'string']),
                    new IdField(['name' => 'identifier', 'type' => 'string']),
                ],
            ]
        );

        $this->uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['scheme' => 'yaml', 'path' => $path]);

        $fileInfo = [
            new SplFileInfo(vfsStream::url('data/existing_dir/test/existing_file.yaml'), 'test', 'test/existing_file.yaml'),
            new SplFileInfo(vfsStream::url('data/existing_dir/test/other_file.yaml'), 'test', 'text/other_file.yaml'),
        ];
        $this->finder->method('count')
            ->willReturn(1);
        $this->finder->method('getIterator')
            ->willReturn(new \ArrayIterator($fileInfo));

        $driver = new YamlDestinationDriver($this->uriParser, $this->yamlParser, $this->yamlDumper, $this->finderFactory);
        $driver->configure($definition);
        $foundEntities = $driver->readMultiple($destIdSet);
        $this->assertEquals($expectedEntities, $foundEntities);
    }

    public function testGetExistingIds()
    {
        $destIdSet = [['group' => 'test', 'identifier' => 'existing_file']];
        $path = vfsStream::url('data/existing_dir');
        $definition = new DataMigration(
            [
                'destination' => 'yaml://'.$path,
                'destinationIds' => [
                    new IdField(['name' => 'group', 'type' => 'string']),
                    new IdField(['name' => 'identifier', 'type' => 'string']),
                ],
            ]
        );

        $this->uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['scheme' => 'yaml', 'path' => $path]);

        $fileInfo = new SplFileInfo(vfsStream::url('data/existing_dir/test/existing_file.yaml'), 'test', 'test/existing_file.yaml');
        $this->finder->method('count')
            ->willReturn(1);
        $this->finder->method('getIterator')
            ->willReturn(new \ArrayIterator([$fileInfo]));

        $driver = new YamlDestinationDriver($this->uriParser, $this->yamlParser, $this->yamlDumper, $this->finderFactory);
        $driver->configure($definition);
        $foundIds = $driver->getExistingIds();
        $this->assertEquals($destIdSet, $foundIds);
    }

    public function testWrite()
    {
        $destIds = ['group' => 'new_group', 'identifier' => 'new_file'];
        $newEntity = [
            'group' => 'new_group',
            'identifier' => 'new_file',
            'field' => 'value',
            'list' => [
                'item1',
                'item2',
            ],
            'referenced_list' => [
                'item1',
                'item2',
            ],
        ];
        $path = vfsStream::url('data/existing_dir');
        $definition = new DataMigration(
            [
                'destination' => 'yaml://'.$path,
                'destinationIds' => [
                    new IdField(['name' => 'group', 'type' => 'string']),
                    new IdField(['name' => 'identifier', 'type' => 'string']),
                ],
            ]
        );

        $this->uriParser->expects($this->once())
            ->method('parse')
            ->willReturn(['scheme' => 'yaml', 'path' => $path]);

        $driver = new YamlDestinationDriver($this->uriParser, $this->yamlParser, $this->yamlDumper, $this->finderFactory);
        $driver->configure($definition);
        $newIds = $driver->write($newEntity);

        // Test proper ids are returned
        $this->assertEquals($destIds, $newIds);

        // Test file contents are written properly
        $driver->flush();
        $driver->flush();
        $innerPath = str_replace('vfs://', '', $path);
        /** @var vfsStreamFile|null $file */
        $file = vfsStreamWrapper::getRoot()
            ->getChild($innerPath.'/new_group/new_file.yaml');
        $this->assertNotNull($file, 'File was not copied to destination.');
        $writtenData = Yaml::parse($file->getContent());
        // The written data shouldn't have any ids written to it.
        $newEntityWithoutIds = $newEntity;
        foreach (array_keys($destIds) as $idName) {
            unset($newEntityWithoutIds[$idName]);
        }
        $this->assertEquals($newEntityWithoutIds, $writtenData);
    }

    protected function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('data'));
        vfsStream::copyFromFileSystem(TEST_RESOURCES_ROOT.'/Drivers/Destination/YamlDestinationDriverTest');

        $this->uriParser = $this->createMock(Parser::class);

        $this->yamlDumper = new YamlDumper(2);

        $this->yamlParser = new YamlParser();

        $this->finder = $this->createMock(Finder::class);
        // Create the fluent interface for the finder
        $finderMethodBlacklist = [
            '__construct',
            'getIterator',
            'hasResults',
            'count',
        ];
        foreach ((new \ReflectionClass(Finder::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (!$reflectionMethod->isStatic() && !in_array($reflectionMethod->getName(), $finderMethodBlacklist)) {
                $this->finder->method($reflectionMethod->getName())
                    ->willReturnSelf();
            }
        }
        $this->finderFactory = $this->createMock(FinderFactory::class);
        $this->finderFactory->method('get')->willReturn($this->finder);
    }
}
