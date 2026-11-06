<?php

namespace App\Http\Requests;

use App\Enums\BatchStatus;
use App\Models\QccBatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QccBatch::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('qcc_batches', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(BatchStatus::class)],
            'participant_target' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_starts_on' => ['nullable', 'date_format:Y-m-d', 'required_with:registration_ends_on'],
            'registration_ends_on' => ['nullable', 'date_format:Y-m-d', 'required_with:registration_starts_on', 'after_or_equal:registration_starts_on'],
            'selection_starts_on' => ['nullable', 'date_format:Y-m-d', 'required_with:selection_ends_on'],
            'selection_ends_on' => ['nullable', 'date_format:Y-m-d', 'required_with:selection_starts_on', 'after_or_equal:selection_starts_on'],
            'execution_starts_on' => ['nullable', 'date_format:Y-m-d', 'required_with:execution_ends_on'],
            'execution_ends_on' => ['nullable', 'date_format:Y-m-d', 'required_with:execution_starts_on', 'after_or_equal:execution_starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode batch',
            'name' => 'nama batch',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'status' => 'status',
            'participant_target' => 'target peserta',
            'registration_starts_on' => 'mulai fase 1',
            'registration_ends_on' => 'selesai fase 1',
            'selection_starts_on' => 'mulai fase 2',
            'selection_ends_on' => 'selesai fase 2',
            'execution_starts_on' => 'mulai fase 3',
            'execution_ends_on' => 'selesai fase 3',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'max' => ':Attribute maksimal :max karakter.',
            'unique' => ':Attribute sudah digunakan.',
            'code.regex' => 'Kode batch hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'date_format' => 'Format :attribute tidak valid.',
            'ends_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'status.enum' => 'Status yang dipilih tidak valid.',
            'required_with' => ':Attribute wajib diisi bila tanggal pasangannya diisi.',
            'integer' => ':Attribute harus berupa angka.',
            'participant_target.min' => 'Target peserta minimal 1.',
            'participant_target.max' => 'Target peserta terlalu besar.',
            'registration_ends_on.after_or_equal' => 'Selesai fase 1 tidak boleh sebelum tanggal mulainya.',
            'selection_ends_on.after_or_equal' => 'Selesai fase 2 tidak boleh sebelum tanggal mulainya.',
            'execution_ends_on.after_or_equal' => 'Selesai fase 3 tidak boleh sebelum tanggal mulainya.',
        ];
    }
}
