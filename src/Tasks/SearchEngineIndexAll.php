<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\CMS\Model\SiteTree;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Environment;

class SearchEngineIndexAll extends BuildTask
{

    /**
     * @var int
     */
    protected $limit = 10000;

    /**
     * @var int
     */
    protected $step = 10;

    /**
     * title of the task
     * @var string
     */
    protected $title = "Add All Pages and Objects to be Indexed";

    /**
     * description of the task
     * @var string
     */
    protected $description = "Add all pages and other objects to be indexed in the future.";

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
        $classNames = SearchEngineDataObject::searchable_class_names();
        foreach ($classNames as $className => $classTitle) {
            $filter = ['ClassName' => $className];
            $hasVersioned = false;
            $count = $className::get()
                ->filter($filter)
                ->count();
            $sort = null;
            if($count > $this->limit) {
                $count = $this->limit;
                $sort = DB::get_conn()->random().' ASC';
            }
            if ($this->verbose) {
                echo "<h4>Found ".$count.' of '.$classTitle.'</h4>';
            }
            for ($i = 0; $i <= $count; $i = $i + $this->step) {
                $objects = $className::get()->filter($filter)->limit($this->step, $i);
                if($sort) {
                    $objects = $objects->sort($sort);
                }
                foreach ($objects as $obj) {
                    $item = SearchEngineDataObject::find_or_make($obj);
                    if ($item) {
                        if ($this->verbose) {
                            $this->flushNow("Queueing: ".$obj->getTitle()." for indexing");
                        }
                        SearchEngineDataObjectToBeIndexed::add($item, false);
                    } else {
                        if ($this->verbose) {
                            $this->flushNow("Cant not queue: ".$obj->getTitle()." for indexing");
                        }
                    }
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
