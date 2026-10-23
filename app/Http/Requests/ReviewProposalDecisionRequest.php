<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewProposalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $decision = $this->input('decision');

        return $project instanceof Project
            && ($decision === 'APPROVE'
                ? $this->user()?->can('approveProposal', $project) === true
                : $this->user()?->can('reviewProposal', $project) === true);
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['APPROVE', 'REQUEST_REVISION', 'REJECT'])],
            'reason' => ['required_if:decision,REQUEST_REVISION,REJECT', 'nullable', 'string', 'max:5000'],
        ];
    }
}
