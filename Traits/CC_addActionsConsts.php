<?php

/**
 * webtrees - clippings cart enhanced
 *
 * Copyright (C) 2025 huhwt. All rights reserved.
 * Copyright (C) 2021 Hermann Hartenthaler. All rights reserved.
 * Copyright (C) 2021 Richard Cissée. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2021 webtrees development team.
 *
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits;

use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;

/**
 * Trait CC_addActionsConsts - bundling the add-Actions consts of origin ClippingsCart
 */
trait CC_addActionsConsts
{
    private const ADD_RECORD_ONLY        = 'add only this record';
    private const ADD_CHILDREN           = 'add children';
    private const ADD_DESCENDANTS        = 'add descendants';
    private const ADD_PARENT_FAMILIES    = 'add parents';
    private const ADD_SPOUSE_FAMILIES    = 'add spouses';
    private const ADD_ANCESTORS          = 'add ancestors';
    private const ADD_ANCESTOR_FAMILIES  = 'add families';
    private const ADD_LINKED_INDIVIDUALS = 'add linked individuals';
    private const TYPES_OF_RECORDS = [
        'Individual' => Individual::class,
        'Family'     => Family::class,
        'Media'      => Media::class,
        'Location'   => Location::class,
        'Note'       => Note::class,
        'Repository' => Repository::class,
        'Source'     => Source::class,
        'Submitter'  => Submitter::class,
    ];
}
