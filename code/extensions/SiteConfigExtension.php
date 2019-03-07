<?php

class SearchEngineSiteConfigExtension extends DataExtension {

	private static $db = array(
		"SearchEngineDebug" => "Boolean"
	);

	function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.SearchEngine", new CheckboxField("SearchEngineDebug", "Debug Search Engine"));
	}

}
