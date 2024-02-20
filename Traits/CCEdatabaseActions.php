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
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
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

}
