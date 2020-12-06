<?php


namespace DragoonBoots\A2B\DataMigration\OutputFormatter;


use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsoleOutputFormatter extends AbstractOutputFormatter implements OutputFormatterInterface
{

    protected const PROGRESS_BAR_FORMAT = <<<TXT
 %last_migrated%
 %message%
 %current%/%max% (%percent%%) [%bar%]
 %elapsed%/%estimated% (%remaining% remaining)
 %memory%
 
TXT;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var ConsoleOutputInterface
     */
    protected $output;

    /**
     * @var ConsoleSectionOutput
     */
    protected $summarySection;

    /**
     * @var SymfonyStyle
     */
    protected $summaryIo;

    /**
     * @var ProgressBar
     */
    protected $summaryProgressBar;

    /**
     * @var ConsoleSectionOutput
     */
    protected $migrationSection;

    /**
     * @var SymfonyStyle
     */
    protected $migrationIo;

    /**
     * @var ProgressBar
     */
    protected $migrationProgressBar;

    /**
     * ConsoleOutputFormatter constructor.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     *
     * Valid options are:
     * - total: The total number of migrations to run
     */
    public function configure(array $options)
    {
        $total = $options['total'];
        if (is_callable([$this->output, 'section'])) {
            $this->summarySection = $this->output->section();
            $this->migrationSection = $this->output->section();
        } else {
            $this->summarySection = $this->output;
            $this->migrationSection = $this->output;
        }
        $this->summaryIo = new SymfonyStyle($this->input, $this->summarySection);
        $this->migrationIo = new SymfonyStyle($this->input, $this->migrationSection);

        $this->summaryProgressBar = new ProgressBar($this->summarySection, $total);
        $this->summaryProgressBar->setFormat(self::PROGRESS_BAR_FORMAT);
        $this->summaryProgressBar->setMessage('', 'last_migrated');
    }

    /**
     * {@inheritdoc}
     */
    public function start(DataMigrationInterface $migration, int $total)
    {
        $this->summaryProgressBar->setMessage(
            sprintf(
                'Migrating %s',
                $migration->getDefinition()->getName()
            )
        );
        $this->summaryProgressBar->display();

        $this->migrationSection->clear();
        $progressBar = new ProgressBar($this->migrationSection, $total);
        $progressBar->setFormat(self::PROGRESS_BAR_FORMAT);
        $progressBar->setMessage('Starting...');
        $progressBar->display();
        $this->migrationProgressBar = $progressBar;
    }

    /**
     * {@inheritdoc}
     */
    public function writeProgress(int $count, array $sourceIds, ?array $destIds)
    {
        $this->migrationProgressBar->setProgress($count);
        $sourceIdString = $this->formatIds($sourceIds);
        if (!is_null($destIds)) {
            $destIdString = $this->formatIds($destIds);
        } else {
            $destIdString = 'SKIPPED';
        }
        $idString = sprintf(
            '%s => %s',
            $sourceIdString,
            $destIdString
        );
        $this->migrationProgressBar->setMessage('Migrated');
        $this->migrationProgressBar->setMessage($idString, 'last_migrated');
    }

    /**
     * Format a set of ids
     *
     * e.g. `["id" = "1", "group" = "a"]`
     *
     * @param array $ids
     *
     * @return string
     */
    protected function formatIds(array $ids): string
    {
        $idStrings = [];
        foreach ($ids as $key => $value) {
            $idStrings[] = $this->formatId($key, $value);
        }

        return sprintf('[%s]', implode(', ', $idStrings));
    }

    /**
     * Format a single id
     *
     * e.g. `"id" = "1"`
     *
     * @param $key
     * @param $value
     *
     * @return string
     */
    protected function formatId($key, $value): string
    {
        return sprintf('"%s" = "%s"', $key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function finish()
    {
        $this->migrationProgressBar->finish();
        $this->migrationSection->clear();
        $this->summaryProgressBar->advance();
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $message, ?string $type = self::MESSAGE_INFO)
    {
        if (!is_null($type)) {
            $result = sprintf('<%s>%s</%s>', $type, $message, $type);
        } else {
            $result = $message;
        }

        $this->migrationProgressBar->setMessage($result);
        $this->migrationProgressBar->display();
    }

    /**
     * {@inheritdoc}
     */
    public function ask(string $message, array $options = [], $default = '')
    {
        if (empty($options)) {
            $q = new Question($message, $default);
        } else {
            $q = new ChoiceQuestion($message, $options, $default);
        }

        $this->migrationProgressBar->clear();
        $this->migrationSection->clear();
        $result = $this->migrationIo->askQuestion($q);
        $this->migrationSection->clear();
        $this->migrationProgressBar->display();

        return $result;
    }
}
