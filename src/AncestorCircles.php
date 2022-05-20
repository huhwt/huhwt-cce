<?php

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;

// array functions
use function count;
use function array_keys;

class AncestorCircles
{
    /**
     * @var array $graph
     */
    private array $graph;

    /**
     * constructor for this class
     *
     * @param Tree $tree
     * @param array $links keywords in database table like ['FAMS', 'FAMC','ALIA']
     */
    public function __construct(Tree $tree, array $links)
    {
        $this->graph = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->pluck('i_id')
            ->mapWithKeys(static function (string $xref): array {
                return [$xref => []];
            })
            ->all();

        // $rows is an array of objects with two pointers:
        // l_from => family XREF and
        // l_to => individual XREF
        $rows = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->whereIn('l_type', $links)
            ->select(['l_from', 'l_to'])
            ->get();

        foreach ($rows as $row) {
            $this->graph[$row->l_from][$row->l_to] = 1;
            $this->graph[$row->l_to][$row->l_from] = 1;
        }
        // $graph is now a square matrix with XREFS of individuals and families in rows and columns
        // value of $graph is 0 or 1 if individual belongs to a family or a family contains an individual
        $this->eliminateLeaves();
    }

    /**
     * eliminate all leaves of the tree as long as there are leaves existing
     */
    private function eliminateLeaves(): void
    {
        $count = count(array_keys($this->graph));
        do {
            $count2 = $count;
            $this->reduceGraph();
            $count = count(array_keys($this->graph));
        } while ($count2 > $count);
    }

    /**
     * reduce this graph by cutting leaves in the tree
     * this is one step and maybe there are still leaves on the next layer after this step
     */
    private function reduceGraph(): void
    {
        foreach ($this->graph as $column => $array) {
            if (count($this->graph[$column]) == 0) {                      // not connected
                unset($this->graph[$column]);
            } elseif (count($this->graph[$column]) == 1) {                // leave
                foreach ($this->graph[$column] as $index => $value) {
                    unset($this->graph[$index][$column]);
                }
                unset($this->graph[$column]);
            }
        }
    }

    /**
     * return the keys of graph, these are XREFs
     *
     * @return array
     */
    public function getXrefs(): array
    {
        return array_keys($this->graph);
    }
}
