<?php

/**
 * Tantou (担当) — Human Capacity OS
 *
 * @author    Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @copyright 2026 Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @license   AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 *
 * @link      https://github.com/ktsourdinis/tantou-oss
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 */

namespace App\Support;

use InvalidArgumentException;

/**
 * Zero-dependency ISO/IEC 18004 QR Code SVG generator (Byte mode, ECC Level M, Versions 1–15).
 */
class QrCodeSvg
{
    /**
     * ECC Level M block parameters per version:
     * version => [totalDataCodewords, ecCodewordsPerBlock, blocksG1, dataG1, blocksG2, dataG2].
     *
     * @var array<int, array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int}>
     */
    private const array VERSION_SPEC_M = [
        1 => [16, 10, 1, 16, 0, 0],
        2 => [28, 16, 1, 28, 0, 0],
        3 => [44, 26, 1, 44, 0, 0],
        4 => [64, 18, 2, 32, 0, 0],
        5 => [86, 24, 2, 43, 0, 0],
        6 => [108, 16, 4, 27, 0, 0],
        7 => [124, 18, 4, 31, 0, 0],
        8 => [154, 22, 2, 38, 2, 39],
        9 => [182, 22, 3, 36, 2, 37],
        10 => [216, 26, 4, 43, 1, 44],
        11 => [254, 30, 1, 50, 4, 51],
        12 => [290, 22, 6, 36, 2, 37],
        13 => [334, 22, 8, 37, 1, 38],
        14 => [365, 24, 4, 40, 5, 41],
        15 => [415, 24, 5, 41, 5, 42],
    ];

    /**
     * Alignment pattern center coordinates per version (ISO/IEC 18004 Annex E).
     *
     * @var array<int, list<int>>
     */
    private const array ALIGNMENT_CENTERS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
        11 => [6, 30, 54],
        12 => [6, 32, 58],
        13 => [6, 34, 62],
        14 => [6, 26, 46, 66],
        15 => [6, 26, 48, 70],
    ];

    /**
     * Generate a crisp, self-contained SVG QR code string for the given payload.
     */
    public static function generate(string $data, int $size = 260, int $quietZone = 4, ?string $id = null): string
    {
        $matrix = self::encodeMatrix($data);
        $modules = count($matrix);
        $fullSize = $modules + ($quietZone * 2);

        $pathParts = [];
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix[$y][$x] === 1) {
                    $px = $x + $quietZone;
                    $py = $y + $quietZone;
                    $pathParts[] = "M{$px},{$py}h1v1h-1z";
                }
            }
        }

        $pathData = implode('', $pathParts);
        $idAttr = $id !== null && $id !== ''
            ? ' id="'.htmlspecialchars($id, ENT_QUOTES, 'UTF-8').'"'
            : '';

        return sprintf(
            '<svg%s xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges" role="img" aria-label="QR Code">'
            .'<rect width="100%%" height="100%%" fill="#ffffff"/>'
            .'<path fill="#09090b" d="%s"/>'
            .'</svg>',
            $idAttr,
            $fullSize,
            $fullSize,
            $size,
            $size,
            $pathData
        );
    }

    /**
     * Encode the payload into a 2D QR module matrix (0 = light, 1 = dark).
     *
     * @return list<list<int>>
     */
    public static function encodeMatrix(string $data): array
    {
        $bytes = array_values(unpack('C*', $data) ?: []);
        $byteCount = count($bytes);
        $version = self::selectVersion($byteCount);
        $spec = self::VERSION_SPEC_M[$version];

        $dataCodewords = self::buildDataCodewords($bytes, $version, $spec[0]);
        $finalCodewords = self::buildFinalCodewords($dataCodewords, $spec);

        $size = 17 + (4 * $version);
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::placeFinderPatterns($matrix, $reserved, $size);
        self::placeTimingPatterns($matrix, $reserved, $size);
        self::placeAlignmentPatterns($matrix, $reserved, $version);
        self::reserveFormatAndVersionAreas($matrix, $reserved, $version, $size);
        self::placeDataBits($matrix, $reserved, $finalCodewords, $size);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = $matrix;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $reserved, $mask, $size);
            self::writeFormatInfo($candidate, $mask, $size);

            if ($version >= 7) {
                self::writeVersionInfo($candidate, $version, $size);
            }

            $penalty = self::calculatePenalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $candidate;
            }
        }

        unset($bestMask);

        return $bestMatrix;
    }

    /**
     * Select the smallest QR version (1–15) capable of holding the byte payload at ECC Level M.
     */
    private static function selectVersion(int $byteCount): int
    {
        foreach (self::VERSION_SPEC_M as $version => $spec) {
            $countBits = $version <= 9 ? 8 : 16;
            $capacityBits = $spec[0] * 8;
            $requiredBits = 4 + $countBits + ($byteCount * 8);

            if ($requiredBits <= $capacityBits) {
                return $version;
            }
        }

        throw new InvalidArgumentException('QR payload exceeds maximum supported length (Version 15, ECC Level M).');
    }

    /**
     * Build padded data codewords in Byte mode.
     *
     * @param  list<int>  $bytes
     * @return list<int>
     */
    private static function buildDataCodewords(array $bytes, int $version, int $totalDataCodewords): array
    {
        $bits = [];

        // Mode indicator: Byte mode (0100)
        self::appendBits($bits, 0b0100, 4);

        // Character count indicator
        $countBits = $version <= 9 ? 8 : 16;
        self::appendBits($bits, count($bytes), $countBits);

        // Data bytes
        foreach ($bytes as $byte) {
            self::appendBits($bits, $byte, 8);
        }

        $capacityBits = $totalDataCodewords * 8;

        // Terminator (up to 4 zero bits)
        $terminatorLen = min(4, $capacityBits - count($bits));
        self::appendBits($bits, 0, $terminatorLen);

        // Pad to byte boundary
        while ((count($bits) % 8) !== 0) {
            $bits[] = 0;
        }

        // Convert bits to bytes
        $codewords = [];
        $bitCount = count($bits);
        for ($i = 0; $i < $bitCount; $i += 8) {
            $val = 0;
            for ($j = 0; $j < 8; $j++) {
                $val = ($val << 1) | $bits[$i + $j];
            }
            $codewords[] = $val;
        }

        // Pad codewords alternating 0xEC and 0x11
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($codewords) < $totalDataCodewords) {
            $codewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        return $codewords;
    }

    /**
     * Split data codewords into Reed-Solomon blocks, compute ECC, and interleave.
     *
     * @param  list<int>  $dataCodewords
     * @param  array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int}  $spec
     * @return list<int>
     */
    private static function buildFinalCodewords(array $dataCodewords, array $spec): array
    {
        [, $ecPerBlock, $blocksG1, $dataG1, $blocksG2, $dataG2] = $spec;

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        for ($i = 0; $i < $blocksG1; $i++) {
            $block = array_slice($dataCodewords, $offset, $dataG1);
            $offset += $dataG1;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::reedSolomonCompute($block, $ecPerBlock);
        }

        for ($i = 0; $i < $blocksG2; $i++) {
            $block = array_slice($dataCodewords, $offset, $dataG2);
            $offset += $dataG2;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::reedSolomonCompute($block, $ecPerBlock);
        }

        $result = [];
        $maxDataLen = max($dataG1, $dataG2);

        for ($col = 0; $col < $maxDataLen; $col++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$col])) {
                    $result[] = $block[$col];
                }
            }
        }

        for ($col = 0; $col < $ecPerBlock; $col++) {
            foreach ($ecBlocks as $ecBlock) {
                $result[] = $ecBlock[$col];
            }
        }

        return $result;
    }

    /**
     * Compute Reed-Solomon error correction codewords over GF(256) (primitive polynomial 0x11D).
     *
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function reedSolomonCompute(array $data, int $degree): array
    {
        $generator = self::reedSolomonGenerator($degree);
        $remainder = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($remainder);
            $remainder[] = 0;

            for ($i = 0; $i < $degree; $i++) {
                $remainder[$i] ^= self::gfMultiply($generator[$i], $factor);
            }
        }

        return $remainder;
    }

    /**
     * Build Reed-Solomon generator polynomial coefficients (excluding leading 1).
     *
     * @return list<int>
     */
    private static function reedSolomonGenerator(int $degree): array
    {
        $coeffs = array_fill(0, $degree, 0);
        $coeffs[$degree - 1] = 1;
        $root = 1;

        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $coeffs[$j] = self::gfMultiply($coeffs[$j], $root);
                if (($j + 1) < $degree) {
                    $coeffs[$j] ^= $coeffs[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }

        return $coeffs;
    }

    /**
     * Multiply two bytes in GF(256) modulo 0x11D.
     */
    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    /**
     * Place the 3 finder patterns and their 1-module white separators.
     *
     * @param  list<list<int>>  $matrix
     * @param  list<list<bool>>  $reserved
     */
    private static function placeFinderPatterns(array &$matrix, array &$reserved, int $size): void
    {
        $origins = [
            [0, 0],
            [0, $size - 7],
            [$size - 7, 0],
        ];

        foreach ($origins as [$top, $left]) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $r = $top + $dy;
                    $c = $left + $dx;

                    if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                        continue;
                    }

                    $isDark = (
                        $dy >= 0 && $dy <= 6 && $dx >= 0 && $dx <= 6
                        && (
                            $dy === 0 || $dy === 6 || $dx === 0 || $dx === 6
                            || ($dy >= 2 && $dy <= 4 && $dx >= 2 && $dx <= 4)
                        )
                    );

                    $matrix[$r][$c] = $isDark ? 1 : 0;
                    $reserved[$r][$c] = true;
                }
            }
        }
    }

    /**
     * Place horizontal and vertical timing patterns along row 6 and column 6.
     *
     * @param  list<list<int>>  $matrix
     * @param  list<list<bool>>  $reserved
     */
    private static function placeTimingPatterns(array &$matrix, array &$reserved, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;

            if (! $reserved[6][$i]) {
                $matrix[6][$i] = $bit;
                $reserved[6][$i] = true;
            }

            if (! $reserved[$i][6]) {
                $matrix[$i][6] = $bit;
                $reserved[$i][6] = true;
            }
        }
    }

    /**
     * Place 5x5 alignment patterns for versions >= 2.
     *
     * @param  list<list<int>>  $matrix
     * @param  list<list<bool>>  $reserved
     */
    private static function placeAlignmentPatterns(array &$matrix, array &$reserved, int $version): void
    {
        $centers = self::ALIGNMENT_CENTERS[$version];
        if (empty($centers)) {
            return;
        }

        foreach ($centers as $cy) {
            foreach ($centers as $cx) {
                if ($reserved[$cy][$cx]) {
                    continue;
                }

                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $dist = max(abs($dy), abs($dx));
                        $matrix[$cy + $dy][$cx + $dx] = ($dist === 2 || $dist === 0) ? 1 : 0;
                        $reserved[$cy + $dy][$cx + $dx] = true;
                    }
                }
            }
        }
    }

    /**
     * Reserve format information strips, dark module, and version info blocks (V >= 7).
     *
     * @param  list<list<int>>  $matrix
     * @param  list<list<bool>>  $reserved
     */
    private static function reserveFormatAndVersionAreas(array &$matrix, array &$reserved, int $version, int $size): void
    {
        for ($i = 0; $i <= 8; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        // Fixed dark module
        $matrix[$size - 8][8] = 1;
        $reserved[$size - 8][8] = true;

        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $reserved[$i][$size - 11 + $j] = true;
                    $reserved[$size - 11 + $j][$i] = true;
                }
            }
        }
    }

    /**
     * Place data and error-correction bits in the standard 2-column zigzag.
     *
     * @param  list<list<int>>  $matrix
     * @param  list<list<bool>>  $reserved
     * @param  list<int>  $codewords
     */
    private static function placeDataBits(array &$matrix, array $reserved, array $codewords, int $size): void
    {
        $totalBits = count($codewords) * 8;
        $bitIdx = 0;
        $upward = true;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }

            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? ($size - 1 - $step) : $step;

                for ($dx = 0; $dx < 2; $dx++) {
                    $col = $right - $dx;

                    if ($reserved[$row][$col]) {
                        continue;
                    }

                    $bit = 0;
                    if ($bitIdx < $totalBits) {
                        $byte = $codewords[$bitIdx >> 3];
                        $bit = ($byte >> (7 - ($bitIdx & 7))) & 1;
                        $bitIdx++;
                    }

                    $matrix[$row][$col] = $bit;
                }
            }

            $upward = ! $upward;
        }
    }

    /**
     * Apply one of the 8 ISO/IEC 18004 mask patterns to non-reserved modules.
     *
     * @param  list<list<int>>  $matrix
     * @param  list<list<bool>>  $reserved
     * @return list<list<int>>
     */
    private static function applyMask(array $matrix, array $reserved, int $mask, int $size): array
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($reserved[$r][$c]) {
                    continue;
                }

                $invert = match ($mask) {
                    0 => (($r + $c) % 2) === 0,
                    1 => ($r % 2) === 0,
                    2 => ($c % 3) === 0,
                    3 => (($r + $c) % 3) === 0,
                    4 => ((intdiv($r, 2) + intdiv($c, 3)) % 2) === 0,
                    5 => ((($r * $c) % 2) + (($r * $c) % 3)) === 0,
                    6 => (((($r * $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    default => false,
                };

                if ($invert) {
                    $matrix[$r][$c] ^= 1;
                }
            }
        }

        return $matrix;
    }

    /**
     * Write 15-bit BCH format information (ECC Level M = 00) into both format areas.
     *
     * @param  list<list<int>>  $matrix
     */
    private static function writeFormatInfo(array &$matrix, int $mask, int $size): void
    {
        // ECC Level M format indicator bits = 00
        $data = $mask & 0b111;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        // First copy (around top-left finder)
        for ($i = 0; $i <= 5; $i++) {
            $matrix[$i][8] = ($bits >> $i) & 1;
        }
        $matrix[7][8] = ($bits >> 6) & 1;
        $matrix[8][8] = ($bits >> 7) & 1;
        $matrix[8][7] = ($bits >> 8) & 1;
        for ($i = 9; $i < 15; $i++) {
            $matrix[8][14 - $i] = ($bits >> $i) & 1;
        }

        // Second copy (top-right and bottom-left)
        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$size - 1 - $i] = ($bits >> $i) & 1;
        }
        for ($i = 8; $i < 15; $i++) {
            $matrix[$size - 15 + $i][8] = ($bits >> $i) & 1;
        }
        $matrix[$size - 8][8] = 1;
    }

    /**
     * Write 18-bit BCH version information for versions >= 7.
     *
     * @param  list<list<int>>  $matrix
     */
    private static function writeVersionInfo(array &$matrix, int $version, int $size): void
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = ($bits >> $i) & 1;
            $a = $size - 11 + ($i % 3);
            $b = intdiv($i, 3);
            $matrix[$a][$b] = $bit;
            $matrix[$b][$a] = $bit;
        }
    }

    /**
     * Evaluate ISO/IEC 18004 mask penalty score (4 rules).
     *
     * @param  list<list<int>>  $matrix
     */
    private static function calculatePenalty(array $matrix, int $size): int
    {
        $penalty = 0;

        // Rule 1: 5+ consecutive same-color modules in rows & columns
        for ($r = 0; $r < $size; $r++) {
            $runColor = $matrix[$r][0];
            $runLen = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                } else {
                    if ($runLen >= 5) {
                        $penalty += 3 + ($runLen - 5);
                    }
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
            if ($runLen >= 5) {
                $penalty += 3 + ($runLen - 5);
            }
        }

        for ($c = 0; $c < $size; $c++) {
            $runColor = $matrix[0][$c];
            $runLen = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                } else {
                    if ($runLen >= 5) {
                        $penalty += 3 + ($runLen - 5);
                    }
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
            if ($runLen >= 5) {
                $penalty += 3 + ($runLen - 5);
            }
        }

        // Rule 2: 2x2 blocks of same color
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $color = $matrix[$r][$c];
                if ($color === $matrix[$r][$c + 1] && $color === $matrix[$r + 1][$c] && $color === $matrix[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: Finder-like patterns (10111010000 or 00001011101)
        $p1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $p2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                $rowSlice = array_slice($matrix[$r], $c, 11);
                if ($rowSlice === $p1 || $rowSlice === $p2) {
                    $penalty += 40;
                }

                $colSlice = [];
                for ($k = 0; $k < 11; $k++) {
                    $colSlice[] = $matrix[$c + $k][$r];
                }
                if ($colSlice === $p1 || $colSlice === $p2) {
                    $penalty += 40;
                }
            }
        }

        // Rule 4: Dark/light balance
        $dark = 0;
        foreach ($matrix as $row) {
            $dark += array_sum($row);
        }
        $total = $size * $size;
        $k = (int) floor(abs(($dark * 100 / $total) - 50) / 5);
        $penalty += $k * 10;

        return $penalty;
    }

    /**
     * Append $length bits of $value (MSB first) to the bit array.
     *
     * @param  list<int>  $bits
     */
    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }
}
