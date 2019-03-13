<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\CMS\Model\SiteTree;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;

class SearchEngineIndexAll extends BuildTask
{

    /**
     * title of the task
     * @var string
     */
    protected $limit = 5;

    /**
     * title of the task
     * @var string
     */
    protected $title = "Index All DataObjects";

    /**
     * description of the task
     * @var string
     */
    protected $description = "Careful - this will take signigicant time and resources.";

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
        set_time_limit(600);
        ob_start();
        if ($this->verbose) {
            DB::alteration_message("Consider running the clear all task first <a href=\"/dev/tasks/SearchEngineRemoveAll/\">remove all task</a> first. This will REMOVE ALL SEARCH ENGINE DATA.");
        }
        if ($this->verbose) {
            echo "<h2>Starting</h2>";
        }
        $classNames = SearchEngineDataObject::searchable_class_names();
        foreach ($classNames as $className => $classTitle) {
            $hasVersioned = false;
            $count = $className::get()->count();
            if($count > $this->limit) {
                $count = $this->limit;
            }
            if ($this->verbose) {
                DB::alteration_message("<h4>Found ".$count.' of '.$classTitle.'</h4>');
            }
            for ($i = 0; $i < $count; $i = $i + 10) {
                $objects = $className::get()->limit(10, $i);
                foreach ($objects as $obj) {
                    if ($hasVersioned || $obj->hasExtension(Versioned::class)) {
                        $hasVersioned = true;
                        if ($obj->IsPublished()) {
                            //all OK!
                        } else {
                            if ($this->verbose) {
                                DB::alteration_message("Not published ".$item->getTitle(), 'deleted');
                            }
                            continue;
                        }
                    } else {
                        if ($this->verbose) {
                            DB::alteration_message("Not versioned, will always be searchable: ".$item->getTitle(), 'deleted');
                        }
                    }
                    $item = SearchEngineDataObject::find_or_make($obj);
                    if ($item) {
                        if ($this->verbose) {
                            DB::alteration_message("Queueing ".$item->getTitle()." for indexing");
                        }
                        SearchEngineDataObjectToBeIndexed::add($item);
                    } else {
                        if ($this->verbose) {
                            DB::alteration_message("No need to queue ".$item->getTitle()." for indexing");
                        }
                    }
                }
                if ($this->verbose) {
                    flush();
                    ob_end_flush();
                    ob_start();
                }
            }
        }
        if ($this->verbose) {
            echo "<h2>======================</h2>";
        }
    }
}
