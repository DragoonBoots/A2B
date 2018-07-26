<?php

namespace DragoonBoots\A2B\Command;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationExecutorInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\DataMigrationManagerInterface;
use DragoonBoots\A2B\Drivers\Destination\DebugDestinationDriver;
use DragoonBoots\A2B\Drivers\DriverManagerInterface;
use League\Uri\Parser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MigrateCommand extends Command
{

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
     * @var Parser
     */
    protected $uriParser;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * MigrateCommand constructor.
     *
     * @param DataMigrationManagerInterface  $dataMigrationManager
     * @param DriverManagerInterface         $driverManager
     * @param DataMigrationExecutorInterface $executor
     * @param Parser                         $uriParser
     * @param ParameterBagInterface          $parameterBag
     */
    public function __construct(
      DataMigrationManagerInterface $dataMigrationManager,
      DriverManagerInterface $driverManager,
      DataMigrationExecutorInterface $executor,
      Parser $uriParser,
      ParameterBagInterface $parameterBag
    ) {
        parent::__construct();

        $this->dataMigrationManager = $dataMigrationManager;
        $this->driverManager = $driverManager;
        $this->executor = $executor;
        $this->uriParser = $uriParser;
        $this->parameterBag = $parameterBag;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setDescription('Migrate data')
          ->addOption(
            'group', 'g', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Migration groups that should be run.  Pass --group once for each group.',
            ['default']
          )->addOption(
            'simulate',
            null,
            InputOption::VALUE_NONE,
            'Simulate results by writing output to the terminal instead of the destination.'
          )->addArgument(
            'migrations', InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
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
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \DragoonBoots\A2B\Exception\NoDriverForSchemeException
     * @throws \DragoonBoots\A2B\Exception\NonexistentDriverException
     * @throws \DragoonBoots\A2B\Exception\NonexistentMigrationException
     * @throws \DragoonBoots\A2B\Exception\UnclearDriverException
     * @throws \DragoonBoots\A2B\Exception\NoIdSetException
     * @throws \DragoonBoots\A2B\Exception\BadUriException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var DataMigrationInterface[] $migrations */
        /** @var DataMigration[] $definitions */
        list($migrations, $definitions) = $this->getMigrations($input->getOption('group'), $input->getArgument('migrations'));

        foreach ($migrations as $migrationId => $migration) {
            $definition = $definitions[$migrationId];

            // Resolve container parameters source/destination urls
            $definition->source = $this->parameterBag->resolveValue($definition->source);
            $definition->destination = $this->parameterBag->resolveValue($definition->destination);

            // Get source driver
            if (isset($definition->sourceDriver)) {
                $sourceDriver = $this->driverManager->getSourceDriver($definition->sourceDriver);
            } else {
                $sourceUri = $this->uriParser->parse($definition->source);
                $sourceDriver = $this->driverManager->getSourceDriverForScheme($sourceUri['scheme']);
            }
            $sourceDriver->configure($definition);
            $migration->configureSource($sourceDriver);

            // Get destination driver
            if ($input->getOption('simulate') === true) {
                $destinationDriver = $this->driverManager->getDestinationDriver(DebugDestinationDriver::class);
                $fakeDefinition = new DataMigration();
                $fakeDefinition->destination = 'debug:stderr';
                $destinationDriver->configure($fakeDefinition);
            } else {
                if (isset($definition->destinationDriver)) {
                $destinationDriver = $this->driverManager->getDestinationDriver($definition->destinationDriver);
                } else {
                    $destinationUri = $this->uriParser->parse($definition->destination);
                    $destinationDriver = $this->driverManager->getDestinationDriverForScheme($destinationUri['scheme']);
                }
                $destinationDriver->configure($definition);
                $migration->configureDestination($destinationDriver);
            }

            // Run migration
            $this->executor->execute($migration, $definition, $sourceDriver, $destinationDriver);
        }
    }

    /**
     * @param array $groups
     * @param array $migrationIds
     *
     * @return array
     *   An array containing migrations and definitions
     *
     * @throws \DragoonBoots\A2B\Exception\NonexistentMigrationException
     */
    protected function getMigrations(array $groups, array $migrationIds)
    {
        $migrations = [];
        $definitions = [];
        if (empty($migrationIds)) {
            foreach ($groups as $group) {
                $migrations = array_merge(
                  $migrations, $this->dataMigrationManager->getMigrationsInGroup($group)
                  ->toArray()
                );
                $definitions = array_merge(
                  $definitions, $this->dataMigrationManager->getMigrationDefinitionsInGroup($group)
                  ->toArray()
                );
            }
        } else {
            foreach ($migrationIds as $migrationId) {
                $migrations[$migrationId] = $this->dataMigrationManager->getMigration($migrationId);
                $definitions[$migrationId] = $this->dataMigrationManager->getMigrationDefinition($migrationId);
            }
        }

        return [$migrations, $definitions];
    }
}
