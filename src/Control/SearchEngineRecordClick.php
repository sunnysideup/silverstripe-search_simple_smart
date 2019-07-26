<?php

namespace Sunnysideup\SearchSimpleSmart\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;

class SearchEngineRecordClick extends Controller
{
    private static $allowed_actions = ['add'];

    /**
     * record the click that the user chooses from the search results.
     * @param SS_HTTPRequest $request
     * @return string
     */
    public function add(HTTPRequest $request)
    {
        $itemID = $request->param('ID');
        $item = SearchEngineDataObject::get()->byID(intval($itemID));
        if ($item) {
            $obj = SearchEngineSearchRecordHistory::register_click($item);
            return $this->redirect($item->SourceObject()->Link());
        }
        $url = isset($_GET['finaldestination']) ? $_GET['finaldestination'] : '404';
        user_error('history could not be recorded', E_USER_NOTICE);
        return $this->redirect(Director::absoluteURL($url));
    }
}
