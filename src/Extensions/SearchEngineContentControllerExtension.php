<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\Core\Extension;
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

    private $_mySearchEngineBasicForm;

    private $_mySearchEngineSuperBasicForm;

    private $_mySearchEngineCustomForm;

    /**
     * this function returns a new Search Engine Form.
     */
    public function SearchEngineBasicForm(): SearchEngineBasicForm
    {
        if (! $this->_mySearchEngineBasicForm) {
            $this->_mySearchEngineBasicForm = SearchEngineBasicForm::create($this->getOwner(), 'SearchEngineBasicForm')
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
        if (! $this->_mySearchEngineSuperBasicForm) {
            $this->_mySearchEngineSuperBasicForm = SearchEngineBasicForm::create($this->getOwner(), 'SearchEngineSuperBasicForm');
        }

        return $this->_mySearchEngineSuperBasicForm;
    }

    /**
     * this function returns a new Search Engine Form.
     */
    public function SearchEngineCustomForm(): SearchEngineBasicForm
    {
        if (! $this->_mySearchEngineCustomForm) {
            $this->_mySearchEngineCustomForm = SearchEngineBasicForm::create($this->getOwner(), 'SearchEngineCustomForm')
                ->setOutputAsJSON(true)
            ;
        }

        return $this->_mySearchEngineCustomForm;
    }

    /**
     * @return DBField (DBVarchar)
     */
    public function SearchEngineKeywordsPhrase()
    {
        $val = $_GET['SearchEngineKeywords'] ?? '';

        return DBField::create_field('Varchar', $val);
    }
}
