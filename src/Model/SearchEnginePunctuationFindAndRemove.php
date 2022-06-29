<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

class SearchEnginePunctuationFindAndRemove extends DataObject
{
    /**
     * @var bool
     */
    private static $add_defaults = true;

    /**
     * Defines the database table name.
     *
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

    /**
     * @param string $character
     */
    public static function is_listed($character): bool
    {
        return (bool) self::get()
            ->filter(['Character' => $character])->count();
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if (true === Config::inst()->get(self::class, 'add_defaults')) {
            $defaults = Config::inst()->get(self::class, 'defaults');
            foreach ($defaults as $default) {
                if (! self::is_listed($default)) {
                    DB::alteration_message('Creating a punctuation: ' . $default, 'created');
                    $obj = self::create();
                    $obj->Character = $default;
                    $obj->Custom = false;
                    $obj->write();
                }
            }
        }
    }
}
