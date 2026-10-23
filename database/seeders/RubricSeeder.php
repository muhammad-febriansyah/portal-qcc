<?php

namespace Database\Seeders;

use App\Models\RubricCriterion;
use App\Models\RubricSet;
use App\Models\RubricVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Baseline rubrics from BRD section 10: Proposal, Final Presentation and Convention.
 *
 * The weights are a baseline the Owner QCC must confirm before publishing (BRD 10.4), so
 * every rubric is saved as draft version 1 and never published here. A draft is rewritten
 * to the baseline when the seeder runs again; a published version is left untouched.
 */
class RubricSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, criteria: list<array{code: string, name: string, weight: int, description: string}>}>
     */
    private const RUBRICS = [
        'proposal' => [
            'name' => 'Rubrik Proposal Scoring',
            'criteria' => [
                ['code' => 'GOALS', 'name' => 'Goals Project', 'weight' => 30, 'description' => 'Menilai kejelasan tujuan proyek: apakah goal dan target dirumuskan spesifik, terukur, realistis, dan berbatas waktu, serta didukung baseline yang jelas.'],
                ['code' => 'PROBLEM_CHALLENGE', 'name' => 'Problem / Challenge Identification', 'weight' => 35, 'description' => 'Menilai ketepatan identifikasi masalah: apakah masalah atau tantangan dirumuskan jelas, didukung data atau fakta kondisi saat ini, dan menunjukkan dampaknya pada proses atau pelanggan.'],
                ['code' => 'CORRECTIVE_ACTION', 'name' => 'Corrective Action / Improvement Action', 'weight' => 35, 'description' => 'Menilai rencana perbaikan: apakah tindakan menjawab penyebab masalah, konkret dan dapat dilaksanakan, memiliki PIC dan jadwal, serta cukup untuk mencapai target.'],
            ],
        ],
        'final_presentation' => [
            'name' => 'Rubrik Final Presentation Scoring',
            'criteria' => [
                ['code' => 'EIGHT_STEP_QCC', 'name' => '8 Step QCC', 'weight' => 25, 'description' => 'Menilai kelengkapan dan ketertiban penerapan 8 Step QCC, dari menentukan tema sampai standardisasi dan rencana pencegahan, termasuk keterkaitan antar-langkah.'],
                ['code' => 'BUSINESS_VALUE_IMPACT', 'name' => 'Business Value & Impact Project', 'weight' => 25, 'description' => 'Menilai nilai bisnis dan dampak proyek: perubahan terhadap baseline dan target serta manfaatnya bagi proses, pelanggan, atau perusahaan. Dampak finansial hanya dinilai jika didukung evidence dan sudah divalidasi fungsi bisnis atau Finance.'],
                ['code' => 'ANALYTICAL_DEPTH', 'name' => 'Project Understanding & Analytical Depth / Ketajaman Analisa', 'weight' => 20, 'description' => 'Menilai pemahaman proyek dan ketajaman analisa: kedalaman analisis penyebab, penggunaan data dan alat analisis, serta logika dari temuan sampai solusi.'],
                ['code' => 'SUSTAINABILITY_STANDARDIZATION', 'name' => 'Sustainability & Standardization', 'weight' => 12, 'description' => 'Menilai keberlanjutan hasil: apakah perbaikan distandarkan (SOP, instruksi kerja, atau aturan baru), ada rencana pencegahan agar masalah tidak muncul lagi, dan hasilnya dipantau setelah perbaikan.'],
                ['code' => 'CROSS_FUNCTIONAL', 'name' => 'Cross-Functional Collaboration', 'weight' => 10, 'description' => 'Menilai kolaborasi lintas fungsi: keterlibatan anggota dari beberapa LOB atau Divisi, pembagian peran yang jelas, dan kontribusi tiap fungsi pada hasil proyek.'],
                ['code' => 'PRESENTATION_VISUAL', 'name' => 'Presentation Visual Quality', 'weight' => 8, 'description' => 'Menilai kualitas visual presentasi: alur yang runtut, materi mudah dibaca, grafik atau tabel yang jelas, dan penyampaian yang ringkas.'],
            ],
        ],
        'convention' => [
            'name' => 'Rubrik Convention Scoring',
            'criteria' => [
                ['code' => 'PROJECT_IMPACT', 'name' => 'Project Impact', 'weight' => 40, 'description' => 'Menilai besarnya dampak proyek yang dapat dibuktikan: perubahan terhadap baseline dan target serta manfaatnya bagi proses, pelanggan, atau perusahaan.'],
                ['code' => 'PROCESS_IMPROVEMENT', 'name' => 'Process Improvement & Standardization', 'weight' => 30, 'description' => 'Menilai perbaikan proses dan standardisasi: perubahan cara kerja yang nyata, hasilnya distandarkan, dan dapat diterapkan kembali di unit lain.'],
                [
                    'code' => 'AUTHENTICITY',
                    'name' => 'Authenticity of Improvement Idea',
                    'weight' => 30,
                    'description' => 'Menilai keaslian ide improvement: kebaruan atau keunikan ide serta bukti improvement. Ketiadaan KPI baru tidak otomatis menghasilkan skor nol apabila improvement yang autentik dan terdokumentasi dapat dibuktikan.',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::RUBRICS as $scoringType => $rubric) {
            DB::transaction(function () use ($scoringType, $rubric): void {
                $set = RubricSet::query()->firstOrCreate(['scoring_type' => $scoringType], ['name' => $rubric['name']]);

                $version = RubricVersion::query()->firstOrCreate(
                    ['rubric_set_id' => $set->id, 'version_number' => 1],
                    ['publication_status' => 'draft'],
                );

                if ($version->publication_status === 'published') {
                    $this->command?->warn("{$rubric['name']} v1 sudah dipublikasikan; dilewati.");

                    return;
                }

                $version->criteria()->delete();

                foreach ($rubric['criteria'] as $position => $criterion) {
                    RubricCriterion::query()->create([
                        'rubric_version_id' => $version->id,
                        'code' => $criterion['code'],
                        'name' => $criterion['name'],
                        'description' => $criterion['description'],
                        'weight_pct' => $criterion['weight'],
                        'position' => $position + 1,
                    ]);
                }

                $this->command?->info("{$rubric['name']} v1 (draft): ".count($rubric['criteria']).' kriteria.');
            });
        }
    }
}
