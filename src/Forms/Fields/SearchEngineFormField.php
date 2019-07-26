<?php

namespace Sunnysideup\SearchSimpleSmart\Forms\Fields;

use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DB;

class SearchEngineFormField extends LiteralField
{
    /**
     * total number days to search back
     * @var int
     */
    protected $numberOfDays = 100;

    /**
     * how many days ago the data-analysis should end
     * @var int
     */
    protected $endingDaysBack = 0;

    /**
     * minimum number of searches for the data to show up
     * @var int
     */
    protected $minimumCount = 1;

    public function __construct($name, $title = '')
    {
        return parent::__construct($name, $title);
    }

    public function FieldHolder($properties = [])
    {
        return $this->Field();
    }

    public function Field($properties = [])
    {
        $totalNumberOfDaysBack = $this->numberOfDays + $this->endingDaysBack;
        /**
         * INNER JOIN SearchEngineSearchRecord ON SearchEngineSearchRecord.ID = SearchEngineSearchRecordHistory.SearchEngineSearchRecordID
         */
        if (! $this->minimumCount < 2) {
            $sql = '
				SELECT COUNT(SearchEngineSearchRecordHistory.ID) myCount
				FROM "SearchEngineSearchRecordHistory"
				WHERE  SearchEngineSearchRecordHistory.Created > ( NOW() - INTERVAL ' . $totalNumberOfDaysBack . ' DAY )
					AND SearchEngineSearchRecordHistory.Created < ( NOW() - INTERVAL ' . $this->endingDaysBack . ' DAY )
					AND MemberID = 0
			';
            $totalCount = DB::query($sql)->value();
            $this->minimumCount = round($totalCount / 1000);
        }
        $sql = '
			SELECT COUNT(SearchEngineSearchRecordHistory.ID) myCount, "Phrase" AS Title
				FROM "SearchEngineSearchRecordHistory"
			WHERE SearchEngineSearchRecordHistory.Created > ( NOW() - INTERVAL ' . $totalNumberOfDaysBack . ' DAY )
				AND SearchEngineSearchRecordHistory.Created < ( NOW() - INTERVAL ' . $this->endingDaysBack . ' DAY )
				AND MemberID = 0
			GROUP BY "Phrase"
			HAVING myCount >= ' . $this->minimumCount . '
			ORDER BY myCount DESC
		';
        $data = DB::query($sql);
        if (! $this->minimumCount) {
            $this->minimumCount++;
        }
        $content = '';
        if ($this->title) {
            $content .= '<h2>' . $this->title . '</h2>';
        }
        $content .= '
		<div id="SearchHistoryTableForCMS">
			<h3>Search Phrases entered at least ' . $this->minimumCount . ' times between ' . date('Y-M-d', strtotime('-' . $totalNumberOfDaysBack . ' days')) . ' and ' . date('Y-M-d', strtotime('-' . $this->endingDaysBack . ' days')) . '</h3>
			<table id="HighToLow" style="width: 100%">';
        $list = [];
        foreach ($data as $key => $row) {
            //for the highest count, we work out a max-width
            if (! $key) {
                $maxwidth = $row['myCount'];
            }
            $multipliedWidthInPercentage = floor(($row['myCount'] / $maxwidth) * 100);
            $list[$row['myCount'] . '-' . $key] = $row['Title'];
            $content .= '
				<tr>
					<td style="text-align: right; width: 30%; padding: 5px;">' . $row['Title'] . '</td>
					<td style="background-color: silver;  padding: 5px; width: 70%;">
						<div style="width: ' . $multipliedWidthInPercentage . '%; background-color: #0066CC; color: #fff;">' . $row['myCount'] . '</div>
					</td>
				</tr>';
        }
        $content .= '
			</table>';
        asort($list);
        $content .= '
			<h3>A - Z</h3>
			<table id="AToz" style="width: 100%">';
        foreach ($list as $key => $title) {
            $array = explode('-', $key);
            $multipliedWidthInPercentage = floor(($array[0] / $maxwidth) * 100);
            $content .= '
				<tr>
					<td style="text-align: right; width: 30%; padding: 5px;">' . $title . '</td>
					<td style="background-color: silver;  padding: 5px; width: 70%">
						<div style="width: ' . $multipliedWidthInPercentage . '%; background-color: #0066CC; color: #fff;">' . trim($array[0]) . '</div>
					</td>
				</tr>';
        }
        $content .= '
			</table>
		</div>';

        return $content;
    }

    /**
     * @param int $days
     * @return Field
     */
    public function setNumberOfDays($days)
    {
        $this->numberOfDays = intval($days);
        return $this;
    }

    /**
     * @param int $count
     * @return Field
     */
    public function setMinimumCount($count)
    {
        $this->minimumCount = intval($count);
        return $this;
    }

    /**
     * @param int $count
     * @return Field
     */
    public function setEndingDaysBack($count)
    {
        $this->endingDaysBack = intval($count);
        return $this;
    }
}
