<?php

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectActivityNotification;
use Symfony\Component\Mime\Email;

/**
 * @return list<Email>
 */
function sentEmails(): array
{
    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();

    return array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all());
}

function projectNotice(Project $project, string $kind = 'STEP_APPROVED'): ProjectActivityNotification
{
    return new ProjectActivityNotification(
        $project->id,
        'Step 3 disetujui',
        'Step 3 proyek telah disetujui oleh reviewer.',
        route('projects.steps.show', ['project' => $project, 'step' => 3], false),
        $kind,
    );
}

beforeEach(function () {
    config(['app.notifications.channels.email' => true, 'app.notifications.channels.in_app' => true]);
});

test('a project notice is also emailed with logo, details and a button when email notifications are on', function () {
    $project = Project::factory()->create(['title' => 'Mengurangi keterlambatan laporan']);
    $user = User::factory()->create(['name' => 'Sari Wulandari', 'email' => 'sari@example.test']);

    $user->notify(projectNotice($project));

    $emails = sentEmails();
    expect($emails)->toHaveCount(1);

    $email = $emails[0];
    $html = (string) $email->getHtmlBody();
    $code = sprintf('PRP-%04d', $project->id);

    expect($email->getTo()[0]->getAddress())->toBe('sari@example.test')
        ->and($email->getSubject())->toBe("[{$code}] Step 3 disetujui")
        ->and($html)->toContain('Halo Sari Wulandari,')
        ->and($html)->toContain('Step 3 proyek telah disetujui oleh reviewer.')
        ->and($html)->toContain("{$code} · Mengurangi keterlambatan laporan")
        ->and($html)->toContain('Step QCC disetujui')
        ->and($html)->toContain('Buka di portal')
        ->and($html)->toContain('src="cid:')
        ->and($html)->toContain(url("/projects/{$project->id}/steps/3"))
        ->and($email->getAttachments())->toHaveCount(1)
        ->and($user->notifications()->count())->toBe(1);
});

test('the email has a plain text version with the same facts', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create(['name' => 'Sari Wulandari']);

    $user->notify(projectNotice($project));

    $text = (string) sentEmails()[0]->getTextBody();

    expect($text)->toContain('Halo Sari Wulandari,')
        ->and($text)->toContain('Step 3 proyek telah disetujui oleh reviewer.')
        ->and($text)->toContain('Jenis: Step QCC disetujui')
        ->and($text)->toContain('Buka di portal: '.url("/projects/{$project->id}/steps/3"));
});

test('no email is sent when email notifications are off, but the bell still gets the notice', function () {
    config(['app.notifications.channels.email' => false]);
    $user = User::factory()->create();

    $user->notify(projectNotice(Project::factory()->create()));

    expect(sentEmails())->toBe([])
        ->and($user->notifications()->count())->toBe(1);
});

test('email only mode sends the email and leaves the bell empty', function () {
    config(['app.notifications.channels.in_app' => false]);
    $user = User::factory()->create();

    $user->notify(projectNotice(Project::factory()->create()));

    expect(sentEmails())->toHaveCount(1)
        ->and($user->notifications()->count())->toBe(0);
});

test('a deactivated account gets no email', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_active' => false])->save();

    $user->notify(projectNotice(Project::factory()->create()));

    expect(sentEmails())->toBe([]);
});

test('an unknown notice kind falls back to a general label', function () {
    $user = User::factory()->create();

    $user->notify(projectNotice(Project::factory()->create(), 'SOMETHING_NEW'));

    expect((string) sentEmails()[0]->getHtmlBody())->toContain('Pembaruan proyek');
});

test('text in the notice is escaped in the email', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create();

    $user->notify(new ProjectActivityNotification($project->id, 'Judul <script>alert(1)</script>', 'Isi <b>tebal</b>', '/dashboard', 'SUBMITTED'));

    $html = (string) sentEmails()[0]->getHtmlBody();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('Judul &lt;script&gt;alert(1)&lt;/script&gt;');
});
