<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\ORM\DataObject;

/**
 * @authors: Nicolaas [at] Sunny Side Up .co.nz
 **/

class SearchEngineAdvancedSettings extends DataObject
{

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineAdvancedSettings';

    /**
     * @var string
     */
    private static $singular_name = "Advanced";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }
}
