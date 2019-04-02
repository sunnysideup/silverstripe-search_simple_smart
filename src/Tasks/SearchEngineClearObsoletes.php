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

class SearchEngineClearObsoletes extends BuildTask
{

    /**
     * @var int
     */
    protected $limit = 100000;

    /**
     * @var int
     */
    protected $step = 10;

    /**
     * title of the task
     * @var string
     */
    protected $title = "Remove obsolete entries";

    /**
     * description of the task
     * @var string
     */
    protected $description = "Go through all searchable objects and remove obsolete ones";

    /**
     *
     * @var boolean
     */
    protected $verbose = true;

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param var $request
     */
    public function run($request)
    {
        //set basics
        ini_set('memory_limit', '512M');
        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);
        if ($this->verbose) {
            $this->flushNow("<h2>Starting</h2>", false);
        }
        $count = SearchEngineDataObject::get()
            ->count();
        $sort = null;
        if($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random().' ASC';
        }
        if ($this->verbose) {
            echo "<h4>Found entries: ".$count.'</h4>';
        }
        for ($i = 0; $i <= $count; $i = $i + $this->step) {
            $objects = SearchEngineDataObject::get()->limit($this->step, $i);
            if($sort) {
                $objects = $objects->sort($sort);
            }
            foreach ($objects as $obj) {
                if($obj->SourceObjectExists() === false) {
                    $this->flushNow('DELETING '.$obj->ID);
                    $obj->delete();
                } else {
                    $this->flushNow('OK ... '.$obj->ID);
                }
            }
        }
        if ($this->verbose) {
            echo "<h2>======================</h2>";
        }
    }


    public function flushNow($message, $type = '', $bullet = true)
    {
        echo '';
        // check that buffer is actually set before flushing
        if (ob_get_length()) {
            @ob_flush();
            @flush();
            @ob_end_flush();
        }
        @ob_start();
        if ($bullet) {
            DB::alteration_message($message, $type);
        } else {
            echo $message;
        }
    }
}
