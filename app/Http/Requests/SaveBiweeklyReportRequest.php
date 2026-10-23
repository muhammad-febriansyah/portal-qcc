<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBiweeklyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('manageReports', $project) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'meeting_date' => ['nullable', 'date_format:Y-m-d'],
            'agenda' => ['nullable', 'string', 'max:10000'],
            'summary' => ['nullable', 'string', 'max:20000'],
            'planned' => ['nullable', 'string', 'max:20000'],
            'actual' => ['nullable', 'string', 'max:20000'],
            'issues' => ['nullable', 'string', 'max:20000'],
            'decisions' => ['nullable', 'string', 'max:20000'],
            'attendee_ids' => ['nullable', 'array', 'max:50'],
            'attendee_ids.*' => ['integer', 'distinct', Rule::exists('project_members', 'user_id')
                ->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')],
            'actions' => ['nullable', 'array', 'max:50'],
            'actions.*.id' => ['nullable', 'integer', 'distinct'],
            'actions.*.title' => ['required', 'string', 'max:255'],
            'actions.*.description' => ['nullable', 'string', 'max:10000'],
            'actions.*.pic_id' => ['required', 'integer', Rule::exists('project_members', 'user_id')
                ->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')],
            'actions.*.due_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'starts_on' => 'awal periode', 'ends_on' => 'akhir periode', 'meeting_date' => 'tanggal rapat',
            'agenda' => 'agenda', 'summary' => 'ringkasan', 'planned' => 'rencana', 'actual' => 'aktual',
            'issues' => 'kendala', 'decisions' => 'keputusan', 'attendee_ids' => 'peserta',
            'actions.*.title' => 'judul tindakan', 'actions.*.pic_id' => 'PIC tindakan', 'actions.*.due_date' => 'tenggat tindakan',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => 'Akhir periode tidak boleh sebelum awal periode.',
            'attendee_ids.*.exists' => 'Peserta harus anggota aktif tim proyek ini.',
            'attendee_ids.*.distinct' => 'Peserta yang sama tidak boleh dipilih dua kali.',
            'actions.*.pic_id.exists' => 'PIC tindakan harus anggota aktif tim proyek ini.',
        ];
    }
}
