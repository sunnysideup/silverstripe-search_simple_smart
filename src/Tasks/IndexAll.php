<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\CMS\Model\SiteTree;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use SilverStripe\Dev\BuildTask;

class SearchEngineIndexAll extends BuildTask
{

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

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: $className (case sensitive)
          * NEW: $className (COMPLEX)
          * EXP: Check if the class name can still be used as such
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        $classNames = SearchEngineDataObject::searchable_class_names();

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: $className (case sensitive)
          * NEW: $className (COMPLEX)
          * EXP: Check if the class name can still be used as such
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        foreach ($classNames as $className => $classTitle) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            $count = $className::get()->count();
            $j = 0;
            for ($i = 0; $i < $count; $i = $i + 10) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                $objects = $className::get()->limit(10, $i);
                foreach ($objects as $obj) {
                    if ($obj instanceof SiteTree) {
                        if ($obj->IsPublished()) {
                            //do nothing
                        } else {
                            continue;
                        }
                    }
                    $item = SearchEngineDataObject::find_or_make($obj);
                    if ($item) {
                        if ($this->verbose) {
                            DB::alteration_message("Queueing ".$item->getTitle()." for indexing");
                        }
                        SearchEngineDataObjectToBeIndexed::add($item);
                        if ($this->verbose) {
                            flush();
                            ob_end_flush();
                            ob_start();
                        }
                    }
                }
            }
        }
        if ($this->verbose) {
            echo "<h2>======================</h2>";
        }
        if ($this->verbose) {
            DB::alteration_message("Now run the <a href=\"/dev/tasks/SearchEngineUpdateSearchIndex/?uptonow=1\">index task</a> to do the actual indexing");
        }
    }
}
