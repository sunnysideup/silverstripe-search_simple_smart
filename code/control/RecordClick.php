<?php


class SearchEngineRecordClick extends Controller {


	private static $allowed_actions = array('add');

	/**
	 * record the click that the user chooses from the search results.
	 * @param SS_HTTPRequest
	 * @return string
	 */
	function add(SS_HTTPRequest $request){
		$itemID = $request->param("ID");
		$item = SearchEngineDataObject::get()->byID(intval($itemID));
		if($item) {
			$obj = SearchEngineSearchRecordHistory::register_click($item);
			return $this->redirect($item->SourceObject()->Link());
		}
		else {
			$url = isset($_GET["finaldestination"]) ? $_GET["finaldestination"] : "404";
			user_error("history could not be recorded", E_USER_NOTICE);
			return $this->redirect(Director::absoluteURL($url));
		}
	}


}
