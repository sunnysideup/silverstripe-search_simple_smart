<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Admin\SearchEngineAdmin;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineStopWords;

class SearchEngineKeywordFindAndRemove extends DataObject
{
    protected static $_is_listed = [];

    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineKeywordFindAndRemove';

    // @var string
    private static $singular_name = 'Keyword Remove';

    // @var string
    private static $plural_name = 'Keywords Remove';

    // @var array
    private static $db = [
        'Keyword' => 'Varchar(150)',
        'Custom' => 'Boolean(1)',
    ];

    // @var bool
    private static $add_stop_words = true;

    /**
     * options are: short, medium, long, extra_long.
     *
     * @var string
     */
    private static $add_stop_words_length = 'short';

    // @var array
    private static $indexes = [
        'Keyword' => true,
    ];

    // @var string
    private static $default_sort = '"Custom" DESC, "Keyword" ASC';

    // @var array
    private static $required_fields = [
        'Keyword',
    ];

    // @var array
    private static $summary_fields = [
        'Keyword' => 'Keyword',
        'Custom.Nice' => 'Manually Entered',
    ];

    // @var array
    private static $field_labels = [
        'Custom' => 'Manually Entered',
    ];

    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    public function i18n_plural_name()
    {
        return $this->Config()->get('plural_name');
    }

    /**
     * @param Member $member
     * @param mixed  $context
     *
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return parent::canCreate() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        return parent::canCreate() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        return parent::canDelete() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canView($member = null)
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param mixed $keyword
     *
     * @return SearchEngineKeywordFindAndRemove
     */
    public static function is_listed($keyword)
    {
        if (! isset(self::$_is_listed[$keyword])) {
            self::$_is_listed[$keyword] =
            (bool) self::get()
                ->filter(['Keyword' => $keyword])->count();
        }

        return self::$_is_listed[$keyword];
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        //see: http://xpo6.com/download-stop-word-list/
        if (true === Config::inst()->get(self::class, 'add_stop_words')) {
            $size = Config::inst()->get(self::class, 'add_stop_words_length');
            $stopwords = SearchEngineStopWords::get_list($size);
            foreach ($stopwords as $stopword) {
                if (! self::is_listed($stopword)) {
                    DB::alteration_message("Creating stop word: {$stopword}", 'created');
                    $obj = self::create();
                    $obj->Keyword = $stopword;
                    $obj->Custom = false;
                    $obj->write();
                }
            }
        }
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Keyword = SearchEngineKeyword::clean_keyword($this->Keyword);
    }

    public function CMSEditLink()
    {
        return '/' . Injector::inst()->get(SearchEngineAdmin::class)->getCMSEditLinkForManagedDataObject($this);
    }

}
