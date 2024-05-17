<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Forms\Fields\SearchEngineFormField;

class SearchHistoryBrowser extends BuildTask
{
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
    protected $startDaysAgo = 365;

    /**
     * @var int
     */
    protected $endDaysAgo = 0;

    /**
     * title of the task.
     *
     * @var string
     */
    protected $title = 'Search Engine: what people searched for';

    /**
     * description of the task.
     *
     * @var string
     */
    protected $description = 'Goes through the search history and shows what people searched for.';

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    private static $segment = 'searchhistorybrowser';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);
        $field = SearchEngineFormField::create('SearchEngineFormField', 'Search History');
        $field->setNumberOfDays($this->startDaysAgo - $this->endDaysAgo);
        $field->setEndingDaysBack($this->endDaysAgo);
        echo $field->forTemplate();

        $this->runEnd($request);
    }


    public function flushNow($message, $type = '', $bullet = true)
    {
        echo '<div style="padding: 20px;">' . $message . '</div>';
    }

    public function Link()
    {
        return '/dev/tasks/' . $this->Config()->get('segment');
    }

    public function runStart($request)
    {
        ini_set('memory_startDaysAgo', '512M');
        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);

        $this->flushNow('<h2>Starting</h2>', false);

        if ($request) {


            if ($request->getVar('startDaysAgo')) {
                $this->startDaysAgo = (int) $request->getVar('startDaysAgo');
            }

            if ($request->getVar('endDaysAgo')) {
                $this->endDaysAgo = (int) $request->getVar('endDaysAgo');
            }

        }

        $this->flushNow('<strong>FROM Days Ago</strong>: ' . $this->startDaysAgo);
        $this->flushNow('<strong>UNTIL Days Ago</strong>: ' . $this->endDaysAgo);
    }

    public function runEnd($request)
    {

        if (! Director::is_cli()) {
            $html =
            '
            <style>
                div {padding: 20px;}
            </style>
            <form method="get" action="'.$this->Link().'">
                <fieldset>

                    <div><input name="startDaysAgo" value="'.$this->startDaysAgo.'" type="number" /> FROM Days Ago (e.g. 365 = starting one year ago)</div>
                    <div><input name="endDaysAgo" value="'.$this->endDaysAgo.'" type="number" /> UNTIL Days Ago (e.g. 0 = up to today)</div>

                </fieldset>

                <fieldset>
                    <div><input name="submit" value="go" type="submit"/></div>
                </fieldset>
            </form>

            ';

            $this->flushNow($html);
        }

        $this->flushNow('<h2>======================</h2>');
    }

}
