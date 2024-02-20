<?php

/**
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2023 huhwt. All rights reserved.
 * Copyright (C) 2021 Hermann Hartenthaler. All rights reserved.
 * Copyright (C) 2021 Richard Cissée. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2021 webtrees development team.
 *
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

/**
 * Trait CCEaddActions - bundling all actions regarding connected vizualtion actions
 */
trait CCEvizActions
{
    private const FILENAME_DOWNL = 'wtcce';
    private const FILENAME_VIZ = 'wt2VIZ.ged';

    private string $VIZ_DSname;

    /** @var string */
    private string $exportFilenameDOWNL;

    /** @var string */
    private string $exportFilenameVIZ;

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function getVIZfname(): string
    {
        return $this->exportFilenameVIZ;
    }

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function putVIZfname(String $_fname): string
    {
        $this->exportFilenameVIZ = $_fname;
        Session::put('FILENAME_VIZ', $this->exportFilenameVIZ);          // EW.H mod ... save it to Session
        return $this->exportFilenameVIZ;
    }

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function getVIZdname(): string
    {
        return $this->VIZ_DSname;
    }

    /**
     * The name of Output to VIZ-Extension
     *
     * @return string
     */
    private function putVIZdname(String $_dname): string
    {
        $this->VIZ_DSname = $_dname;
        Session::put('VIZ_DSname', $this->VIZ_DSname);          // EW.H mod ... save it to Session
        return $this->VIZ_DSname;
    }

    /**
     * Load all notes as crossreference structure
     * 
     * @param   Tree    $tree
     * @return  array                                   // array of all tags regarding informations
     */
    public function getNotes_All(Tree $tree): array
    {
        $Nxrefs             = [];
        $txtNotes           = [];

        $notes              = $this->get_AllNotes($tree)->toArray();        // get all notes

        foreach ($notes as $idx => $note) {                                 // store crossreferences for notes and texts
            $_nxref         = $note->xref();
            $_tagTxt        = $note->getNote();
            $Nxrefs[$_nxref]      = $_tagTxt;
            $txtNotes[$_tagTxt]   = $_nxref;
        }

        $ret = [];
        $ret['Nxrefs']      = $Nxrefs;
        $ret['txtNotes']    = $txtNotes;

        return $ret;
    }

}