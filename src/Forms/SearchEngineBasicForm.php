<?php

namespace Sunnysideup\SearchSimpleSmart\Forms;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\GroupedList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineFilterForDescriptor;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;
use Sunnysideup\SearchSimpleSmart\Core\SearchEngineCoreSearchMachine;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;

class SearchEngineBasicForm extends Form
{
    /**
     * @var bool
     */
    protected $isMoreDetailsResult = false;

    /**
     * starting point.
     *
     * @var int
     */
    protected $start = 0;

    /**
     * @var int
     */
    protected $numberOfResultsPerPage = 10;

    /**
     * @var int
     */
    protected $totalNumberOfItemsToReturn = 9999;

    /**
     * @var bool
     */
    protected $includeFilter = false;

    /**
     * @var bool
     */
    protected $includeSort = false;

    /**
     * @var bool
     */
    protected $useAutoComplete = true;

    /**
     * @var bool
     */
    protected $updateBrowserHistory = false;

    /**
     * alternative input field selector.
     *
     * @var string
     */
    protected $displayedFormInputSelector = '';

    /**
     * @var bool
     */
    protected $useInfiniteScroll = false;

    /**
     * @var bool
     */
    protected $outputAsJSON = false;

    /**
     * @var array
     */
    protected $customScript = [];

    /**
     * @var string
     */
    protected $keywords = '';

    /**
     * @var bool
     */
    protected $setLimitToZero = false;

    protected static $_for_template_completed = false;

    /**
     * @var string
     */
    private static $jquery_source = 'none';

    /**
     * classnames of sort classes to be used
     * - ClassName
     * - ClassName
     * - ClassName.
     *
     * @var array
     */
    private static $sort_by_options = [
        //"SearchEngineSortByRelevance",
        //"SearchEngineSortByLastEdited"
    ];

    /**
     * classnames of filters to be used
     * - ClassName
     * - ClassName
     * - ClassName.
     *
     * @var array
     */
    private static $filter_for_options = [
        //"SearchEngineFilterForClassName",
        //"SearchEngineFilterForRecent"
    ];

    /**
     * class name of the page that is used to show search results.
     *
     * @var string
     */
    private static $full_results_page_type = '';

    /**
     * this function constructs a new Search Engine Basic Form.
     *
     * @param mixed  $controller
     * @param string $name
     *
     * @return SearchEngineBasicForm
     */
    public function __construct($controller, $name)
    {
        if (isset($_GET['SearchEngineKeywords'])) {
            $this->keywords = $_GET['SearchEngineKeywords'];
        }

        $fields = new FieldList(
            TextField::create('SearchEngineKeywords', _t('SearchEngineBasicForm.KEYWORDS', ' '), $this->keywords)
                ->setAttribute('placeholder', _t('SearchEngineBasicForm.WHAT_ARE_YOU_LOOKING_FOR', 'Search phrase ...'))
                ->addExtraClass('awesomplete')
                ->setAttribute('autocomplete', 'off')
        );

        $actions = new FieldList(
            FormAction::create('doSubmitForm', 'Search')
        );

        parent::__construct($controller, $name, $fields, $actions);

        $this->setFormMethod('GET');
        $this->setAttribute('autocomplete', 'false');
        $this->disableSecurityToken();
        $this->customScript[] = "SearchEngineInitFunctions.formSelector = '#" . $this->FormName() . "';";
    }

    public function forTemplate()
    {
        if (! self::$_for_template_completed) {
            $this->addFields();

            //requirements
            $this->workOutRequirements();

            //extra classes
            $this->addExtraClass('searchEngineFormForm');
            if ($this->includeFilter || $this->includeSort) {
                $this->addExtraClass('searchEngineFormWithSideBar');
            } else {
                $this->addExtraClass('searchEngineFormWithoutSideBar');
            }
        }

        return parent::forTemplate();
    }

    /**
     * this method submits the Search Engine Form.
     *
     * @param array                 $data
     * @param SearchEngineBasicForm $form
     */
    public function doSubmitForm($data, $form)
    {
        if (Director::is_ajax()) {
            if ($this->outputAsJSON) {
                if ($this->controller->getResponse()) {
                    $this->controller->getResponse()->addHeader('Content-Type', 'text/json');
                }
            }

            Requirements::clear();

            return $this->workOutResults($data);
        }

        return [];
    }

    /**
     * @param bool $b
     *
     * @return SearchEngineBasicForm
     */
    public function setIncludeSort($b): self
    {
        $this->includeSort = $b;
        $this->Fields()->removeByName('SortBy');

        return $this;
    }

    /**
     * @return SearchEngineBasicForm
     */
    public function setIncludeFilter(bool $b): self
    {
        $this->includeFilter = $b;
        $this->Fields()->removeByName('FilterFor');

        return $this;
    }

    /**
     * this function sets the number of items to return
     * per page when a search is conducted.
     *
     * @return SearchEngineBasicForm
     */
    public function setNumberOfResultsPerPage(int $i): self
    {
        $this->numberOfResultsPerPage = $i;

        return $this;
    }

    /**
     * total number of items to return.
     *
     * @return SearchEngineBasicForm
     */
    public function setTotalNumberOfItemsToReturn(int $i): self
    {
        $this->totalNumberOfItemsToReturn = $i;

        return $this;
    }

    /**
     * what is the first item to return.
     *
     * @return SearchEngineBasicForm
     */
    public function setIsMoreDetailsResult(bool $b): self
    {
        $this->isMoreDetailsResult = $b;

        return $this;
    }

    /**
     * what is the first item to return.
     *
     * @return SearchEngineBasicForm
     */
    public function setStart(int $i): self
    {
        $this->start = $i;

        return $this;
    }

    /**
     * @return SearchEngineBasicForm
     */
    public function setUseAutoComplete(bool $b): self
    {
        $this->useAutoComplete = $b;

        return $this;
    }

    /**
     * @return SearchEngineBasicForm
     */
    public function setUseInfiniteScroll(bool $b): self
    {
        $this->useInfiniteScroll = $b;

        return $this;
    }

    /**
     * @return SearchEngineBasicForm
     */
    public function setdisplayedFormInputSelector(string $string): self
    {
        $this->displayedFormInputSelector = $string;

        return $this;
    }

    /**
     * @return SearchEngineBasicForm
     */
    public function setOutputAsJSON(bool $b): self
    {
        $this->outputAsJSON = $b;

        return $this;
    }

    /**
     * @param bool $b
     *
     * @return SearchEngineBasicForm
     */
    public function setUpdateBrowserHistory($b): self
    {
        $this->updateBrowserHistory = $b;

        return $this;
    }

    protected function addFields()
    {
        $sortBy = $this->SortByProvider();
        if ($sortBy && count($sortBy) > 1) {
            $default = isset($_GET['SortBy']) ? $_GET['SortBy'] : key($sortBy);
            if ($this->includeSort) {
                $this->Fields()->insertAfter(
                    //TextField::create('SortBy', _t("SearchEngineBasicForm.SORT_BY", "Sort by ..."), $sortBy, $default),
                    'SearchEngineKeywords',
                    OptionsetField::create(
                        'SortBy',
                        _t('SearchEngineBasicForm.SORT_BY', 'Sort by ...'),
                        $sortBy,
                        $default
                    )
                );
            } else {
                $this->Fields()->insertAfter(
                    'SearchEngineKeywords',
                    HiddenField::create('SortBy', '', $default)
                );
            }
        }

        $filterFor = $this->FilterForProvider();
        if ($filterFor && count($filterFor) > 1) {
            if ($this->includeFilter) {
                $defaults = isset($_GET['FilterFor']) ? $_GET['FilterFor'] : [];
                $this->Fields()->insertAfter(
                    'SortBy',
                    CheckboxSetField::create('FilterFor', _t('SearchEngineBasicForm.FILTER_FOR', 'Filter for ...'), $filterFor)->setDefaultItems($defaults)
                );
            }
        }

        $results = '';
        if ($this->keywords) {
            if (Director::is_ajax()) {
                //do nothing
            } else {
                $this->resultsCompleted = true;
                $results = $this->workOutResults($_GET);
            }
        }

        $this->Fields()->push(LiteralField::create(
            'SearchEngineResultsHolderOuter',
            '<div id="SearchEngineResultsHolderOuter">' . $results . '</div>'
        ));
    }

    protected function workOutRequirements()
    {
        if($this->Config()->jquery_source ==='none') {
            //do nothing
        } elseif($this->Config()->jquery_source ==='block') {
            Requirements::block('silverstripe/admin: thirdparty/jquery/jquery.js');
        } elseif ($this->Config()->jquery_source) {
            Requirements::block('silverstripe/admin: thirdparty/jquery/jquery.js');
            Requirements::javascript($this->Config()->jquery_source);
        } else {
            Requirements::javascript('silverstripe/admin: thirdparty/jquery/jquery.js');
        }

        $hasScript = false;
        if ($this->useInfiniteScroll) {
            $hasScript = true;
            Requirements::javascript('sunnysideup/search_simple_smart: client/javascript/jquery.infinitescroll.min.js');
            $this->customScript[] = 'SearchEngineInitFunctions.useInfiniteScroll = true;';
        }

        if ($this->useAutoComplete) {
            $hasScript = true;
            Requirements::javascript('sunnysideup/search_simple_smart: client/javascript/SearchEngineInitFunctions.js');
            Requirements::javascript('sunnysideup/search_simple_smart: client/javascript/jquery.form.min.js');
            Requirements::javascript('sunnysideup/search_simple_smart: client/javascript/awesomplete.min.js');
            $this->customScript[] = 'SearchEngineInitFunctions.useAutoComplete = true;';
            $hasKeywordFile = ExportKeywordList::get_js_keyword_file_name($includeBase = false);
            if ($hasKeywordFile) {
                Requirements::javascript(ExportKeywordList::get_js_keyword_file_name($includeBase = false));
            }
        }
        if($hasScript) {
            if ($this->displayedFormInputSelector) {
                $this->customScript[] = 'SearchEngineInitFunctions.displayedFormInputSelector = "' . $this->displayedFormInputSelector . '";';
            }
            Requirements::themedCSS('client/css/SearchEngine', 'searchengine');
            Requirements::themedCSS('client/css/awesomplete', 'searchengine');
            if ($this->updateBrowserHistory) {
                $this->customScript[] = 'SearchEngineInitFunctions.updateBrowserHistory = true;';
            }

            Requirements::javascript('sunnysideup/search_simple_smart: client/javascript/SearchEngineInitFunctions.js');
            Requirements::customScript(implode("\n", $this->customScript), 'SearchEngineInitFunctions');
        }

        //css settings
    }

    /**
     * @param array $data
     */
    protected function workOutResults($data)
    {
        $results = $this->workOutResultsFilterAndSort($data);
        $count = 0;
        if ($results) {
            $count = $results->count();
        }

        $resultsPaginated = $this->workOutResultsPaginated($results);

        // After dealing with the data you can redirect the user back.
        $link = str_replace('&', '&amp;', $_SERVER['REQUEST_URI']);
        $fullResultsLink = '';
        $fullResultsClassName = (string) Config::inst()->get(self::class, 'full_results_page_type');
        if ($fullResultsClassName) {
            if ($this->Controller()->dataRecord->ClassName !== $fullResultsClassName) {
                if (is_subclass_of($fullResultsClassName, SiteTree::class)) {
                    $obj = DataObject::get_one($fullResultsClassName);
                    if ($obj) {
                        unset($data['url']);
                        $fullResultsLink = $obj->Link(self::class) . '?' . http_build_query($data, '', '&amp;');
                    }
                }
            }
        }

        $arrayData = new ArrayData(
            [
                'SearchedFor' => Convert::raw2xml($data['SearchEngineKeywords']),
                'Results' => $resultsPaginated,
                'ResultsGrouped' => GroupedList::create($resultsPaginated),
                'Count' => $count,
                'Link' => $link,
                'IsMoreDetailsResult' => $this->isMoreDetailsResult,
                'FullResultsLink' => $fullResultsLink,
                'NumberOfItemsPerPage' => $this->numberOfResultsPerPage,
                // 'DebugHTML' => $searchMachine->getDebugString(),
            ]
        );
        $classGroups = Config::inst()->get(SearchEngineSortByDescriptor::class, 'class_groups');
        if (is_array($classGroups) && count($classGroups)) {
            return $arrayData->renderWith('Sunnysideup\SearchSimpleSmart\Includes\SearchEngineSearchResultsOuterWithSpecialSortGrouping');
        }

        return $arrayData->renderWith('Sunnysideup\SearchSimpleSmart\Includes\SearchEngineSearchResultsOuter');
    }

    protected function workOutResultsFilterAndSort($data)
    {
        $results = null;
        $searchMachine = Injector::inst()->create(SearchEngineCoreSearchMachine::class);
        //filter for is an array!
        $filterForArray = [];
        $this->setLimitToZero = false;
        if (isset($data['FilterFor'])) {
            foreach (array_keys($data['FilterFor']) as $filterForClass) {
                $filterForArray[$filterForClass] = $filterForClass;
            }
        }

        $sortBy = (! empty($data['SortBy']) && class_exists($data['SortBy']) ? $data['SortBy'] : '');
        $this->start = (isset($_GET['start']) ? (int) $_GET['start'] : 0);
        if ($this->totalNumberOfItemsToReturn && $this->totalNumberOfItemsToReturn < $this->start) {
            //$results = SearchEngineDataObject::get()->filter("ID", -1);
            $this->setLimitToZero = true;
        } else {
            $results = $searchMachine->run(
                $data['SearchEngineKeywords'],
                $filterForArray,
                $sortBy
            );
            if ($this->totalNumberOfItemsToReturn && $this->totalNumberOfItemsToReturn < ($this->start + $this->numberOfResultsPerPage)) {
                $this->numberOfResultsPerPage = $this->totalNumberOfItemsToReturn - $this->start;
                if ($this->numberOfResultsPerPage < 1) {
                    $this->numberOfResultsPerPage = 1;
                    $this->setLimitToZero = true;
                }
            }
        }

        if (! $results) {
            $results = ArrayList::create();
        }

        return $results;
    }

    protected function workOutResultsPaginated($results): PaginatedList
    {
        //paginate
        $results = $this->setLimitToZero ? $results->limit(0) : $results->limit($this->totalNumberOfItemsToReturn);
        $resultsPaginated = PaginatedList::create($results, ['start' => $this->start]);

        $resultsPaginated->setPageLength($this->numberOfResultsPerPage);

        return $resultsPaginated;
    }

    /**
     * @return array
     */
    protected function SortByProvider()
    {
        $options = Config::inst()->get(self::class, 'sort_by_options');
        $array = [];

        foreach ($options as $className) {
            $provider = Injector::inst()->get($className);
            if (! $provider instanceof SearchEngineSortByDescriptor) {
                user_error($provider->ClassName . 'should extend SearchEngineSortByDescriptor - like so: class <u>' . $provider->ClassName . ' extends SearchEngineSortByDescriptor</u>');
            }

            $array[$className] = $provider->getShortTitle();
        }

        return $array;
    }

    /**
     * returns a list of searchable objects.
     *
     * @return array
     */
    protected function FilterForProvider()
    {
        $options = Config::inst()->get(self::class, 'filter_for_options');
        $array = [];

        foreach ($options as $className) {
            $provider = Injector::inst()->get($className);
            if (! $provider instanceof SearchEngineFilterForDescriptor) {
                user_error(
                    $provider->ClassName . 'should extend SearchEngineFilterForDescriptor' .
                    ' - like so: class <u>' . $provider->ClassName .
                    ' extends SearchEngineFilterForDescriptor</u>'
                );
            }

            $optionsToAdd = $provider->getFilterList();
            foreach ($optionsToAdd as $optionKey => $optionValue) {
                $array[$className . '.' . $optionKey] = $optionValue;
            }
        }

        return $array;
    }
}
