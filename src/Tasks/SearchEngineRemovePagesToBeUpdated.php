<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

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
    protected $title = 'Remove Entries that should be updated';

    /**
     * Description of the task.
     *
     * @var string
     */
    protected $description = 'Goes through all objects marked as to be indexed and removes them from this list so that you can just run a couple (added after you add this one).';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    private static $segment = 'searchengineremovetobeindexed';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->runStart($request);
        DB::query('DELETE FROM "SearchEngineDataObjectToBeIndexed" WHERE Completed = 0');
        $this->runEnd($request);
    }
}
