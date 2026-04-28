<?php

declare(strict_types=1);

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\SearchSimpleSmart\Forms\Fields\SearchEngineFormField;

class SearchHistoryBrowser extends BuildTask
{
    /**
     * @var string
     */
    protected $task = '';

    /**
     * @var string
     */
    protected $type = '';

    /**
     * @var int
     */
    protected $startDaysAgo = 365;

    /**
     * @var int
     */
    protected $endDaysAgo = 0;

    /**
     * title of the task.
     *
     * @var string
     */
    protected string $title = 'Search Engine: what people searched for';

    /**
     * description of the task.
     *
     * @var string
     */
    protected static string $description = 'Goes through the search history and shows what people searched for.';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    protected static string $commandName = 'searchhistorybrowser';

    /**
     * Stored PolyOutput instance for use in helper methods.
     */
    protected PolyOutput $polyOutput;

    #[Override]
    public function getOptions(): array
    {
        return [
            new InputOption('startDaysAgo', 's', InputOption::VALUE_REQUIRED, 'From how many days ago to start (e.g. 365 = one year ago)', 365),
            new InputOption('endDaysAgo', 'e', InputOption::VALUE_REQUIRED, 'How many days ago to end (e.g. 0 = up to today)', 0),
        ];
    }

    /**
     * this function runs the SearchHistoryBrowser task.
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->polyOutput = $output;

        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);

        $this->startDaysAgo = (int) ($input->getOption('startDaysAgo') ?? 365);
        $this->endDaysAgo   = (int) ($input->getOption('endDaysAgo') ?? 0);

        $html = SearchEngineFormField::create('SearchEngineFormField', 'Search History')
            ->setNumberOfDays($this->startDaysAgo - $this->endDaysAgo)
            ->setEndingDaysBack($this->endDaysAgo)
            ->setShowSource(false)
            ->forTemplate();

        $output->writeForHtml((string) $html);

        return Command::SUCCESS;
    }

    public function flushNow($message, $type = '', $bullet = true)
    {
        $this->polyOutput->writeForHtml('<div style="padding: 20px;">' . $message . '</div>');
    }

    public function Link()
    {
        return '/dev/tasks/' . static::$commandName;
    }
}
