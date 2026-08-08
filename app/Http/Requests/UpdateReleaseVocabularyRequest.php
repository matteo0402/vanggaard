<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Models\Riddim;
use App\Models\Tag;
use App\Models\Track;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReleaseVocabularyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $release = $this->route('release');

        return $release instanceof Release
            && $this->user()?->can('updateVocabulary', $release) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Release $release */
        $release = $this->route('release');
        $userId = $this->user()->id;

        return [
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct:strict',
                Rule::exists((new Tag)->getTable(), 'id')->where('user_id', $userId),
            ],
            'release_riddim_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists((new Riddim)->getTable(), 'id')->where('user_id', $userId),
            ],
            'track_riddim_overrides' => ['present', 'array'],
            'track_riddim_overrides.*' => ['required', 'array:sequence,riddim_id'],
            'track_riddim_overrides.*.sequence' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists((new Track)->getTable(), 'sequence')
                    ->where('release_id', $release->id)
                    ->whereNull('retired_at'),
            ],
            'track_riddim_overrides.*.riddim_id' => [
                'required',
                'integer',
                Rule::exists((new Riddim)->getTable(), 'id')->where('user_id', $userId),
            ],
        ];
    }
}
