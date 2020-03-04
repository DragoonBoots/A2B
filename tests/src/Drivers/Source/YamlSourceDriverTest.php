<?php

namespace DragoonBoots\A2B\Tests\Drivers\Source;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\Source\YamlSourceDriver;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Factory\FinderFactory;
use DragoonBoots\A2B\Tests\Drivers\FinderTestTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Parser as YamlParser;

class YamlSourceDriverTest extends TestCase
{

    use FinderTestTrait;

    /**
     * @var DataMigration
     */
    protected $definition;

    /**
     * @var YamlSourceDriver
     */
    protected $driver;

    /**
     * @var FinderFactory|MockObject
     */
    protected $finderFactory;

    /**
     * @var Finder|MockObject
     */
    protected $finder;

    /**
     * @var YamlParser
     */
    protected $yamlParser;

    public function testConfigure()
    {
        $this->setupDriver();
        $this->driver->configure($this->definition);
    }

    protected function setupDriver(FinderFactory $finderFactory = null)
    {
        $url = vfsStream::url('data');
        $sourceUri = $url;
        $this->definition = new DataMigration(
            [
                'source' => $sourceUri,
                'sourceIds' => [
                    new IdField(
                        [
                            'name' => 'group',
                            'type' => 'string',
                        ]
                    ),
                    new IdField(
                        [
                            'name' => 'identifier',
                            'type' => 'string',
                        ]
                    ),
                ],
            ]
        );

        $this->yamlParser = new YamlParser();

        $this->finder = $this->createMock(Finder::class);
        $this->finder = $this->buildFinderMock($this->finder);
        $files = [
            new SplFileInfo(vfsStream::url('data/group1/file1.yaml'), 'group1', 'group1/file1.yaml'),
            new SplFileInfo(vfsStream::url('data/group1/file2.yaml'), 'group1', 'group1/file2.yaml'),
            new SplFileInfo(vfsStream::url('data/group2/file3.yaml'), 'group2', 'group2/file3.yaml'),
        ];
        $this->finder->method('count')
            ->willReturn(count($files));
        $this->finder->method('getIterator')
            ->willReturn($files);

        if (!isset($finderFactory)) {
            $finderFactory = $this->createMock(FinderFactory::class);
            $finderFactory->expects($this->once())
                ->method('get')
                ->willReturn($this->finder);
        }
        $this->finderFactory = $finderFactory;

        $this->driver = new YamlSourceDriver($this->yamlParser, $this->finderFactory);
    }

    public function testConfigureBad()
    {
        $badPath = 'nonexistent/directory';
        $badUri = $badPath;

        $finderFactory = $this->createMock(FinderFactory::class);
        $finderFactory->expects($this->never())
            ->method('get');
        $this->setupDriver($finderFactory);
        $this->definition->setSource($badUri);

        $this->expectException(BadUriException::class);
        $this->driver->configure($this->definition);
    }

    public function testCount()
    {
        $expected = 3;

        $this->setupDriver();
        $this->driver->configure($this->definition);

        $this->assertEquals($expected, $this->driver->count());
    }

    public function testGetIterator()
    {
        $this->setupDriver();
        $this->driver->configure($this->definition);

        $expected = [
            [
                'group' => 'group1',
                'identifier' => 'file1',
                'field1' => 'Test',
                'field2' => '1',
            ],
            [
                'group' => 'group1',
                'identifier' => 'file2',
                'field1' => 'Test',
                'field2' => '2',
            ],
            [
                'group' => 'group2',
                'identifier' => 'file3',
                'field1' => 'Test',
                'field2' => '3',
            ],
        ];
        $actual = [];
        foreach ($this->driver->getIterator() as $entity) {
            $actual[] = $entity;
        }

        $this->assertEquals($expected, $actual);
    }

    protected function setUp(): void
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('data'));
        vfsStream::copyFromFileSystem(TEST_RESOURCES_ROOT.'/Drivers/Source/YamlSourceDriverTest');
    }
}
