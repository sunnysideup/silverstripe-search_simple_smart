<?php

namespace Sunnysideup\SearchSimpleSmart\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineDataObjectApi;
use Sunnysideup\SearchSimpleSmart\Core\SearchEngineCoreSearchMachine;
use Sunnysideup\SearchSimpleSmart\Filters\SearchEngineFilterForClassName;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineAdvancedSettings;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeywordFindAndRemove;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeywordFindAndReplace;
use Sunnysideup\SearchSimpleSmart\Model\SearchEnginePunctuationFindAndRemove;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;

class SearchEngineAdmin extends ModelAdmin implements PermissionProvider
{
    /*
     * @var array
     */
    private static $managed_models = [
        SearchEngineDataObject::class,
        SearchEngineDataObjectToBeIndexed::class,
        SearchEngineFullContent::class,
        SearchEngineKeyword::class,
        SearchEngineKeywordFindAndRemove::class,
        SearchEngineKeywordFindAndReplace::class,
        SearchEnginePunctuationFindAndRemove::class,
        SearchEngineSearchRecord::class,
        SearchEngineSearchRecordHistory::class,
        SearchEngineAdvancedSettings::class,
    ];

    /*
     * @var string
     */
    private static $url_segment = 'searchengine';

    /*
     * @var string
     */
    private static $menu_title = 'Search Engine';

    public function providePermissions()
    {
        return [
            'SEARCH_ENGINE_ADMIN' => 'Administer Search Engine',
        ];
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm();
        if ($this->modelClass === SearchEngineAdvancedSettings::class) {
            $jsLastChanged = '';
            $fileName = ExportKeywordList::get_js_keyword_file_name(true);
            if ($fileName && file_exists($fileName)) {
                $jsLastChanged = Date('Y-m-d H:i', filemtime($fileName));
            } else {
                $jsLastChanged = 'unknown';
            }
            $linkFields = [];
            $linkFields[] = HTMLReadonlyField::create(
                'DebugTestField',
                'Debug Search',
                '
                <h4>
                To debug a search, please add ?searchenginedebug=1 to the end of the search result link AND make sure you are logged in as an ADMIN.
                <br /><br />
                To bypass all caching please add ?flush=1 to the end of the search result link AND make sure you are logged in as an ADMIN.
                <br /><br />
                Also please review the <a href="/searchenginemanifest">full search manifest</a>.
                </h4>'
            );
            $linkFields[] = HTMLReadonlyField::create(
                rand(0, 333333),
                'Tasks',
                '
                <h4>
                    <a href="/dev/tasks/searchenginebasetask/">Run tasks now .... (careful!)</a>
                </h4>
                '
            );
            $field = new FieldList(
                new TabSet(
                    'Root',
                    new Tab(
                        'Settings',
                        HTMLReadonlyField::create(
                            'searchable_class_names',
                            'Searchable Class Names',
                            self::print_nice(SearchEngineDataObjectApi::searchable_class_names())
                        ),
                        HTMLReadonlyField::create(
                            'classes_to_exclude',
                            'Data Object - Classes To Exclude',
                            self::print_nice(Config::inst()->get(SearchEngineDataObject::class, 'classes_to_exclude'))
                        )
                            ->setDescription('All classes are included, except these ones'),
                        HTMLReadonlyField::create(
                            'classes_to_include',
                            'Data Object - Classes To Include',
                            self::print_nice(Config::inst()->get(SearchEngineDataObject::class, 'classes_to_include'))
                        )
                            ->setDescription('Only these classes are included'),
                        HTMLReadonlyField::create(
                            'class_name_for_search_provision',
                            'Class or Search Provision',
                            Config::inst()->get(SearchEngineCoreSearchMachine::class, 'class_name_for_search_provision')
                        ),
                        HTMLReadonlyField::create(
                            'add_stop_words',
                            'Add Default Stop Words',
                            Config::inst()->get(SearchEngineKeywordFindAndRemove::class, 'add_stop_words') ? 'True' : 'False'
                        ),
                        HTMLReadonlyField::create(
                            'add_stop_words_length',
                            'Length of Default Stop Words List',
                            Config::inst()->get(SearchEngineKeywordFindAndRemove::class, 'add_stop_words_length')
                        ),
                        HTMLReadonlyField::create(
                            'remove_all_non_alpha_numeric_full_content',
                            'Full Content - Remove Non Alpha Numeric Keywords',
                            Config::inst()->get(SearchEngineFullContent::class, 'remove_all_non_alpha_numeric') ? 'True' : 'False'
                        ),
                        HTMLReadonlyField::create(
                            'remove_all_non_letters',
                            'Keywords - Remove Non Letter Characters',
                            Config::inst()->get(SearchEngineFullContent::class, 'remove_all_non_letters') ? 'True' : 'False'
                        ),
                        HTMLReadonlyField::create(
                            SearchEngineDataObjectToBeIndexed::class,
                            'Cron Job Is Running - make sure to turn this on once you have set up your Cron Job',
                            Config::inst()->get(SearchEngineDataObjectToBeIndexed::class, 'cron_job_running') ? 'True' : 'False'
                        ),
                        HTMLReadonlyField::create(
                            'search_engine_default_level_one_fields',
                            'Make Searchable - Default Level 1 Fields',
                            self::print_nice(Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_level_one_fields'))
                        ),
                        HTMLReadonlyField::create(
                            'search_engine_default_excluded_db_fields',
                            'Make Searchable - Fields Excluded by Default',
                            self::print_nice(Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_excluded_db_fields'))
                        ),
                        HTMLReadonlyField::create(
                            'class_groups',
                            'Sort By Descriptor - Class Groups - what classes are always shown on top OPTIONAL',
                            self::print_nice(Config::inst()->get(SearchEngineSortByDescriptor::class, 'class_groups'))
                        ),
                        HTMLReadonlyField::create(
                            'class_group_limits',
                            'Sort By Descriptor - Class Groups Limits - how many of the on entries are shown - OPTIONAL',
                            self::print_nice(Config::inst()->get(SearchEngineSortByDescriptor::class, 'class_group_limits'))
                        ),
                        HTMLReadonlyField::create(
                            'classes_to_include_for_filter',
                            'Filter for class names list - OPTIONAL',
                            self::print_nice(Config::inst()->get(SearchEngineFilterForClassName::class, 'classes_to_include'))
                        ),
                        HTMLReadonlyField::create(
                            'get_js_keyword_file_name',
                            'Location for saving Keywords as JSON for autocomplete',
                            self::print_nice((ExportKeywordList::get_js_keyword_file_name(true) ?: '--- not set ---'))
                        ),
                        HTMLReadonlyField::create(
                            'get_js_keyword_file_last_changed',
                            'Keyword autocomplete last updated ... (see tasks to update keyword list) ',
                            $jsLastChanged
                        )
                    )
                )
            );
            $form->setFields($field);
            $form->Fields()->addFieldsToTab(
                'Root.Links',
                $linkFields
            );
        } elseif ($this->modelClass === SearchEngineSearchRecordHistory::class) {
            // $gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
            // $field = new FieldList(
            //     new TabSet(
            //         'Root',
            //         new Tab(
            //             'Graph',
            //             SearchEngineSearchHistoryFormField::create("SearchHistoryTable")
            //         ),
            //         new Tab(
            //             'Log',
            //             $gridField
            //         )
            //     )
            // );
            // $form->setFields($field);
        }
        return $form;
    }

    /*
     * @param $arr
     * @return string
     */
    public static function print_nice($arr)
    {
        if (is_array($arr)) {
            return self::array2ul($arr);
        }
        return '<pre>' . print_r($arr, true) . '</pre>';
    }

    //code by acmol
    public static function array2ul($array)
    {
        $out = '<ul>';
        foreach ($array as $key => $elem) {
            if (! is_array($elem)) {
                if ($key === intval($key)) {
                    $out .= '<li><span>' . $elem . '</span></li>';
                } else {
                    $out .= '<li><span><em>' . $key . ' --- </em> ' . $elem . '</span></li>';
                }
            } else {
                $out .= '<li><span>' . $key . '</span>' . self::array2ul($elem) . '</li>';
            }
        }
        return $out . '</ul>';
    }

    public function canView($member = null)
    {
        return SiteConfig::current_site_config()->SearchEngineDebug || Permission::check('SEARCH_ENGINE_ADMIN');
    }
}
