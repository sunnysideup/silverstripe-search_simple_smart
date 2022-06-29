<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBVarchar;
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

    private $_mySearchEngineBasicForm;

    private $_mySearchEngineSuperBasicForm;

    private $_mySearchEngineCustomForm;

    /**
     * this function returns a new Search Engine Form.
     */
    public function SearchEngineBasicForm(): SearchEngineBasicForm
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
                ->setUpdateBrowserHistory(true)
            ;
        }

        return $this->_mySearchEngineBasicForm;
    }

    /**
     * this function returns a new Search Engine Form.
     */
    public function SearchEngineSuperBasicForm(): SearchEngineBasicForm
    {
        $this->SearchEngineClearHistoryID();
        if (! $this->_mySearchEngineSuperBasicForm) {
            $this->_mySearchEngineSuperBasicForm = SearchEngineBasicForm::create($this->owner, 'SearchEngineSuperBasicForm');
        }

        return $this->_mySearchEngineSuperBasicForm;
    }

    /**
     * this function returns a new Search Engine Form.
     */
    public function SearchEngineCustomForm(): SearchEngineBasicForm
    {
        $this->SearchEngineClearHistoryID();
        if (! $this->_mySearchEngineCustomForm) {
            $this->_mySearchEngineCustomForm = SearchEngineBasicForm::create($this->owner, 'SearchEngineCustomForm')
                ->setOutputAsJSON(true)
            ;
        }

        return $this->_mySearchEngineCustomForm;
    }

    /**
     * @return DBVarchar
     */
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
            $request = $this->getOwner()->getRequest();
            $request->getSession()->clear('SearchEngineSearchRecordHistoryID');
            $request->getSession()->set('SearchEngineSearchRecordHistoryID', 0);
            $request->getSession()->save($request);
        }
    }
}
