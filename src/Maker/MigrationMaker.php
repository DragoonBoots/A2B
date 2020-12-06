<?php


namespace DragoonBoots\A2B\Maker;


use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use DragoonBoots\A2B\A2BBundle;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\DataMigrationManagerInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;
use RuntimeException;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Generate Migrations with MakerBundle
 */
class MigrationMaker extends AbstractMaker
{

    protected const TEMPLATE = __DIR__.'/../../Resources/skeleton/migration.tpl.php';

    protected const COMPOSER_PACKAGE = 'dragoonboots/a2b';

    /**
     * @var DataMigrationManagerInterface
     */
    protected $migrationManager;

    /**
     * @var DriverManagerInterface
     */
    protected $driverManager;

    /**
     * @var Inflector
     */
    protected $inflector;

    /**
     * MigrationMaker constructor.
     *
     * @param DataMigrationManagerInterface $migrationManager
     * @param DriverManagerInterface $driverManager
     */
    public function __construct(DataMigrationManagerInterface $migrationManager, DriverManagerInterface $driverManager)
    {
        $this->migrationManager = $migrationManager;
        $this->driverManager = $driverManager;
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandName(): string
    {
        return 'make:a2b:migration';
    }

    /**
     * {@inheritdoc}
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        $command->addArgument(
            'name',
            InputArgument::REQUIRED,
            'Human readable name of the migration (e.g. <fg=yellow>Sample Migration</>)'
        );
        $command->addOption(
            'class',
            null,
            InputOption::VALUE_REQUIRED,
            'Class name of the migration to create or update (e.g. <fg=yellow>SampleDataMigration</>'
        );
        $command->addOption(
            'group',
            null,
            InputOption::VALUE_REQUIRED,
            '(Optional) The migration group this migration is part of'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        if (!$input->getOption('group')) {
            $option = $command->getDefinition()->getOption('group');
            $q = new Question($option->getDescription(), null);
            $existingGroups = $this->migrationManager->getGroups();
            $q->setAutocompleterValues($existingGroups);
            $input->setOption('group', $io->askQuestion($q));
        }

        if (!$input->getOption('class')) {
            $name = $input->getArgument('name');
            $class = $this->inflector->classify($name);
            $input->setOption('class', $class);
        }
    }

    /**
     * Configure any library dependencies that your maker requires.
     *
     * @param DependencyBuilder $dependencies
     */
    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(A2BBundle::class, self::COMPOSER_PACKAGE);
    }

    /**
     * Called after normal code generation: allows you to do anything.
     *
     * @param InputInterface $input
     * @param ConsoleStyle $io
     * @param Generator $generator
     */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        $class = $input->getOption('class');
        if ($input->getOption('group')) {
            $class = $input->getOption('group').'\\'.$class;
        }

        // Don't allow generating something that already exists.
        $classNameDetails = $generator->createClassNameDetails($class, 'DataMigration');
        if (class_exists($classNameDetails->getFullName())) {
            $io->error(sprintf('The class "%s" already exists.', $classNameDetails->getFullName()));

            return;
        }

        $variables = [
            'name' => $input->getArgument('name'),
            'group' => $input->getOption('group'),
            'source' => $this->askForSource($io),
            'source_driver' => $this->askForSourceDriver($io),
            'source_ids' => $this->askForSourceIds($io),
            'destination' => $this->askForDestination($io),
            'destination_driver' => $this->askForDestinationDriver($io),
            'destination_ids' => $this->askForDestinationIds($io),
            'dependencies' => $this->askForDependencies($io),
        ];
        $generator->generateClass($classNameDetails->getFullName(), self::TEMPLATE, $variables);
        $generator->writeChanges();
    }

    /**
     * @param ConsoleStyle $io
     *
     * @return string
     */
    protected function askForSource(ConsoleStyle $io): string
    {
        return $this->askForUri($io, 'source');
    }

    /**
     * @param ConsoleStyle $io
     * @param string $uriType The user-presentable URI type
     *
     * @return string
     */
    protected function askForUri(ConsoleStyle $io, string $uriType): string
    {
        $q = new Question(sprintf('Enter %s URI', $uriType));

        return $io->askQuestion($q);
    }

    /**
     * @param ConsoleStyle $io
     *
     * @return string
     */
    protected function askForSourceDriver(ConsoleStyle $io): string
    {
        return $this->askForDriver($io, 'source', $this->driverManager->getSourceDrivers());
    }

    /**
     * @param ConsoleStyle $io
     * @param string $driverType
     * @param iterable|SourceDriverInterface[]|DestinationDriverInterface[] $driverList
     *   A list of drivers to use for autocompletion.
     *
     * @return string
     */
    protected function askForDriver(ConsoleStyle $io, string $driverType, iterable $driverList): string
    {
        $driverNames = [];
        foreach ($driverList as $driver) {
            $driverNames[] = get_class($driver);
        }
        $q = new ChoiceQuestion(
            sprintf('Select %s driver', $driverType),
            $driverNames
        );
        do {
            $driverName = $io->askQuestion($q);
        } while ($driverName === null);

        return $driverName;
    }

    /**
     * @param ConsoleStyle $io
     *
     * @return IdField[]
     */
    protected function askForSourceIds(ConsoleStyle $io): array
    {
        return $this->askForIds($io, 'source');
    }

    /**
     * @param ConsoleStyle $io
     * @param string $idType
     *
     * @return IdField[]
     */
    protected function askForIds(ConsoleStyle $io, string $idType): array
    {
        $ids = [];
        $idNames = [];
        $validIdTypes = ['int', 'string'];
        while (true) {
            $idValues = [];

            $q = new Question(sprintf('Enter %s id name (leave blank to stop entering ids)', $idType), '');
            $q->setValidator(
                function ($value) use ($idNames) {
                    if ($value) {
                        if (in_array($value, $idNames)) {
                            throw new RuntimeException('That id is already used.');
                        }
                    } else {
                        if (empty($idNames)) {
                            throw new RuntimeException('You must define at least one id.');
                        }
                    }

                    return $value;
                }
            );
            $newSourceIdName = $io->askQuestion($q);
            if (!$newSourceIdName) {
                break;
            }
            $idValues['name'] = $newSourceIdName;
            $idNames[] = $newSourceIdName;

            $q = new Question(sprintf('Enter %s id type', $idType), 'int');
            $q->setAutocompleterValues($validIdTypes);
            $q->setValidator(
                function ($value) use ($validIdTypes) {
                    if (!in_array($value, $validIdTypes)) {
                        throw new RuntimeException(sprintf('Valid id types are %s.', implode(', ', $validIdTypes)));
                    }

                    return $value;
                }
            );
            $newIdType = $io->askQuestion($q);
            $idValues['type'] = $newIdType;

            $ids[] = new IdField($idValues);
        }

        return $ids;
    }

    /**
     * @param ConsoleStyle $io
     *
     * @return string
     */
    protected function askForDestination(ConsoleStyle $io): string
    {
        return $this->askForUri($io, 'destination');
    }

    /**
     * @param ConsoleStyle $io
     *
     * @return string
     */
    protected function askForDestinationDriver(ConsoleStyle $io): string
    {
        return $this->askForDriver($io, 'destination', $this->driverManager->getDestinationDrivers());
    }

    /**
     * @param ConsoleStyle $io
     *
     * @return IdField[]
     */
    protected function askForDestinationIds(ConsoleStyle $io): array
    {
        return $this->askForIds($io, 'destination');
    }

    /**
     * @param ConsoleStyle $io
     *
     * @return string[]
     */
    protected function askForDependencies(ConsoleStyle $io): array
    {
        $dependencyNames = [];
        foreach ($this->migrationManager->getMigrations() as $migration) {
            $dependencyNames[] = get_class($migration);
        }

        $dependencies = [];
        while (true) {
            $q = new Question('Enter a dependency (leave blank to stop entering dependencies)', '');
            $q->setAutocompleterValues($dependencyNames);
            $q->setNormalizer(
                function ($value) {
                    {
                        $value = trim($value);
                        if ($value) {
                            $value = ltrim($value, '\\');
                        }

                        return $value;
                    }
                }
            );
            $q->setValidator(
                function ($value) use ($dependencies) {
                    if ($value) {
                        if (in_array($value, $dependencies)) {
                            throw new RuntimeException(
                                sprintf('The migration %s has already been declared a dependency.', $value)
                            );
                        }
                    }

                    return $value;
                }
            );
            $dependency = $io->askQuestion($q);
            if (!$dependency) {
                break;
            }

            $dependencies[] = $dependency;
        }

        return $dependencies;
    }
}
