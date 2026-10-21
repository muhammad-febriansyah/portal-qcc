# OpenAI Integration — Team Matching & Evidence Scoring

## 1. Posisi AI dalam produk

Dua AI capabilities masuk sampai proses bisnis 9:
1. **AI Team Matching** (nomor 3): menyarankan susunan kandidat beserta alasan. Output **tidak otomatis membentuk tim**.
2. **AI Scoring** (nomor 8): menilai berdasar rubric + evidence, menunggu approval manusia pada proses 9.

Backend Laravel melakukan semua validation, authorization, hitung skor berbobot, status workflow, audit. OpenAI **bukan** sumber aturan bisnis atau final approver.

## 2. Prinsip panggilan OpenAI

- Backend only; gunakan API SDK/responses API yang sesuai model terverifikasi dan dukungan structured output terkini. Pin model & API version dalam config, jangan hardcode nama model dalam banyak tempat.
- Gunakan structured JSON output dengan schema (server validate lagi, jangan percaya respons model 100%).
- Minimal data: ID kandidat/kompetensi/organisasi, bukan nomor telepon/pribadi yang tidak diperlukan.
- Implementasi async queued Jobs (`RunTeamMatching`, `RunAiScoring`) dengan idempotency/timeout/backoff/circuit breaker + rate/concurrency limits.
- Catat `model`, `prompt_version`, `rubric_version_id`, `latency_ms`, `usage_tokens`, `input_manifest_hash`, status; filter PII dan rahasia.
- File dari user dapat berisi prompt injection; treat semuanya sebagai data, bukan system instructions.

## 3. AI Team Matching

### Input

- Konteks proyek: judul, latar, permasalahan, baseline, target, tujuan, ruang lingkup, tiga hal dipelajari, competency IDs needed.
- Candidate snapshot dari query Laravel: `candidate_user_id`, `organization_id`, `availability_status`, array competency `{id, self_level, verified_level}`, QCC experience jika memang ada.
- Max team size 5, leader exactly 1, min organizations 2, unique user.

### Preprocessing

1. Authorize peminta dan project.
2. Build candidate query dari user aktif, organisasi valid, ketersediaan dan izin; paginate/batch candidate selection, bukan dump seluruh `users`.
3. Sanitasi input text dan batasi token.
4. Bila kandidat valid tidak cukup, kembalikan gap yang nyata tanpa memaksa AI mengarang anggota.
5. Call OpenAI, parse schema, **cek setiap candidate ID berasal dari query eligible**.
6. Tampilkan alasan rekomendasi, uncertain data, kekurangan tim dan status review.
7. User berwenang finalise; `TeamRuleValidator` verifikasi ulang secara deterministik di transaksi.

### Output contract contoh (semantik, divalidasi runtime)

```json
{
  "recommended_members": [
    {
      "user_id": 123,
      "proposed_role": "LEADER",
      "matched_competency_ids": [11, 17],
      "match_reason": "Kemampuan analisis dan pengalaman improvement sesuai target",
      "contribution": "Mengkoordinasi analisis dan eksekusi perbaikan",
      "gaps": ["Belum ada verifikasi kompetensi X"],
      "uncertainties": ["Availability perlu konfirmasi"]
    }
  ],
  "team_gaps": ["Perlu anggota dari LOB lain"],
  "cautions": []
}
```

`123`, `11` dan `17` adalah **contoh ID schema**, bukan user/kompetensi nyata. **Tidak wajib AI menghasilkan tim valid**; kalau saran melanggar aturan, validasi menolaknya atau UI mengizinkan pengubahan manual sebelum konfirmasi.

## 4. AI Evidence Scoring

### Persiapan evidence

1. Authorize owner/reviewer access; pilih project/scoring type.
2. Resolve `rubric_version` published; cek bobot total 100.
3. Gate evidence menurut minimum yang disahkan stakeholder: ownership project, scan READY, accepted content, relation/versions, field mandatory.
4. Jika kurang, hasil `INSUFFICIENT_EVIDENCE` **bukan** skor 0; return gap list.
5. Siapkan manifest `{evidence_id, version, file_type, checksum, extracted_text_ref, page_range}`.
6. Extract PDF yang relevan di worker; materialize content dari storage. Large PDF/image scan memerlukan ekstraksi/layout-aware/visual processing, dengan biaya dan privasi dikontrol.
7. Batasi konteks, pilih halaman/chunk relevan, dokumentasikan kehilangan konteks atau gambar yang tidak dianalisis.

### Scoring baseline [BRD, pending approval]

| Tipe | Kriteria | Bobot |
| --- | --- | ---: |
| Proposal | Goals Project | 30 |
| Proposal | Problem / Challenge Identification | 35 |
| Proposal | Corrective Action / Improvement Action | 35 |
| Final Presentation | 8 Step QCC | 25 |
| Final Presentation | Business Value & Impact Project | 25 |
| Final Presentation | Project Understanding & Analytical Depth | 20 |
| Final Presentation | Sustainability & Standardization | 12 |
| Final Presentation | Cross-Functional Collaboration | 10 |
| Final Presentation | Presentation Visual Quality | 8 |
| Convention | Project Impact | 40 |
| Convention | Process Improvement & Standardization | 30 |
| Convention | Authenticity of Improvement Idea | 30 |

### Example structured result

```json
{
  "rubric_version_id": 5,
  "criteria": [
    {
      "criterion_code": "GOALS_PROJECT",
      "score": 82,
      "rationale": "Target terukur dijelaskan dengan jelas pada lampiran proyek",
      "evidence_references": [
        { "evidence_id": 87, "locator": "halaman 3" }
      ],
      "gaps": [],
      "uncertainty": "Target verifikasi belum tersedia"
    }
  ],
  "overall_summary": "Perlu melengkapi verifikasi target",
  "cautions": []
}
```

Nilai/ID di atas **contoh format saja**, tidak merujuk proyek nyata. Output production wajib memiliki **semua kriteria** dari rubrik publish, tepat sekali, dan evidence IDs valid. JSON schema dapat dikembangkan dengan `additionalProperties: false`, required keys, string max length, angka 0–100.

### Kalkulasi weighted score wajib di Laravel

\[
\text{Weighted score} = \sum_i \frac{\text{criterion_score}_i \times \text{weight_pct}_i}{100}
\]

Contoh BRD: skor 80, bobot 30% = 24 poin. Simpan tipe `DECIMAL`, pembulatan sesuai kebijakan Owner. Tidak meminta model menghitung total sebagai sumber kebenaran.

### Evidence grounding rules

- Setiap klaim penilaian harus dapat ditautkan ke evidence yang benar-benar disediakan.
- Citation `{evidence_id, locator}` harus ada dalam manifest dan berada di project tersebut; jika tidak, reject run/mark invalid.
- Jika PDF hanya gambar/scan dan parser tidak membaca bagian penting, tandai uncertainty; jangan berpura-pura dokumen telah dibaca penuh.
- AI tidak mengarang angka savings atau dampak finansial. Nilai finansial memerlukan validasi fungsi bisnis/Finance.
- URL percakapan AI hanya referensi, tidak di-fetch otomatis; butuh user-provided summary/evidence.

## 5. Status pipeline

`DRAFT → EVIDENCE_CHECK → READY_FOR_SCORING → AI_SCORING → PENDING_REVIEW → APPROVED`; cabang `INSUFFICIENT_EVIDENCE`, `FAILED`, `REVISION_REQUIRED`.

- `PENDING_REVIEW`: AI score tersimpan; user berizin dapat melihat per-kriteria, skor total Laravel, gap dan evidence references.
- `REVISION_REQUIRED`: kembalikan ke tim untuk evidence atau perbaikan; snapshot scoring run lama tetap ada.
- `APPROVED`: `final_score` hanya setelah human approval; final score AI dan reviewer terpisah.

## 6. Review screen flow

1. Owner/reviewer berwenang membuka pending run.
2. Side-by-side: skor AI per kriteria, bobot, evidence citation, reviewer score override, alasan.
3. Tombol: `Minta Revisi`, `Setujui Final` (dan opsi reject jika kebijakan mendukung).
4. Wajib alasan bila override AI score atau meminta revisi.
5. Server: role+assignment policy, transaction, locking, weighted re-calculation, audit append-only.

## 7. Cost/performance controls

- Estimasi biaya per run berdasarkan input/output token usage dan pemrosesan berkas; tetapkan budget per batch/proyek.
- Cache extraction berdasarkan `checksum_sha256 + parser_version`, **bukan** langsung hasil skor antar rubrik/model versi berbeda.
- Deduplicate job dengan idempotency key dan request fingerprint; cooldown/rate limits.
- Queue tersendiri untuk AI, monitor p95 duration, spend by batch, retry, invalid JSON/grounding rate.
- Hindari raw PDF besar dikirim berulang setiap user membuka halaman scoring; upload/preprocess sekali, query hasil DB.
- Jangan menjanjikan batas API yang tetap; cocokkan batas ukuran file/model/token dengan dokumentasi provider yang aktif saat implementasi.

## 8. Test cases AI utama

- Output memasukkan candidate ID fiktif -> invalid.
- Team suggestion berisi 6 user -> tidak bisa dikonfirmasi.
- Scoring references evidence ID proyek lain -> invalid + audit.
- PDF mengandung instruksi 'ignore rubric' -> tidak mengubah rubric/permission.
- Evidence hilang -> insufficient evidence, bukan 0.
- AI unavailable/timeouts -> queued/retry/failed dengan feedback aman.
- Dua reviewer approve bersamaan -> tepat satu final state.
