<?php


class SearchEngineAdmin extends ModelAdmin implements PermissionProvider {

	function providePermissions() {
	 return array(
		 'SEARCH_ENGINE_ADMIN' => 'Administer Search Engine'
	 );
	}

	/*
	 * @var array
	 */
	private static $managed_models = array(
		"SearchEngineDataObject",
		"SearchEngineDataObjectToBeIndexed",
		"SearchEngineFullContent",
		"SearchEngineKeyword",
		"SearchEngineKeywordFindAndRemove",
		"SearchEngineKeywordFindAndReplace",
		"SearchEnginePunctuationFindAndRemove",
		"SearchEngineSearchRecord",
		"SearchEngineSearchRecordHistory",
		"SearchEngineAdvancedSettings"
	);

	/*
	 * @var string
	 */
	private static $url_segment = 'searchengine';

	/*
	 * @var string
	 */
	private static $menu_title = 'Search Engine';

	function getEditForm($id = null, $fields = null){
		$form = parent::getEditForm();
		if($this->modelClass == "SearchEngineAdvancedSettings") {
			Requirements::customScript("SearchEngineManifest();", "SearchEngineManifest");
			$classNames = SearchEngineDataObject::searchable_class_names();
			asort($classNames);
			$manifest = "";
			if(is_array($classNames) && count($classNames)) {
				$manifest .= "<div id=\"SearchEngineManifest\"><ul>";
				foreach($classNames as $className => $classNameTitle) {
					$numberOfIndexedObjects = SearchEngineDataObject::get()->filter(array("DataObjectClassName" => $className))->count();
					$manifest .= "<li class=\"".($numberOfIndexedObjects ? "hasEntries" : "doesNotHaveEntries")."\"><h3>$classNameTitle ($numberOfIndexedObjects)</h3><ul>";
					$class = Injector::inst()->get($className);
					$manifest .= "<li><strong>Fields Indexed (level 1 / 2  is used to determine importance for relevance sorting):</strong>".$class->SearchEngineFieldsToBeIndexedHumanReadable()."</li>";
					$manifest .= "<li><strong>Templates:</strong>".$this->printNice($class->SearchEngineResultsTemplates(false))."</li>";
					$manifest .= "<li><strong>Templates (more details):</strong>".$this->printNice($class->SearchEngineResultsTemplates(true))."</li>";
					$manifest .= "<li><strong>Also trigger:</strong>".$this->printNice($class->SearchEngineAlsoTrigger())."</li>";
					$manifest .= "</ul></li>";
				}
				$manifest .= "</ul></div>";
			}
			$jsLastChanged = "";
			if(file_exists(SearchEngineKeyword::get_js_keyword_file_name(true))) {
				$jsLastChanged = Date("Y-m-d H:i", filemtime(SearchEngineKeyword::get_js_keyword_file_name(true)));
			}
			else {
				$jsLastChanged = "unknown";
			}
			$printNice = array();
			$field = new FieldList(
				new TabSet(
					'Root',
					new Tab(
						'Settings',
						$printNice[] = ReadOnlyField::create("searchable_class_names",'Searchable Class Names', $this->printNice(SearchEngineDataObject::searchable_class_names())),
						$printNice[] = ReadOnlyField::create("classes_to_exclude",'Data Object - Classes To Exclude', $this->printNice(Config::inst()->get("SearchEngineDataObject", "classes_to_exclude"))),
						ReadOnlyField::create("class_name_for_search_provision",'Class or Search Provision', Config::inst()->get("SearchEngineCoreSearchMachine", "class_name_for_search_provision")),
						ReadOnlyField::create("remove_all_non_alpha_numeric",'Keywords - Remove Non Alpha Numeric Keywords', Config::inst()->get("SearchEngineKeyword", "remove_all_non_alpha_numeric")? "True" : "False"),
						ReadOnlyField::create("add_stop_words",'Keyword Find And Remove - Add Stop Words', Config::inst()->get("SearchEngineKeywordFindAndRemove", "add_stop_words")? "True" : "False"),
						ReadOnlyField::create("remove_all_non_alpha_numeric_full_content",'Full Content - Remove Non Alpha Numeric Keywords', Config::inst()->get("SearchEngineFullContent", "remove_all_non_alpha_numeric")? "True" : "False"),
						ReadOnlyField::create("SearchEngineDataObjectToBeIndexed",'Cron Job Is Running - make sure to turn this on once you have set up your Cron Job', Config::inst()->get("SearchEngineDataObjectToBeIndexed", "cron_job_running")? "True" : "False"),
						$printNice[] = ReadOnlyField::create("default_level_one_fields",'Make Searchable - Default Level 1 Fields', $this->printNice(Config::inst()->get("SearchEngineMakeSearchable", "default_level_one_fields"))),
						$printNice[] = ReadOnlyField::create("default_excluded_db_fields",'Make Searchable - Fields Excluded by Default', $this->printNice(Config::inst()->get("SearchEngineMakeSearchable", "default_excluded_db_fields"))),
						$printNice[] = ReadOnlyField::create("class_groups",'Sort By Descriptor - Class Groups - what classes are always shown on top OPTIONAL', $this->printNice(Config::inst()->get("SearchEngineSortByDescriptor", "class_groups"))),
						$printNice[] = ReadOnlyField::create("class_group_limits",'Sort By Descriptor - Class Groups Limits - how many of the on entries are shown - OPTIONAL', $this->printNice(Config::inst()->get("SearchEngineSortByDescriptor", "class_group_limits"))),
						$printNice[] = ReadOnlyField::create("classes_to_include",'Filter for class names list - OPTIONAL', $this->printNice(Config::inst()->get("SearchEngineFilterForClassName", "classes_to_include"))),
						$printNice[] = ReadOnlyField::create("get_js_keyword_file_name",'Location for saving Keywords as JSON for autocomplete', $this->printNice(SearchEngineKeyword::get_js_keyword_file_name())),
						$printNice[] = ReadOnlyField::create("get_js_keyword_file_last_changed",'Keyword autocomplete last updated ... (see tasks to update keyword list) ', $jsLastChanged)
					),
					new Tab(
						'Tasks',
						$removeAllField = ReadOnlyField::create("RemoveAllSearchData", "1. Remove All Search Data", "<h4><a href=\"/dev/tasks/SearchEngineRemoveAll\">Run Task: remove all</a></h4>"),
						$indexAllField = ReadOnlyField::create("IndexAllObjects", "2. Queue for indexing", "<h4><a href=\"/dev/tasks/SearchEngineIndexAll\">Run Task: list all for indexing</a></h4>"),
						$updateVerboseField = ReadOnlyField::create("UpdateSearchIndexVerbose","3. Do index", "<h4><a href=\"/dev/tasks/SearchEngineUpdateSearchIndex?verbose=1&amp;uptonow=1\">Run Task: execute the to be indexed list</a></h4>"),
						$updateKeywordList = ReadOnlyField::create("SearchEngineCreateKeywordJS","4. Update keywords", "<h4><a href=\"/dev/tasks/SearchEngineCreateKeywordJS\">Run Task: update keyword list</a></h4>"),
						$debugTestField = ReadOnlyField::create("DebugTestField", "5. Debug Search", "
							<h4>
							To debug a search, please add ?searchenginedebug=1 to the end of the search result link AND make sure you are logged in as an ADMIN.
							<br /><br />
							To bypass all caching please add ?flush=1 to the end of the search result link AND make sure you are logged in as an ADMIN.
						</h4>")
					),
					new Tab(
						'Manifest',
						$manifestField = LiteralField::create("Manifest", $manifest)
					)
				)
			);
			foreach($printNice as $myField) {
				$myField->dontEscape = true;
			}
			$removeAllField->dontEscape = true;
			$removeAllField->setRightTitle("Careful - this will remove all the search engine index data.");
			$indexAllField->dontEscape = true;
			$indexAllField->setRightTitle("Careful - this will take signigicant time and resources.");
			$updateVerboseField->dontEscape = true;
			$updateVerboseField->setRightTitle("Updates all the search indexes with verbose set to true.");
			$updateKeywordList->dontEscape = true;
			$debugTestField->dontEscape = true;

			$form->setFields($field);
		}
		else if($this->modelClass == "SearchEngineSearchRecordHistory") {
			$gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
			$field = new FieldList(
				new TabSet(
					'Root',
					new Tab(
						'Graph',
						SearchEngineSearchHistoryFormField::create("SearchHistoryTable")
					),
					new Tab(
						'Log',
						$gridField
					)
				)
			);
			$form->setFields($field);
		}
		return $form;
	}

	/*
	 * @param array
	 * @return string
	 */
	protected function printNice($arr){
		if(is_array($arr)) {
			return $this->array2ul($arr);
		}
		else {
			$string = "<pre>".print_r($arr, true)."</pre>";
			return $string;
		}
	}

		//code by acmol
	protected function array2ul($array) {
		$out="<ul>";
		foreach($array as $key => $elem){
				if(!is_array($elem)){
					$out = $out."<li><span><em>$key:</em> $elem</span></li>";
				}
				else $out=$out."<li><span>$key</span>".$this->array2ul($elem)."</li>";
		}
		$out=$out."</ul>";
		return $out;
	}

	function canView($member = null) {
		return SiteConfig::current_site_config()->SearchEngineDebug || Permission::check("SEARCH_ENGINE_ADMIN");
	}

}
