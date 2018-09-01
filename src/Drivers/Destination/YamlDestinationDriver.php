<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\YamlDriverTrait;
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

    use YamlDriverTrait;

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
            $ids[] = $this->buildIdsFromFilePath($fileInfo, $this->destIds);
        }

        return $ids;
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
                    $this->buildFilePathFromIds($destIds, $this->destUri['path'], 'ya?ml')
                ), '/'
            );
            $finder->path(sprintf('`^%s$`', $searchPath));
        }

        return $finder;
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
            $destIds = $this->buildIdsFromFilePath($fileInfo, $this->destIds);
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
            $destIds[$idField->getName()] = $this->resolveIdType($idField, $data[$idField->getName()]);

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

        $path = $this->buildFilePathFromIds($destIds, $this->destUri['path']);

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

            // Should an anchor be built for this path?
            $include = $this->options['refs']['include'] ?? ['`.+`'];
            $exclude = $this->options['refs']['exclude'] ?? [];
            $buildAnchor = false;
            foreach ($include as $includePattern) {
                $buildAnchor = (preg_match($includePattern, $anchor) === 1);
                if ($buildAnchor) {
                    break;
                }
            }
            // None of the include patterns match, so don't bother
            // checking the exclude patterns.
            if (!$buildAnchor) {
                continue;
            }
            foreach ($exclude as $excludePattern) {
                $buildAnchor = (preg_match($excludePattern, $anchor) === 0);
                if (!$buildAnchor) {
                    break;
                }
            }
            if (!$buildAnchor) {
                continue;
            }

            if (is_array($value)) {
                $yamlValue = $this->dumpYaml($value, count($path) + 1);
            } else {
                // Need to isolate the dumper's decision on quoting, so fake a
                // list and remove the list characters.
                $yamlValue = $this->dumpYaml([$value]);
                $yamlValue = preg_replace('`^\s*- `', '', $yamlValue, 1);
                $yamlValue = str_replace("\n", "\n".str_repeat(' ', count($path) * self::INDENT_SPACES), $yamlValue);
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
            if (empty($matches)) {
                // $useAnchors should contain only anchors that exist and should
                // be used.  If one of those anchors can't be found, it probably
                // means the YAML file has been mutated improperly and is no
                // longer valid YAML, with or without the problem anchor.
                throw new \LogicException(
                    implode(
                        "\n", [
                            'Could not replace value with an anchor reference.  This is probably a bug.',
                            'Anchor: '.var_export($anchor, true),
                            'Value: '.var_export($value, true),
                            'Current YAML state:'.var_export($yaml, true),
                        ]
                    )
                );
            }
            $pos = $matches[0][1] + 1;
            $before = substr($yaml, 0, $pos - 1);
            $space = substr($yaml, $pos - 1, 1);
            $firstValueWithAnchor = ' &'.$anchor.$space.$value;
            $after = substr($yaml, $pos + strlen($value));

            // Replace later occurrences with an alias.
            $after = str_replace(':'.$space.$value, ': *'.$anchor, $after);
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
