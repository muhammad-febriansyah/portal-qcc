<?php

namespace App\Actions\Proposals;

use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Models\Project;

/**
 * The mandatory parts of a proposal. A proposal is submitted only once all of them are filled,
 * and a draft can be exported as a presentation from that same moment, so the proposer can
 * discuss it with their superior before submitting.
 */
class ProposalCompleteness
{
    /**
     * @var array<string, string> Required text fields and their labels.
     */
    private const REQUIRED_FIELDS = [
        'title' => 'Judul proposal',
        'theme' => 'Tema improvement',
        'theme_category' => 'Kategori tema',
        'background' => 'Latar belakang',
        'current_situation' => 'Situasi saat ini & baseline',
        'improvement_plan' => 'Rencana perbaikan',
        'target' => 'Target/tujuan',
        'scope' => 'Ruang lingkup proyek',
    ];

    /**
     * What is still missing, as a field key with the message to show.
     *
     * @return array<string, string>
     */
    public function missing(Project $project): array
    {
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field => $label) {
            if (blank($project->{$field})) {
                $missing[$field] = "{$label} wajib diisi sebelum proposal diajukan.";
            }
        }

        $goals = array_filter($project->learning_goals_json ?? [], fn ($goal): bool => filled($goal));

        if (count($goals) !== 3) {
            $missing['learning_goals'] = 'Isi tepat 3 hal yang akan dipelajari sebelum proposal diajukan.';
        }

        $plannedSteps = array_filter(
            $project->step_timeline_json ?? [],
            fn (array $step): bool => filled($step['start_date'] ?? null) && filled($step['target_date'] ?? null),
        );

        if (count($plannedSteps) !== count(QccStep::cases())) {
            $missing['step_timeline'] = 'Isi tanggal mulai dan target selesai semua step QCC sebelum proposal diajukan.';
        }

        if (! $project->requirements()->exists()) {
            $missing['requirements'] = 'Tambahkan minimal satu kebutuhan kompetensi sebelum proposal diajukan.';
        }

        return $missing;
    }

    /**
     * Short names of the missing parts, for a hint next to a button.
     *
     * @return list<string>
     */
    public function missingLabels(Project $project): array
    {
        $labels = [
            ...self::REQUIRED_FIELDS,
            'learning_goals' => '3 hal yang akan dipelajari',
            'step_timeline' => 'Tanggal mulai & target semua step QCC',
            'requirements' => 'Kebutuhan kompetensi',
        ];

        return array_values(array_map(
            fn (string $field): string => $labels[$field],
            array_keys($this->missing($project)),
        ));
    }

    /**
     * Drafts and proposals sent back for revision are exported only once every mandatory part
     * is filled; a proposal already in review or decided can always be exported.
     */
    public function canExportPresentation(Project $project): bool
    {
        if (! in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)) {
            return true;
        }

        return $this->missing($project) === [];
    }
}
