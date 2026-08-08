<?php

namespace App\Http\Requests;

use App\Models\Riddim;
use App\VocabularyName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRiddimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $riddim = $this->route('riddim');

        return $riddim instanceof Riddim
            && $this->user()?->can('update', $riddim) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Riddim $riddim */
        $riddim = $this->route('riddim');

        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($riddim): void {
                    if (Riddim::query()
                        ->whereBelongsTo($this->user())
                        ->whereKeyNot($riddim->id)
                        ->where('normalized_name', VocabularyName::normalize((string) $value))
                        ->exists()) {
                        $fail('You already have a riddim with this name.');
                    }
                },
            ],
        ];
    }
}
