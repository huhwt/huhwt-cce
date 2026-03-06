<?php

/**
 * webtrees - clippings tags enhanced
 *
 * Copyright (C) 2024 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2023 webtrees development team.
 *
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Trait CCEdatabaseActions - bundling all actions regarding DB::table
 */

trait CCEdatabaseActions
{
    /**
     * Find all notes, casually narrowed to active Tags.
     *
     * @param Tree $tree
     *
     * @return Collection<int,Note>
     */
    public function get_AllNotes(Tree $tree): Collection
    {
        $query = DB::table('other')
            ->where('o_file', '=', $tree->id())
            ->where('o_type', '=', Note::RECORD_TYPE)
            ->distinct()
            ->select(['other.*'])
            ->get()
            ->map(Registry::noteFactory()->mapper($tree))
            ->filter(GedcomRecord::accessFilter());

        return $query;
    }

    public function get_CCElistINDIdb(Tree $tree, array $xrefsI, string $galpha = '' ): Collection
    {
        $query = DB::table('individuals')
            ->join('name', static function (JoinClause $join): void {
                $join
                    ->on('n_id', '=', 'i_id')
                    ->on('n_file', '=', 'i_file');
            })
            ->where('i_file', '=', $tree->id())
            ->select(['i_id AS xref', 'i_gedcom AS gedcom', 'n_givn', 'n_surn']);

        $query->whereIn('i_id', $xrefsI);

        $individuals = new Collection();

        foreach ($query->get() as $row) {
            $individual = Registry::individualFactory()->make($row->xref, $tree, $row->gedcom);
            assert($individual instanceof Individual);

            // The name from the database may be private - check the filtered list...
            foreach ($individual->getAllNames() as $n => $name) {
                if ($name['givn'] === $row->n_givn && $name['surn'] === $row->n_surn) {
                    if ($galpha === '' || I18N::language()->initialLetter(I18N::language()->normalize(I18N::strtoupper($row->n_givn))) === $galpha) {
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

    /**
     * Find the notes, narrowed to active Tags.
     * 
     * @param Tree $tree
     *
     * @return Collection<int,Note>
     */
    public function get_AllNotes_aTags(Tree $tree, array $xrefsN): Collection
    {

        $query = $this->get_AllNotes($tree);

        $query->whereIn('o_id', $xrefsN);

        return $query;
    }
    /**
     * Find all records linked to active Tags.
     *
     * @param Tree $tree
     *
     * @return Collection<int,array>
     */

     public function get_linkedXREFs(Tree $tree, array $xrefsN): Collection
     {
        $query = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->where('l_type', '=', Note::RECORD_TYPE)
            ->distinct()
            ->select(['link.l_from', 'link.l_to'])
            ->get()
            ->whereIn('l_to', $xrefsN);

        return $query;
     }

    /**
     * Get a module setting. Return a default if the setting is not set.
     *
     * @param string $M_name                // the module's name
     * @param string $setting_name
     * @param string $default
     *
     * @return string
     */
    public function getPreferenceNamed(string $M_name, string $setting_name, string $default = ''): string
    {
        return DB::table('module_setting')
            ->where('module_name', '=', $M_name)
            ->where('setting_name', '=', $setting_name)
            ->value('setting_value') ?? $default;
    }

    /**
     * Set a module setting.
     *
     * Since module settings are NOT NULL, setting a value to NULL will cause
     * it to be deleted.
     *
     * @param string $M_name                // the module's name
     * @param string $setting_name
     * @param string $setting_value
     *
     * @return void
     */
    public function setPreferenceNamed(string $M_name, string $setting_name, string $setting_value): void
    {
        DB::table('module_setting')->updateOrInsert([
            'module_name'  => $M_name,
            'setting_name' => $setting_name,
        ], [
            'setting_value' => $setting_value,
        ]);
    }

    /**
     * Get a count of individuals with each initial letter
     * 
     *      derived from AbstractIndividualListModule->givenNameInitials()
     *
     * @param Collection    $individuals
     *
     * @return array<int>
     */
    protected function surnameInitials(Collection $individuals): array
    {
        $initials = [];

        // Ensure our own language comes before others.
        foreach (I18N::language()->alphabet() as $initial) {
            $initials[$initial] = 0;
        }

        foreach ($individuals as $individual) {
            $initial = I18N::language()->initialLetter(I18N::language()->normalize(I18N::strtoupper($individual->sortname())));

            $initials[$initial] ??= 0;
            $initials[$initial] += 1;
        }

        // Move specials to the end
        $count_none = $initials[''] ?? 0;

        if ($count_none > 0) {
            unset($initials['']);
            $initials[','] = $count_none;
        }

        $count_unknown = $initials['@'] ?? 0;

        if ($count_unknown > 0) {
            unset($initials['@']);
            $initials['@'] = $count_unknown;
        }

        return $initials;
    }

    /**
     * copied from LinkedRecordService
     * 
     * siehe auch UnconnectedPage
     * 
     * @return Collection<int,Individual>
     */
    public function linkedIndividuals(GedcomRecord $record, string|null $link_type = null): Collection
    {
        $query = DB::table('individuals')
            ->join('link', static function (JoinClause $join): void {
                $join
                    ->on('l_file', '=', 'i_file')
                    ->on('l_from', '=', 'i_id');
            })
            ->where('i_file', '=', $record->tree()->id())
            ->where('l_to', '=', $record->xref());

        if ($link_type !== null) {
            $query->where('l_type', '=', $link_type);
        }

        return $query
            ->distinct()
            ->select(['individuals.*'])
            ->get()
            ->map(Registry::individualFactory()->mapper($record->tree()))
            ->filter(GedcomRecord::accessFilter());
    }


}
