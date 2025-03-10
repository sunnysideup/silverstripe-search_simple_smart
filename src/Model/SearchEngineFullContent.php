<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Admin\SearchEngineAdmin;

/**
 * Full Content for each dataobject, separated by level of importance.
 *
 * Adding the content here, will also add it to the Keywords.
 *
 * Todo: consider breaking it up in sentences.
 */
class SearchEngineFullContent extends DataObject
{
    protected static $_punctuation_objects;

    /**
     * we keep the . for website addresses.
     *
     * @var array
     */
    private static $default_punctuation_to_be_removed = [
        "'",
        '"',
        ';',
        ',',
        '&nbsp',
    ];

    private static $pattern_for_alpha_numeric_characters = '[^a-zA-Z0-9āēīōūĀĒĪŌŪáéíóúÁÉÍÓÚüÜöÖäÄçÇñÑßåÅæÆøØčČřŘšŠžŽłŁęĘśćŚĆżŻźŹđĐ_~\-\.\/\: ]';
    private static $pattern_for_letters = '[^\p{L}\p{N}]';

    private static $acceptable_one_letter_words = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'i'];

    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineFullContent';

    // @var string
    private static $singular_name = 'Full Content Extract';

    // @var string
    private static $plural_name = 'Full Content Extracts';

    // @var array
    private static $db = [
        'Level' => 'Int(1)',
        'Content' => 'Varchar(9999)',
    ];

    // @var array
    private static $has_one = [
        'SearchEngineDataObject' => SearchEngineDataObject::class,
    ];

    // @var array
    private static $indexes = [
        'Level' => true,
        'SearchFields' => [
            'type' => 'fulltext',
            'name' => 'SearchFields',
            'columns' => ['Content'],
        ],
    ];

    // @var string
    private static $default_sort = '"Level" ASC, "Content" ASC';

    // @var array
    private static $required_fields = [
        'Level',
        'Content',
    ];

    // @var array
    private static $summary_fields = [
        'LastEdited.Nice' => 'Last Changed',
        'SearchEngineDataObject.Title' => 'Searchable Object',
        'Level' => 'Level',
        'Content.LimitWordCount' => 'Content',
    ];

    /**
     * Defines a default list of filters for the search context.
     *
     * @var array
     */
    private static $searchable_fields = [
        'Level' => 'ExactMatchFilter',
        'Content' => 'PartialMatchFilter',
    ];

    // @var array
    private static $field_labels = [
        'SearchEngineDataObject' => 'Data Object',
    ];

    /**
     * this is very important to allow Mysql FullText Searches.
     *
     * @var array
     */
    private static $create_table_options = [
        MySQLSchemaManager::ID => 'ENGINE=MyISAM',
    ];

    /**
     * @var bool
     */
    private static $remove_all_non_alpha_numeric = false;

    /**
     * @var bool
     */
    private static $remove_all_non_letters = true;

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
        return false;
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        return false;
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
     * @param SearchEngineDataObject $item
     * @param array                  $fullAray
     *                                         1 => content
     *                                         2 => content
     *                                         3 => content
     *
     * You can specify up to three levels
     */
    public static function add_data_object_array($item, $fullAray)
    {
        foreach ($fullAray as $level => $content) {
            self::add_one($item, $level, $content);
        }
    }

    /**
     * @param SearchEngineDataObject $item
     * @param int                    $level
     * @param string                 $content
     *
     * @return SearchEngineFullContent
     */
    public static function add_one($item, $level, $content)
    {
        $level = SearchEngineKeyword::level_sanitizer($level);
        //you dont want to clean keywords now as this will remove all the spaces!
        //$content = SearchEngineKeyword::clean_keyword($content);
        $fieldArray = ['SearchEngineDataObjectID' => $item->ID, 'Level' => $level];
        /** @var SearchEngineFullContent $obj */
        $obj = DataObject::get_one(self::class, $fieldArray);
        if (! $obj) {
            $obj = self::create($fieldArray);
        }

        $obj->Content = $content;
        $obj->write();
        return $obj;
    }

    /**
     * cleans a string.
     *
     * @param string $content
     *
     * @return string
     * @todo: cache using SS caching system.
     */
    public static function clean_content($content): string
    {
        $content = strtolower((string) $content);

        //important!!!! - create space around tags ....
        $content = str_replace('<', ' <', $content);
        $content = str_replace('>', '> ', $content);

        //remove tags!
        $content = strip_tags($content);

        //default punctuation removal
        $defaultPuncs = Config::inst()->get(self::class, 'default_punctuation_to_be_removed');
        foreach ($defaultPuncs as $defaultPunc) {
            $content = str_replace($defaultPunc, ' ', (string) $content);
        }

        //custom punctuation removal
        if (null === self::$_punctuation_objects) {
            self::$_punctuation_objects = SearchEnginePunctuationFindAndRemove::get();
            if (0 === self::$_punctuation_objects->count()) {
                self::$_punctuation_objects = false;
            }
        }

        if (self::$_punctuation_objects) {
            foreach (self::$_punctuation_objects as $punctuationObject) {
                $content = str_replace((string) $punctuationObject->Character, ' ', (string) $content);
            }
        }

        //remove non-alpha
        $removeNonAlphas = Config::inst()->get(self::class, 'remove_all_non_alpha_numeric');
        if (true === $removeNonAlphas) {
            $content = preg_replace(
                '/'.self::get_pattern_for_alpha_numeric_characters() .'+/u',
                ' ',
                (string) $content
            );
        }

        //remove non letters
        //
        //remove all white space with single space
        //see: http://stackoverflow.com/questions/5059996/php-preg-replace-with-unicode-chars
        //see: http://stackoverflow.com/questions/11989482/how-to-replace-all-none-alphabetic-characters-in-php-with-utf-8-support
        //                     Let's break down the regular expression #[\P{L}\P{N}]+#u:

        // #: These are delimiters for the regular expression. You can use other characters as delimiters, but # is common because it is less likely to appear in the pattern itself.

        // [\P{L}\P{N}]+: This is the core of the regular expression pattern.

        // [ and ]: These square brackets define a character class, which matches any one character that is listed within them.
        // \P{L}: This matches any character that is not a letter. \P (uppercase 'P') is the negation of \p (lowercase 'p'), which matches a letter. L stands for letter in Unicode properties.
        // \P{N}: This matches any character that is not a number. Similarly, \P negates \p, and N stands for number in Unicode properties.
        // \P{L}\P{N}: Together, these match any character that is neither a letter nor a number.
        // +: This quantifier matches one or more occurrences of the preceding character class.
        // #u: These are modifiers applied to the regular expression.

        // #: The closing delimiter for the regular expression.
        // u: This is the Unicode modifier, which tells the regular expression engine to treat the pattern as a Unicode string.
        // Putting it all together, the pattern #[\P{L}\P{N}]+#u matches any sequence of one or more characters that are neither letters nor numbers. In other words, it matches any group of non-letter, non-number characters and replaces them with a single space.
        $removeNonLetters = Config::inst()->get(self::class, 'remove_all_non_letters');
        if (true === $removeNonLetters) {
            $exclude = self::get_pattern_for_letters();
            if ($removeNonAlphas) {
                $exclude .= '|'.self::get_pattern_for_alpha_numeric_characters();
            }
            $content = trim(
                preg_replace(
                    '/'. $exclude . '+/u',
                    ' ',
                    (string) $content
                )
            );
        }
        // remove multiple white space
        return trim(preg_replace('#\s+#', ' ', (string) $content));
    }

    public static function get_pattern_for_alpha_numeric_characters(): string
    {
        return Config::inst()->get(self::class, 'pattern_for_alpha_numeric_characters');
    }

    public static function get_pattern_for_letters(): string
    {
        return Config::inst()->get(self::class, 'pattern_for_letters');
    }

    public static function get_pattern_for_alpha_numeric_characters_human_readable(): array
    {
        $pattern = self::get_pattern_for_alpha_numeric_characters();
        preg_match('/\[\^([^\]]+)\]/u', $pattern, $matches);
        if (! isset($matches[1])) {
            return [];
        }

        // Extract characters within the character class and remove ranges
        $allowedCharacters = $matches[1];
        $allowedCharacters = str_replace('-', '', $allowedCharacters);

        // Return the unique characters as an array
        return array_unique(mb_str_split($allowedCharacters));
    }

    /**
     * CMS Fields.
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $obj = $this->SearchEngineDataObject();
        if ($obj) {
            $fields->replaceField(
                'SearchEngineDataObjectID',
                ReadonlyField::create(
                    'SearchEngineDataObjectTitle',
                    'Object',
                    DBField::create_field(
                        'HTMLText',
                        '<a href="' . $obj->CMSEditLink() . '">' . $obj->getTitle() . '</a>'
                    )
                )
            );
        }

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Level = SearchEngineKeyword::level_sanitizer($this->Level);
        $this->Content = self::clean_content($this->Content);
        //this is a method in the DataObject class to forces onAfterWrite to run
        $this->forceChange();
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        $fullArray = [];
        $item = $this->SearchEngineDataObject();
        if ($item) {
            //todo: turn Content into Keywords
            //1. take full content.
            $content = $this->Content;
            //2. remove stuff that is not needed (e.g. strip_tags)
            $keywords = explode(' ', $content);
            foreach ($keywords as $keyword) {
                // we know content is clean already!
                // $keyword = SearchEngineKeyword::clean_keyword($keyword);
                $oneLetterWords = Config::inst()->get(self::class, 'acceptable_one_letter_words');
                if (strlen($keyword) > 1 || in_array($keyword, (array) $oneLetterWords, true)) {
                    //check if it is a valid keyword.
                    if (SearchEngineKeywordFindAndRemove::is_listed($keyword)) {
                        //not a valid keyword
                        continue;
                    }

                    $keywordObject = SearchEngineKeyword::add_keyword($keyword, $runClean = false);
                    if (! isset($fullArray[$keywordObject->ID])) {
                        $fullArray[$keywordObject->ID] = [
                            'Object' => $keywordObject,
                            'Count' => 0,
                        ];
                    }

                    ++$fullArray[$keywordObject->ID]['Count'];
                }
            }

            //remove All previous entries
            $this->Level = SearchEngineKeyword::level_sanitizer($this->Level);
            $methodName = 'SearchEngineKeywords_Level' . $this->Level;
            $list = $item->{$methodName}();
            $list->removeAll();
            //add all keywords
            foreach ($fullArray as $a) {
                $list->add($a['Object'], ['Count' => $a['Count']]);
            }
        }
    }


}
