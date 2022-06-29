<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;

class SearchEngineClearToBeIndexedDoubles extends SearchEngineBaseTask
{
    /**
     * title of the task.
     *
     * @var string
     */
    protected $title = 'Remove to be indexed doubles';

    /**
     * description of the task.
     *
     * @var string
     */
    protected $description = 'Go through all searchable objects to be indexed and clear and double-ups';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    private static $segment = 'searchenginecleartobeindexeddoubles';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);

        $count = SearchEngineDataObjectToBeIndexed::get()
            ->count()
        ;
        $sort = ['ID' => 'ASC'];
        if ($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random() . ' ASC';
        }

        $this->flushNow('<h4>Found entries: ' . $count . '</h4>');

        $ids = SearchEngineDataObjectToBeIndexed::get()
            ->exclude(['Completed' => 1])
            ->sort($sort)
            ->map('ID', 'SearchEngineDataObjectID')
            ->toArray()
        ;
        $test = [];
        foreach ($ids as $id => $foreignID) {
            if (isset($test[$foreignID])) {
                $obj = SearchEngineDataObjectToBeIndexed::get()->byID($id);
                $foreign = SearchEngineDataObject::get()->byID($foreignID);
                $this->flushNow('Deleting ' . $foreign->getTitle());
                $obj->delete();
            } else {
                $this->flushNow('.');
                if (! isset($test[$foreignID])) {
                    $test[$foreignID] = [];
                }
            }

            $test[$foreignID][$id] = $id;
        }

        $this->runEnd($request);
    }
}
