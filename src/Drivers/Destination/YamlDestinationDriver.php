<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\Destination\Yaml\YamlDumper;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\YamlDriverTrait;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Factory\FinderFactory;
use League\Uri\Parser;
use Symfony\Component\Finder\Finder;
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
        $entityFiles = $this->findEntities([$destIds]);
        if (empty($entityFiles)) {
            return null;
        }

        $entityFile = array_pop($entityFiles);
        $entity = $this->yamlParser->parse(file_get_contents($entityFile->getPathname()));
        $entity = $this->addIdsToEntity($destIds, $entity);

        return $entity;
    }

    /**
     * @param array $destIdSet
     *
     * @return \SplFileInfo[]
     */
    protected function findEntities(array $destIdSet): array
    {
        $entityFiles = [];
        foreach ($destIdSet as $destIds) {
            $matched = 0;
            foreach (['yaml', 'yml'] as $ext) {
                $searchPath = $this->buildFilePathFromIds($destIds, $this->destUri['path'], $ext);
                if (file_exists($searchPath)) {
                    $matched++;
                    $entityFiles[] = new \SplFileInfo($searchPath);
                }
            }
            if ($matched > 1) {
                // The filesystem would normally enforce uniqueness here, however,
                // because both "yaml" and "yml" extensions are allowed, it's
                // conceivable that a file could exist with both extensions.
                throw new \RangeException(sprintf("More than one entity matched the ids:\n%s\n", var_export($destIds, true)));
            }
        }

        return $entityFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $destIdSet)
    {
        $entityFiles = $this->findEntities($destIdSet);

        $entities = [];
        foreach ($entityFiles as $fileInfo) {
            $entity = $this->yamlParser->parse(file_get_contents($fileInfo->getPathname()));
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

        if ($this->options['refs']) {
            $useAnchors = $this->compileAnchors($data);
        } else {
            $useAnchors = null;
        }
        $yaml = $this->dumpYaml($data, $useAnchors);
        // Ensure file always has a newline at the end.
        $yaml = rtrim($yaml)."\n";

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
     * @param array      $data
     * @param array|null $useAnchors
     * @param int        $depth
     *   The depth of this dump stage, for internal use.
     *
     * @return string
     */
    protected function dumpYaml(array $data, ?array $useAnchors, $depth = 0): string
    {
        $flagValue = 0;
        foreach ($this->options['flags'] as $flag) {
            $flagValue |= $flag;
        }
        $yaml = $this->yamlDumper->dump($data, $this->options['inline'], $depth * self::INDENT_SPACES, $flagValue, $useAnchors);

        return $yaml;
    }

    /**
     * Create a list of possible anchors to use.
     *
     * @param array           $data
     * @param array|null      $useAnchors
     *   An array, passed by reference, to store a a list of anchors that should
     *   be used.
     * @param array|null      $anchors
     *   An array, passed by reference, to store the possible anchors in.
     *   Anchors are named by separating their first path with a "."
     * @param Collection|null $path
     *
     * @return array
     *   A map of anchor names and their values.
     */
    protected function compileAnchors(array $data, ?array &$useAnchors = null, ?array &$anchors = null, ?Collection $path = null)
    {
        if (!isset($anchors)) {
            $anchors = [];
        }
        if (!isset($useAnchors)) {
            $useAnchors = [];
        }
        if (!isset($path)) {
            $path = new ArrayCollection();
        }
        foreach ($data as $key => $value) {
            $valuePath = clone $path;
            $valuePath->add($key);
            $anchorKey = implode('.', $valuePath->toArray());

            // Should an anchor be built for this path?
            $include = $this->options['refs']['include'] ?? ['`.+`'];
            $exclude = $this->options['refs']['exclude'] ?? [];
            $buildAnchor = false;
            foreach ($include as $includePattern) {
                $buildAnchor = (preg_match($includePattern, $anchorKey) === 1);
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
                $buildAnchor = (preg_match($excludePattern, $anchorKey) === 0);
                if (!$buildAnchor) {
                    break;
                }
            }
            if (!$buildAnchor) {
                continue;
            }

            // Use the anchor if this is an array or the final key in the key
            // path matches (this means these values are likely similar
            // contextually.
            $useAnchor = false;
            foreach ($anchors as $checkAnchorKey => $checkValue) {
                $anchorPath = new ArrayCollection(explode('.', $checkAnchorKey));
                if ($checkValue === $value && (is_array($value) || $anchorPath->last() === $valuePath->last())) {
                    $useAnchor = $checkAnchorKey;
                    break;
                }
            }

            if ($useAnchor !== false) {
                $useAnchors[$useAnchor] = $value;
            } else {
                $anchors[$anchorKey] = $value;
                if (is_array($value)) {
                    $this->compileAnchors($value, $useAnchors, $anchors, $valuePath);
                }
            }
        }

        return $useAnchors;
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
