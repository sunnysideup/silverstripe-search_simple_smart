<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\CMS\Model\SiteTree;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Environment;

class SearchEngineClearToBeIndexedDoubles extends SearchEngineBaseTask
{

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginecleartobeindexeddoubles';

    /**
     * title of the task
     * @var string
     */
    protected $title = 'Remove to be indexed doubles';

    /**
     * description of the task
     * @var string
     */
    protected $description = 'Go through all searchable objects to be indexed and clear and double-ups';

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param var $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);

        $count = SearchEngineDataObjectToBeIndexed::get()
            ->count();
        $sort = null;
        if($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random().' ASC';
        }
        $this->flushNow('<h4>Found entries: '.$count.'</h4>');

        $ids = SearchEngineDataObjectToBeIndexed::get()
            ->exclude(['Completed' => 1])
            ->sort($sort)
            ->map('ID', 'SearchEngineDataObjectID')
            ->toArray();
        $test = [];
        $pos = 0;
        foreach($ids as $id => $foreignID) {
            if(isset($test[$foreignID])) {
                $obj = SearchEngineDataObjectToBeIndexed::get()->byID($id);
                $foreign = SearchEngineDataObject::get()->byID($foreignID);
                $this->flushNow('Deleting '.$foreign->getTitle());
                $obj->delete();
            } else {
                $this->flushNow('.');
                $test[$foreignID] = [];
            }
            $test[$foreignID][] = $id;
        }

        $this->runEnd($request);

    }



}
