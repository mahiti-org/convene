<?php

namespace App\Services\Beneficiary;

use App\Models\Beneficiary;
use App\Models\BeneficiaryDuplicateFlag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Exact government-ID match blocks (DB unique index); other matches only flag for review.
 */
class BeneficiaryDedupService
{
    private const NAME_SIMILARITY_THRESHOLD_PERCENT = 70.0;

    /**
     * Org-wide exact-ID lookup that bypasses location scope; use only to block, never to expose the
     * record.
     */
    public function findExactDuplicate(?string $governmentIdType, ?string $governmentIdValue): ?Beneficiary
    {
        if (empty($governmentIdType) || empty($governmentIdValue)) {
            return null;
        }

        return Beneficiary::query()
            ->withoutLocationScope()
            ->where('government_id_type', $governmentIdType)
            ->where('government_id_value', $governmentIdValue)
            ->first();
    }

    /**
     * Heuristic match: same node, similar name, same DOB year if known. Never blocks.
     *
     * @return Collection<int, Beneficiary>
     */
    public function findPotentialDuplicates(
        string $name,
        ?string $dob,
        int $geographyNodeId,
        ?int $excludeBeneficiaryId = null,
    ): Collection {
        $query = Beneficiary::query()
            ->withoutLocationScope()
            ->where('geography_node_id', $geographyNodeId);

        if ($excludeBeneficiaryId !== null) {
            $query->where('id', '!=', $excludeBeneficiaryId);
        }

        if ($dob !== null) {
            $query->whereYear('dob', Carbon::parse($dob)->year);
        }

        return $query->get()->filter(fn (Beneficiary $candidate) => $this->namesAreSimilar($name, $candidate->name))->values();
    }

    /** Creates (or reuses) an open duplicate-flag row for every potential match found. */
    public function flagPotentialDuplicates(Beneficiary $beneficiary): void
    {
        $matches = $this->findPotentialDuplicates(
            $beneficiary->name,
            $beneficiary->dob?->toDateString(),
            $beneficiary->geography_node_id,
            $beneficiary->id,
        );

        foreach ($matches as $match) {
            BeneficiaryDuplicateFlag::query()->firstOrCreate([
                'beneficiary_id' => $beneficiary->id,
                'matched_beneficiary_id' => $match->id,
                'match_type' => 'attribute_heuristic',
            ], [
                'status' => BeneficiaryDuplicateFlag::STATUS_OPEN,
            ]);
        }
    }

    private function namesAreSimilar(string $a, string $b): bool
    {
        $normalize = fn (string $value) => strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');

        $normalizedA = $normalize($a);
        $normalizedB = $normalize($b);

        if ($normalizedA === '' || $normalizedB === '') {
            return false;
        }

        similar_text($normalizedA, $normalizedB, $percent);

        return $percent >= self::NAME_SIMILARITY_THRESHOLD_PERCENT;
    }
}
