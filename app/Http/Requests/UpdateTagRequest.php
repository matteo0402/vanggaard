<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\VocabularyName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            && $this->user()?->can('update', $tag) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tag $tag */
        $tag = $this->route('tag');

        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($tag): void {
                    if (Tag::query()
                        ->whereBelongsTo($this->user())
                        ->whereKeyNot($tag->id)
                        ->where('normalized_name', VocabularyName::normalize((string) $value))
                        ->exists()) {
                        $fail('You already have a tag with this name.');
                    }
                },
            ],
        ];
    }
}
