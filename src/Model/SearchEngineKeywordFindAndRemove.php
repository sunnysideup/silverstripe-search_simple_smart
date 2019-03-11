<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeywordFindAndRemove;
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
            $stopwords = array("a", "about", "above", "above", "across", "after", "afterwards", "again", "against", "all", "almost", "alone", "along", "already", "also","although","always","am","among", "amongst", "amoungst", "amount",  "an", "and", "another", "any","anyhow","anyone","anything","anyway", "anywhere", "are", "around", "as",  "at", "back","be","became", "because","become","becomes", "becoming", "been", "before", "beforehand", "behind", "being", "below", "beside", "besides", "between", "beyond", "bill", "both", "bottom","but", "by", "call", "can", "cannot", "cant", "co", "con", "could", "couldnt", "cry", "de", "describe", "detail", "do", "done", "down", "due", "during", "each", "eg", "eight", "either", "eleven","else", "elsewhere", "empty", "enough", "etc", "even", "ever", "every", "everyone", "everything", "everywhere", "except", "few", "fifteen", "fify", "fill", "find", "fire", "first", "five", "for", "former", "formerly", "forty", "found", "four", "from", "front", "full", "further", "get", "give", "go", "had", "has", "hasnt", "have", "he", "hence", "her", "here", "hereafter", "hereby", "herein", "hereupon", "hers", "herself", "him", "himself", "his", "how", "however", "hundred", "ie", "if", "in", "inc", "indeed", "interest", "into", "is", "it", "its", "itself", "keep", "last", "latter", "latterly", "least", "less", "ltd", "made", "many", "may", "me", "meanwhile", "might", "mill", "mine", "more", "moreover", "most", "mostly", "move", "much", "must", "my", "myself", "name", "namely", "neither", "never", "nevertheless", "next", "nine", "no", "nobody", "none", "noone", "nor", "not", "nothing", "now", "nowhere", "of", "off", "often", "on", "once", "one", "only", "onto", "or", "other", "others", "otherwise", "our", "ours", "ourselves", "out", "over", "own","part", "per", "perhaps", "please", "put", "rather", "re", "same", "see", "seem", "seemed", "seeming", "seems", "serious", "several", "she", "should", "show", "side", "since", "sincere", "six", "sixty", "so", "some", "somehow", "someone", "something", "sometime", "sometimes", "somewhere", "still", "such", "system", "take", "ten", "than", "that", "the", "their", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "therefore", "therein", "thereupon", "these", "they", "thickv", "thin", "third", "this", "those", "though", "three", "through", "throughout", "thru", "thus", "to", "together", "too", "top", "toward", "towards", "twelve", "twenty", "two", "un", "under", "until", "up", "upon", "us", "very", "via", "was", "we", "well", "were", "what", "whatever", "when", "whence", "whenever", "where", "whereafter", "whereas", "whereby", "wherein", "whereupon", "wherever", "whether", "which", "while", "whither", "who", "whoever", "whole", "whom", "whose", "why", "will", "with", "within", "without", "would", "yet", "you", "your", "yours", "yourself", "yourselves", "the");
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
