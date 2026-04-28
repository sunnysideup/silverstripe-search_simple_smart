<?php

declare(strict_types=1);

namespace Sunnysideup\SearchSimpleSmart\Reports;

use Override;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\ORM\DataList;
use SilverStripe\Reports\Report;

class PagesNotIncludedInSearch extends Report
{
    #[Override]
    public function title()
    {
        return 'Pages not included in search';
    }

    public function group()
    {
        return _t(self::class . '.ContentGroupTitle', 'Content reports');
    }

    public function sort()
    {
        return 100;
    }

    /**
     * Gets the source records
     *
     * @param array $params
     * @return DataList
     */
    public function sourceRecords($params = null)
    {
        return SiteTree::get()
            ->exclude([
                'ClassName' => [RedirectorPage::class, VirtualPage::class],
            ])
            ->filter(['ShowInSearch' => false])
            ->sort(['Title' => 'ASC']);
    }

    #[Override]
    public function columns()
    {
        return [
            'Title' => [
                'title' => 'Title', // todo: use NestedTitle(2)
                'link' => true,
            ],
        ];
    }
}
