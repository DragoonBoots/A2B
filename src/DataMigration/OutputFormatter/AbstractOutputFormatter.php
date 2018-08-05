<?php


namespace DragoonBoots\A2B\DataMigration\OutputFormatter;

/**
 * Class AbstractOutputFormatter
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
        return;
    }

}
