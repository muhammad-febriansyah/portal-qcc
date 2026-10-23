<?php

namespace App\Services\Teams\Matching;

use App\Actions\Teams\TeamRuleValidator;
use App\Services\Ai\Exceptions\InvalidAiOutput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Never trust the model. Every person and competency must come from the
 * shortlist that was sent. A suggestion that breaks the team rules (size,
 * one leader, two organizations, no duplicates) is kept but flagged, so a person
 * can adjust it; it can never be confirmed as it is.
 */
class TeamMatchingResultValidator
{
    public function __construct(private readonly TeamRuleValidator $rules) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{
     *     project_id: int, generated_at: string,
     *     requirements: list<array{competency_id: int, minimum_level: int|null}>,
     *     candidates: list<array{
     *         candidate_user_id: int, organization_id: int, availability_status: string|null,
     *         competencies: list<array{competency_id: int, self_level: int, verified_level: int|null}>,
     *         qcc_experience: array{projects_count: int, leader_projects_count: int, completed_projects_count: int}
     *     }>
     * }  $snapshot  from CandidateShortlist::snapshot()
     * @return array<string, mixed> the payload plus `rule_problems`
     *
     * @throws InvalidAiOutput
     */
    public function validate(array $payload, array $snapshot): array
    {
        $unexpectedKeys = array_diff(array_keys($payload), ['recommended_members', 'team_gaps', 'cautions', 'mitigations']);

        if ($unexpectedKeys !== []) {
            throw new InvalidAiOutput(['Output AI memiliki properti yang tidak dikenal: '.implode(', ', $unexpectedKeys).'.']);
        }

        $validator = Validator::make($payload, [
            'recommended_members' => ['required', 'array', 'min:1', 'max:20'],
            'recommended_members.*' => ['array:user_id,proposed_role,matched_competency_ids,match_reason,contribution,gaps,uncertainties'],
            'recommended_members.*.user_id' => ['required', 'integer'],
            'recommended_members.*.proposed_role' => ['required', 'in:LEADER,MEMBER'],
            'recommended_members.*.matched_competency_ids' => ['present', 'array'],
            'recommended_members.*.matched_competency_ids.*' => ['integer'],
            'recommended_members.*.match_reason' => ['required', 'string'],
            'recommended_members.*.contribution' => ['required', 'string'],
            'recommended_members.*.gaps' => ['present', 'array'],
            'recommended_members.*.gaps.*' => ['string'],
            'recommended_members.*.uncertainties' => ['present', 'array'],
            'recommended_members.*.uncertainties.*' => ['string'],
            'team_gaps' => ['present', 'array'],
            'team_gaps.*' => ['string'],
            'cautions' => ['present', 'array'],
            'cautions.*' => ['string'],
            'mitigations' => ['present', 'array'],
            'mitigations.*' => ['array:gap,recommendation,type'],
            'mitigations.*.gap' => ['required', 'string'],
            'mitigations.*.recommendation' => ['required', 'string'],
            'mitigations.*.type' => ['required', 'in:COACHING,TRAINING,MENTOR'],
        ]);

        if ($validator->fails()) {
            throw new InvalidAiOutput(array_values($validator->errors()->all()));
        }

        $candidates = collect($snapshot['candidates'])->keyBy('candidate_user_id');
        $problems = [];

        foreach ($payload['recommended_members'] as $member) {
            $candidate = $candidates->get($member['user_id']);

            if ($candidate === null) {
                $problems[] = "Kandidat {$member['user_id']} tidak ada di daftar kandidat yang valid.";

                continue;
            }

            $held = array_column($candidate['competencies'], 'competency_id');

            foreach ($member['matched_competency_ids'] as $competencyId) {
                if (! in_array($competencyId, $held, true)) {
                    $problems[] = "Kompetensi {$competencyId} tidak dimiliki kandidat {$member['user_id']}.";
                }
            }
        }

        if ($problems !== []) {
            throw new InvalidAiOutput($problems);
        }

        $payload['rule_problems'] = $this->ruleProblems($payload['recommended_members'], $candidates);

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @param  Collection<int|string, array{candidate_user_id: int, organization_id: int, availability_status: string|null, competencies: list<array{competency_id: int, self_level: int, verified_level: int|null}>, qcc_experience: array{projects_count: int, leader_projects_count: int, completed_projects_count: int}}>  $candidates
     * @return list<string>
     */
    private function ruleProblems(array $members, $candidates): array
    {
        try {
            $this->rules->validate(array_map(fn (array $member): array => [
                'user_id' => $member['user_id'],
                'organization_id' => $candidates->get($member['user_id'])['organization_id'],
                'role_in_project' => $member['proposed_role'],
            ], $members));
        } catch (ValidationException $e) {
            return array_values($e->errors()['members'] ?? []);
        }

        return [];
    }
}
