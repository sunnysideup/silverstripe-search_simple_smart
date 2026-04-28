<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use Override;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Admin\SearchEngineAdmin;

/**
 * @authors: Nicolaas [at] Sunny Side Up .co.nz
 */
class SearchEngineAdvancedSettings extends DataObject
{
    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineAdvancedSettings';

    /**
     * @var string
     */
    private static $singular_name = 'Advanced';

    /**
     * @var string
     */
    private static $plural_name = 'Advanced Settings';

    #[Override]
    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    #[Override]
    public function plural_name()
    {
        return $this->Config()->get('plural_name');
    }

    /**
     * @param Member $member
     * @param mixed  $context
     *
     * @return bool
     */
    #[Override]
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    #[Override]
    public function canEdit($member = null)
    {
        return false;
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    #[Override]
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    #[Override]
    public function canView($member = null)
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    #[Override]
    public function CMSEditLink()
    {
        return '/' . Injector::inst()->get(SearchEngineAdmin::class)->getCMSEditLinkForManagedDataObject($this);
    }
}
