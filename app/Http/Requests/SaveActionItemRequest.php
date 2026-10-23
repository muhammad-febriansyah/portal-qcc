<?php

namespace App\Http\Requests;

use App\Enums\ActionStatus;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('manageRisks', $project) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'pic_id' => ['required', 'integer', Rule::exists('project_members', 'user_id')
                ->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(ActionStatus::class)],
            'source_type' => ['nullable', Rule::in(['PROJECT', 'RISK'])],
            'source_id' => ['nullable', 'integer', 'required_if:source_type,RISK', Rule::exists('project_risks', 'id')->where('project_id', $project->id)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'judul tindakan', 'description' => 'deskripsi', 'pic_id' => 'PIC', 'due_date' => 'tenggat', 'status' => 'status', 'source_id' => 'risiko terkait'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['pic_id.exists' => 'PIC harus anggota aktif tim proyek ini.', 'source_id.exists' => 'Risiko atau isu terkait tidak ada di proyek ini.'];
    }
}
