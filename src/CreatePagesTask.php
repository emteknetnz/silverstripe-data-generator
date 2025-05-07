<?php

namespace emteknetnz\DataGenerator;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\DB;

class CreatePagesTask extends BuildTask
{
    protected static string $commandName = 'CreatePagesTask';

    private const NUM_LEVEL_1 = 20;
    private const NUM_LEVEL_2 = 40;
    private const NUM_LEVEL_3 = 60;
    private const VALUES_SIZE = 500; // will be double this for _Versions table

    private $draftValues = [];
    private $liveValues = [];
    private $versionValues = [];
    private $id = 0;

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->id = SiteTree::get()->max('ID');
        for ($i = 1; $i <= self::NUM_LEVEL_1; $i++) {
            $parentID = $this->page(0, $i);
            for ($j = 1; $j <= self::NUM_LEVEL_2; $j++) {
                $parentID2 = $this->page($parentID, $i, $j);
                for ($k = 1; $k <= self::NUM_LEVEL_3; $k++) {
                    $this->page($parentID2, $i, $j, $k);
                }
            }
        }
        $this->commit(true);
        return 0;
    }

    private function page($parentID, $i, $j = 0, $k = 0)
    {
        $title = sprintf("Test page %02s%02s%02s", $i, $j, $k);
        $urlSegment = sprintf("test-page-%02s%02s%02s", $i, $j, $k);
        $datetime = date('Y-m-d H:i:s', time());
        $this->id++;
        $content = '<p>Test content</p>';
        $values = $this->createValues($this->id, $parentID, $title, $urlSegment, $datetime, $datetime, $content, 2);
        $this->draftValues[] = $values;
        $this->liveValues[] = $values;
        $this->versionValues[] = $this->createValues($this->id, $parentID, $title, $urlSegment, $datetime, $datetime, $content, 1, 1, 0);
        $this->versionValues[] = $this->createValues($this->id, $parentID, $title, $urlSegment, $datetime, $datetime, $content, 2, 1, 1);
        $this->commit();
        return $this->id;
    }

    private function createValues(...$values)
    {
        return '(' . implode(', ', array_map(fn($v) => "'$v'", $values)) . ')';
    }

    private function commit($force = false)
    {
        // Using raw sql rather than ORM because it is far faster
        $fields = '(ID, ParentID, Title, URLSegment, Created, LastEdited, Content, Version)';
        $c = count($this->draftValues);
        if ($c === self::VALUES_SIZE || $force && $c) {
            $draftValues = implode(', ', $this->draftValues);
            $this->draftValues = [];
            DB::query("INSERT INTO SiteTree $fields VALUES $draftValues;");
            echo "INSERTED $c SiteTree into DB\n";
        }
        $c = count($this->liveValues);
        if ($c === self::VALUES_SIZE || $force && $c) {
            $liveValues = implode(', ', $this->liveValues);
            $this->liveValues = [];
            DB::query("INSERT INTO SiteTree_Live $fields VALUES $liveValues;");
            echo "INSERTED $c SiteTree_Live into DB\n";
        }
        $fields = '(RecordID, ParentID, Title, URLSegment, Created, LastEdited, Content, Version, WasDraft, WasPublished)';
        $c = count($this->versionValues);
        if ($c === self::VALUES_SIZE * 2 || $force && $c) {
            $versionValues = implode(', ', $this->versionValues);
            $this->versionValues = [];
            DB::query("INSERT INTO SiteTree_Versions $fields VALUES $versionValues;");
            echo "INSERTED $c SiteTree_Versions into DB\n";
        }
    }
}
