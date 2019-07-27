<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\Core;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\SearchSimpleSmart\Forms\SearchEngineBasicForm;

class SearchEngineContentControllerExtension extends Extension
{
    /**
     * An array of actions that can be accessed via a request. Each array element should be an action name, and the
     * permissions or conditions required to allow the user to access it.
     *
     * <code>
     * array (
     *     'action', // anyone can access this action
     *     'action' => true, // same as above
     *     'action' => 'ADMIN', // you must have ADMIN permissions to access this action
     *     'action' => '->checkAction' // you can only access this action if $this->checkAction() returns true
     * );
     * </code>
     *
     * @var array
     */
    private static $allowed_actions = [
        'SearchEngineBasicForm',
        'SearchEngineSuperBasicForm',
        'SearchEngineCustomForm',
    ];

    private $_mySearchEngineBasicForm = null;

    private $_mySearchEngineSuperBasicForm = null;

    private $_mySearchEngineCustomForm = null;

    /**
     * this function returns a new Search Engine Form
     * @return SearchEngineBasicForm
     */
    public function SearchEngineBasicForm()
    {
        $this->SearchEngineClearHistoryID();
        if (! $this->_mySearchEngineBasicForm) {
            $this->_mySearchEngineBasicForm = SearchEngineBasicForm::create($this->owner, SearchEngineBasicForm::class)
                ->setIsMoreDetailsResult(true)
                ->setNumberOfResultsPerPage(20)
                ->setIncludeFilter(true)
                ->setIncludeSort(true)
                ->setUseAutoComplete(true)
                ->setUseInfiniteScroll(true)
                ->setUpdateBrowserHistory(true);
        }
        return $this->_mySearchEngineBasicForm;
    }

    /**
     * this function returns a new Search Engine Form
     * @return SearchEngineBasicForm
     */
    public function SearchEngineSuperBasicForm()
    {
        $this->SearchEngineClearHistoryID();
        if (! $this->_mySearchEngineSuperBasicForm) {
            $this->_mySearchEngineSuperBasicForm = SearchEngineBasicForm::create($this->owner, 'SearchEngineSuperBasicForm');
        }
        return $this->_mySearchEngineSuperBasicForm;
    }

    /**
     * this function returns a new Search Engine Form
     * @return SearchEngineBasicForm
     */
    public function SearchEngineCustomForm()
    {
        $this->SearchEngineClearHistoryID();
        if (! $this->_mySearchEngineCustomForm) {
            $this->_mySearchEngineCustomForm = SearchEngineBasicForm::create($this->owner, 'SearchEngineCustomForm')
                ->setOutputAsJSON(true);
        }
        return $this->_mySearchEngineCustomForm;
    }

    public function SearchEngineKeywordsPhrase()
    {
        $val = isset($_GET['SearchEngineKeywords']) ? $_GET['SearchEngineKeywords'] : '';

        return DBField::create_field('Varchar', $val);
    }

    protected function SearchEngineClearHistoryID()
    {
        //clear old history
        if (Director::is_ajax()) {
            //do nothing
        } else {
            $request = $this->owner->getRequest();
            $request->getSession()->clear('SearchEngineSearchRecordHistoryID');
            $request->getSession()->set('SearchEngineSearchRecordHistoryID', 0);
            $request->getSession()->save($request);
        }
    }
}
