<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class SubmitProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('submit', $project) === true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [];
    }
}
