<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Factory\FinderFactory;
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

    const DEFAULT_OPTIONS = [
        'inline' => PHP_INT_MAX,
        'refs' => false,
        'flags' => [Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK],
    ];

    const INDENT_SPACES = 2;

    /**
     * @var YamlParser
     */
    protected $yamlParser;

    /**
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
    protected $options = self::DEFAULT_OPTIONS;

    /**
     * YamlDestinationDriver constructor.
     *
     * @param Parser        $uriParser
     * @param YamlParser    $yamlParser
     * @param YamlDumper    $yamlDumper
     * @param FinderFactory $finderFactory
     */
    public function __construct(Parser $uriParser, YamlParser $yamlParser, YamlDumper $yamlDumper, FinderFactory $finderFactory)
    {
        parent::__construct($uriParser);

        $this->yamlParser = $yamlParser;
        $this->yamlDumper = $yamlDumper;
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
        $this->options = self::DEFAULT_OPTIONS;

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
            $ids[] = $this->buildIdsFromFilePath($fileInfo);
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
    protected function buildIdsFromFilePath(SplFileInfo $fileInfo): array
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

        $count = $finder->count();
        if ($count > 1) {
            // The filesystem would normally enforce uniqueness here, however,
            // because both "yaml" and "yml" extensions are allowed, it's
            // conceivable that a file could exist with both extensions.
            throw new \RangeException(sprintf("More than one entity matched the ids:\n%s\n", var_export($destIds, true)));
        } elseif ($count == 1) {
            /** @var SplFileInfo $fileInfo */
            $iterator = $finder->getIterator();
            $iterator->rewind();
            $fileInfo = $iterator->current();
            $entity = $this->yamlParser->parse($fileInfo->getContents());
            $entity = $this->addIdsToEntity($destIds, $entity);

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
            $searchPath = ltrim(
                str_replace(
                    $this->destUri['path'],
                    '',
                    $this->buildFilePathFromIds($destIds, 'ya?ml')
                ), '/'
            );
            $finder->path(sprintf('`^%s$`', $searchPath));
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
    protected function buildFilePathFromIds(array $destIds, string $ext = 'yaml'): string
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
     * Add the given ids back to the entity
     *
     * Ids are removed from the entity when it is saved (as this information is
     * now stored in the path), so they need to be added back to the entity.
     *
     * @param array $destIds
     * @param array $entity
     *
     * @return array
     */
    protected function addIdsToEntity(array $destIds, array $entity)
    {
        foreach ($destIds as $destId => $value) {
            $entity[$destId] = $value;
        }

        return $entity;
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
            $destIds = $this->buildIdsFromFilePath($fileInfo);
            $entity = $this->addIdsToEntity($destIds, $entity);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function write($data)
    {
        $destIds = [];
        foreach ($this->destIds as $idField) {
            $destIds[$idField->getName()] = $this->resolveDestId($idField, $data[$idField->getName()]);

            // Remove the id from the data, as it will be represented in the
            // file path.
            unset($data[$idField->getName()]);
        }

        $yaml = $this->dumpYaml($data);
        if ($this->options['refs']) {
            $this->compileAnchors($data, $useAnchors);
            // Sort by increasing depth to ensure replacement search strings
            // can be found.
            uksort(
                $useAnchors,
                function (string $a, $b) {
                    return substr_count($a, '.') - substr_count($b, '.');
                }
            );
            $this->addRefs($yaml, $useAnchors);
        }

        $path = $this->buildFilePathFromIds($destIds);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $yaml);

        return $destIds;
    }

    /**
     * Dump the data into YAML format according to configured options.
     *
     * @param array $data
     * @param int   $depth
     *   The depth of this dump stage, for internal use.
     *
     * @return string
     */
    protected function dumpYaml(array $data, $depth = 0): string
    {
        $flagValue = 0;
        foreach ($this->options['flags'] as $flag) {
            $flagValue |= $flag;
        }
        $yaml = $this->yamlDumper->dump($data, $this->options['inline'], $depth * self::INDENT_SPACES, $flagValue);

        return $yaml;
    }

    /**
     * Create a list of possible anchors to use.
     *
     * @param array $data
     * @param array $useAnchors
     *   An array, passed by reference, to store a a list of anchors that should
     *   be used.
     * @param array $anchors
     *   An array, passed by reference, to store the possible anchors in.
     *   Anchors are named by separating their first path with a "."
     * @param array $path
     */
    protected function compileAnchors(array $data, ?array &$useAnchors, ?array &$anchors = null, array $path = [])
    {
        if (!isset($anchors)) {
            $anchors = [];
        }
        if (!isset($useAnchors)) {
            $useAnchors = [];
        }
        foreach ($data as $key => $value) {
            $valuePath = array_merge($path, [$key]);
            $anchor = implode('.', $valuePath);
            if (is_array($value)) {
                $yamlValue = $this->dumpYaml($value, count($path) + 1);
            } else {
                // Need to isolate the dumper's decision on quoting, so fake a
                // list and remove the list characters.
                $yamlValue = $this->dumpYaml([$value]);
                $yamlValue = str_replace('- ', '', $yamlValue);
            }
            $yamlValue = rtrim($yamlValue);

            $useAnchor = array_search($yamlValue, $anchors);
            if ($useAnchor !== false) {
                $useAnchors[$useAnchor] = $yamlValue;
            } else {
                $anchors[$anchor] = $yamlValue;
                if (is_array($value)) {
                    $this->compileAnchors($value, $useAnchors, $anchors, $valuePath);
                }
            }
        }
    }

    /**
     * Replace values with anchors where appropriate
     *
     * @param string $yaml
     *   The yaml string to act upon, passed by reference
     * @param array  $useAnchors
     */
    protected function addRefs(string &$yaml, array $useAnchors)
    {
        foreach ($useAnchors as $anchor => $value) {
            // Add the anchor on the first occurrence
            preg_match('`\s+'.preg_quote($value, '`').'\s+`', $yaml, $matches, PREG_OFFSET_CAPTURE);
            $pos = $matches[0][1]+1;
            $before = substr($yaml, 0, $pos - 1);
            $space = substr($yaml, $pos - 1, 1);
            $firstValueWithAnchor = ' &'.$anchor.$space.$value;
            $after = substr($yaml, $pos + strlen($value));

            // Replace later occurrences with an alias.
            $after = str_replace($space.$value, ' *'.$anchor, $after);
            $yaml = $before.$firstValueWithAnchor.$after;
        }
    }

    /**
     * Set an option for the YAML dumper.
     *
     * Valid options are:
     * - inline: The level at which the output switches from expanded
     *   (multiline) arrays to the inline representation.  Reference generation
     *   is not available with inline arrays.
     * - refs: Automatically generate YAML anchors and references.  *This is a
     *   slow process!*  See the [docs](https://dragoonboots.gitlab.io/a2b/Drivers/Destination/YamlDestinationDriver.html)
     *   for further detail.
     * - flags: Special flags for the YAML dumper.  See
     *   https://symfony.com/doc/current/components/yaml.html#advanced-usage-flags
     *   for valid flags.  *This will overwrite all flags, including defaults.*
     *   Use setFlag() and unsetFlag() to control flags.
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
            $this->options['flags'][] = $flag;
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
