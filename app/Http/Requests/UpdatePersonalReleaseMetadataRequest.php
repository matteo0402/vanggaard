<?php

namespace App\Http\Requests;

use App\Models\Release;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonalReleaseMetadataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $release = $this->route('release');

        return $release instanceof Release
            && $this->user()?->can('updatePersonalMetadata', $release) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'personal_notes' => ['present', 'nullable', 'string', 'max:5000'],
            'corrected_year' => [
                'present',
                'nullable',
                Rule::when($this->boolean('is_year_approximate'), ['required']),
                'integer',
                'between:1000,'.(now()->year + 1),
            ],
            'is_year_approximate' => ['required', 'boolean'],
            'rating' => ['present', 'nullable', 'integer', 'between:1,5'],
            'is_favourite' => ['required', 'boolean'],
            'is_dj_ready' => ['required', 'boolean'],
            'energy' => ['present', 'nullable', 'integer', 'between:1,5'],
            'bpm' => ['present', 'nullable', 'numeric', 'decimal:0,2', 'between:1,999.99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'corrected_year.required' => 'Enter a corrected year before marking it as approximate.',
            'rating.between' => 'The personal rating must be between 1 and 5.',
            'energy.between' => 'The energy must be between 1 and 5.',
            'bpm.between' => 'The BPM must be between 1 and 999.99.',
        ];
    }
}
