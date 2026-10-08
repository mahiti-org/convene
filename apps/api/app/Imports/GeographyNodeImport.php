<?php

namespace App\Imports;

use App\Models\GeographyNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * All-or-nothing Excel import. Columns: level, label, code, parent_code (blank for root).
 * Parents must come first.
 */
class GeographyNodeImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<int, string>> row number => list of error messages */
    private array $rowErrors = [];

    private int $createdCount = 0;

    public function collection(Collection $rows): void
    {
        $seenCodesInBatch = [];
        $validatedRows = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for zero-index, +1 for the header row

            $data = [
                'level' => $row['level'] ?? null,
                'label' => $row['label'] ?? null,
                'code' => $row['code'] ?? null,
                'parent_code' => $row['parent_code'] ?? null,
            ];

            $validator = Validator::make($data, [
                'level' => ['required', 'integer', 'min:0', 'max:255'],
                'label' => ['required', 'string', 'max:255'],
                'code' => ['nullable', 'string', 'max:255'],
                'parent_code' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $this->rowErrors[$rowNumber] = $validator->errors()->all();

                continue;
            }

            $parentCode = $data['parent_code'];
            if ($parentCode !== null && $parentCode !== '') {
                $parentExists = isset($seenCodesInBatch[$parentCode])
                    || GeographyNode::query()->where('code', $parentCode)->exists();

                if (! $parentExists) {
                    $this->rowErrors[$rowNumber] = [
                        "parent_code '{$parentCode}' does not match any existing node or an earlier row in this file.",
                    ];

                    continue;
                }
            }

            if (! empty($data['code'])) {
                $seenCodesInBatch[$data['code']] = true;
            }

            $validatedRows[] = $data;
        }

        if (! empty($this->rowErrors)) {
            return;
        }

        DB::transaction(function () use ($validatedRows) {
            foreach ($validatedRows as $data) {
                $parentId = null;
                if (! empty($data['parent_code'])) {
                    $parentId = GeographyNode::query()->where('code', $data['parent_code'])->value('id');
                }

                GeographyNode::create([
                    'parent_id' => $parentId,
                    'level' => $data['level'],
                    'label' => $data['label'],
                    'code' => $data['code'] ?: null,
                ]);

                $this->createdCount++;
            }
        });
    }

    /** @return array<int, array<int, string>> */
    public function errors(): array
    {
        return $this->rowErrors;
    }

    public function createdCount(): int
    {
        return $this->createdCount;
    }
}
