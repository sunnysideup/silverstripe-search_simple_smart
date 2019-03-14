<?php

namespace Sunnysideup\SearchSimpleSmart\Admin;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeywordFindAndRemove;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeywordFindAndReplace;
use Sunnysideup\SearchSimpleSmart\Model\SearchEnginePunctuationFindAndRemove;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineAdvancedSettings;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Core\SearchEngineCoreSearchMachine;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Filters\SearchEngineFilterForClassName;
use SilverStripe\Forms\Tab;
use Sunnysideup\SearchSimpleSmart\Tasks\SearchEngineCreateKeywordJS;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldList;
use Sunnysideup\SearchSimpleSmart\Forms\Fields\SearchEngineSearchHistoryFormField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Security\Permission;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\ORM\FieldType\DBField;

class SearchEngineAdmin extends ModelAdmin implements PermissionProvider
{
    public function providePermissions()
    {
        return array(
            'SEARCH_ENGINE_ADMIN' => 'Administer Search Engine'
        );
    }

    /*
     * @var array
     */
    private static $managed_models = array(
        SearchEngineDataObject::class,
        SearchEngineDataObjectToBeIndexed::class,
        SearchEngineFullContent::class,
        SearchEngineKeyword::class,
        SearchEngineKeywordFindAndRemove::class,
        SearchEngineKeywordFindAndReplace::class,
        SearchEnginePunctuationFindAndRemove::class,
        SearchEngineSearchRecord::class,
        SearchEngineSearchRecordHistory::class,
        SearchEngineAdvancedSettings::class
    );

    /*
     * @var string
     */
    private static $url_segment = 'searchengine';

    /*
     * @var string
     */
    private static $menu_title = 'Search Engine';

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm();
        if ($this->modelClass == SearchEngineAdvancedSettings::class) {
            Requirements::customScript("SearchEngineManifest();", "SearchEngineManifest");

            $classNames = SearchEngineDataObject::searchable_class_names();
            asort($classNames);
            $manifest = "";
            if (is_array($classNames) && count($classNames)) {
                $manifest .= "<div id=\"SearchEngineManifest\"><ul>";
                foreach ($classNames as $className => $classNameTitle) {
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
            if (file_exists(SearchEngineKeyword::get_js_keyword_file_name(true))) {
                $jsLastChanged = Date("Y-m-d H:i", filemtime(SearchEngineKeyword::get_js_keyword_file_name(true)));
            } else {
                $jsLastChanged = "unknown";
            }
            $printNice = [];
            $field = new FieldList(
                new TabSet(
                    'Root',
                    new Tab(
                        'Settings',
                        $printNice[] = HTMLReadonlyField::create(
                            "searchable_class_names",
                            'Searchable Class Names',
                            $this->printNice(SearchEngineDataObject::searchable_class_names())
                        ),
                        $printNice[] = HTMLReadonlyField::create("classes_to_exclude", 'Data Object - Classes To Exclude', $this->printNice(Config::inst()->get(SearchEngineDataObject::class, "classes_to_exclude"))),
                        HTMLReadonlyField::create("class_name_for_search_provision", 'Class or Search Provision', Config::inst()->get(SearchEngineCoreSearchMachine::class, "class_name_for_search_provision")),
                        HTMLReadonlyField::create("remove_all_non_alpha_numeric", 'Keywords - Remove Non Alpha Numeric Keywords', Config::inst()->get(SearchEngineKeyword::class, "remove_all_non_alpha_numeric")? "True" : "False"),
                        HTMLReadonlyField::create("add_stop_words", 'Keyword Find And Remove - Add Stop Words', Config::inst()->get(SearchEngineKeywordFindAndRemove::class, "add_stop_words")? "True" : "False"),
                        HTMLReadonlyField::create("remove_all_non_alpha_numeric_full_content", 'Full Content - Remove Non Alpha Numeric Keywords', Config::inst()->get(SearchEngineFullContent::class, "remove_all_non_alpha_numeric")? "True" : "False"),
                        HTMLReadonlyField::create(SearchEngineDataObjectToBeIndexed::class, 'Cron Job Is Running - make sure to turn this on once you have set up your Cron Job', Config::inst()->get(SearchEngineDataObjectToBeIndexed::class, "cron_job_running")? "True" : "False"),
                        $printNice[] = HTMLReadonlyField::create("search_engine_default_level_one_fields", 'Make Searchable - Default Level 1 Fields', $this->printNice(Config::inst()->get(SearchEngineMakeSearchable::class, "search_engine_default_level_one_fields"))),
                        $printNice[] = HTMLReadonlyField::create("search_engine_default_excluded_db_fields", 'Make Searchable - Fields Excluded by Default', $this->printNice(Config::inst()->get(SearchEngineMakeSearchable::class, "search_engine_default_excluded_db_fields"))),
                        $printNice[] = HTMLReadonlyField::create("class_groups", 'Sort By Descriptor - Class Groups - what classes are always shown on top OPTIONAL', $this->printNice(Config::inst()->get(SearchEngineSortByDescriptor::class, "class_groups"))),
                        $printNice[] = HTMLReadonlyField::create("class_group_limits", 'Sort By Descriptor - Class Groups Limits - how many of the on entries are shown - OPTIONAL', $this->printNice(Config::inst()->get(SearchEngineSortByDescriptor::class, "class_group_limits"))),
                        $printNice[] = HTMLReadonlyField::create("classes_to_include", 'Filter for class names list - OPTIONAL', $this->printNice(Config::inst()->get(SearchEngineFilterForClassName::class, "classes_to_include"))),
                        $printNice[] = HTMLReadonlyField::create("get_js_keyword_file_name", 'Location for saving Keywords as JSON for autocomplete', $this->printNice(SearchEngineKeyword::get_js_keyword_file_name())),
                        $printNice[] = HTMLReadonlyField::create("get_js_keyword_file_last_changed", 'Keyword autocomplete last updated ... (see tasks to update keyword list) ', $jsLastChanged)
                    ),
                    new Tab(
                        'Tasks',
                        $removeAllField = HTMLReadonlyField::create("RemoveAllSearchData", "1. Remove All Search Data", "<h4><a href=\"/dev/tasks/SearchEngineRemoveAll\">Run Task: remove all</a></h4>"),
                        $indexAllField = HTMLReadonlyField::create("IndexAllObjects", "2. Queue for indexing", "<h4><a href=\"/dev/tasks/SearchEngineIndexAll\">Run Task: list all for indexing</a></h4>"),
                        $updateVerboseField = HTMLReadonlyField::create("UpdateSearchIndexVerbose", "3. Do index", "<h4><a href=\"/dev/tasks/SearchEngineUpdateSearchIndex?verbose=1&amp;oldonesonly=1\">Run Task: execute the to be indexed list</a></h4>"),
                        $updateKeywordList = HTMLReadonlyField::create(SearchEngineCreateKeywordJS::class, "4. Update keywords", "<h4><a href=\"/dev/tasks/SearchEngineCreateKeywordJS\">Run Task: update keyword list</a></h4>"),
                        $debugTestField = HTMLReadonlyField::create("DebugTestField", "5. Debug Search", "
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
            $removeAllField->setRightTitle("Careful - this will remove all the search engine index data.");
            $indexAllField->setRightTitle("Careful - this will take signigicant time and resources.");
            $updateVerboseField->setRightTitle("Updates all the search indexes with verbose set to true.");

            $form->setFields($field);
        } elseif ($this->modelClass == SearchEngineSearchRecordHistory::class) {
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
    protected function printNice($arr)
    {
        if (is_array($arr)) {
            return $this->array2ul($arr);
        } else {
            $string = "<pre>".print_r($arr, true)."</pre>";
            return $string;
        }
    }

    //code by acmol
    protected function array2ul($array)
    {
        $out="<ul>";
        foreach ($array as $key => $elem) {
            if (!is_array($elem)) {
                $out = $out."<li><span><em>$key:</em> $elem</span></li>";
            } else {
                $out=$out."<li><span>$key</span>".$this->array2ul($elem)."</li>";
            }
        }
        $out=$out."</ul>";
        return $out;
    }

    public function canView($member = null, $context = [])
    {
        return SiteConfig::current_site_config()->SearchEngineDebug || Permission::check("SEARCH_ENGINE_ADMIN");
    }
}
