<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;

class SearchEngineRemoveAll extends SearchEngineBaseTask
{
    /**
     * Title of the task
     * @var string
     */
    protected $title = 'Remove All Search Engine Index Data';

    /**
     * Description of the task
     * @var string
     */
    protected $description = 'Careful - remove all the search engine index data.';

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchengineremoveall';

    /**
     * list of all model tables
     * @var array
     */
    private static $index_tables = [
        'SearchEngineDataObject',
        'SearchEngineDataObjectToBeIndexed',
        'SearchEngineFullContent',
        'SearchEngineKeyword',
        'SearchEngineKeyword_SearchEngineDataObjects_Level1',
        'SearchEngineDataObject_SearchEngineKeywords_Level2',
    ];

    private static $search_history_tables = [
        'SearchEngineSearchRecord',
        'SearchEngineSearchRecordHistory',
        'SearchEngineSearchRecord_SearchEngineKeywords',
    ];

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->runStart($request);

        $iAmSure = $request->getVar('i-am-sure') ? true : false;
        if (! $iAmSure) {
            die('please add the i-am-sure get variable to this task');
        }
        if ($this->type === 'all') {
            $allTables = array_merge(
                Config::inst()->get(self::class, 'search_history_tables'),
                Config::inst()->get(self::class, 'index_tables')
            );
        } elseif ($this->type === 'history') {
            $allTables = Config::inst()->get(self::class, 'search_history_tables');
        } elseif ($this->type === 'indexes') {
            $allTables = Config::inst()->get(self::class, 'index_tables');
        } else {
            die('Please set type: all|history|indexes');
        }
        foreach ($allTables as $table) {
            DB::alteration_message("Drop \"${table}\"", 'deleted');
            if (method_exists(DB::get_conn(), 'clearTable')) {
                // @DB::query("DROP \"$table\"");
                DB::get_conn()->clearTable($table);
            } else {
                DB::query("TRUNCATE \"${table}\"");
            }
        }

        $this->runEnd($request);
    }
}
