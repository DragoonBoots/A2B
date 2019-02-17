<?php


namespace DragoonBoots\A2B\DataMigration\OutputFormatter;


use DragoonBoots\A2B\DataMigration\DataMigrationInterface;

/**
 * Interface for classes that output migration progress and information.
 */
interface OutputFormatterInterface
{

    public const MESSAGE_NORMAL = null;

    public const MESSAGE_INFO = 'info';

    public const MESSAGE_QUESTION = 'question';

    public const MESSAGE_ERROR = 'error';

    /**
     * Configure the output formatter.
     *
     * @param array $options
     *   A set of options to configure the output formatter.  See the
     *   implementing class for information on valid values.
     */
    public function configure(array $options);

    /**
     * Start a migration
     *
     * @param DataMigrationInterface $migration
     * @param int                    $total
     *   The total number of rows to be migrated
     */
    public function start(DataMigrationInterface $migration, int $total);

    /**
     * Output migration progress
     *
     * @param int   $count
     *   The number of rows migrated
     * @param array $sourceIds
     * @param array|null $destIds
     *   An array of destination ids, or null if the entity was not migrated.
     */
    public function writeProgress(int $count, array $sourceIds, ?array $destIds);

    /**
     * Finish a migration
     */
    public function finish();

    /**
     * Display a message to the user
     *
     * @param string $message
     * @param string $type
     *   One of the OutputFormatterInterface::MESSAGE_* constants.
     */
    public function message(string $message, ?string $type = self::MESSAGE_INFO);

    /**
     * Ask the user a question
     *
     * @param string      $message
     *   The question to ask
     * @param array       $options
     *   The options available.  The value is what is displayed to the user, the
     *   key is what the user must type and the value returned when that option
     *   is selected.  Omit this to ask for a simple string, not a choice.
     * @param       mixed $default
     *   The key of the default option or the default string.
     *
     * @return mixed
     */
    public function ask(string $message, array $options = [], $default = '');
}
