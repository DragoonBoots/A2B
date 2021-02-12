<?php


namespace DragoonBoots\A2B\Drivers\Destination;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\YamlDriverTrait;
use DragoonBoots\A2B\Factory\FinderFactory;
use DragoonBoots\YamlFormatter\AnchorBuilder\AnchorBuilderOptions;
use DragoonBoots\YamlFormatter\Yaml\YamlDumper;
use DragoonBoots\YamlFormatter\Yaml\YamlDumperOptions;
use RangeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Destination driver for yaml files
 *
 * @Driver()
 */
class YamlDestinationDriver extends AbstractDestinationDriver implements DestinationDriverInterface
{

    use YamlDriverTrait;

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

    private const OPTION_INDENT = 'indent';
    private const OPTION_REFS = 'refs';
    private const OPTION_REFS_INCLUDE = 'include';
    private const OPTION_REFS_EXCLUDE = 'exclude';
    private const OPTIONS = [
        self::OPTION_INDENT,
        self::OPTION_REFS,
    ];
    private const OPTIONS_REFS = [
        self::OPTION_REFS_INCLUDE,
        self::OPTION_REFS_EXCLUDE,
    ];

    /**
     * Dumper options
     *
     * @var array
     */
    private $options = [];

    /**
     * YamlDestinationDriver constructor.
     *
     * @param YamlParser $yamlParser
     * @param YamlDumper $yamlDumper
     * @param FinderFactory $finderFactory
     */
    public function __construct(YamlParser $yamlParser, YamlDumper $yamlDumper, FinderFactory $finderFactory)
    {
        parent::__construct();

        $this->yamlParser = $yamlParser;
        $this->yamlDumper = $yamlDumper;
        $this->finderFactory = $finderFactory;
    }

    /**
     * Set the destination of this driver.
     *
     * @param DataMigration $definition
     *   The migration definition.
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);

        if (!is_dir($this->migrationDefinition->getDestination())) {
            mkdir($this->migrationDefinition->getDestination(), 0755, true);
        }
        $this->finder = $this->finderFactory->get()
            ->files()
            ->in($this->migrationDefinition->getDestination())
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
            $ids[] = $this->buildIdsFromFilePath($fileInfo, $this->ids);
        }

        return $ids;
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $destIds): ?array
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
     * @return SplFileInfo[]
     */
    protected function findEntities(array $destIdSet): array
    {
        $entityFiles = [];
        foreach ($destIdSet as $destIds) {
            $matched = 0;
            foreach (['yaml', 'yml'] as $ext) {
                $destDir = $this->migrationDefinition->getDestination();
                $searchPath = $this->buildFilePathFromIds($destIds, $destDir, $ext);
                if (file_exists($searchPath)) {
                    $matched++;
                    $entityFiles[] = new SplFileInfo($searchPath);
                }
            }
            if ($matched > 1) {
                // The filesystem would normally enforce uniqueness here, however,
                // because both "yaml" and "yml" extensions are allowed, it's
                // conceivable that a file could exist with both extensions.
                throw new RangeException(
                    sprintf("More than one entity matched the ids:\n%s\n", var_export($destIds, true))
                );
            }
        }

        return $entityFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $destIdSet): array
    {
        $entityFiles = $this->findEntities($destIdSet);

        $entities = [];
        foreach ($entityFiles as $fileInfo) {
            $entity = $this->yamlParser->parse(file_get_contents($fileInfo->getPathname()));
            $destIds = $this->buildIdsFromFilePath($fileInfo, $this->ids);
            $entity = $this->addIdsToEntity($destIds, $entity);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function write($data): ?array
    {
        $destIds = [];
        foreach ($this->ids as $idField) {
            $destIds[$idField->getName()] = $this->resolveIdType($idField, $data[$idField->getName()]);

            // Remove the id from the data, as it will be represented in the
            // file path.
            unset($data[$idField->getName()]);
        }

        $this->yamlDumper->setOptions($this->buildDumperOptions());
        $yaml = $this->yamlDumper->dump($data);
        // Ensure file always has a newline at the end.
        $yaml = rtrim($yaml)."\n";

        $path = $this->buildFilePathFromIds($destIds, $this->migrationDefinition->getDestination());

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $yaml);

        return $destIds;
    }

    /**
     * Build the options for the YAML dumper
     *
     * @return YamlDumperOptions
     */
    private function buildDumperOptions(): YamlDumperOptions
    {
        $options = new YamlDumperOptions();
        if (isset($this->options[self::OPTION_INDENT])) {
            $options->setIndentation($this->options[self::OPTION_INDENT]);
        }
        if (isset($this->options[self::OPTION_REFS])) {
            if ($this->options[self::OPTION_REFS] === false) {
                $options->setAnchors(null);
            } else {
                $anchorOptions = new AnchorBuilderOptions();
                if ($this->options[self::OPTION_REFS] === true) {
                    $anchorOptions->setInclude([])->setExclude([]);
                } else {
                    if (isset($this->options[self::OPTION_REFS][self::OPTION_REFS_INCLUDE])) {
                        $anchorOptions->setInclude($this->options[self::OPTION_REFS][self::OPTION_REFS_INCLUDE]);
                    }
                    if (isset($this->options[self::OPTION_REFS][self::OPTION_REFS_EXCLUDE])) {
                        $anchorOptions->setExclude($this->options[self::OPTION_REFS][self::OPTION_REFS_EXCLUDE]);
                    }
                }
                $options->setAnchors($anchorOptions);
            }
        }

        return $options;
    }

    /**
     * Set an option for the YAML dumper.
     *
     * Valid options are:
     * - indent: Number of spaces to use for indentation
     * - refs: Automatically generate YAML anchors and references.  *This is a slow process!*  See the
     *   [docs](https://dragoonboots.gitlab.io/a2b/Drivers/Destination/YamlDestinationDriver.html)
     *   for further detail.
     *
     * @param string $option
     * @param mixed $value
     *
     * @return self
     */
    public function setOption(string $option, $value): YamlDestinationDriver
    {
        // Validate
        if (!in_array($option, self::OPTIONS)) {
            throw new \LogicException('Invalid option '.$option);
        } elseif ($option === self::OPTION_INDENT && !is_int($value)) {
            throw new \LogicException('Option '.$option.' must be an integer');
        } elseif ($option === self::OPTION_REFS) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (!in_array($k, self::OPTIONS_REFS)) {
                        throw new \LogicException(
                            'Option '.$option.' keys must be one of '.implode(', ', self::OPTIONS_REFS)
                        );
                    }
                }
            } elseif (!is_bool($value)) {
                throw new \LogicException('Option '.$option.' must be an array or boolean');
            }
        }
        $this->options[$option] = $value;

        return $this;
    }
}
