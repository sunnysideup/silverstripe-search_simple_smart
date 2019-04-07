<?php

namespace Sunnysideup\SearchSimpleSmart\Api;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extensible;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Director;
use SilverStripe\Security\Security;
use SilverStripe\ORM\DB;

class ExportKeywordList
{


   use Extensible;
   use Injectable;
   use Configurable;

    /**
     * if this is not set, the list will NOT be exported!
     * @var string
     */
    private static $keyword_list_folder_name = '';

    public static function export_keyword_list()
    {
        $fileName = self::get_js_keyword_file_name(true);
        if ($fileName) {
            //only write once a minute
            if (file_exists($fileName) && (time() -  filemtime($fileName) < 120)) {
                return 'no new file created as the current one is less than 120 seconds old: '.$fileName;
            //do nothing
            } else {
                if(Security::database_is_ready()) {
                    $rows = DB::query('SELECT "Keyword" FROM "SearchEngineKeyword" ORDER BY "Keyword";');
                    $array = [];
                    foreach ($rows as $row) {
                        $array[] = str_replace('\'', '', Convert::raw2js($row['Keyword']));
                    }
                    $written = null;
                    if ($fh = fopen($fileName, 'w')) {
                        $written = fwrite($fh, 'SearchEngineInitFunctions.keywordList = [\''.implode('\',\'', $array).'\'];');
                        fclose($fh);
                    }
                    if (!$written) {
                        user_error('Could not write keyword list to $fileName', E_USER_NOTICE);
                    }
                    return 'Writing: <br />'.implode('<br />', $array);
                }
            }

        } else {
            return 'no file name specified';
        }
    }

    /**
     * returns the location of the keyword file...
     * @param Boolean $includeBase
     *
     * @return string|null
     */
    public static function get_js_keyword_file_name($includeBase = false)
    {
        $myFolderName = Config::inst()->get(ExportKeywordList::class, 'keyword_list_folder_name');
        //without folder name we return null!
        if ($myFolderName) {
            $myFolderName = 'public/assets/'.$myFolderName;
            $myFolder = Folder::find_or_make($myFolderName);
            $fileName = 'keywords.js';
            if ($includeBase) {
                $str = Director::baseFolder().'/'.$myFolder->getFilename();
            } else {
                $str = $myFolder->getFilename();
            }
            if(! file_exists($str)) {
                mkdir($str);
            }
            $str = rtrim(str_replace('//', '/', $str),'/').'/'.$fileName;

            return $str;
        }
    }


}
