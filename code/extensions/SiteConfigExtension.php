<?php

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
