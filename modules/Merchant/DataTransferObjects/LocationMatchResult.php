<?php

declare(strict_types=1);

namespace Modules\Merchant\DataTransferObjects;

/**
 * The outcome of matching a printed address against one merchant's branches.
 * `all()` is the reviewer's picker — every branch, whether or not it looks like
 * the printed one. `candidates()` is the narrower set worth naming in an
 * artifact or a finding.
 */
final class LocationMatchResult
{
    /**
     * @param  list<array{id: int, hash_id: string, address: string, score: float|null}>  $all
     * @param  list<array{id: int, hash_id: string, address: string, score: float}>  $candidates
     * @param  array{id: int, hash_id: string, address: string, score: float}|null  $accepted
     */
    public function __construct(
        private readonly array $all,
        private readonly array $candidates,
        private readonly ?array $accepted,
        private readonly bool $ambiguous,
    ) {}

    /**
     * @return list<array{id: int, hash_id: string, address: string, score: float|null}>
     */
    public function all(): array
    {
        return $this->all;
    }

    /**
     * @return list<array{id: int, hash_id: string, address: string, score: float}>
     */
    public function candidates(): array
    {
        return $this->candidates;
    }

    /**
     * @return array{id: int, hash_id: string, address: string, score: float}|null
     */
    public function accepted(): ?array
    {
        return $this->accepted;
    }

    public function isAmbiguous(): bool
    {
        return $this->ambiguous;
    }
}
