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

        $objects = SearchEngineDataObject::get()->sort(['ID' => 'ASC']);
        foreach ($objects as $obj) {
            $source = $obj->SourceObject();
            if($source) {
                $key = $source->ClassName.'-'.$source->ID;
                $key2 = $obj->DataObjectClassName.'-'.$obj->DataObjectID;
                if($key !== $key2) {
                    $this->flushNow('Deleting ' . $obj->getTitle() . ' as there is a mismatch between source and search engine object', 'deleted');
                    $obj->delete();
                } elseif(isset($test[$key])) {
                    $this->flushNow('Deleting ' . $obj->getTitle() . ' as there is a double-up', 'deleted');
                    $obj->delete();
                } else {
                    $test[$key] = 1;
                }
            } else {
                $this->flushNow('Deleting ' . $obj->getTitle() . ' as there is no source object', 'deleted');
                $obj->delete();
            }
        }

        $this->runEnd($request);
    }
}
