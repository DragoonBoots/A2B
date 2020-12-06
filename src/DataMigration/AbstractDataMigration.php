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
    public function setDefinition(DataMigration $definition)
    {
        $this->definition = $definition;

        return $this;
    }

    /**
     * Remove nulls from the passed data
     *
     * @param array $data
     *
     * @return array
     */
    protected function removeNulls(array $data): array
    {
        return array_filter(
            $data,
            function ($value) {
                return !is_null($value);
            }
        );
    }

    /**
     * Build a human-readable string for creating a range of numbers.
     *
     * @param int $min
     * @param int $max
     *
     * @return int|string
     */
    protected function buildRangeString(int $min, int $max)
    {
        if ($min === $max) {
            return $min;
        } else {
            return "$min-$max";
        }
    }

    /**
     * Convert the fields listed in $fields to int.
     *
     * @param array $data
     * @param array $fields
     *
     * @return array
     */
    protected function convertToInts(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

}
