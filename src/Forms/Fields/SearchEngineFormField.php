<?php

namespace Sunnysideup\SearchSimpleSmart\Forms\Fields;

use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Core\SearchEngineCoreSearchMachine;

class SearchEngineFormField extends LiteralField
{
    /**
     * total number days to search back.
     *
     * @var int
     */
    protected $numberOfDays = 180;

    /**
     * how many days ago the data-analysis should end.
     *
     * @var int
     */
    protected $endingDaysBack = 0;

    /**
     * minimum number of searches for the data to show up.
     *
     * @var float
     */
    protected $minimumCount = 1;

    protected bool $showSource = true;

    public function __construct($name, $title = '')
    {
        parent::__construct($name, $title);
        $this->title = $title;
    }

    public function FieldHolder($properties = [])
    {
        return $this->Field();
    }

    public function Field($properties = [])
    {
        $totalNumberOfDaysBack = $this->numberOfDays + $this->endingDaysBack;
        // INNER JOIN SearchEngineSearchRecord ON SearchEngineSearchRecord.ID = SearchEngineSearchRecordHistory.SearchEngineSearchRecordID
        if ($this->minimumCount > 0) {
            $sql = '
                SELECT COUNT(SearchEngineSearchRecordHistory.ID) myCount
                FROM "SearchEngineSearchRecordHistory"
                WHERE  SearchEngineSearchRecordHistory.Created > ( NOW() - INTERVAL ' . $totalNumberOfDaysBack . ' DAY )
                    AND SearchEngineSearchRecordHistory.Created < ( NOW() - INTERVAL ' . $this->endingDaysBack . ' DAY )
                    AND MemberID = 0
            ';
            $totalCount = (int) DB::query($sql)->value();
            $this->minimumCount = (int) round($totalCount / 10000);
        }
        $endWhere = '';
        if ($this->endingDaysBack > 0) {
            $endWhere = 'AND SearchEngineSearchRecordHistory.Created < ( NOW() - INTERVAL ' . $this->endingDaysBack . ' DAY )';
        }
        $sql = '
            SELECT COUNT(SearchEngineSearchRecordHistory.ID) myCount, "Phrase" AS Title
                FROM "SearchEngineSearchRecordHistory"
            WHERE SearchEngineSearchRecordHistory.Created > ( NOW() - INTERVAL ' . $totalNumberOfDaysBack . ' DAY )
            AND "Phrase" IS NOT NULL AND "Phrase" <> \'\' AND Phrase <> \'' . SearchEngineCoreSearchMachine::NO_KEYWORD_PROVIDED . '\'
                ' . $endWhere . '
            GROUP BY "Phrase"
            HAVING myCount >= ' . $this->minimumCount . '
            ORDER BY myCount DESC
        ';
        $data = DB::query($sql);
        if ((int) $this->minimumCount < 2) {
            $this->minimumCount = 2;
        }

        $content = '';
        if ($this->title) {
            $content .= '<h2>' . $this->title . '</h2>';
        }

        $content .= '
        <div id="SearchHistoryTableForCMS">
            <h2>
                From ' . date('Y-M-d', strtotime('-' . $totalNumberOfDaysBack . ' days')) .
                ' to ' . date('Y-M-d', strtotime('-' . $this->endingDaysBack . ' days')) .
                ', entered at least ' . $this->minimumCount . ' times
            <h2>
            <h3>By Ranking</h3>

            <table id="HighToLow" style="width: 100%">';
        $list = [];
        $maxwidth = -1;
        foreach ($data as $key => $row) {
            //for the highest count, we work out a max-width
            if (-1 === $maxwidth) {
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
            <h3>The same list from A - Z</h3>
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
        if ($this->showSource) {
            $content .= '<p><a href="/dev/tasks/searchhistorybrowser">Browse Search History</a></p>';
        }
        return $content;
    }

    /**
     * @param int $days
     */
    public function setNumberOfDays($days): self
    {
        $this->numberOfDays = (int) $days;

        return $this;
    }

    public function setShowSource(bool $b): self
    {
        $this->showSource = $b;

        return $this;
    }

    /**
     * @param int $count
     */
    public function setMinimumCount($count): self
    {
        $this->minimumCount = (int) $count;

        return $this;
    }

    /**
     * @param int $count
     */
    public function setEndingDaysBack($count): self
    {
        $this->endingDaysBack = (int) $count;

        return $this;
    }
}
