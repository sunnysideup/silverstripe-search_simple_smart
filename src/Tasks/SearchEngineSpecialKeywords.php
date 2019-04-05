<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;

class SearchEngineSpecialKeywords extends SearchEngineBaseTask
{



    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginespecialkeywords';

    /**
     * title of the task
     * @var string
     */
    protected $title = "Find special characters in keywords";

    /**
     * description of the task
     * @var string
     */
    protected $description = "Go through all the keywords and work out what keywords have special characters.";

    protected $regex1 = "/[^A-Za-z0-9 ]/";
    protected $regex2 = "/\P{L}+/u";


    protected $step = 1000;

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param var $request
     */
    public function run($request)
    {
        header('Content-Type: text/html; charset=utf-8');
        $this->runStart($request);

        $count = SearchEngineKeyword::get()->count();
        if($count > $this->limit) {
            $count = $this->limit;
        }

        $characters = [];


        for($i = 0; $i < $count; $i = $i + $this->step){
            $this->flushNow('.', '', true);
            $keywords = SearchEngineKeyword::get()->limit($this->step, $i)->column('Keyword');
            foreach($keywords as $keyword) {
                $newKeyword = preg_replace($this->regex2, '', $keyword);
                if($newKeyword !== $keyword) {
                    $this->flushNow('x', '', false);
                    $array = str_split($keyword);
                    foreach($array as $char) {
                        if(strpos($newKeyword, $char) === false) {
                            $this->flushNow('!', '', false);
                            if(! isset($characters[$char])) {
                                $characters[$char] = $char;
                                $this->flushNow('Found character: '. $char);
                            }
                        }
                    }
                } else {
                    $this->flushNow('.', '', false);
                }
            }
        }

        foreach($characters as $char) {
            $this->flushNow(utf8_encode($char));
        }

        $this->runEnd($request);
    }





}
