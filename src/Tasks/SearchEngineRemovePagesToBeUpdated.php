<?php

declare(strict_types=1);

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;

class SearchEngineRemovePagesToBeUpdated extends SearchEngineBaseTask
{
    /**
     * Title of the task.
     *
     * @var string
     */
    protected string $title = 'Remove Entries that should be updated';

    /**
     * Description of the task.
     *
     * @var string
     */
    protected static string $description = 'Goes through all objects marked as to be indexed and removes them from this list so that you can just run a couple (added after you add this one).';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    protected static string $commandName = 'searchengineremovetobeindexed';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    #[Override]
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->runStart($request);
        DB::query('DELETE FROM "SearchEngineDataObjectToBeIndexed" WHERE Completed = 0');
        $this->runEnd();
        return Command::SUCCESS;
    }
}
