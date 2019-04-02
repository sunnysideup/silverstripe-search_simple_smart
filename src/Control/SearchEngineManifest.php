<?php

namespace Sunnysideup\SearchSimpleSmart\Control;

use SilverStripe\Control\HTTPRequest;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;
use Sunnysideup\SearchSimpleSmart\Admin\SearchEngineAdmin;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBField;

class SearchEngineManifest extends Controller
{
    private static $allowed_actions = [
        'index' => 'ADMIN'
    ];


    public function index(HTTPRequest $request)
    {
        Requirements::javascript('sunnysideup/search_simple_smart:client/javascript/CMS.js');
        Requirements::themedCSS('client/css/CMS');
        Requirements::customScript("SearchEngineManifest();", "SearchEngineManifest");

        return $this->renderWith('SearchEngineManifest', ['Content' => $this->getContent()]);
    }

    /**
     * record the click that the user chooses from the search results.
     * @param SS_HTTPRequest
     * @return string
     */
    public function getContent()
    {

        $classNames = SearchEngineDataObject::searchable_class_names();
        asort($classNames);
        $manifest = "";
        if (is_array($classNames) && count($classNames)) {
            $manifest .=
                "<div id=\"SearchEngineManifest\">
                    <ul>";
            foreach ($classNames as $className => $classNameTitle) {
                $numberOfIndexedObjects = SearchEngineDataObject::get()->filter(array("DataObjectClassName" => $className))->count();
                $manifest .=
                        "<li class=\"".($numberOfIndexedObjects ? "hasEntries" : "doesNotHaveEntries")."\">
                            <h3>$classNameTitle ($numberOfIndexedObjects)</h3>
                            <ul>";
                $class = Injector::inst()->get($className);
                $manifest .=
                                "<li>
                                    <strong>Fields Indexed (level 1 / 2  is used to determine importance for relevance sorting):</strong>".
                                    $class->SearchEngineFieldsToBeIndexedHumanReadable().
                                "</li>";
                $manifest .=
                                "<li>
                                    <strong>Templates:</strong>".
                                    $this->printNice($class->SearchEngineResultsTemplates(false)).
                                "</li>";
                $manifest .=
                                "<li>
                                    <strong>Templates (more details):</strong>".
                                    $this->printNice($class->SearchEngineResultsTemplates(true)).
                                "</li>";
                $manifest .=
                                "<li>
                                    <strong>Also trigger:</strong>".
                                    $this->printNice($class->SearchEngineAlsoTrigger()).
                                "</li>";
                $manifest .=
                            "</ul>
                        </li>";
            }
            $manifest .=
                    "</ul>
                </div>";
        }

        return DBField::create_field('HTMLText', $manifest);
    }

    public function printNice($arr)
    {
        return SearchEngineAdmin::print_nice($arr);
    }
}
