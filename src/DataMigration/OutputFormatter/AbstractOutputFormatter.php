<?php


namespace DragoonBoots\A2B\DataMigration\OutputFormatter;

/**
 * Base class for output formatters
 */
abstract class AbstractOutputFormatter implements OutputFormatterInterface
{

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     */
    public function configure(array $options)
    {
        // Default to not requiring any configuration
    }

}
