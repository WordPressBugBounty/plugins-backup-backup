<?php

declare(strict_types=1);

namespace BMI\Plugin\Services;

use RuntimeException;

require_once __DIR__ . '/class-incremental-chained-hasher.php';

/**
 * One-shot file-hashing utility.
 *
 * Computes either a standard (v1) or chained (v2) hash of an entire file.
 *
 * ── Modes ─────────────────────────────────────────────────────────────────
 *   STANDARD (v1) — equivalent to PHP's hash_file(); one digest over the
 *                   whole file.
 *
 *   CHAINED  (v2) — splits the file into $chunkSize-byte links and builds a
 *                   rolling chained digest via IncrementalChainedHasher.
 *                   The chain formula is:
 *                     Hₙ = hash(algo, Hₙ₋₁ ‖ hash(algo, piece))
 *
 * ── Usage ─────────────────────────────────────────────────────────────────
 *   // Standard hash (v1)
 *   $hash = FileHasher::compute('/path/to/file.zip');
 *
 *   // Chained hash (v2) with CHAINED_HASH_CHUNK_SIZE MiB links and md5 algorithm
 *   $hash = FileHasher::compute('/path/to/file.zip', FileHasher::CHAINED, CHAINED_HASH_CHUNK_SIZE, 'md5');
 */
class FileHasher
{
    // ── Mode constants ─────────────────────────────────────────────────────

    /** Standard hash: hash_file() equivalent (v1). */
    public const STANDARD = 'standard';

    /** Chained hash: linked rolling digest over per-chunk hashes (v2). */
    public const CHAINED = 'chained';

    /** Internal read-buffer size when streaming the file (64 KiB). */
    private const IO_BUFFER = 65536;

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Compute the hash of a file.
     *
     * @param string   $filePath  Absolute path to the file.
     * @param string   $mode      FileHasher::STANDARD or FileHasher::CHAINED.
     * @param string   $algorithm PHP hash() algorithm (default 'md5').
     * @param int|null $chunkSize Chain-link size in bytes for CHAINED mode.
     *                            Ignored in STANDARD mode.
     *                            Defaults to 1 MiB when null.
     * @return string Hex-encoded hash.
     * @throws RuntimeException If the file is not readable or the mode is unrecognised.
     */
    public static function compute(
        string $filePath,
        string $mode      = self::STANDARD,
        int   $chunkSize = CHAINED_HASH_CHUNK_SIZE,
        string $algorithm = 'md5'
    ): string {
        if (!is_readable($filePath)) {
            throw new RuntimeException('File is not readable: ' . $filePath);
        }

        if ($mode === self::CHAINED) {
            return self::computeChained($filePath, $algorithm, $chunkSize);
        }

        if ($mode === self::STANDARD) {
            return self::computeStandard($filePath, $algorithm);
        }

        throw new RuntimeException('Unknown hash mode: ' . $mode);
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Standard hash: stream the file and produce a single digest.
     */
    private static function computeStandard(string $filePath, string $algorithm): string
    {
        $hash = hash_file($algorithm, $filePath);
        if ($hash === false) {
            throw new RuntimeException('hash_file() failed for: ' . $filePath);
        }

        return $hash;
    }

    /**
     * Chained hash: stream the file through IncrementalChainedHasher in
     * IO_BUFFER-sized reads; chain links are $chunkSize bytes.
     */
    private static function computeChained(string $filePath, string $algorithm, int $chunkSize): string
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cannot open file for hashing: ' . $filePath);
        }

        $hasher = new IncrementalChainedHasher($algorithm, $chunkSize);

        while (!feof($handle)) {
            $piece = fread($handle, self::IO_BUFFER);
            if ($piece === false || $piece === '') {
                break;
            }
            $hasher->update($piece);
        }

        fclose($handle);

        return $hasher->finalize();
    }

    // ── Prevent instantiation ──────────────────────────────────────────────

    private function __construct() {}
}
