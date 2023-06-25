<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * keyword replace engine.
 */
class SearchEngineKeywordFindAndReplace extends DataObject
{
    /**
     * making sure that we do not have infinite loops...
     *
     * @var array
     */
    protected static $_words_used = [];

    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineKeywordFindAndReplace';

    /**
     * @var string
     */
    private static $singular_name = 'Keyword Replace';

    /**
     * @var string
     */
    private static $plural_name = 'Keywords Replace';

    /**
     * @var array
     */
    private static $db = [
        'Keyword' => 'Varchar(150)',
        'ReplaceWith' => 'Text', // comma separated list ...
        'Custom' => 'Boolean(1)',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Keyword' => true,
    ];

    /**
     * @var string
     */
    private static $default_sort = '"Custom" DESC, "Keyword" ASC';

    /**
     * @var array
     */
    private static $defaults = [
        'Custom' => 1,
    ];

    /**
     * @var array
     */
    private static $required_fields = [
        'Keyword',
        'ReplaceWith',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Keyword' => 'Keyword',
        'ReplaceWith' => 'Replace With',
        'Custom.Nice' => 'Manually Entered',
    ];

    /**
     * @var array
     */
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
        return parent::canDelete() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        return parent::canDelete() && Permission::check('SEARCH_ENGINE_ADMIN');
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

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $keywordField = $fields->fieldByName('Root.Main')->fieldByName('Keyword');
        $keywordField->setTitle('Original Keyword');

        $replaceWithField = $fields->fieldByName('Root.Main')->fieldByName('ReplaceWith');
        $replaceWithField->setRightTitle(
            'Enter all the keywords (separated by a comma) you need to be included when a user searches for the original keyword.
            If you need the original keyword to be included in the search it should also be included.'
        );

        return $fields;
    }

    /**
     * this method is recursive...
     *
     * @param string $keyword
     *
     * @return string
     */
    public static function find_replacements($keyword)
    {
        $objects = self::get()
            ->filter(['Keyword' => $keyword])
        ;
        self::$_words_used[$keyword] = $keyword;
        foreach ($objects as $object) {
            $newEntries = explode(',', $object->ReplaceWith);
            $newerEntries = [];
            foreach ($newEntries as $newEntryKeyword) {
                $newEntryKeyword = SearchEngineKeyword::clean_keyword($newEntryKeyword);
                if (! isset(self::$_words_used[$newEntryKeyword])) {
                    $newerEntries[] = self::find_replacements($newEntryKeyword);
                } else {
                    $newerEntries[] = $newEntryKeyword;
                }
            }

            return implode(' ', $newerEntries);
        }

        return $keyword;
    }

    /**
     * clean up entries.
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Keyword = SearchEngineKeyword::clean_keyword($this->Keyword);
        $replaceWithArray = $this->multiExplode([',', ' '], $this->ReplaceWith);
        $finalArray = [];
        foreach ($replaceWithArray as $keyword) {
            $keyword = SearchEngineKeyword::clean_keyword($keyword);
            if (strlen($keyword) > 1) {
                $finalArray[] = $keyword;
            }
        }

        $this->ReplaceWith = implode(',', $finalArray);
    }

    /**
     * @param array  $delimiters
     * @param string $string
     *
     * @return array
     */
    private function multiExplode($delimiters, $string)
    {
        $ready = str_replace($delimiters, $delimiters[0], $string);

        return explode($delimiters[0], $ready);
    }
}
