<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeywordFindAndRemove;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineStopWords;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;

class SearchEngineKeywordFindAndRemove extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineKeywordFindAndRemove';

    /*
     * @var string
     */
    private static $singular_name = "Keyword Remove";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /*
     * @var string
     */
    private static $plural_name = "Keywords Remove";
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    /*
     * @var array
     */
    private static $db = array(
        "Keyword" => "Varchar(150)",
        "Custom" => "Boolean(1)"
    );

    /*
     * @var boolean
     */
    private static $add_stop_words = true;

    /**
     * options are: short, medium, long, extra_long
     * @var string
     */
    private static $add_stop_words_length = 'short';

    /*
     * @var array
     */
    private static $indexes = array(
        "Keyword" => true
    );

    /*
     * @var string
     */
    private static $default_sort = "\"Custom\" DESC, \"Keyword\" ASC";

    /*
     * @var array
     */
    private static $required_fields = array(
        "Keyword"
    );

    /*
     * @var array
     */
    private static $summary_fields = array(
        "Keyword" => "Keyword",
        "Custom.Nice" => "Manually Entered"
    );

    /*
     * @var array
     */
    private static $field_labels = array(
        "Custom" => "Manually Entered"
    );

    /**
     * @return boolean
     */
    public function canDelete($member = null, $context = [])
    {
        return false;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Keyword = SearchEngineKeyword::clean_keyword($this->Keyword);
    }

    /**
     * @return SearchEngineKeywordFindAndRemove
     */
    public static function is_listed($keyword)
    {
        return SearchEngineKeywordFindAndRemove::get()
            ->filter(array("Keyword" => $keyword))->count();
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        //see: http://xpo6.com/download-stop-word-list/
        if (Config::inst()->get(SearchEngineKeywordFindAndRemove::class, "add_stop_words") === true) {
            $size = Config::inst()->get(SearchEngineKeywordFindAndRemove::class, "add_stop_words_length");
            $stopwords = Config::inst()->get(SearchEngineStopWords::class, 'list_'.$size);
            if(! ($stopwords && is_array($stopwords)) {
                user_error('Stopword list specified is not correct, choose from short, medium, long, extra_long, your entry is: '.$size)
            }
            foreach ($stopwords as $stopword) {
                if (!self::is_listed($stopword)) {
                    DB::alteration_message("Creating stop word: $stopword", "created");
                    $obj = SearchEngineKeywordFindAndRemove::create();
                    $obj->Keyword = $stopword;
                    $obj->Custom = false;
                    $obj->write();
                }
            }
        }
    }
}
