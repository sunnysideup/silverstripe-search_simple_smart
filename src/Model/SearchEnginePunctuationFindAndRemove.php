<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Member;
use SilverStripe\Forms\FieldList;


class SearchEnginePunctuationFindAndRemove extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEnginePunctuationFindAndRemove';

    /**
     * @var string
     */
    private static $singular_name = 'Punctuation to Remove';

    /**
     * @var string
     */
    private static $plural_name = 'Punctuations to Remove';

    /**
     * @var array
     */
    private static $db = [
        'Character' => 'Varchar(3)',
        'Custom' => 'Boolean(1)',
    ];

    /**
     * @var array
     */
    private static $defaults = [];

    /**
     * @var array
     */
    private static $indexes = [
        'Character' => true,
    ];

    /**
     * @var string
     */
    private static $default_sort = '"Custom" DESC, "Character" ASC';

    /**
     * @var array
     */
    private static $required_fields = [
        'Character',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Character' => 'Character',
        'Custom.Nice' => 'Manually Entered',
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
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        return parent::canDelete() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canEdit($member = null)
    {
        return parent::canDelete() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canDelete($member = null)
    {
        return parent::canDelete() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canView($member = null)
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param string $character
     * @return bool
     */
    public static function is_listed($character) : bool
    {
        return self::get()
            ->filter(['Character' => $character])->count() ? true : false;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if (Config::inst()->get(self::class, 'add_defaults') === true) {
            $defaults = Config::inst()->get(self::class, 'defaults');
            foreach (self::$defaults as $default) {
                if (! self::is_listed($default)) {
                    DB::alteration_message("Creating a punctuation: ".$default, 'created');
                    $obj = self::create();
                    $obj->Character = $default;
                    $obj->Custom = false;
                    $obj->write();
                }
            }
        }
    }
}
