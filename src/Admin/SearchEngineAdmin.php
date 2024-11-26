<?php

namespace Sunnysideup\SearchSimpleSmart\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\ReadonlyField;
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
use Sunnysideup\SearchSimpleSmart\Forms\Fields\SearchEngineFormField;
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
    // @var array
    private static $managed_models = [
        SearchEngineDataObject::class,
        SearchEngineDataObjectToBeIndexed::class,
        SearchEngineKeyword::class,
        SearchEngineKeywordFindAndRemove::class,
        SearchEngineKeywordFindAndReplace::class,
        SearchEnginePunctuationFindAndRemove::class,
        SearchEngineSearchRecord::class,
        SearchEngineSearchRecordHistory::class,
        SearchEngineAdvancedSettings::class,
    ];

    // @var string
    private static $url_segment = 'searchengine';

    // @var string
    private static $menu_title = 'Keyword Search';

    public function providePermissions()
    {
        return [
            'SEARCH_ENGINE_ADMIN' => [
                'name' => 'Administer Search Engine',
                'category' => 'Keywords',
            ],
        ];
    }

    public function getList()
    {
        $list = parent::getList();
        if (SearchEngineDataObjectToBeIndexed::class === $this->modelClass) {
            $list = $list->filter(['Completed' => false]);
        }
        return $list;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm();
        if (SearchEngineAdvancedSettings::class === $this->modelClass) {
            $jsLastChanged = '';
            $fileName = ExportKeywordList::get_js_keyword_file_name(true);
            $jsLastChanged = $fileName && file_exists($fileName) ? date('Y-m-d H:i', filemtime($fileName)) : 'unknown';
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
                Also please review the <a href="/admin-searchenginemanifest">full search manifest</a>.
                </h4>'
            );
            if (Director::isDev()) {
                $linkFields[] = HTMLReadonlyField::create(
                    rand(0, 333333),
                    'Tasks',
                    '
                    <h4>
                        <a href="/dev/tasks/searchenginebasetask/">Run tasks now .... (careful!)</a>
                    </h4>
                    '
                );
            }
            $field = new FieldList(
                [
                    new TabSet(
                        'Root',
                        new TabSet(
                            'TabSet',
                            new Tab(
                                'Manifesto',
                                HTMLReadonlyField::create(
                                    'searchable_class_names',
                                    'Searchable Records',
                                    self::print_nice(array_keys(SearchEngineDataObjectApi::searchable_class_names()))
                                ),
                                HTMLReadonlyField::create(
                                    'classes_to_exclude',
                                    'Records To Exclude',
                                    self::print_nice(Config::inst()->get(SearchEngineDataObject::class, 'classes_to_exclude'))
                                )
                                    ->setDescription('All classes are included, except these ones'),
                                HTMLReadonlyField::create(
                                    'classes_to_include',
                                    'Records to Always Include',
                                    self::print_nice(Config::inst()->get(SearchEngineDataObject::class, 'classes_to_include'))
                                )
                                    ->setDescription('Only these classes are included'),
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
                            ),
                            new Tab(
                                'Settings',
                                ReadonlyField::create(
                                    'class_name_for_search_provision',
                                    'Class or Search Provision',
                                    Config::inst()->get(SearchEngineCoreSearchMachine::class, 'class_name_for_search_provision')
                                ),
                                ReadonlyField::create(
                                    'add_stop_words',
                                    'Add Default Stop Words',
                                    Config::inst()->get(SearchEngineKeywordFindAndRemove::class, 'add_stop_words') ? 'True' : 'False'
                                )
                                    ->setDescription('This adds the default stop word list for excluding common words from searches (e.g. the, and, a).'),
                                ReadonlyField::create(
                                    'add_stop_words_length',
                                    'Length of Default Stop Words List',
                                    Config::inst()->get(SearchEngineKeywordFindAndRemove::class, 'add_stop_words_length')
                                ),
                                ReadonlyField::create(
                                    'remove_all_non_alpha_numeric_full_content',
                                    'Remove non Latin Characters',
                                    Config::inst()->get(SearchEngineFullContent::class, 'remove_all_non_alpha_numeric') ? 'True' : 'False'
                                )->setDescription('Inclusion list: ' . implode(' ', SearchEngineFullContent::get_pattern_for_alpha_numeric_characters_human_readable())),
                                ReadonlyField::create(
                                    'remove_all_non_letters',
                                    'Remove Non Letter Characters',
                                    Config::inst()->get(SearchEngineFullContent::class, 'remove_all_non_letters') ? 'True' : 'False'
                                )->setDescription('Remove any characters that are not considered letters or numbers in any language. '),
                                ReadonlyField::create(
                                    SearchEngineDataObjectToBeIndexed::class,
                                    'Cron Job Is Running',
                                    Config::inst()->get(SearchEngineDataObjectToBeIndexed::class, 'cron_job_running') ? 'True' : 'False'
                                )->setDescription(
                                    'If set to TRUE you need to set up a CRON JOB for indexing.
                                        If set to FALSE, the index will update immediately (pages will take longer to save).
                                        On DEV Environments, it always runs immediately (you never need to run the cron job)'
                                ),
                            ),
                            new Tab(
                                'Filter',
                                HTMLReadonlyField::create(
                                    'classes_to_include_for_filter',
                                    'Filter for class names list - OPTIONAL',
                                    self::print_nice(Config::inst()->get(SearchEngineFilterForClassName::class, 'classes_to_include'))
                                ),
                                HTMLReadonlyField::create(
                                    'get_js_keyword_file_name',
                                    'Location for saving Keywords as JSON for autocomplete',
                                    self::print_nice((ExportKeywordList::get_js_keyword_file_name(false) ?: '--- not set ---'))
                                ),
                                HTMLReadonlyField::create(
                                    'get_js_keyword_file_last_changed',
                                    'Keyword autocomplete last updated ... (see tasks to update keyword list) ',
                                    $jsLastChanged
                                )
                            ),
                            new Tab(
                                'Sort',
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
                            ),
                            new Tab(
                                'Cached',
                                GridField::create(
                                    'SearchEngineSearchRecord',
                                    'Recent Searches',
                                    SearchEngineSearchRecord::get(),
                                    GridFieldConfig_RecordViewer::create()
                                ),
                            ),
                            new Tab(
                                'Links',
                                ...$linkFields
                            )
                        )
                    ),
                ]
            );
            $form->setFields($field);
        } elseif (SearchEngineSearchRecordHistory::class === $this->modelClass) {
            $gridField = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
            $field = new FieldList(
                [
                    new TabSet(
                        'Root',
                        new Tab(
                            'Graph',
                            SearchEngineFormField::create('SearchHistoryTable')
                        ),
                        new Tab(
                            'Log',
                            $gridField
                        )
                    ),
                ]
            );
            $form->setFields($field);
        }

        return $form;
    }

    /*
     * @param $arr
     * @return string
     */
    public static function print_nice($arr)
    {
        return self::array2ul($arr, '');
    }

    //code by acmol
    public static function array2ul(array|string $arrayOrString, string|int|null $key = '')
    {
        $out = '<ul>';
        if (is_array($arrayOrString)) {
            foreach ($arrayOrString as $key => $elem) {
                $out .= self::array2ul($elem, $key);
            }
        } else {
            $elem = $arrayOrString;
            if (class_exists($elem)) {
                $elem = singleton($elem)->i18n_singular_name() . ' (' . $elem . ')';
            }
            if (class_exists($key)) {
                $key = singleton($key)->i18n_singular_name() . ' (' . $key . ')';
            }
            if ($key === (int) $key || ! $key) {
                $out .= '<li><span><pre>' . $elem . '</pre></span></li>';
            } else {
                $out .= '<li><span><em>' . $key . ' --- </em> <pre>' . $elem . '</pre></span></li>';
            }
        }

        return $out . '</ul>';
    }

    public function canView($member = null)
    {
        return SiteConfig::current_site_config()->SearchEngineDebug || Permission::check('SEARCH_ENGINE_ADMIN');
    }
}
