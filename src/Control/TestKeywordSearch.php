<?php

namespace Sunnysideup\SearchSimpleSmart\Control;

use SilverStripe\Control\Controller;
use Sunnysideup\SearchSimpleSmart\Core\SearchEngineCoreSearchMachine;
use Page;

class TestKeywordSearch extends Controller
{
    private static $url_segment = 'tests/testkeywordsearch';

    private static $allowed_actions = [
        'index' => 'ADMIN',
    ];

    public function index()
    {
        $page = Page::get()->first();
        $keywords = strtok($page->Title, ' ');
        echo '<h2>search for: ' . $keywords . '</h2>';
        $obj = (new SearchEngineCoreSearchMachine());
        $searchList = $obj
            ->setDebug(true)
            ->run($keywords)
        ;
        print_r($obj->getDebugString());
        echo '---------';
        echo '<h2>Result Count</h2>';
        // var_dump($keywords);
        var_dump($searchList->Count());
        echo '<h2>Results</h2>';
        foreach ($searchList as $item) {
            echo '<li>'.$item->title.'</li>';
        }
    }
}
