<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineDataObjectApi;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;

class SearchEngineIndexAll extends SearchEngineBaseTask
{
    /**
     * title of the task.
     *
     * @var string
     */
    protected $title = 'Add All Pages and Objects to the Queue to be Indexed';

    /**
     * description of the task.
     *
     * @var string
     */
    protected $description = 'Add all pages and other objects to be indexed in the future. You need to run the update tasks after this to actually index them.';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    private static $segment = 'searchengineindexall';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->runStart($request);

        $classNames = SearchEngineDataObjectApi::searchable_class_names();
        foreach ($classNames as $className => $classTitle) {
            $filter = ['ClassName' => $className];
            $className = (string) $className;
            $count = $className::get()
                ->filter($filter)
                ->count()
            ;
            $sort = null;
            if ($count > $this->limit) {
                $count = $this->limit;
                $sort = true;
            }

            $this->flushNow('<h4>Found ' . $count . ' of ' . $classTitle . ' (' . $className . ')</h4>');

            for ($i = 0; $i <= $count; $i += $this->step) {
                $objects = $className::get()->filter($filter)->limit($this->step, $i);
                if ($sort) {
                    $objects = $objects->shuffle();
                }

                foreach ($objects as $obj) {
                    $run = false;
                    $run = $this->unindexedOnly ? ! (bool) $obj->SearchEngineIsIndexed() : true;

                    if ($run) {
                        $item = SearchEngineDataObjectApi::find_or_make($obj);
                        if ($item) {
                            $this->flushNow('Queueing: ' . $obj->getTitle() . ' for indexing');
                            SearchEngineDataObjectToBeIndexed::add($item, false);
                        } elseif ($obj->SearchEngineExcludeFromIndex()) {
                            $this->flushNow('Object is excluded from search index: ' . $obj->getTitle());
                        } else {
                            $this->flushNow('Error that needs to be investigating .... object is ....' . $obj->getTitle());
                        }
                    } else {
                        $this->flushNow('already indexed ...' . $obj->getTitle());
                    }
                }
            }
        }

        $this->runEnd($request);
    }
}
