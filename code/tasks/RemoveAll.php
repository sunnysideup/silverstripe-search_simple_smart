<?php


class SearchEngineRemoveAll extends BuildTask {

	/**
	 * list of all model tables
	 * @var Array
	 */
	private static $all_tables = array(
		"SearchEngineDataObject",
		"SearchEngineDataObjectToBeIndexed",
		"SearchEngineFullContent",
		"SearchEngineKeyword",
		"SearchEngineSearchRecord",
		"SearchEngineSearchRecordHistory",
		"SearchEngineKeyword_SearchEngineDataObjects",
		"SearchEngineDataObject_SearchEngineKeywords_Level1",
		"SearchEngineDataObject_SearchEngineKeywords_Level2",
		"SearchEngineKeyword_SearchEngineDataObjects_Level1",
		"SearchEngineKeyword_SearchEngineDataObjects_Level2",
		"SearchEngineSearchRecord_SearchEngineKeywords"
	);

	/**
	 * Title of the task
	 * @var string
	 */
	protected $title = "Remove All Search Engine Index Data";

	/**
	 * Description of the task
	 * @var string
	 */
	protected $description = "Careful - remove all the search engine index data.";

	/**
	 * this function runs the SearchEngineRemoveAll task
	 * @param var $request
	 */
	public function run($request) {
		$iAmSure = $request->getVar("i-am-sure") ? true : false;
		if(!$iAmSure) {
			die("please add the i-am-sure get variable to this task");
		}
		$allTables = Config::inst()->get("SearchEngineRemoveAll", "all_tables");
		$tables = DB::getConn()->tableList();
		foreach($tables as $table) {
			if(in_array($table, $allTables)) {
				DB::alteration_message("Drop \"$table\"", "deleted");
				if(method_exists(DB::getConn(), 'clearTable')) {
					@DB::query("DROP \"$table\"");
					DB::getConn()->clearTable($table);
				}
				else {
					DB::query("TRUNCATE \"$table\"");
				}
			}
		}
		DB::alteration_message("====================== completed =======================");
		DB::alteration_message("Please make sure to run a <a href=\"/dev/build/\">dev/build</a> to finalise your cleanup");
	}

}
