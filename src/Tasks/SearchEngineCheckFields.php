<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\SearchSimpleSmart\Api\CheckFieldsApi;

class SearchEngineCheckFields extends BuildTask
{
    /**
     * title of the task.
     *
     * @var string
     */
    protected string $title = 'Search Engine: List of All fields that can be indexed';

    /**
     * description of the task.
     *
     * @var string
     */
    protected static string $description = 'Goes through all DataObjects and lists the fields that can be indexed. ';

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
    protected static string $commandName = 'checkfields';

    /**
     * Stored PolyOutput instance for use in helper methods.
     */
    protected PolyOutput $polyOutput;

    /**
     * this function runs the SearchEngineCheckFields task.
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->polyOutput = $output;

        //set basics
        $this->runStart();
        $end = '';
        $start = '';
        $start .= 'Sunnysideup\SearchSimpleSmart\Api\CheckFieldsApi:';
        $start .= PHP_EOL . '  default_exclude_classes:';
        $start .= PHP_EOL . '    - SilverStripe\RedirectedURLs\Model\RedirectedURL';
        $start .= PHP_EOL . '  default_exclude_class_field_combos:';
        $start .= PHP_EOL . '    SilverStripe\Assets\File: priority';
        $start .= PHP_EOL;
        $start .= PHP_EOL . 'Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject:';
        $start .= PHP_EOL . '  default_exclude_fields:';
        $start .= PHP_EOL . '    - ExtraClass';
        $start .= PHP_EOL . '  classes_to_exclude: []';
        $start .= PHP_EOL . '  classes_to_include:';
        $array = (CheckFieldsApi::create())->getList();
        foreach ($array['AllValidFields'] as $className => $classData) {
            $hasFields = count($classData['Level1']) + count($classData['Level2']) > 0;
            if ($classData['IsBaseClass'] === false && $hasFields === false) {
                continue;
            }

            $end .= PHP_EOL . $className . ':';
            if ($classData['IsBaseClass'] === true) {
                $start .= PHP_EOL . '    - ' . $className;
                $end .= PHP_EOL . '  extensions:';
                $end .= PHP_EOL . '    - Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable';
            }

            if ($hasFields) {
                $end .= PHP_EOL . '  search_engine_full_contents_fields_array:';
                for ($i = 1; $i < 3; $i++) {
                    $level = 'Level' . $i;
                    if (count($classData[$level]) > 0) {
                        $end .= PHP_EOL . '    ' . strtolower($level) . ':';
                        foreach ($classData[$level] as $fieldName) {
                            $end .= PHP_EOL . '      - ' . $fieldName;
                        }
                    }
                }
            }

            $end .= PHP_EOL;
        }

        $output->writeln($start . PHP_EOL . PHP_EOL . $end);
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

    public function runStart()
    {
        ini_set('memory_limit', '512M');
        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);

        $this->flushNow('<h2>Starting</h2>', false);
    }

    public function runEnd()
    {
        $this->flushNow('<h2>======================</h2>');
        $this->flushNow('<h2>------ END -----------</h2>');
        $this->flushNow('<h2>======================</h2>');
    }
}
