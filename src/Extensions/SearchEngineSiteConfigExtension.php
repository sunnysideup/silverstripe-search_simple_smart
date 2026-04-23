<?php

declare(strict_types=1);

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;

class SearchEngineSiteConfigExtension extends Extension
{
    private static $db = [
        'SearchEngineDebug' => 'Boolean',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.KeywordSearch', CheckboxField::create('SearchEngineDebug', 'Debug Search Engine'));
    }
}
