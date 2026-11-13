<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadProposalAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $project = $this->route('project');

        return $user !== null && $project instanceof Project && $user->can('update', $project);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.config('qcc.attachments.max_files')],
            'files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx,xlsx', 'max:'.(config('qcc.attachments.max_size_mb') * 1024)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu lampiran.',
            'files.max' => 'Maksimal '.config('qcc.attachments.max_files').' lampiran per unggahan.',
            'files.*.mimes' => 'Lampiran harus berupa PDF, JPG, PNG, DOCX, atau XLSX.',
            'files.*.max' => 'Ukuran setiap lampiran maksimal '.config('qcc.attachments.max_size_mb').' MB.',
        ];
    }
}
