<?php

use App\Models\Competency;
use App\Models\Project;

it('redirects guests from the project requirement update', function () {
    $project = Project::factory()->create();
    $competency = Competency::factory()->create();

    $this->put(route('proposals.requirements.update', $project), [
        'requirements' => [['competency_id' => $competency->id, 'minimum_level' => null]],
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('project_requirements', 0);
});
