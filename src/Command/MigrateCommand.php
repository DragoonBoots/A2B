<?php

namespace DragoonBoots\A2B\Command;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\SchemaException;
use DragoonBoots\A2B\DataMigration\DataMigrationExecutorInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManagerInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationMapperInterface;
use DragoonBoots\A2B\DataMigration\OutputFormatter\ConsoleOutputFormatter;
use DragoonBoots\A2B\Drivers\Destination\DebugDestinationDriver;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use DragoonBoots\A2B\Exception\NoDestinationException;
use DragoonBoots\A2B\Exception\NoIdSetException;
use DragoonBoots\A2B\Exception\NonexistentDriverException;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;
use MJS\TopSort\CircularDependencyException;
use MJS\TopSort\ElementNotFoundException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;

class MigrateCommand extends Command
{

    /**
     * MigrateCommand constructor.
     *
     * @param DataMigrationManagerInterface $dataMigrationManager
     * @param DriverManagerInterface $driverManager
     * @param DataMigrationExecutorInterface $executor
     * @param DataMigrationMapperInterface $mapper
     * @param AbstractDumper $varDumper
     * @param ClonerInterface $varCloner
     */
    const ERROR_NO_PRUNE_PRESERVE = 'You cannot use both "--prune" and "--preserve" together.';

    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'a2b:migrate';

    /**
     * @var DataMigrationManagerInterface
     */
    protected $dataMigrationManager;

    /**
     * @var DriverManagerInterface
     */
    protected $driverManager;

    /**
     * @var DataMigrationExecutorInterface
     */
    protected $executor;

    /**
     * @var DataMigrationMapperInterface
     */
    protected $mapper;

    /**
     * @var AbstractDumper
     */
    protected $varDumper;

    /**
     * @var ClonerInterface
     */
    protected $varCloner;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    public function __construct(
        DataMigrationManagerInterface $dataMigrationManager,
        DriverManagerInterface $driverManager,
        DataMigrationExecutorInterface $executor,
        DataMigrationMapperInterface $mapper,
        AbstractDumper $varDumper,
        ClonerInterface $varCloner
    ) {
        parent::__construct();

        $this->dataMigrationManager = $dataMigrationManager;
        $this->driverManager = $driverManager;
        $this->executor = $executor;
        $this->mapper = $mapper;
        $this->varDumper = $varDumper;
        $this->varCloner = $varCloner;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Migrate data')
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Migration groups that should be run.  Pass --group once for each group.',
                ['default']
            )->addOption(
                'simulate',
                null,
                InputOption::VALUE_NONE,
                'Simulate results by writing output to the terminal instead of the destination.'
            )->addOption(
                'prune',
                null,
                InputOption::VALUE_NONE,
                'Remove destination entities that do not exist in the source.'
            )->addOption(
                'preserve',
                null,
                InputOption::VALUE_NONE,
                'Keep destination entities that do not exist in the source.'
            )->addOption(
                'no-deps',
                null,
                InputOption::VALUE_NONE,
                'Ignore dependencies migrations require.'
            )->addArgument(
                'migrations',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'A list of migration classes to run.  Do not specify any migrations to run all migrations.',
                []
            );
    }

    /**
     * @inheritDoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * {@inheritdoc}
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws DBALException
     * @throws SchemaException
     * @throws BadUriException
     * @throws NoDestinationException
     * @throws NoIdSetException
     * @throws NonexistentDriverException
     * @throws NonexistentMigrationException
     * @throws CircularDependencyException
     * @throws ElementNotFoundException
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Validate options
        if ($input->getOption('prune') && $input->getOption('preserve')) {
            $this->io->error(self::ERROR_NO_PRUNE_PRESERVE);

            return 1;
        }

        $migrations = $this->getMigrations($input->getOption('group'), $input->getArgument('migrations'));
        if (!$input->getOption('no-deps')) {
            $migrations = $this->dataMigrationManager->resolveDependencies($migrations);
        }

        $outputFormatter = new ConsoleOutputFormatter($input, $output);
        $outputFormatter->configure(['total' => count($migrations)]);
        $this->executor->setOutputFormatter($outputFormatter);

        foreach ($migrations as $migration) {
            $definition = $migration->getDefinition();

            if ($input->getOption('simulate')) {
                $this->injectProperty($definition, 'destination', 'stderr');
                $this->injectProperty($definition, 'destinationDriver', DebugDestinationDriver::class);
            }

            $sourceDriver = $this->driverManager->getSourceDriver($definition->getSourceDriver());
            $sourceDriver->configure($definition);
            $migration->configureSource($sourceDriver);
            $destinationDriver = $this->driverManager->getDestinationDriver($definition->getDestinationDriver());
            $destinationDriver->configure($definition);
            $migration->configureDestination($destinationDriver);

            // Run migration
            $orphans = $this->executor->execute($migration, $sourceDriver, $destinationDriver);

            if (!empty($orphans) && !$input->getOption('prune')) {
                if ($input->getOption('preserve')) {
                    $this->executor->writeOrphans($orphans, $migration, $destinationDriver);
                } else {
                    $this->executor->askAboutOrphans($orphans, $migration, $destinationDriver);
                }
            }
            $destinationDriver->flush();
        }

        return 0;
    }

    /**
     * @param array $groups
     * @param array $migrationIds
     *
     * @return DataMigrationInterface[]
     *
     * @throws NonexistentMigrationException
     */
    protected function getMigrations(array $groups, array $migrationIds): array
    {
        $migrations = [];
        if (empty($migrationIds)) {
            foreach ($groups as $group) {
                $migrations = array_merge(
                    $migrations,
                    $this->dataMigrationManager
                        ->getMigrationsInGroup($group)
                        ->toArray()
                );
            }
        } else {
            foreach ($migrationIds as $migrationId) {
                $migrations[$migrationId] = $this->dataMigrationManager->getMigration($migrationId);
            }
        }

        return $migrations;
    }

    /**
     * Inject a value into an object property with reflection.
     *
     * @param object $object
     * @param string $propertyName
     * @param mixed $value
     *
     * @throws ReflectionException
     */
    private function injectProperty(object $object, string $propertyName, $value)
    {
        $refl = new ReflectionClass($object);
        $property = $refl->getProperty($propertyName);
        $accessible = $property->isPublic();
        $property->setAccessible(true);
        $property->setValue($object, $value);
        $property->setAccessible($accessible);
    }
}
