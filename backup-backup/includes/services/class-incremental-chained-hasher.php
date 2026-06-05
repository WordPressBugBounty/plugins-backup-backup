<?php

declare(strict_types=1);

namespace BMI\Plugin\Services;

/**
 * Streaming chained-hash accumulator.
 *
 * Accepts arbitrary data in incremental pieces via update() and maintains a
 * running chained-hash state.  The chain advances one link per $hashChunkSize
 * bytes processed.
 *
 * ── Chain formula per link ─────────────────────────────────────────────────
 *   Hₙ = hash(algo, Hₙ₋₁ ‖ hash(algo, piece))
 *
 * H₀ is an empty string, or a caller-supplied seed.
 *
 * ── Typical stream usage ───────────────────────────────────────────────────
 *   foreach (upload chunks) {
 *       $hasher->update($chunkBytes);     // feed data (hash chunk links auto-advance)
 *       $hash = $hasher->checkpoint();    // flush partial link, advance chain
 *       upload($chunkBytes, $hash);
 *   }
 *
 * ── Typical file usage (via FileHasher) ───────────────────────────────────
 *   while (!feof($handle)) { $hasher->update(fread($handle, $bufSize)); }
 *   $finalHash = $hasher->finalize();
 */
class IncrementalChainedHasher
{
    // ── Properties ─────────────────────────────────────────────────────────

    /** @var string PHP hash() algorithm name. */
    private string $algorithm;

    /** @var int Bytes per automatic chain link. */
    private int $hashChunkSize;

    /** @var string Running chain hash (committed links only). */
    private string $runningHash;

    /** @var string Bytes accumulated since the last committed link. */
    private string $pending = '';

    // ── Constructor ────────────────────────────────────────────────────────

    /**
     * @param string $algorithm     PHP hash() algorithm (default 'md5').
     * @param int    $hashChunkSize Bytes per chain link (default 1 MiB).
     * @param string $seedHash      Initial running hash H₀ (default '' = fresh chain).
     */
    public function __construct(
        string $algorithm     = 'md5',
        int    $hashChunkSize = 1048576,
        string $seedHash      = ''
    ) {
        $this->algorithm     = $algorithm;
        $this->hashChunkSize = max(1, $hashChunkSize);
        $this->runningHash   = $seedHash;
    }

    // ── Feed data ──────────────────────────────────────────────────────────

    /**
     * Accumulate data into the chain, auto-advancing for every complete link.
     *
     * After this call the pending buffer is guaranteed to be < $hashChunkSize.
     *
     * @param string $data Raw bytes to accumulate.
     */
    public function update(string $data): void
    {
        $this->pending .= $data;

        while (strlen($this->pending) >= $this->hashChunkSize) {
            $link          = substr($this->pending, 0, $this->hashChunkSize);
            $this->pending = substr($this->pending, $this->hashChunkSize);
            $this->advanceChain($link);
        }
    }

    // ── Commit points ──────────────────────────────────────────────────────

    /**
     * Flush any pending bytes as a chain link and return the updated running hash.
     *
     * Use this at logical boundaries (e.g. end of an upload chunk) to ensure
     * the running hash reflects ALL data fed so far, not just complete links.
     * After this call the pending buffer is empty and the chain has advanced.
     *
     * @return string Running chain hash after flushing.
     */
    public function checkpoint(): string
    {
        if ($this->pending !== '') {
            $this->advanceChain($this->pending);
            $this->pending = '';
        }

        return $this->runningHash;
    }

    /**
     * Alias for checkpoint(). Signals end-of-stream semantically.
     * The instance can be reused after calling reset().
     *
     * @return string Final running chain hash.
     */
    public function finalize(): string
    {
        return $this->checkpoint();
    }

    // ── State management ───────────────────────────────────────────────────

    /**
     * Restore both the running hash and the pending buffer to a previously
     * saved state.
     *
     * Pass the values returned by getRunningHash() and getPending() at the
     * save point.  The pending buffer must contain raw bytes — base64-decode
     * before passing if it was encoded for storage.
     *
     * @param string $runningHash Hex-encoded chain hash to restore.
     * @param string $pending     Raw pending bytes to restore (default '' = empty).
     */
    public function setState(string $runningHash, string $pending = ''): void
    {
        $this->runningHash = $runningHash;
        $this->pending     = $pending;
    }

    /**
     * Reset to a fresh (or seeded) state.
     *
     * @param string $seedHash New H₀ seed (default '' = fresh chain).
     */
    public function reset(string $seedHash = ''): void
    {
        $this->runningHash = $seedHash;
        $this->pending     = '';
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /**
     * Current running chain hash (committed links only).
     * Does NOT include any pending buffer.
     * Call checkpoint() first if you need all fed data reflected.
     *
     * @return string Hex-encoded digest.
     */
    public function getRunningHash(): string
    {
        return $this->runningHash;
    }

    /**
     * Raw bytes accumulated since the last committed link.
     * Always < $hashChunkSize in length.
     * Persist alongside getRunningHash() to enable full state resumption.
     *
     * @return string Raw (binary) pending bytes.
     */
    public function getPending(): string
    {
        return $this->pending;
    }

    /** @return string PHP hash() algorithm name. */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /** @return int Bytes per chain link. */
    public function getHashChunkSize(): int
    {
        return $this->hashChunkSize;
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Advance the chain by one link: Hₙ = hash(algo, Hₙ₋₁ ‖ hash(algo, piece)).
     *
     * @param string $piece Raw bytes for this link.
     */
    private function advanceChain(string $piece): void
    {
        $pieceHash         = hash($this->algorithm, $piece);
        $this->runningHash = hash($this->algorithm, $this->runningHash . $pieceHash);
    }
}
