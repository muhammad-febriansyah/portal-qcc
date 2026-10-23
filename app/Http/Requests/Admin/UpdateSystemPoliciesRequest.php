<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSystemPoliciesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('system-settings.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notifications_in_app' => ['required', 'boolean'],
            'notifications_email' => ['required', 'boolean'],
            'attachments_allowed_types' => ['required', 'array', 'min:1'],
            'attachments_allowed_types.*' => [
                'required',
                'string',
                Rule::in(['pdf', 'docx', 'xlsx', 'png', 'jpg', 'jpeg', 'webp']),
            ],
            'attachments_storage_disk' => [
                'required',
                Rule::in(blank(config('filesystems.disks.s3.bucket')) ? ['local'] : ['local', 's3']),
            ],
            'attachments_max_size_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'attachments_max_files' => ['required', 'integer', 'min:1', 'max:10'],
            'attachments_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'ai_enabled' => ['required', 'boolean'],
            'ai_model' => [
                'nullable',
                Rule::requiredIf(filter_var($this->input('ai_enabled'), FILTER_VALIDATE_BOOLEAN)),
                'string',
                'max:100',
                Rule::in(array_values(array_unique(array_filter([
                    ...array_column(config('scoring.openai.models', []), 'id'),
                    config('scoring.openai.model'),
                    DB::table('system_settings')->where('key', 'ai_model')->value('value'),
                ])))),
            ],
            'ai_api_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Add validation that depends on the saved provider credentials.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! filter_var($this->input('ai_enabled'), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            $hasSavedKey = DB::table('system_settings')->where('key', 'ai_api_key')->exists();
            $hasConfiguredKey = filled(config('scoring.openai.key')) || $hasSavedKey || filled($this->input('ai_api_key'));

            if (! $hasConfiguredKey) {
                $validator->errors()->add('ai_api_key', 'Isi API key sebelum mengaktifkan integrasi AI.');
            }
        }];
    }
}
