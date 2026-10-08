<?php

namespace App\Imports;

use App\Models\MasterDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * All-or-nothing Excel import. Columns: category, code, label, sort_order (optional, default 0).
 */
class MasterDefinitionImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<int, string>> */
    private array $rowErrors = [];

    private int $createdCount = 0;

    public function collection(Collection $rows): void
    {
        $seenInBatch = []; // "category|code" => true, to catch in-file duplicates
        $validatedRows = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $data = [
                'category' => $row['category'] ?? null,
                'code' => $row['code'] ?? null,
                'label' => $row['label'] ?? null,
                'sort_order' => $row['sort_order'] ?? 0,
            ];

            $validator = Validator::make($data, [
                'category' => ['required', 'string', 'max:100'],
                'code' => ['required', 'string', 'max:100'],
                'label' => ['required', 'string', 'max:255'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                $this->rowErrors[$rowNumber] = $validator->errors()->all();

                continue;
            }

            $key = $data['category'].'|'.$data['code'];
            $alreadyExists = isset($seenInBatch[$key]) || MasterDefinition::query()
                ->where('category', $data['category'])
                ->where('code', $data['code'])
                ->withTrashed()
                ->exists();

            if ($alreadyExists) {
                $this->rowErrors[$rowNumber] = [
                    "'{$data['code']}' already exists in category '{$data['category']}' (duplicate within file or already in the database).",
                ];

                continue;
            }

            $seenInBatch[$key] = true;
            $validatedRows[] = $data;
        }

        if (! empty($this->rowErrors)) {
            return;
        }

        DB::transaction(function () use ($validatedRows) {
            foreach ($validatedRows as $data) {
                MasterDefinition::create([
                    'category' => $data['category'],
                    'code' => $data['code'],
                    'label' => $data['label'],
                    'sort_order' => $data['sort_order'] ?: 0,
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
