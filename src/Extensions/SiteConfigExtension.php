<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataExtension;

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD:  extends DataExtension (ignore case)
  * NEW:  extends DataExtension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class SearchEngineSiteConfigExtension extends DataExtension
{
    private static $db = array(
        "SearchEngineDebug" => "Boolean"
    );

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab("Root.SearchEngine", new CheckboxField("SearchEngineDebug", "Debug Search Engine"));
    }
}
