<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Control\HTTPRequest;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;

class SearchEngineSpecialKeywords extends SearchEngineBaseTask
{
    /**
     * title of the task.
     *
     * @var string
     */
    protected string $title = 'Find special characters in keywords';

    /**
     * description of the task.
     *
     * @var string
     */
    protected static string $description = 'Go through all the keywords and work out what keywords have special characters.';

    protected $regex1 = '/[^A-Za-z0-9 ]/';

    protected $regex2 = '/\\P{L}+/u';

    protected $step = 1000;

    /**
     * Set a custom url segment (to follow dev/tasks/).
     *
     * @config
     *
     * @var string
     */
    protected static string $commandName = 'searchenginespecialkeywords';

    /**
     * this function runs the SearchEngineRemoveAll task.
     *
     * @param HTTPRequest $request
     */
    #[Override]
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        header('Content-Type: text/html; charset=utf-8');
        $this->runStart($request);
        $count = SearchEngineKeyword::get()->count();
        if ($count > $this->limit) {
            $count = $this->limit;
        }

        $characters = [];
        for ($i = 0; $i < $count; $i += $this->step) {
            $this->flushNow('.', '', true);
            $keywords = SearchEngineKeyword::get()->limit($this->step, $i)->column('Keyword');
            foreach ($keywords as $keyword) {
                $newKeyword = preg_replace($this->regex1, '', (string) $keyword);
                $newKeyword = preg_replace($this->regex2, '', (string) $newKeyword);
                if ($newKeyword !== $keyword) {
                    $this->flushNow('x', '', false);
                    $array = str_split((string) $keyword);
                    foreach ($array as $char) {
                        if (!str_contains((string) $newKeyword, $char)) {
                            $this->flushNow('!', '', false);
                            if (! isset($characters[$char])) {
                                $characters[$char] = $char;
                                $this->flushNow('Found character: ' . $char);
                            }
                        }
                    }
                } else {
                    $this->flushNow('.', '', false);
                }
            }
        }

        foreach ($characters as $char) {
            $this->flushNow(mb_convert_encoding($char, 'UTF-8', 'ISO-8859-1'));
        }

        $this->runEnd($request);
        return Command::SUCCESS;
    }
}
