<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;

class SearchEngineCreateKeywordJS extends SearchEngineBaseTask
{
    /**
     * title of the task
     * @var string
     */
    protected $title = 'Update Keyword Javascript List';

    /**
     * description of the task
     * @var string
     */
    protected $description = 'This list is used for the autocomplete function.';

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginecreatekeywordjs';

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->runStart($request);

        $outcome = ExportKeywordList::export_keyword_list();
        DB::alteration_message($outcome, 'created');

        $this->runEnd($request);
    }
}
