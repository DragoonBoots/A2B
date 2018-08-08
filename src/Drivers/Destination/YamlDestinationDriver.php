<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Factory\FinderFactory;
use DragoonBoots\A2B\Factory\YamlDumperFactory;
use League\Uri\Parser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Dumper as YamlDumper;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

/**
 * Destination driver for yaml files
 *
 * @Driver({"yaml", "yml"})
 */
class YamlDestinationDriver extends AbstractDestinationDriver implements DestinationDriverInterface
{

    /**
     * @var YamlParser
     */
    protected $yamlParser;

    /**
     * @var YamlDumperFactory
     */
    protected $yamlDumperFactory;

    /**
     * This dumper is created per migration
     *
     * @var YamlDumper
     */
    protected $yamlDumper;

    /**
     * @var FinderFactory
     */
    protected $finderFactory;

    /**
     * @var Finder
     */
    protected $finder;

    /**
     * YAML dumper options
     *
     * @var array
     */
    protected $options = [
        'inline' => 3,
        'indent' => 2,
        'flags' => [Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK],
    ];

    /**
     * YamlDestinationDriver constructor.
     *
     * @param Parser            $uriParser
     * @param YamlParser        $yamlParser
     * @param YamlDumperFactory $yamlDumperFactory
     * @param FinderFactory     $finderFactory
     */
    public function __construct(Parser $uriParser, YamlParser $yamlParser, YamlDumperFactory $yamlDumperFactory, FinderFactory $finderFactory)
    {
        parent::__construct($uriParser);

        $this->yamlParser = $yamlParser;
        $this->yamlDumperFactory = $yamlDumperFactory;
        $this->finderFactory = $finderFactory;
    }

    /**
     * Set the destination of this driver.
     *
     * @param DataMigration $definition
     *   The migration definition.
     *
     * @throws BadUriException
     *   Thrown when the given URI is not valid.
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);

        $this->yamlDumper = null;

        if (!is_dir($this->destUri['path'])) {
            mkdir($this->destUri['path'], 0755, true);
        }
        $this->finder = $this->finderFactory->get()
            ->files()
            ->in($this->destUri['path'])
            ->name('`.+\.ya?ml$`')
            ->followLinks()
            ->ignoreDotFiles(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getExistingIds(): array
    {
        $ids = [];
        foreach ($this->finder->getIterator() as $fileInfo) {
            $ids[] = $this->getFileId($fileInfo);
        }

        return $ids;
    }

    /**
     * Get id field values from the file path.
     *
     * The Id is built as in this example:
     * - Id fields: a, b, c
     * - Path: w/x/y/z.yaml
     * - Result: a=x, b=y, c=z (note that w is ignored because there are only
     *   3 id fields.
     *
     * @param SplFileInfo $fileInfo
     *
     * @return array
     */
    protected function getFileId($fileInfo): array
    {
        $pathParts = explode('/', $fileInfo->getPath());
        $pathParts[] = $fileInfo->getBasename('.'.$fileInfo->getExtension());

        $id = [];
        foreach (array_reverse($this->destIds) as $idField) {
            /** @var IdField $idField */
            $id[$idField->getName()] = $this->resolveDestId($idField, array_pop($pathParts));
        }

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $destIds)
    {
        $finder = $this->findEntities([$destIds]);

        if ($finder->count() > 1) {
            throw new \RangeException(sprintf("More than one entity matched the ids:\n%s\n", var_export($destIds, true)));
        } elseif ($finder->count() == 1) {
            /** @var SplFileInfo $fileInfo */
            $fileInfo = $finder->getIterator()->current();
            $entity = $this->yamlParser->parse($fileInfo->getContents());

            return $entity;
        }

        return null;
    }

    /**
     * @param array $destIdSet
     *
     * @return Finder
     */
    protected function findEntities(array $destIdSet)
    {
        $finder = $this->finderFactory->get()
            ->files()
            ->in($this->destUri['path'])
            ->followLinks()
            ->ignoreDotFiles(true);

        foreach ($destIdSet as $destIds) {
            $finder->path(sprintf('`%s$`', $this->buildFilePath($destIds, 'ya?ml')));
        }

        return $finder;
    }

    /**
     * Build the file path an entity with the given ids will be stored at.
     *
     * @param array  $destIds
     * @param string $ext
     *   File extension to use.  Defaults to "yaml".
     *
     * @return string
     */
    protected function buildFilePath(array $destIds, string $ext = 'yaml'): string
    {
        $pathParts = [];
        foreach ($destIds as $destId => $value) {
            $pathParts[] = $value;
        }

        $fileName = array_pop($pathParts).'.'.$ext;
        $filePath = sprintf('%s/%s/%s', $this->destUri['path'], implode('/', $pathParts), $fileName);

        return $filePath;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $destIdSet)
    {
        $finder = $this->findEntities($destIdSet);

        $entities = [];
        foreach ($finder as $fileInfo) {
            $entity = $this->yamlParser->parse($fileInfo->getContents());
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function write($data)
    {
        if (!$this->yamlDumper) {
            $this->yamlDumper = $this->yamlDumperFactory->get($this->options['indent']);
        }

        $destIds = [];
        foreach ($this->destIds as $idField) {
            $destIds[$idField->getName()] = $this->resolveDestId($idField, $data[$idField->getName()]);

            // Remove the id from the data, as it will be represented in the
            // file path.
            unset($data[$idField->getName()]);
        }

        $flagValue = 0;
        foreach ($this->options['flags'] as $flag) {
            $flagValue |= $flag;
        }
        $yaml = $this->yamlDumper->dump($data, $this->options['inline'], 0, $flagValue);

        $path = $this->buildFilePath($destIds);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $yaml, LOCK_EX);

        return $destIds;
    }

    /**
     * Set an option for the YAML dumper.
     *
     * Valid options are:
     * - inline: The level at which the output switches from expanded
     *   (multiline) arrays to the inline representation.
     * - indent:  The indentation level.
     * - flags: Special flags for the YAML dumper.  See
     *   https://symfony.com/doc/current/components/yaml.html#advanced-usage-flags
     *   for valid flags.
     *
     * @param string $option
     * @param mixed  $value
     *
     * @return self
     */
    public function setOption(string $option, $value)
    {
        $this->options[$option] = $value;

        return $this;
    }

    /**
     * Set a special flag for the YAML dumper.
     *
     * Call this once per flag to allow them to be merged with the defaults.
     *
     * @see https://symfony.com/doc/current/components/yaml.html#advanced-usage-flags
     *
     * @param $flag
     *
     * @return self
     */
    public function setFlag($flag)
    {
        if (!in_array($flag, $this->options['flags'])) {
            $this->options[] = $flag;
        }

        return $this;
    }

    /**
     * Unset a special flag for the YAML dumper.
     *
     * Call this once per flag to allow them to be merged with the defaults.
     *
     * @see https://symfony.com/doc/current/components/yaml.html#advanced-usage-flags
     *
     * @param $flag
     *
     * @return $this
     */
    public function unsetFlag($flag)
    {
        $key = array_search($flag, $this->options['flags']);
        if ($key !== false) {
            unset($this->options['flags'][$key]);
        }

        return $this;
    }
}
