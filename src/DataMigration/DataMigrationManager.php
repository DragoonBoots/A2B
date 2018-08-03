<?php


namespace DragoonBoots\A2B\DataMigration;


use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Exception\NonexistentMigrationException;

class DataMigrationManager implements DataMigrationManagerInterface
{

    /**
     * @var Reader
     */
    protected $annotationReader;

    /**
     * @var Collection|DataMigrationInterface[]
     */
    protected $migrations = [];

    /**
     * @var Collection|DataMigration[]
     */
    protected $migrationDefinitions = [];

    /**
     * DataMigrationManager constructor.
     *
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;

        $this->migrations = new ArrayCollection();
        $this->migrationDefinitions = new ArrayCollection();
    }

    /**
     * Add a new migration
     *
     * @internal
     *
     * @param DataMigrationInterface $migration
     *
     * @throws \ReflectionException
     */
    public function addMigration(DataMigrationInterface $migration)
    {
        $this->migrations[get_class($migration)] = $migration;
        $reflClass = new \ReflectionClass($migration);
        $this->migrationDefinitions[get_class($migration)] = $this->annotationReader->getClassAnnotation($reflClass, DataMigration::class);

    }

    public function getMigrations(): Collection
    {
        return $this->migrations;
    }

    public function getMigrationDefinitions(): Collection
    {
        return $this->migrationDefinitions;
    }

    public function getMigration(string $migrationName)
    {
        if (!$this->migrations->containsKey($migrationName)) {
            throw new NonexistentMigrationException($migrationName);
        }

        return $this->migrations[$migrationName];
    }

    public function getMigrationsInGroup(string $groupName)
    {
        $migrations = $this->migrations->filter(
          function (DataMigrationInterface $migration) use ($groupName) {
              return $this->getMigrationDefinition(get_class($migration))->group === $groupName;
          }
        );

        return $migrations;
    }

    /**
     * @todo Remove this and inject the definition into each migration.
     *
     * @param string $migrationName
     *
     * @return DataMigration|mixed
     * @throws NonexistentMigrationException
     */
    public function getMigrationDefinition(string $migrationName)
    {
        if (!$this->migrationDefinitions->containsKey($migrationName)) {
            throw new NonexistentMigrationException($migrationName);
        }

        return $this->migrationDefinitions[$migrationName];
    }

    public function getMigrationDefinitionsInGroup(string $groupName)
    {
        $criteria = new Criteria(new Comparison('group', '=', $groupName));
        $definitions = $this->migrationDefinitions->matching($criteria);

        return $definitions;
    }
}
