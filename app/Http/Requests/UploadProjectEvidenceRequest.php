<?php

namespace App\Http\Requests;

use App\Enums\ProposalStatus;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UploadProjectEvidenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user() !== null
            && $project->proposal_status === ProposalStatus::Approved
            && $this->user()->can('uploadEvidence', $project);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['source_type' => $this->input('source_type', 'FILE')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', config('qcc_evidence.allowed_extensions'));

        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(['PROPOSAL', 'QCC_STEP', 'BIWEEKLY', 'MENTORING', 'ACTION', 'OTHER'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'source_type' => ['required', Rule::in(['FILE', 'LINK', 'AI_CHAT_LINK'])],
            'url' => ['nullable', 'required_if:source_type,LINK,AI_CHAT_LINK', 'prohibited_unless:source_type,LINK,AI_CHAT_LINK', 'url:http,https', 'max:2048'],
            'summary' => ['nullable', 'required_if:source_type,AI_CHAT_LINK', 'prohibited_unless:source_type,AI_CHAT_LINK', 'string', 'min:30', 'max:10000'],
            'target_type' => ['required', Rule::in(['PROJECT', 'QCC_STEP', 'BIWEEKLY_REPORT', 'MENTORING_REPORT'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'file' => ['nullable', 'required_if:source_type,FILE', 'prohibited_unless:source_type,FILE', 'file', "mimes:{$extensions}", "extensions:{$extensions}", 'max:'.config('qcc_evidence.max_file_size_kb')],
        ];
    }

    /** @return array{title: string, category: string, description: string|null, source_type: string, url: string|null, summary: string|null, target_type: string, target_id: int, file: UploadedFile|null} */
    public function evidenceData(): array
    {
        $file = $this->file('file');

        if ($this->input('source_type', 'FILE') === 'FILE' && ! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'File evidence wajib diunggah.']);
        }

        $description = $this->input('description');

        return [
            'title' => $this->string('title')->toString(),
            'category' => $this->string('category')->toString(),
            'description' => is_string($description) ? $description : null,
            'source_type' => $this->string('source_type')->toString(),
            'url' => $this->input('url'),
            'summary' => $this->input('summary'),
            'target_type' => $this->string('target_type')->toString(),
            'target_id' => $this->integer('target_id'),
            'file' => $file instanceof UploadedFile ? $file : null,
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'judul evidence',
            'category' => 'kategori',
            'description' => 'deskripsi',
            'target_type' => 'keterkaitan',
            'target_id' => 'proyek atau step',
            'file' => 'file evidence',
            'source_type' => 'jenis evidence',
            'url' => 'tautan evidence',
            'summary' => 'ringkasan percakapan AI',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimes' => 'File harus berupa PDF, JPG, PNG, DOCX, atau XLSX.',
            'file.extensions' => 'Ekstensi file tidak sesuai dengan format yang didukung.',
            'file.max' => 'Ukuran file melewati batas yang berlaku.',
        ];
    }
}
