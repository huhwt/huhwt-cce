<?php

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;

// array functions
use function count;
use function array_keys;

class CompleteGED
{
    /**
     * @var array $graphI
     */
    private array $graphI;
    /**
     * @var array $graphF
     */
    private array $graphF;

    /**
     * constructor for this class
     *
     * @param Tree $tree
     */
    public function __construct(Tree $tree)
    {
        $this->graphI = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->pluck('i_id')
            ->mapWithKeys(static function (string $xref): array {
                return [$xref => []];
            })
            ->all();

        $this->graphF = DB::table('families')
            ->where('f_file', '=', $tree->id())
            ->pluck('f_id')
            ->mapWithKeys(static function (string $xref): array {
                return [$xref => []];
            })
            ->all();

    }

    /**
     * return the keys of graphI, these are XREFs
     *
     * @return array
     */
    public function getXrefsI(): array
    {
        return array_keys($this->graphI);
    }

    /**
     * return the keys of graphF, these are XREFs
     *
     * @return array
     */
    public function getXrefsF(): array
    {
        return array_keys($this->graphF);
    }
}
