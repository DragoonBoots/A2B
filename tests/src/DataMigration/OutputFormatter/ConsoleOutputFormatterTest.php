<?php

namespace DragoonBoots\A2B\Tests\DataMigration\OutputFormatter;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\DataMigration\OutputFormatter\ConsoleOutputFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mock ProgressBar for testing.
 *
 * The real progress bar is declared final and cannot be mocked.  This is a
 * substitute.  The official recommendation is to check output; the output
 * itself is an implementation detail and would needlesslys complicate this
 * test.
 *
 * @internal
 */
interface MockProgressBar
{

    public function display();

    public function setProgress(int $progress);

    public function advance(int $steps = 1);

    public function finish();

    public function setMessage(string $message, string $placeholder = 'message');

    public function clear();
}

class ConsoleOutputFormatterTest extends TestCase
{

    public function testConfigure()
    {
        /** @var ConsoleSectionOutput $summarySection */
        /** @var ConsoleSectionOutput $migrationSection */
        /** @var ConsoleOutputFormatter $formatter */
        $this->setupFormatter($formatter, $migrationSection, $summarySection);
        $formatter->configure(['total' => 1]);
    }

    /**
     * @param ConsoleOutputFormatter $formatter
     * @param ConsoleSectionOutput   $migrationSection
     * @param ConsoleSectionOutput   $summarySection
     */
    protected function setupFormatter(&$formatter = null, &$migrationSection = null, &$summarySection = null)
    {
        $summaryOutputFormatter = $this->createMock(OutputFormatterInterface::class);
        if (!isset($summarySection)) {
            $summarySection = $this->createMock(ConsoleSectionOutput::class);
        }
        $summarySection->method('getFormatter')
            ->willReturn($summaryOutputFormatter);

        $migrationOutputFormatter = $this->createMock(OutputFormatterInterface::class);
        if (!isset($migrationSection)) {
            $migrationSection = $this->createMock(ConsoleSectionOutput::class);
        }
        $migrationSection->method('getFormatter')
            ->willReturn($migrationOutputFormatter);

        $input = $this->createMock(InputInterface::class);

        $output = $this->createMock(ConsoleOutput::class);
        $output->expects($this->exactly(2))
            ->method('section')
            ->willReturnOnConsecutiveCalls($summarySection, $migrationSection);

        $formatter = new ConsoleOutputFormatter($input, $output);
    }

    public function testStart()
    {
        $summarySection = $this->createMock(ConsoleSectionOutput::class);
        $summarySection->expects($this->atLeastOnce())->method('write');
        $migrationSection = $this->createMock(ConsoleSectionOutput::class);
        $migrationSection->expects($this->atLeastOnce())->method('write');

        /** @var ConsoleOutputFormatter $formatter */
        $this->setupFormatter($formatter, $migrationSection, $summarySection);
        $formatter->configure(['total' => 1]);

        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $definition = new DataMigration(['name' => 'Test Migration']);
        $migration->method('getDefinition')
            ->willReturn($definition);
        $formatter->start($migration, 1);
    }

    public function testWriteProgress()
    {
        /** @var ConsoleOutputFormatter $formatter */
        $this->setupFormatter($formatter);
        $formatter->configure(['total' => 1]);

        $count = 1;
        $sourceIds = ['id' => 1];
        $destIds = ['identifier' => 'test'];

        // Use reflection to inject an observer progress bar.
        $refl = new \ReflectionClass($formatter);
        $migrationProgressBar = $this->createMock(MockProgressBar::class);
        $migrationProgressBar->expects($this->once())
            ->method('setProgress')
            ->with($count);
        $formatterMigrationProgressBar = $refl->getProperty('migrationProgressBar');
        $formatterMigrationProgressBar->setAccessible(true);
        $formatterMigrationProgressBar->setValue($formatter, $migrationProgressBar);

        $formatter->writeProgress($count, $sourceIds, $destIds);
    }

    public function testFinish()
    {
        $migrationSection = $this->createMock(ConsoleSectionOutput::class);
        $migrationSection->expects($this->once())
            ->method('clear');
        /** @var ConsoleOutputFormatter $formatter */
        $this->setupFormatter($formatter, $migrationSection);
        $formatter->configure(['total' => 1]);

        // Use reflection to inject an observer progress bar.
        $refl = new \ReflectionClass($formatter);
        $migrationProgressBar = $this->createMock(MockProgressBar::class);
        $migrationProgressBar->expects($this->once())
            ->method('finish');
        $formatterMigrationProgressBar = $refl->getProperty('migrationProgressBar');
        $formatterMigrationProgressBar->setAccessible(true);
        $formatterMigrationProgressBar->setValue($formatter, $migrationProgressBar);
        $summaryProgressBar = $this->createMock(MockProgressBar::class);
        $summaryProgressBar->expects($this->once())
            ->method('advance');
        $formatterSummaryProgressBar = $refl->getProperty('summaryProgressBar');
        $formatterSummaryProgressBar->setAccessible(true);
        $formatterSummaryProgressBar->setValue($formatter, $summaryProgressBar);

        $formatter->finish();
    }

    public function testMessage()
    {
        /** @var ConsoleOutputFormatter $formatter */
        $this->setupFormatter($formatter);
        $formatter->configure(['total' => 1]);

        $message = 'Test message';

        // Use reflection to inject an observer progress bar.
        $refl = new \ReflectionClass($formatter);
        $migrationProgressBar = $this->createMock(MockProgressBar::class);
        $migrationProgressBar->expects($this->exactly(2))
            ->method('setMessage')
            ->withConsecutive([$message], [sprintf('<info>%s</info>', $message)]);
        $formatterMigrationProgressBar = $refl->getProperty('migrationProgressBar');
        $formatterMigrationProgressBar->setAccessible(true);
        $formatterMigrationProgressBar->setValue($formatter, $migrationProgressBar);

        $formatter->message($message, ConsoleOutputFormatter::MESSAGE_NORMAL);
        $formatter->message($message, ConsoleOutputFormatter::MESSAGE_INFO);
    }

    /**
     * @dataProvider questionDataProvider
     *
     * @param string $message
     * @param array  $choices
     * @param string $default
     * @param string $result
     *
     * @throws \ReflectionException
     */
    public function testAsk(string $message, array $choices, string $default)
    {
        $migrationSection = $this->createMock(ConsoleSectionOutput::class);
        // Clears once to ask the question and once to display the progress
        // bar again.
        $migrationSection->expects($this->atLeast(2))
            ->method('clear');
        /** @var ConsoleOutputFormatter $formatter */
        $this->setupFormatter($formatter, $migrationSection);
        $formatter->configure(['total' => 1]);
        
        /** @var DataMigrationInterface|MockObject $migration */
        $migration = $this->getMockBuilder(DataMigrationInterface::class)
            ->disableOriginalConstructor()
            ->setMockClassName('TestMigration')
            ->getMock();
        $definition = new DataMigration(['name' => 'Test Migration']);
        $migration->method('getDefinition')
            ->willReturn($definition);
        $formatter->start($migration, 1);

        // Use reflection to inject some observers.
        $refl = new \ReflectionClass($formatter);
        $style = $this->createMock(SymfonyStyle::class);
        if (empty($choices)) {
            $q = new Question($message, $default);
        } else {
            $q = new ChoiceQuestion($message, $choices, $default);
        }
        $style->expects($this->once())
            ->method('askQuestion')
            ->with($q);
        $formatterMigrationIo = $refl->getProperty('migrationIo');
        $formatterMigrationIo->setAccessible(true);
        $formatterMigrationIo->setValue($formatter, $style);
        $migrationProgressBar = $this->createMock(MockProgressBar::class);
        $migrationProgressBar->expects($this->once())
            ->method('clear');
        $migrationProgressBar->expects($this->once())
            ->method('display');
        $formatterMigrationProgressBar = $refl->getProperty('migrationProgressBar');
        $formatterMigrationProgressBar->setAccessible(true);
        $formatterMigrationProgressBar->setValue($formatter, $migrationProgressBar);

        $formatter->ask($message, $choices, $default);
    }

    public function questionDataProvider()
    {
        return [
            'no choices' => [
                // Message
                'Test question',
                // Choices
                [],
                // Default
                'default answer',
            ],
            'with choices' => [
                // Message
                'Test multiple-choice question',
                // Choices
                [
                    'y' => 'Yes',
                    'n' => 'No',
                ],
                // Default
                'y',
            ],
        ];
    }
}
