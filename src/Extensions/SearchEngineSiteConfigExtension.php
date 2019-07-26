<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class SearchEngineSiteConfigExtension extends DataExtension
{
    private static $db = [
        'SearchEngineDebug' => 'Boolean',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.SearchEngine', new CheckboxField('SearchEngineDebug', 'Debug Search Engine'));
    }
}
