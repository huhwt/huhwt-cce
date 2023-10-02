<?php

/*
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2023 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2021 webtrees development team.
 *
 * This module is a stub for the operations in Fisharebest\Webtrees\Module\IndividualListModule.
 * It's performing the same operations but results are returned in a CCE compliant way.
 * 
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Module\IndividualListModule;

use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

use HuHwt\WebtreesMods\ClippingsCartEnhanced\ClippingsCartEnhanced;

// array functions
use function in_array;

class ListProcessor
{
    /**
     * @var array $cart
     */
    private array $cart;

    /**
     * @var ClippingsCartEnhanced $master;
     */
    private ClippingsCartEnhanced $master;

    /**
     * ListProcessor constructor.
     *
     * Session-Variable 'cart' is checked and initialized if necessary
     * @param ClippingsCartEnhanced $master
     */
    public function __construct($master) {
        $this->master = $master;

        $cart = Session::get('cart');
        if ( !is_array($cart) ) {
            $cart = [];
            Session::put('cart', $cart);
        }
        $this->cart = $cart;
    }

    /**
     * Fetch a list of individuals with specified names
     * To search for unknown names, use $surn="@N.N.", $salpha="@" or $galpha="@"
     * To search for names with no surnames, use $salpha=","
     *
     * @param Tree            $tree
     * @param string          $surname  if set, only fetch people with this n_surn
     * @param array<string>   $surnames if set, only fetch people with this n_surname
     * @param string          $galpha   if set, only fetch given names starting with this letter
     * @param bool            $marnm    if set, include married names
     * @param bool            $fams     if set, only fetch individuals with FAMS records
     *
     * @return Collection<int,Individual>
     */
    public function individuals(Tree $tree, string $surname, array $surnames, string $galpha, bool $marnm, bool $fams) :  Collection
    {
        $query = DB::table('individuals')
            ->join('name', static function (JoinClause $join): void {
                $join
                    ->on('n_id', '=', 'i_id')
                    ->on('n_file', '=', 'i_file');
            })
            ->where('i_file', '=', $tree->id())
            ->select(['i_id AS xref', 'i_gedcom AS gedcom', 'n_givn', 'n_surn']);

        $this->whereFamily($fams, $query);
        $this->whereMarriedName($marnm, $query);

        $query
            ->orderBy(new Expression("CASE n_surn WHEN '" . Individual::NOMEN_NESCIO . "' THEN 1 ELSE 0 END"))
            ->orderBy('n_surn')
            ->orderBy(new Expression("CASE n_givn WHEN '" . Individual::NOMEN_NESCIO . "' THEN 1 ELSE 0 END"))
            ->orderBy('n_givn');

        $individuals = new Collection();

        $surn_chunks = array_chunk($surnames, 50, true);

        if ($surnames === []) {
            // SURN, with no surname
            $query->where('n_surn', '=', $surname);
        } else {
            // $query->whereIn($this->binaryColumn('n_surname'), $surnames);        // EW.H - MOD ...
            $query->whereIn('n_surn', $surnames);
        }
        $query_rows = $query->get();

        foreach ($query->get() as $row) {
            $individual = Registry::individualFactory()->make($row->xref, $tree, $row->gedcom);
            assert($individual instanceof Individual);

            // The name from the database may be private - check the filtered list...
            foreach ($individual->getAllNames() as $n => $name) {
                if ($name['givn'] === $row->n_givn && $name['surn'] === $row->n_surn) {
                    if ($galpha === '' || I18N::strtoupper(I18N::language()->initialLetter($row->n_surn)) === $galpha) {
                        $individual->setPrimaryName($n);
                        // We need to clone $individual, as we may have multiple references to the
                        // same individual in this list, and the "primary name" would otherwise
                        // be shared amongst all of them.
                        $individuals->push(clone $individual);
                        break;
                    }
                }
            }
        }

        return $individuals;
    }

    protected function toCartIndividual(Individual $individual, $trKey) 
    {
        $xref = $individual->xref();
        if (($this->cart[$trKey][$xref] ?? false) === false) {
            $this->cart[$trKey][$xref] = true;
            Session::put('cart', $this->cart);
            $this->master->addLocationLinksToCart($individual);
            $this->master->addMediaLinksToCart($individual);
            $this->master->addNoteLinksToCart($individual);
            $this->master->addSourceLinksToCart($individual);
        }
}

    /**
     * Fetch a list of families with specified names
     * To search for unknown names, use $surn="@N.N.", $salpha="@" or $galpha="@"
     * To search for names with no surnames, use $salpha=","
     *
     * @param Tree          $tree
     * @param string        $surname  if set, only fetch people with this n_surn
     * @param array<string> $surnames if set, only fetch people with this n_surname
     * @param string        $galpha   if set, only fetch given names starting with this letter
     * @param bool          $marnm    if set, include married names
     *
     * @param bool          $withp    if set, include parents
     *
    //  * @return Collection<int,Family>
     */
    public function clip_families(Tree $tree, string $surname, array $surnames, string $galpha, bool $marnm, bool $withp) // : Collection
    {
        $families = new Collection();
        $trKey = $tree->name();

        foreach ($this->individuals($tree, $surname, $surnames, $galpha, $marnm, true) as $indi) {
            // put xref of individual to cart
            $this->toCartIndividual($indi, $trKey);
            $this->toCartParents($indi, $trKey);
            $indi_xref = $indi->xref();
            // put xref of family to cart
            foreach ($indi->spouseFamilies() as $family) {
                $families->push($family);

                $indi_wife = $family->wife();
                if ($indi_wife && $indi_wife->xref() != $indi_xref) {
                    $this->toCartIndividual($indi_wife, $trKey);
                    if ($withp) {
                        $this->toCartParents($indi_wife, $trKey);
                    }
                }
                $indi_husb = $family->husband();
                if ($indi_husb && $indi_husb->xref() != $indi_xref) {
                    $this->toCartIndividual($indi_husb, $trKey);
                    if ($withp) {
                        $this->toCartParents($indi_husb, $trKey);
                    }
                }
                $this->toCartFamily($family, $trKey);
            }
        }
        return true;
    }
    protected function toCartParents(Individual $individual, string $trKey) {
        foreach ($individual->childFamilies() as $pfamily) {
            foreach ($pfamily->spouses() as $spouse) {
                $this->toCartIndividual($spouse, $trKey);
            }
            $this->toCartFamily($pfamily, $trKey);
        }
}
    protected function toCartFamily(Family $family, string $trKey)
    {
        $xref = $family->xref();
        if (($this->cart[$trKey][$xref] ?? false) === false) {
            $this->cart[$trKey][$xref] = true;
            Session::put('cart', $this->cart);
            $this->master->addLocationLinksToCart($family);
            $this->master->addMediaLinksToCart($family);
            $this->master->addNoteLinksToCart($family);
            $this->master->addSourceLinksToCart($family);
            $this->master->addSubmitterLinksToCart($family);
        }
}

    /**
     * This module assumes the database will use binary collation on the name columns.
     * Until we convert MySQL databases to use utf8_bin, we need to do this at run-time.
     *
     * @param string      $column
     * @param string|null $alias
     *
     * @return Expression
     */
    private function binaryColumn(string $column, string $alias = null): Expression
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $sql = 'CAST(' . $column . ' AS binary)';
        } else {
            $sql = $column;
        }

        if ($alias !== null) {
            $sql .= ' AS ' . $alias;
        }

        return new Expression($sql);
    }

    /**
     * Restrict a query to individuals that are a spouse in a family record.
     *
     * @param bool    $fams
     * @param Builder $query
     */
    protected function whereFamily(bool $fams, Builder $query): void
    {
        if ($fams) {
            $query->join('link', static function (JoinClause $join): void {
                $join
                    ->on('l_from', '=', 'n_id')
                    ->on('l_file', '=', 'n_file')
                    ->where('l_type', '=', 'FAMS');
            });
        }
    }

    /**
     * Restrict a query to include/exclude married names.
     *
     * @param bool    $marnm
     * @param Builder $query
     */
    protected function whereMarriedName(bool $marnm, Builder $query): void
    {
        if (!$marnm) {
            $query->where('n_type', '<>', '_MARNM');
        }
    }

}