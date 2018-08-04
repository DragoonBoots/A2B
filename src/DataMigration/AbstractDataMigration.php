<?php


namespace DragoonBoots\A2B\DataMigration;


use DragoonBoots\A2B\Annotations\DataMigration;

/**
 * Base class for Data Migrations
 */
abstract class AbstractDataMigration implements DataMigrationInterface
{

    /**
     * @var DataMigration
     */
    protected $definition;

    /**
     * {@inheritdoc}
     */
    public function defaultResult()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition(): ?DataMigration
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefinition(DataMigration $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

}
