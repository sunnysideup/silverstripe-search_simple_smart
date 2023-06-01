<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Api\CheckFieldsApi;

class SearchEngineCheckFields extends BuildTask
{
    /**
     * title of the task.
     *
     * @var string
     */
    protected $title = 'Search Engine: List of All fields that can be indexed';

    /**
     * description of the task.
     *
     * @var string
     */
    protected $description = 'Goes through all DataObjects and lists the fields that can be indexed. ';

    /**
     * @var bool
     */
    protected $verbose = true;

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    private static $segment = 'checkfields';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);
        $array = (new CheckFieldsApi())->getList();
        foreach($array['AllValidFields'] as $className => $classData) {
            echo PHP_EOL.$className.':';
            if(! empty($classData['IsBaseClass'])) {
                echo PHP_EOL.'  extensions:';
                echo PHP_EOL.'    - Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable';
            }
            for($i = 1; $i < 3; $i++) {
                $level = 'Level'.$i;
                if(! empty($classData[$level])) {
                    echo PHP_EOL.'  '.strtolower($level).':';
                    foreach($classData[$level] as $fieldName) {
                        echo PHP_EOL.'    - '.$fieldName;
                    }
                }
            }

        }

        $this->runEnd($request);
    }

    public function flushNow($message, $type = '', $bullet = true)
    {
        if ($this->verbose) {
            echo '';
            // check that buffer is actually set before flushing
            try {
                if (ob_get_length()) {
                    ob_flush();
                    flush();
                    ob_end_flush();
                }

                ob_start();
            } catch (\Exception $exception) {
                echo ' ';
            }

            if ($bullet) {
                DB::alteration_message($message, $type);
            } else {
                echo $message;
            }
        }
    }


    public function Link()
    {
        return '/dev/tasks/' . $this->Config()->get('segment');
    }

    public function runStart($request)
    {
        ini_set('memory_limit', '512M');
        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);

        $this->flushNow('<h2>Starting</h2>', false);
        echo '<pre>';

    }

    public function runEnd($request)
    {
        echo '</pre>';
        $this->flushNow('<h2>======================</h2>');

        $this->flushNow('<h2>------ END -----------</h2>');
        $this->flushNow('<h2>======================</h2>');
    }


}
