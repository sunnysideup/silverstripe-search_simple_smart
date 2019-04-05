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

class SearchEngineClearDataObjectDoubles extends SearchEngineBaseTask
{

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginecleardataobjectdoubles';

    /**
     * title of the task
     * @var string
     */
    protected $title = 'Remove to be search engine data object doubles';

    /**
     * description of the task
     * @var string
     */
    protected $description = 'Go through all searcheable objects and clear and double-ups';

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param var $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);

        $count = SearchEngineDataObject::get()
            ->count();
        $sort = null;
        if($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random().' ASC';
        }
        $this->flushNow('<h4>Found entries: '.$count.'</h4>');

        $ids = SearchEngineDataObject::get()
            ->map('DataObjectID', 'DataObjectClassName')
            ->toArray();
        $test = [];
        $pos = 0;
        foreach($ids as $id => $className) {
            $key = $className.'_'.$id;
            if(isset($test[$key])) {
                $objects = SearchEngineDataObject::get()->filter(
                    [
                        'DataObjectID' => $id,
                        'DataObjectClassName' => $className,
                    ]
                );
                $count = 0;
                foreach($objects as $obj) {
                    if($count === 0) {
                        //keep!
                    } else {
                        $obj->delete();
                        $this->flushNow('Deleting '.$obj->getTitle());
                    }
                    $count++;
                }
            } else {
                $this->flushNow($pos);
                $test[$key] = [];
            }
            $test[$key][] = $pos;
            $pos++;
        }


        $this->runEnd($request);
    }



}
