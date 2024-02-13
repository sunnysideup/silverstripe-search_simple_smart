<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Api\FasterIDLists;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;

class SearchEngineClearDataObjectDoubles extends SearchEngineBaseTask
{
    /**
     * title of the task.
     *
     * @var string
     */
    protected $title = 'Remove search engine data object doubles';

    /**
     * description of the task.
     *
     * @var string
     */
    protected $description = 'Go through all searcheable objects and clear double-ups';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    private static $segment = 'searchenginecleardataobjectdoubles';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);

        $count = SearchEngineDataObject::get()
            ->count()
        ;
        if ($count > $this->limit) {
            $count = $this->limit;
        }

        $this->flushNow('<h4>Found entries: ' . $count . '</h4>');

        $ids = SearchEngineDataObject::get()
            ->shuffle()
            ->map('DataObjectID', 'DataObjectClassName')
            ->toArray()
        ;
        $test = [];
        $pos = 0;
        foreach ($ids as $id => $className) {
            $key = $className . '_' . $id;
            if (! isset($test[$key])) {
                $objects = SearchEngineDataObject::get()
                    ->filter(['DataObjectClassName' => $className, 'DataObjectID' => $id])
                    ->sort(['ID' => 'DESC']);

                $count = 0;
                foreach ($objects as $obj) {
                    if (0 === $count) {
                        //the first one we keep!
                    } else {
                        // the rest we delete
                        $obj->delete();
                        $this->flushNow('Deleting ' . $obj->getTitle() . ' as there is a double-up', 'deleted');
                    }

                    ++$count;
                }
            } else {
                $this->flushNow($pos);
                $test[$key] = [];
            }

            $test[$key][] = $pos;
            ++$pos;
        }

        $this->runEnd($request);
    }
}
