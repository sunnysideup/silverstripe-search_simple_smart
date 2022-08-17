<?php

namespace Sunnysideup\SearchSimpleSmart\Control;

use SilverStripe\Control\Controller;
use Sunnysideup\SearchSimpleSmart\Core\SearchEngineCoreSearchMachine;
use Page;

class TestKeywordSearch extends Controller
{
    private static $url_segment = 'admin/tests/search';

    private static $allowed_actions = [
        'test' => 'ADMIN',
    ];

    public function test()
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
        // var_dump($keywords);
        var_dump($searchList->Count());
        foreach ($searchList as $item) {
            print_r($item);
        }
    }
}
