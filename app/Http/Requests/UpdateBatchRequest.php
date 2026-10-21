<?php

namespace App\Http\Requests;

use App\Models\QccBatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateBatchRequest extends StoreBatchRequest
{
    public function authorize(): bool
    {
        /** @var QccBatch $batch */
        $batch = $this->route('batch');

        return $this->user()?->can('update', $batch) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var QccBatch $batch */
        $batch = $this->route('batch');

        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('qcc_batches', 'code')->ignore($batch)],
        ];
    }
}
