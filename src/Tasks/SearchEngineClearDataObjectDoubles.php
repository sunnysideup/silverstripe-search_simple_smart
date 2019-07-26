<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;

class SearchEngineClearDataObjectDoubles extends SearchEngineBaseTask
{
    /**
     * title of the task
     * @var string
     */
    protected $title = 'Remove search engine data object doubles';

    /**
     * description of the task
     * @var string
     */
    protected $description = 'Go through all searcheable objects and clear double-ups';

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginecleardataobjectdoubles';

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
        $sort = ['ID' => 'ASC'];
        if ($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random() . ' ASC';
        }
        $this->flushNow('<h4>Found entries: ' . $count . '</h4>');

        $ids = SearchEngineDataObject::get()
            ->sort($sort)
            ->map('DataObjectID', 'DataObjectClassName')
            ->toArray();
        $test = [];
        $pos = 0;
        foreach ($ids as $id => $className) {
            $key = $className . '_' . $id;
            if (isset($test[$key])) {
                $objects = SearchEngineDataObject::get()
                    ->filter(
                        [
                            'DataObjectID' => $id,
                            'DataObjectClassName' => $className,
                        ]
                    )
                    ->sort(['ID' => 'DESC']);
                $count = 0;
                foreach ($objects as $obj) {
                    if ($count === 0) {
                        //keep!
                    } else {
                        $obj->delete();
                        $this->flushNow('Deleting ' . $obj->getTitle());
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
