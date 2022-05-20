<?php

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;

// array functions
use function count;

class PartnerChainsGlobal
{
    /**
     * @var array $indiFamilyList           // individual XREF => array of families XREF
     */
    private array $indiFamilyList;

    /**
     * @var array $familyIndiList           // family XREF => array of individual XREF (husband and wife)
     */
    private array $familyIndiList;

    /**
     * @var array $familyCount              // family XREF => int
     */
    private array $familyCount;

    /**
     * constructor for this class
     *
     * @param Tree $tree
     * @param array $links keywords in database table like ['HUSB', 'WIFE']
     */
    public function __construct(Tree $tree, array $links)
    {
        // $rows is array of objects with two pointers:
        // l_from => family XREF and
        // l_to => individual XREF (spouse/partner)
        $rows = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->whereIn('l_type', $links)
            ->select(['l_from', 'l_to'])
            ->get();


        foreach ($rows as $row) {
            $this->indiFamilyList[$row->l_to][] = $row->l_from;
            $this->familyIndiList[$row->l_from][] = $row->l_to;
        }

        // ignore all standard families (chains have at least 3 partners)
        foreach ($this->familyIndiList as $family => $indiList) {
            if (count($indiList) > 1) {
                $sum = 0;                   // standard: HUSB and WIFE are existing in one family
            } else {
                $sum = 1;                   // only one spouse is existing: we count the not existing spouse, too
            }
            foreach ($indiList as $individual) {
                $sum += count($this->indiFamilyList[$individual]);
            }
            $this->familyCount[$family] = $sum;
        }
    }

    /**
     * return the size of all families
     *
     * @return array
     */
    public function getFamilyCount(): array
    {
        return $this->familyCount;
    }
}
