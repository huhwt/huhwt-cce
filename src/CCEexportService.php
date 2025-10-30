<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Encodings\UTF16BE;
use Fisharebest\Webtrees\Encodings\UTF16LE;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Encodings\Windows1252;
use Fisharebest\Webtrees\Factories\AbstractGedcomRecordFactory;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomFilters\GedcomEncodingFilter;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Header;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use function addcslashes;
use function date;
use function explode;
use function fclose;
use function fopen;
use function fwrite;
use function is_string;
use function pathinfo;
use function preg_match_all;
use function rewind;
use function stream_filter_append;
use function stream_get_meta_data;
use function strlen;
use function strpos;
use function strtolower;
use function strtoupper;
use function tmpfile;

use const PATHINFO_EXTENSION;
use const PREG_SET_ORDER;
use const STREAM_FILTER_WRITE;

/**
 * Export data in GEDCOM format
 */
class CCEexportService
{
    private ResponseFactoryInterface $response_factory;

    private StreamFactoryInterface $stream_factory;

    public function __construct(ResponseFactoryInterface $response_factory, StreamFactoryInterface $stream_factory)
    {
        $this->response_factory = $response_factory;
        $this->stream_factory   = $stream_factory;
    }

    /**
     * @param Tree                                            $tree         Export data from this tree
     * @param string                                          $encoding     Convert from UTF-8 to other encoding
     * @param string                                          $line_endings CRLF or LF
     * @param string                                          $filename     Name of download file, without an extension
     * @param string                                          $format       One of: csv
    //  * @param array<string|string>|null                       $records
     * @param Collection<int,object>|null                     $records      Just export this list of xrefs
     */
    public function downloadResponse(
        Tree $tree,
        string $encoding,
        string $line_endings,
        string $filename,
        string $format,
        Collection|null $records
    // ): ResponseInterface | null {
    ): array | null {

        $isOK = false;

        if ($format === 'csv') {
            $resource = $this->exportXREF($tree, $encoding, $line_endings, $records);
            $encoded_records = stream_get_contents($resource);
            $isOK = true;
        }
        if ($isOK) {
            $stream   = $this->stream_factory->createStreamFromResource($resource);

            $_resp = $this->response_factory->createResponse()
                ->withBody($stream)
                ->withHeader('content-type', 'text/csv; charset=' . $encoding)
                ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . '.csv"');
            return [$_resp, $encoded_records];
            // return $this->response_factory->createResponse()
            //     ->withBody($stream)
            //     ->withHeader('content-type', 'text/csv; charset=' . $encoding)
            //     ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . '.csv"');

        }

        return null;
    }

    /**
     * Write GEDCOM data to a stream.
     *
     * @param Tree                                            $tree           Export data from this tree
     * @param string                                          $encoding       Convert from UTF-8 to other encoding
     * @param string                                          $line_endings   CRLF or LF
     * @param array<string>                                   $records        Export these XREFs
     *
     * @return resource
     */
    public function exportXREF(
        Tree $tree,
        string $encoding,
        string $line_endings = 'CRLF',
        Collection|null $records = null,
    ) {
        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        stream_filter_append($stream, GedcomEncodingFilter::class, STREAM_FILTER_WRITE, ['src_encoding' => UTF8::NAME, 'dst_encoding' => $encoding]);

        foreach ($records as $outLine) {
            $bytes_written = fwrite($stream, $outLine);

            if ($bytes_written !== strlen($outLine)) {
                throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
            }
        }

        if (rewind($stream) === false) {
            throw new RuntimeException('Cannot rewind temporary stream');
        }

        return $stream;
    }

}
