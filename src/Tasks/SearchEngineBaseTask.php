<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SearchEngineBaseTask extends BuildTask
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
    protected $limit = 100000;

    /**
     * @var int
     */
    protected $step = 10;

    /**
     * title of the task.
     *
     * @var string
     */
    protected $title = 'Base Search Engine Task';

    /**
     * description of the task.
     *
     * @var string
     */
    protected $description = 'Does not do anything special, just sets up the task.';

    /**
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
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    private static $segment = 'searchenginebasetask';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        //set basics
        $this->runStart($request);

        if ($this->task && 'searchenginebasetask' !== $this->task) {
            unset($_GET['task'], $_GET['submit']);

            return Controller::curr()->redirect('/dev/tasks/' . $this->task . '/?' . http_build_query($_GET));
        }

        $this->runEnd($request);
        return null;
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

        if ($request) {
            if ($request->getVar('verbose')) {
                $this->verbose = 'on' === $request->getVar('verbose');
            }

            if ($request->getVar('limit')) {
                $this->limit = (int) $request->getVar('limit');
            }

            if ($request->getVar('step')) {
                $this->step = (int) $request->getVar('step');
            }

            if ($request->getVar('type')) {
                $this->type = $request->getVar('type');
            }

            if ($request->getVar('oldonesonly')) {
                $this->oldOnesOnly = 'on' === $request->getVar('oldonesonly');
            }

            if ($request->getVar('unindexedonly')) {
                $this->unindexedOnly = 'on' === $request->getVar('unindexedonly');
            }

            $this->task = $request->getVar('task');
        } else {
            $this->task = self::$segment;
        }

        $this->flushNow('<strong>verbose</strong>: ' . ($this->verbose ? 'yes' : 'no'));
        $this->flushNow('<strong>limit</strong>: ' . $this->limit);
        $this->flushNow('<strong>step</strong>: ' . $this->step);
        $this->flushNow('<strong>type</strong>: ' . $this->type);
        $this->flushNow('<strong>old ones only</strong>: ' . ($this->oldOnesOnly ? 'yes' : 'no'));
        $this->flushNow('<strong>unindexedonly only</strong>: ' . ($this->unindexedOnly ? 'yes' : 'no'));
        $this->flushNow('<strong>task</strong>: ' . $this->task);
        $this->flushNow('==========================', false);
    }

    public function runEnd($request)
    {
        $this->flushNow('<h2>======================</h2>');

        if (! Director::is_cli()) {
            $html =
            '
            <style>
                div {padding: 20px;}
            </style>
            <form method="get" action="/dev/tasks/searchenginebasetask/">
                <fieldset>
                    ' . $this->createOptionList() . '

                    ' . $this->onOffInput('verbose', 'verbose', 'verbose') . '
                    <div><input name="limit" value="100000" type="number" /> limit</div>
                    <div><input name="step" value="10" type="number" /> step</div>
                    ' . $this->onOffInput('unindexedonly', 'unindexedOnly', 'Unindexed Only') . '
                    ' . $this->onOffInput('oldonesonly', 'oldOnesOnly', 'Old Ones Only') . '
                    <div>
                        <select name="type">
                            <option value="">--- choose type ---</option>
                            <option value="history">history</option>
                            <option value="indexes">indexes</option>
                            <option value="all">all</option>
                        </select>
                        type (only applicable to deletes)
                    </div>
                </fieldset>

                <fieldset>
                    <div><input name="submit" value="go" type="submit"/></div>
                </fieldset>
            </form>

            ';

            $this->flushNow($html);
        }

        $this->flushNow('<h2>------ END -----------</h2>');
        $this->flushNow('<h2>======================</h2>');
    }

    protected function onOffInput(string $field, string $property, string $label): string
    {
        $checkedOff = '';
        $checkedOn = '';
        if ($this->{$property}) {
            $checkedOn = 'checked="checked"';
        } else {
            $checkedOff = 'checked="checked"';
        }

        return '
        <div>
            ' . $label . '
            <input name="' . $field . '" ' . $checkedOff . ' value="off" type="radio" /> no |
            <input name="' . $field . '" ' . $checkedOn . ' value="on" type="radio" /> yes
        </div>
        ';
    }

    protected function createOptionList(): string
    {
        return '
        <div>
            <select name="task">
                <option value="">--- choose task ---</option>
                <option value="searchengineremoveall">search engine removeall: removes ALL the search data</option>
                <option value="searchengineindexall">index all: index all the indexable objects on your site</option>
                <option value="searchengineupdatesearchindex">update search index: update objects that need updating</option>
                <option value="searchengineremovetobeindexed">remove all objects that are marked to be indexed</option>
                <option value="">--- CLEAN UP ---</option>
                <option value="searchenginecleardataobjectdoubles">dataobject doubles: look for doubles</option>
                <option value="searchenginecleartobeindexeddoubles">remove index double: remove doubles</option>
                <option value="searchenginesetsortdate">update search sort dates: update sort dates</option>
                <option value="searchengineclearobsoletes">clear obsoletes</option>
                <option value="">--- SPECIALTY ---</option>
                <option value="searchenginecreatekeywordjs">create keyword js</option>
                <option value="searchenginespecialkeywords">special keywords</option>
            </select>
            type
        </div>';
    }
}
