<?php

namespace Sunnysideup\SearchSimpleSmart\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;

class SearchEngineRecordClick extends Controller
{
    private static $allowed_actions = [
        'add' => true,
    ];

    /**
     * record the click that the user chooses from the search results.
     * @param HTTPRequest $request
     * @return string
     */
    public function add(HTTPRequest $request)
    {
        $itemID = intval($request->param('ID'));
        if ($itemID) {
            $item = SearchEngineDataObject::get()->byID($itemID);
            if ($item) {
                SearchEngineSearchRecordHistory::register_click($item);
                return $this->redirect($item->SourceObject()->Link());
            }
        }
        $url = empty($_GET['finaldestination']) ? '404' : $_GET['finaldestination'];

        return $this->redirect(Director::absoluteURL($url));
    }
}
