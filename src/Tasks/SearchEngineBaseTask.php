<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\PolyExecution\PolyOutput;
use Exception;
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
    protected string $title = 'Base Search Engine Task';

    /**
     * description of the task.
     *
     * @var string
     */
    protected static string $description = 'Does not do anything special, just sets up the task.';

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
    protected static string $commandName = 'searchenginebasetask';

    /**
     * Stored PolyOutput instance for use in helper methods.
     */
    protected PolyOutput $polyOutput;

    public function getOptions(): array
    {
        return [
            new InputOption('verbose', null, InputOption::VALUE_REQUIRED, 'on or off', 'on'),
            new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of items to process', 100000),
            new InputOption('step', null, InputOption::VALUE_REQUIRED, 'Step size', 10),
            new InputOption('type', null, InputOption::VALUE_REQUIRED, 'Type: history, indexes, or all', ''),
            new InputOption('oldonesonly', null, InputOption::VALUE_REQUIRED, 'on or off', 'off'),
            new InputOption('unindexedonly', null, InputOption::VALUE_REQUIRED, 'on or off', 'off'),
            new InputOption('task', null, InputOption::VALUE_REQUIRED, 'Sub-task name to redirect to', ''),
        ];
    }

    /**
     * this function runs the SearchEngineRemoveAll task.
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->polyOutput = $output;

        //set basics
        $this->runStart($input);

        if ($this->task && 'searchenginebasetask' !== $this->task) {
            // @TODO (SS6 upgrade): In SS5 this redirected to another task via HTTP. In SS6 CLI context,
            // sub-task redirection via Controller::curr()->redirect() is not available.
            // Run the correct task directly using sake tasks:<task-name> instead.
            $output->writeln('Please run the sub-task directly: sake tasks:' . $this->task);
            return Command::SUCCESS;
        }

        $this->runEnd();
        return Command::SUCCESS;
    }

    public function flushNow($message, $type = '', $bullet = true)
    {
        if ($this->verbose) {
            if ($bullet) {
                $this->polyOutput->writeForHtml($message);
            } else {
                $this->polyOutput->writeln(strip_tags((string) $message));
            }
        }
    }

    public function Link()
    {
        return '/dev/tasks/' . static::$commandName;
    }

    public function runStart(InputInterface $input)
    {
        ini_set('memory_limit', '512M');
        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);

        $this->flushNow('<h2>Starting</h2>', false);

        if ($input->getOption('verbose') !== null) {
            $this->verbose = 'on' === $input->getOption('verbose');
        }

        if ($input->getOption('limit')) {
            $this->limit = (int) $input->getOption('limit');
        }

        if ($input->getOption('step')) {
            $this->step = (int) $input->getOption('step');
        }

        if ($input->getOption('type')) {
            $this->type = (string) $input->getOption('type');
        }

        if ($input->getOption('oldonesonly') !== null) {
            $this->oldOnesOnly = 'on' === $input->getOption('oldonesonly');
        }

        if ($input->getOption('unindexedonly') !== null) {
            $this->unindexedOnly = 'on' === $input->getOption('unindexedonly');
        }

        $this->task = (string) ($input->getOption('task') ?? '');

        $this->flushNow('<strong>verbose</strong>: ' . ($this->verbose ? 'yes' : 'no'));
        $this->flushNow('<strong>limit</strong>: ' . $this->limit);
        $this->flushNow('<strong>step</strong>: ' . $this->step);
        $this->flushNow('<strong>type</strong>: ' . $this->type);
        $this->flushNow('<strong>old ones only</strong>: ' . ($this->oldOnesOnly ? 'yes' : 'no'));
        $this->flushNow('<strong>unindexedonly only</strong>: ' . ($this->unindexedOnly ? 'yes' : 'no'));
        $this->flushNow('<strong>task</strong>: ' . $this->task);
        $this->flushNow('==========================', false);
    }

    public function runEnd()
    {
        $this->flushNow('<h2>======================</h2>');
        $this->polyOutput->writeln('Available sub-tasks: sake tasks:searchengineremoveall | sake tasks:searchengineindexall | sake tasks:searchengineupdatesearchindex | sake tasks:searchengineremovetobeindexed');
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
