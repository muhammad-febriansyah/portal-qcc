<?php

namespace App\Mail;

use App\Mail\Concerns\HasMailBranding;
use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Email copy of a project notice. Like the bell it only carries the title, one sentence and
 * where to go, never scores or private contacts.
 */
class ProjectNotificationMail extends Mailable
{
    use HasMailBranding, Queueable, SerializesModels;

    /** @var array<string, string> */
    private const KIND_LABELS = [
        'SUBMITTED' => 'Proposal diajukan',
        'RESUBMITTED' => 'Proposal diajukan ulang',
        'TEAM_COMPOSITION_PENDING' => 'Tim menunggu disusun',
        'APPROVE' => 'Proposal disetujui',
        'REQUEST_REVISION' => 'Proposal perlu revisi',
        'REJECT' => 'Proposal ditolak',
        'REVIEW_STARTED' => 'Review dimulai',
        'STEP_SUBMITTED' => 'Step QCC diajukan',
        'STEP_APPROVED' => 'Step QCC disetujui',
        'STEP_REVISION' => 'Step QCC perlu revisi',
        'BIWEEKLY_SUBMITTED' => 'Laporan bi-weekly diajukan',
        'BIWEEKLY_APPROVED' => 'Laporan bi-weekly disetujui',
        'BIWEEKLY_REVISION' => 'Laporan bi-weekly perlu revisi',
        'MENTOR_ASSIGNED' => 'Penugasan mentor',
        'MENTORING_SUBMITTED' => 'Feedback mentoring',
        'MENTORING_ACKNOWLEDGED' => 'Tanggapan tim atas mentoring',
        'RISK_ASSIGNED' => 'Risiko atau isu ditugaskan',
        'ACTION_ASSIGNED' => 'Tindakan ditugaskan',
        'SCORING_COMPLETED' => 'AI Scoring selesai',
        'SCORING_APPROVED' => 'Hasil scoring disetujui',
        'SCORING_REVISION' => 'Scoring perlu revisi',
        'DUE_SOON' => 'Tenggat mendekati',
        'overdue' => 'Melewati tenggat',
    ];

    public function __construct(
        public readonly User $recipient,
        public readonly int $projectId,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $url,
        public readonly string $kind,
        public readonly ?string $dueDate = null,
    ) {}

    public function envelope(): Envelope
    {
        $project = Project::query()->find($this->projectId, ['id']);
        $code = $project === null ? null : $this->projectCode();

        return new Envelope(
            subject: ($code === null ? '' : "[{$code}] ").$this->title,
        );
    }

    public function content(): Content
    {
        $project = Project::query()->find($this->projectId, ['id', 'title']);
        $rows = [];

        if ($project !== null) {
            $rows['Proyek'] = $this->projectCode().' · '.$project->title;
        }

        $rows['Jenis'] = self::KIND_LABELS[$this->kind] ?? 'Pembaruan proyek';

        if ($this->dueDate !== null) {
            $dueDate = Carbon::parse($this->dueDate, $this->timezone())->settings(['locale' => 'id']);
            $rows['Tenggat'] = $dueDate->translatedFormat('j M Y');
        }

        $rows['Waktu'] = Carbon::now($this->timezone())->translatedFormat('j F Y, H.i T');

        $data = [
            ...$this->brandData(),
            'recipientName' => $this->recipient->name,
            'headline' => $this->title,
            'summary' => $this->summary,
            'rows' => $rows,
            'actionUrl' => $this->absoluteUrl(),
        ];

        return new Content(
            view: 'mail.project-notification',
            text: 'mail.project-notification-text',
            with: $data,
        );
    }

    private function projectCode(): string
    {
        return sprintf('PRP-%04d', $this->projectId);
    }

    private function absoluteUrl(): string
    {
        return str_starts_with($this->url, '/') ? url($this->url) : $this->url;
    }
}
