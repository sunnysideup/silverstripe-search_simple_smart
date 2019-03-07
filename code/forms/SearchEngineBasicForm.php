<?php
class SearchEngineBasicForm extends Form {

	/**
	 * @var string
	 */
	private static $jquery_source = "framework/thirdparty/jquery/jquery.js";

	/**
	 * classnames of sort classes to be used
	 * - ClassName
	 * - ClassName
	 * - ClassName
	 *
	 * @var array
	 */
	private static $sort_by_options = array(
		//"SearchEngineSortByRelevance",
		//"SearchEngineSortByDate"
	);

	/**
	 * classnames of filters to be used
	 * - ClassName
	 * - ClassName
	 * - ClassName
	 *
	 * @var array
	 */
	private static $filter_for_options = array(
		//"SearchEngineFilterForClassName",
		//"SearchEngineFilterForRecent"
	);

	/**
	 * class name of the page that is used to show search results.
	 * @var string
	 */
	private static $full_results_page_type = "";

	/**
	 *
	 * @var Boolean
	 */
	protected $isMoreDetailsResult = false;

	/**
	 * starting point
	 * @var Int
	 */
	protected $start = 0;

	/**
	 *
	 * @var Int
	 */
	protected $numberOfResultsPerPage = 10;

	/**
	 *
	 * @var Int
	 */
	protected $totalNumberOfItemsToReturn = 9999;

	/**
	 * @var boolean
	 */
	protected $includeFilter = false;

	/**
	 * @var boolean
	 */
	protected $includeSort = false;

	/**
	 * @var boolean
	 */
	protected $useAutoComplete = false;

	/**
	 * @var boolean
	 */
	protected $updateBrowserHistory = false;


	/**
	 * alternative input field selector
	 * @var boolean
	 */
	protected $displayedFormInputSelector = "";

	/**
	 * @var boolean
	 */
	protected $useInfiniteScroll = false;

	/**
	 * @var boolean
	 */
	protected $outputAsJSON = false;

	/**
	 * for internal use...
	 * @var array
	 */
	protected $customScript = array();

	/**
	 * for internal use...
	 * @var string
	 */
	protected $keywords = "";


	/**
	 * this function constructs a new Search Engine Basic Form
	 * @param Object $controller
	 * @param string $name
	 * @return SearchEngineBasicForm $searchEngineBasicForm
	 */
	public function __construct($controller, $name) {
		if(isset($_GET["SearchEngineKeywords"])) {
			$this->keywords = $_GET["SearchEngineKeywords"];
		}
		$fields = new FieldList(
			$keywordField = TextField::create('SearchEngineKeywords', _t("SearchEngineBasicForm.KEYWORDS", "Search for ..."), $this->keywords)
		);
		$keywordField->setAttribute("placeholder", _t("SearchEngineBasicForm.WHAT_ARE_YOU_LOOKING_FOR", "What are you looking for ..."));
		$keywordField->extraClass("awesomplete");
		$keywordField->setAttribute("autocomplete", "off");

		$actions = new FieldList(
			FormAction::create('doSubmitForm', 'Search')
		);

		parent::__construct($controller, $name, $fields, $actions);

		$this->setFormMethod("GET");
		$this->setAttribute("autocomplete", "false");
		$this->disableSecurityToken();
		$this->customScript[] = 'SearchEngineInitFunctions.formSelector = \'#'.$this->FormName().'\';';


		return $this;
	}

	private static $_for_template_completed = false;

	public function forTemplate(){
		if(!self::$_for_template_completed) {
			$sortBy = $this->SortByProvider();
			if($sortBy && count($sortBy) > 1) {
				$default = isset($_GET["SortBy"]) ? $_GET["SortBy"] : key($sortBy);
				if($this->includeSort) {
					$this->Fields()->insertAfter(
						//TextField::create('SortBy', _t("SearchEngineBasicForm.SORT_BY", "Sort by ..."), $sortBy, $default),
						OptionSetField::create('SortBy', _t("SearchEngineBasicForm.SORT_BY", "Sort by ..."), $sortBy, $default),
						"SearchEngineKeywords"
					);
				}
				else {
					$this->Fields()->insertAfter(
						HiddenField::create('SortBy', '', $default),
						"SearchEngineKeywords"
					);
				}
			}

			$filterFor = $this->FilterForProvider();
			if($filterFor && count($filterFor) > 1) {
				if($this->includeFilter) {
					$defaults = isset($_GET["FilterFor"]) ? $_GET["FilterFor"] : array();
					$this->Fields()->insertAfter(
						CheckboxSetField::create('FilterFor', _t("SearchEngineBasicForm.FILTER_FOR", "Filter for ..."), $filterFor)->setDefaultItems($defaults),
						"SortBy"
					);
				}
			}

			$results = "";
			if($this->keywords) {
				if(Director::is_ajax()) {
					//do nothing
				}
				else {
					$this->resultsCompleted = true;
					$results = $this->workOutResults($_GET);
				}
			}
			$this->Fields()->push(LiteralField::create(
				'SearchEngineResultsHolderOuter',
				"<div id=\"SearchEngineResultsHolderOuter\">".$results."</div>"
			));
			if($this->Config()->jquery_source) {
				Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
			}
			Requirements::javascript(THIRDPARTY_DIR."/jquery-form/jquery.form.js");
			Requirements::javascript("searchengine/javascript/SearchEngineInitFunctions.js");

			if($this->useInfiniteScroll) {
				Requirements::javascript("searchengine/javascript/jquery.infinitescroll.min.js");
				$this->customScript[] = 'SearchEngineInitFunctions.useInfiniteScroll = true;';
			}
			if($this->displayedFormInputSelector) {
				$this->customScript[] = 'SearchEngineInitFunctions.displayedFormInputSelector = "' . $this->displayedFormInputSelector . '";';
			}
			if($this->useAutoComplete) {
				Requirements::javascript("searchengine/javascript/awesomplete.min.js");
				$this->customScript[]= 'SearchEngineInitFunctions.useAutoComplete = true;';
				$keywordFile = SearchEngineKeyword::get_js_keyword_file_name($includeBase = false);
				if($keywordFile) {
					Requirements::javascript(SearchEngineKeyword::get_js_keyword_file_name($includeBase = false));
				}
			}
			if($this->updateBrowserHistory) {
				$this->customScript[]= 'SearchEngineInitFunctions.updateBrowserHistory = true;';
			}

			Requirements::customScript(implode("\n", $this->customScript), "SearchEngineInitFunctions");

			//css settings
			Requirements::themedCSS("awesomplete", "searchengine");
			Requirements::themedCSS("SearchEngine", "searchengine");
			$this->addExtraClass("searchEngineFormForm");
			if($this->includeFilter || $this->includeSort) {
				$this->addExtraClass("searchEngineFormWithSideBar");
			}
			else {
				$this->addExtraClass("searchEngineFormWithoutSideBar");
			}
		}
		return parent::forTemplate();
	}

	/**
	 * this method submits the Search Engine Form
	 * @param map $data
	 * @param SearchEngineBasicForm $form
	 *
	 * @return array $results
	 */
	public function doSubmitForm($data, $form) {
		if(Director::is_ajax()) {
			if($this->outputAsJSON) {
				$this->controller->response->addHeader('Content-Type', 'text/json');
			}
			Requirements::clear();
			return $this->workOutResults($data);
		}
		return array();
	}

	/**
	 *
	 * @param array $data
	 *
	 * @return string (html)
	 */
	protected function workOutResults($data) {
		// Submitted data is available as a map.
		$searchMachine = Injector::inst()->create('SearchEngineCoreSearchMachine');
		//filter for is an array!
		$filterForArray = array();
		$setLimitToZero = false;
		if(isset($data["FilterFor"])) {
			foreach($data["FilterFor"] as $filterForClass => $notUsed) {
				$filterForArray[$filterForClass] = $filterForClass;
			}
		}
		$sortBy = (!empty($data["SortBy"]) && class_exists($data["SortBy"]) ? $data["SortBy"] : "");
		$this->start = (isset($_GET["start"]) ? intval($_GET["start"]) : 0);
		if($this->totalNumberOfItemsToReturn && $this->totalNumberOfItemsToReturn < $this->start) {
			//$results = SearchEngineDataObject::get()->filter("ID", -1);
			$setLimitToZero = true;
		}
		else {
			$results = $searchMachine->run(
				$data["SearchEngineKeywords"],
				$filterForArray,
				$sortBy
			);
			if($this->totalNumberOfItemsToReturn && $this->totalNumberOfItemsToReturn < ($this->start + $this->numberOfResultsPerPage)) {
				$this->numberOfResultsPerPage = $this->totalNumberOfItemsToReturn - $this->start;
				if($this->numberOfResultsPerPage < 1) {
					$this->numberOfResultsPerPage = 1;
					$setLimitToZero = true;
				}
			}
		}
		//paginate
		$count = 0;
		if($results) {
			$count = $results->count();
		}
		SearchEngineSearchRecordHistory::add_number_of_results($count);
		if($setLimitToZero) {
			$results = $results->limit(0);
		}
		else {
			$results = $results->limit($this->totalNumberOfItemsToReturn);
		}
		$results = PaginatedList::create($results, array("start" => $this->start));

		$results->setPageLength($this->numberOfResultsPerPage);
		// After dealing with the data you can redirect the user back.
		$link = str_replace("&", "&amp;", $_SERVER['REQUEST_URI']);
		$fullResultsLink = "";
		if($fullResultsClassName = Config::inst()->get("SearchEngineBasicForm", "full_results_page_type")) {
			if($this->Controller()->dataRecord->ClassName != $fullResultsClassName) {
				if(is_subclass_of($fullResultsClassName, "SiteTree")) {
					$obj = $fullResultsClassName::get()->first();
					if($obj) {
						unset($data["url"]);
						$fullResultsLink = $obj->Link("SearchEngineBasicForm")."?".http_build_query($data, "", "&amp;");
					}
				}
			}
		}
		$arrayData = new ArrayData(
			array(
				"SearchedFor" => Convert::raw2xml($data["SearchEngineKeywords"]),
				"Results" => $results,
				"ResultsGrouped" => GroupedList::create($results),
				"Count" => $count,
				"Link" => $link,
				"IsMoreDetailsResult" => $this->isMoreDetailsResult,
				"FullResultsLink" => $fullResultsLink,
				"NumberOfItemsPerPage" => $this->numberOfResultsPerPage,
				"DebugHTML" => $searchMachine->getDebugString(),
			)
		);
		$classGroups = Config::inst()->get("SearchEngineSortByDescriptor", "class_groups");
		if(is_array($classGroups) && count($classGroups)) {
			return $arrayData->renderWith("SearchEngineSearchResultsOuterWithSpecialSortGrouping");
		}
		else {
			return $arrayData->renderWith("SearchEngineSearchResultsOuter");
		}
	}

	/**
	 *
	 * @return array
	 */
	protected function SortByProvider() {
		$options = Config::inst()->get("SearchEngineBasicForm", "sort_by_options");
		$array = array();
		foreach($options as $key => $className) {
			$provider = Injector::inst()->get($className);
			if(!$provider instanceof SearchEngineSortByDescriptor) {
				user_error($provider->ClassName."should extend SearchEngineSortByDescriptor - like so: class <u>".$provider->ClassName." extends SearchEngineSortByDescriptor</u>");
			}
			$array[$className] = $provider->getShortTitle();
		}

		return $array;
	}

	/**
	 * returns a list of searchable objects
	 * @return array
	 */
	protected function FilterForProvider() {
		$options = Config::inst()->get("SearchEngineBasicForm", "filter_for_options");
		$array = array();
		foreach($options as $key => $className) {
			$provider = Injector::inst()->get($className);
			if(!$provider instanceof SearchEngineFilterForDescriptor) {
				user_error($provider->ClassName."should extend SearchEngineFilterForDescriptor - like so: class <u>".$provider->ClassName." extends SearchEngineFilterForDescriptor</u>");
			}
			$optionsToAdd = $provider->getFilterList();
			foreach($optionsToAdd as $optionKey => $optionValue) {
				$array[$className.".".$optionKey] = $optionValue;
			}
		}
		return $array;
	}


	/**
	 * @param int
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setIncludeSort($b) {
		$this->includeSort = $b;
		$this->Fields()->removeByName("SortBy");
		return $this;
	}

	/**
	 * @param int
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setIncludeFilter($b) {
		$this->includeFilter = $b;
		$this->Fields()->removeByName("FilterFor");
		return $this;
	}

	/**
	 * this function sets the number of items to return
	 * per page when a search is conducted
	 * @param int
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setNumberOfResultsPerPage($i) {
		$this->numberOfResultsPerPage = $i;
		return $this;
	}

	/**
	 * total number of items to return
	 * @param int
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setTotalNumberOfItemsToReturn($i) {
		$this->totalNumberOfItemsToReturn = $i;
		return $this;
	}

	/**
	 * what is the first item to return
	 * @param boolean
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setIsMoreDetailsResult($b) {
		$this->isMoreDetailsResult = $b;
		return $this;
	}

	/**
	 * what is the first item to return
	 * @param Int
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setStart($i) {
		$this->start = $i;
		return $this;
	}

	/**
	 * @param boolean
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setUseAutoComplete($b) {
		$this->useAutoComplete = $b;
		return $this;
	}

	/**
	 * @param boolean
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setUseInfiniteScroll($b) {
		$this->useInfiniteScroll = $b;
		return $this;
	}
	/**
	 * @param string
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setdisplayedFormInputSelector($string) {
		$this->displayedFormInputSelector = $string;
		return $this;
	}

	/**
	 * @param boolean
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setOutputAsJSON($b) {
		$this->outputAsJSON = $b;
		return $this;
	}

	/**
	 * @param boolean
	 *
	 * @return SearchEngineBasicForm
	 */
	public function setUpdateBrowserHistory($b) {
		$this->updateBrowserHistory = $b;
		return $this;
	}


}
