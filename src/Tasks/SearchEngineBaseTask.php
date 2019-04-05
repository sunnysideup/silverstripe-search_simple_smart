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
use SilverStripe\Control\Controller;

class SearchEngineBaseTask extends BuildTask
{

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginebasetask';

    /**
     * @var string
     */
    protected $task = '';

    /**
     * @var string
     */
    protected $type = '';

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
    protected $title = 'Base Search Engine Task';

    /**
     * description of the task
     * @var string
     */
    protected $description = 'Does not do anything special, just sets up the task.';

    /**
     *
     * @var bool
     */
    protected $verbose = true;

    /**
     * @var bool
     */
    protected $unindexedOnly = false;

    /**
     * @var bool
     */
    protected $oldOnesOnly = false;

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param var $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);

        if($this->task) {
            unset($_GET['task']);
            unset($_GET['submit']);
            return Controller::curr()->redirect('/dev/tasks/'.$this->task.'/?'.http_build_query($_GET));
        }

        $this->runEnd($request);
    }



    public function flushNow($message, $type = '', $bullet = true)
    {
        if($this->verbose) {
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


    function Link()
    {
        return '/dev/tasks/'.$this->Config()->get('segment');
    }

    public function runStart($request)
    {
        ini_set('memory_limit', '512M');
        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);

        $this->flushNow('<h2>Starting</h2>', false);

        $verbose = intval($request->getVar('verbose'));
        if($verbose) {$this->verbose = $verbose;}
        $this->flushNow('verbose: '.$this->verbose ? 'yes' : 'no');

        $limit = intval($request->getVar('limit'));
        if($limit) {$this->limit = $limit;}
        $this->flushNow('limit: '.$this->limit);

        $step = intval($request->getVar('step'));
        if($step) {$this->step = $step;}
        $this->flushNow('step: '.$this->step);

        $type = $request->getVar('type');
        if($type) {$this->type = $type;}
        $this->flushNow('type: '.$this->type);

        $oldOnesOnly = $request->getVar('oldonesonly') ? true : false;
        if($oldOnesOnly) {$this->oldOnesOnly = $oldOnesOnly;}
        $this->flushNow('old ones only: '.$this->oldOnesOnly ? 'yes' : 'no');

        $unindexedOnly = $request->getVar('unindexedonly') ? true : false;
        if($unindexedOnly) {$this->unindexedOnly = $unindexedOnly;}
        $this->flushNow('unindexed only: '.$this->unindexedOnly ? 'yes' : 'no');

        $task = $request->getVar('task');
        if($task) {$this->task = $task;}

        $this->flushNow('<h2>Starting</h2>', false);

    }

    public function runEnd($request)
    {



        $this->flushNow('<h2>======================</h2>');


        $html =
        '
        <style>
            div {padding: 20px;}
        </style>
        <form method="get" action="/dev/tasks/searchenginebasetask/">
            <fieldset>
            <div>
                <select name="task">
                    <option value="">--- choose task ---</option>
                    <option value="searchengineremoveall">search engine removeall</option>
                    <option value="searchengineindexall">index all</option>
                    <option value="searchenginecleardataobjectdoubles">dataobject doubles</option>
                    <option value="searchenginecleartobeindexeddoubles">remove index double</option>
                    <option value="searchengineupdatesearchindex">update search index</option>
                    <option value="searchengineclearobsoletes">clear obsoletes</option>
                    <option value="searchenginecreatekeywordjs">create keyword js</option>
                </select>
                type
            </div>

            <div><input name="verbose" checked="checked" type="checkbox" /> verbose</div>
            <div><input name="limit" value="100000" type="number" /> limit</div>
            <div><input name="step" value="10" type="number" /> step</div>
            <div><input name="unindexedonly" checked="checked" type="checkbox" /> unindexed only</div>
            <div><input name="oldonesonly" checked="checked" type="checkbox" /> old ones only</div>
            <div>
                <select name="type">
                    <option value="">--- choose type ---</option>
                    <option value="history">history</option>
                    <option value="indexes">indexes</option>
                    <option value="all">all</option>
                </select>
                type
            </div>
            </fieldset>
            <fieldset>
            <div><input name="submit" value="go" type="submit"/></div>
            </fieldset>
        </form>

        ';

        $this->flushNow($html);

        $this->flushNow('<h2>------ END -----------</h2>');
        $this->flushNow('<h2>======================</h2>');
    }

}
