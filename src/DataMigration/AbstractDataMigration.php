<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;

/**
 * Base class for Data Migrations
 */
abstract class AbstractDataMigration implements DataMigrationInterface
{

    /**
     * @var MigrationReferenceStoreInterface
     */
    protected $referenceStore;

    /**
     * @var DataMigration|null
     */
    protected $definition;

    /**
     * AbstractDataMigration constructor.
     *
     * @param MigrationReferenceStoreInterface $referenceStore
     */
    public function __construct(MigrationReferenceStoreInterface $referenceStore)
    {
        $this->referenceStore = $referenceStore;
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function defaultResult()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function configureSource(SourceDriverInterface $sourceDriver)
    {
        // Do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function configureDestination(DestinationDriverInterface $destinationDriver)
    {
        // Do nothing
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function getDefinition(): ?DataMigration
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function setDefinition(DataMigration $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

}
