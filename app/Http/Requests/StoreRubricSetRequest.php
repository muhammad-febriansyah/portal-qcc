<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRubricSetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('rubrics.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scoring_type' => ['required', 'string', Rule::in(['proposal', 'final_presentation', 'convention']), Rule::unique('rubric_sets', 'scoring_type')],
            'name' => ['required', 'string', 'max:150'],
            'criteria' => ['required', 'array', 'min:1', 'max:30'],
            'criteria.*.code' => ['required', 'string', 'alpha_dash:ascii', 'max:32', 'distinct'],
            'criteria.*.name' => ['required', 'string', 'max:150'],
            'criteria.*.description' => ['nullable', 'string', 'max:2000'],
            'criteria.*.weight_pct' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }
}
