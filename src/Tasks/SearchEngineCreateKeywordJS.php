<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\Console\PolyOutput;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;

class SearchEngineCreateKeywordJS extends SearchEngineBaseTask
{
    /**
     * title of the task.
     *
     * @var string
     */
    protected string $title = 'Update Keyword Javascript List';

    /**
     * description of the task.
     *
     * @var string
     */
    protected $description = 'This list is used for the autocomplete function.';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    protected static string $commandName = 'searchenginecreatekeywordjs';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->runStart($request);
        $outcome = ExportKeywordList::export_keyword_list();
        DB::alteration_message($outcome, 'created');
        $this->runEnd($request);
        return 0;
    }
}
