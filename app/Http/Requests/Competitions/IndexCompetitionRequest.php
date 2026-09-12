<?php

namespace App\Http\Requests\Competitions;

use App\Enums\CompetitionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCompetitionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CompetitionStatus::class)],
        ];
    }

    /**
     * De actieve filters, genormaliseerd naar `null` zodat lege strings uit de
     * querystring niet als filter gelden.
     *
     * @return array{search: string|null, status: string|null}
     */
    public function filters(): array
    {
        $search = trim((string) $this->validated('search', ''));
        $status = (string) $this->validated('status', '');

        return [
            'search' => $search === '' ? null : $search,
            'status' => $status === '' ? null : $status,
        ];
    }
}
