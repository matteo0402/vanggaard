<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\VocabularyName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Tag::query()
                        ->whereBelongsTo($this->user())
                        ->where('normalized_name', VocabularyName::normalize((string) $value))
                        ->exists()) {
                        $fail('You already have a tag with this name.');
                    }
                },
            ],
        ];
    }
}
