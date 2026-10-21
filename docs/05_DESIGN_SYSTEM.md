# Design System — QCC SIMGROUP

**Style:** clean enterprise, restrained, practical, no "AI slop", light-only.  
**Technology:** React + Inertia TS, Tailwind CSS 4, shadcn/ui **Base UI**, Lucide React, Google Font **Poppins**, Sonner.

## 1. Non-negotiable design rules

1. **Palet utama tepat**: primary `#2547F9`, background `#F7F8FD`, surface `#FFFFFF` (penulisan user `#fffffff` dinormalisasi menjadi `#FFFFFF`, kode hex putih yang valid).
2. **Tidak ada dark mode**: tidak ada theme toggle, class `dark`, `prefers-color-scheme` overriding; semua token single light theme.
3. **Tidak pakai emoji** di mana pun. Pakai `lucide-react` untuk navigasi, tombol, badge, toast, empty state dan action.
4. Font global **Poppins** (400/500/600/700), hindari fallback yang mengubah layout.
5. Setiap halaman internal wajib urutan **Breadcrumb → Title → Description → Main Action → Content**. Pengecualian layar auth (login/forgot) yang tidak butuh breadcrumb aplikasi.
6. Table list wajib mengikuti **shadcn Base Data Table** https://ui.shadcn.com/docs/components/base/data-table menggunakan TanStack Table dan backend pagination, search, filter, sort.
7. **Aksi tiap baris:** trigger `Ellipsis` icon (titik tiga) di akhir tabel, `DropdownMenu` dengan aksi View/Edit/Review/Download/Archive/Delete **sesuai konteks**. Tidak tampilkan item yang tidak authorized; periksa ulang di backend.
8. Setiap field form wajib punya **label, placeholder/hint, dan `*` merah untuk required**; pesan error dekat field. Placeholder bukan pengganti label.
9. **Tanggal wajib** shadcn Base Date Picker (`Popover + Calendar`), **bukan** `<input type="date">` native.
10. **Sonner**: success hijau, warning oranye, error/danger merah. Semua ikon Lucide, tanpa emoji. Toast tidak menggantikan inline form validation.
11. Image/file upload modern: dropzone, preview/thumbnail, metadata/size, progress, status keamanan, cancel/remove/retry; tidak gunakan base64 data besar ke Inertia props.
12. Border halus, shadow sangat lembut. Tidak gradient besar, glassmorphism, glow, card berlapis-lapis, icon 3D, atau hero marketing dalam dashboard.

## 2. Tokens

| Token | Nilai | Penggunaan |
| --- | --- | --- |
| `--primary` | `#2547F9` | CTA primer, active nav, focus |
| `--primary-foreground` | `#FFFFFF` | Label tombol primer |
| `--background` | `#F7F8FD` | App canvas |
| `--card` | `#FFFFFF` | Card/table/dialog |
| `--foreground` | `#182337` | Judul & body utama (penunjang) |
| `--muted-foreground` | `#697386` | Deskripsi/helper |
| `--border` | `#E7EAF2` | Divider/field border |
| `--input` | `#E7EAF2` | Input outline |
| `--ring` | `#2547F9` | Focus accessible |
| `--success` | `#16834A` | Sonner success / approved |
| `--warning` | `#C96B0D` | Sonner warning / needs attention |
| `--destructive` | `#D92D20` | Required star, error, dangerous actions |
| `--radius` | `0.625rem` | Standard corners, not overly pillowy |

Warna selain 3 warna utama adalah **warna utilitas semantik** agar error/success/teks tetap jelas, bukan branding tambahan.

### Tailwind CSS 4 global example

```css
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");
@import "tailwindcss";

:root {
  color-scheme: light;
  --primary: #2547F9;
  --primary-foreground: #FFFFFF;
  --background: #F7F8FD;
  --foreground: #182337;
  --card: #FFFFFF;
  --card-foreground: #182337;
  --popover: #FFFFFF;
  --popover-foreground: #182337;
  --muted: #F1F3FA;
  --muted-foreground: #697386;
  --border: #E7EAF2;
  --input: #E7EAF2;
  --ring: #2547F9;
  --destructive: #D92D20;
  --radius: 0.625rem;
}

@theme inline {
  --font-sans: "Poppins", sans-serif;
  --color-primary: var(--primary);
  --color-primary-foreground: var(--primary-foreground);
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-border: var(--border);
  --color-destructive: var(--destructive);
}

html, body, #app { min-height: 100%; }
body { background: var(--background); color: var(--foreground); font-family: "Poppins", sans-serif; }
```

> Selaraskan nama theme vars dengan hasil generated komponen shadcn yang dipasang (Base UI), jangan mengganti token bawaan setengah-setengah. Tidak perlu setup theme toggle.

## 3. Typography & spacing

| Elemen | Token awal | Catatan |
| --- | --- | --- |
| App page title | 24px/32px, semibold | Mobile 22px |
| Card title | 16px/24px, semibold | Jaga ringkas |
| Body/input | 13–14px/20–22px, regular | Poppins agak lebar; uji density |
| Description/helper | 12–13px/18px | Muted, tetap kontras |
| Breadcrumb | 12px/18px | ChevronRight icon |
| Form labels | 13px, medium | Required red `*` |
| Content max width | 1440px | Center on very wide screens |
| Page padding | 24px desktop, 16px mobile | Consistent |
| Card padding | 20–24px | Mobile 16px |
| Grid gap | 16px / 24px | Jangan menumpuk card tanpa hirarki |

- Border `1px #E7EAF2`; shadow: `0 2px 12px rgba(24,35,55,.04)` atau none.
- Button: primary biru, secondary putih-outline, ghost untuk tindakan netral, destructive merah hanya irreversible.
- Button + icon + text; `rounded-full` hanya jika cocok untuk primary CTA, jangan semua field bulat penuh.
- Responsif: desktop tabel penuh; tablet horizontal scroll area; mobile filter via `Sheet` dan responsive card/list alternatif hanya bila data tabel terlalu kompleks. Semua list desktop menggunakan Data Table resmi.

## 4. Layout frame setiap page

```text
AppShell (Topbar + compact Sidebar or collapsible Sidebar)
  Topbar: logo QCC SIMGROUP | global search | notifications | user menu
  Main:
    Breadcrumb: Dashboard / Proyek QCC / Detail
    H1: Detail Proyek QCC
    Description: Pantau progres delapan tahap dan kelengkapan evidence.
    Actions: [Kembali] [Tambah Evidence] [Ajukan Review]
    Content: Tabs / DataTable / Form / Detail
```

Sidebar **tidak dominan**; pada desktop 224–240px, collapsible menjadi icon rail, topbar putih. Menu sesuai role/permission, tidak menampilkan semua 24 form sekaligus. Contoh modul: Dashboard, Kompetensi, Proposal, Tim, Proyek QCC, Monitoring, Evidence, AI Scoring (Owner/Admin saja), Approval, Pengaturan.

## 5. shadcn/ui Base UI component mapping

| Kebutuhan | Komponen | Aturan |
| --- | --- | --- |
| Breadcrumb | `Breadcrumb` + `BreadcrumbItem/Link/Page/Separator` | Wajib setiap internal page |
| Container | `Card`, `Separator`, `ScrollArea`, `Skeleton` | Minimal card |
| Fields | `Field`, `FieldLabel`, `FieldDescription`, `FieldError`, `Input`, `Textarea` | Field wrapper reusable |
| Pilihan | `Select`, `Combobox`, `Checkbox`, `RadioGroup`, `Switch` | Combobox untuk 62 kompetensi / user besar |
| Date | `Popover` + `Calendar` + `Button` | No native date picker |
| Navigasi/progress | `Tabs`, `Progress`, `Badge`, `Stepper` custom from primitives | 8 Step QCC kiri / tab monitoring |
| List | `Table` + TanStack Data Table | Server query, reusable filters |
| Row actions | `DropdownMenu`, `DropdownMenuItem`, `AlertDialog` | Ellipsis, confirm destructive |
| Modal/drawer | `Dialog`, `Sheet`, `AlertDialog` | Sheet desktop untuk quick edit, dialog confirm |
| File | UI dropzone custom, `Button`, `Card`, `Progress`, `Alert`, `Dialog` | Native `<input type=file>` disembunyikan di balik UI shadcn, **boleh** sebagai mekanisme browser; larangan native berlaku khusus tipe date |
| Feedback | `Badge`, `Alert`, `Skeleton`, **Sonner** toast | Colors semantic |
| Security | `Tooltip`, `Alert`, `Empty` | Locked/insufficient evidence |
| Profil/team | `Avatar`, `AvatarFallback`, `Popover`, `Command` | Inisial non-sensitive |
| Dashboard | `Chart` bila perlu (recharts), `Progress`, `Card` | Chart secukupnya, no excessive gradients |
| Preview | `Dialog`/`Sheet` dengan responsive image/PDF viewer | Object URLs direvoke saat unmount |

Referensi resmi: https://ui.shadcn.com/docs/components/base, https://ui.shadcn.com/docs/components/base/date-picker, https://ui.shadcn.com/docs/components/base/dropdown-menu.

## 6. Data Table (wajib patuh link user)

Dokumentasi rujukan: https://ui.shadcn.com/docs/components/base/data-table. Guide saat penulisan memakai **TanStack Table v9** dengan konsep feature registration; jangan menyalin snippet TanStack v8 (`useReactTable` + row models) lalu mencampurnya dengan contoh v9 (`useTable`, features). **Pin satu versi dan adaptasi API sesuai versi yang terinstall.**

### Data Table reusable

- `DataTable<T>` receives columns, rows, loading, pagination meta, sorting, filters, selection, onPage/onSort/onFilter.
- `DataTableToolbar`: search field debounce 300–500ms, status/LOB/batch filters, reset filters, optional column visibility, export hanya jika disetujui scope.
- `DataTableRowActions`: Ellipsis button (`aria-label="Aksi untuk [nama baris]"`), menu detail/edit/review/archive tergantung permission/status.
- Aksi `Hapus` selalu `AlertDialog`; tidak otomatis tampil pada evidence yang sudah dipakai scoring.
- `DataTablePagination`: `page`, `perPage` (`10/25/50`), total, prev/next; server-managed; sorting whitelist server.
- Checkbox selection hanya jika bulk action memang diizinkan; hindari enable select seluruh data lintas page yang belum di-load.
- Query parameter canonical: `?search=&status=&organization_id=&batch_id=&sort=updated_at&direction=desc&page=1&per_page=25`.
- Empty state & loading skeleton; tombol tetap navigable via keyboard.

**Referensi action composition (konseptual, sesuaikan API Base UI hasil generate):**

```tsx
import { Ellipsis, Eye, Pencil, Trash2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"

export function RowActions({ onView, onEdit, onDelete, canEdit, canDelete }: {
  onView: () => void
  onEdit: () => void
  onDelete: () => void
  canEdit: boolean
  canDelete: boolean
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger render={<Button variant="ghost" size="icon" aria-label="Buka aksi baris" />}>
        <Ellipsis className="size-4" aria-hidden="true" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={onView}><Eye /> Lihat Detail</DropdownMenuItem>
        {canEdit && <DropdownMenuItem onClick={onEdit}><Pencil /> Edit</DropdownMenuItem>}
        {canDelete && <DropdownMenuItem onClick={onDelete}><Trash2 /> Hapus</DropdownMenuItem>}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
```

**Catatan kompatibilitas:** Pada varian Base UI terbaru, prop `render` pada trigger dipakai untuk composition. Jika generated UI project berbeda, ikuti signature aktual dari file component; jangan memaksakan kode referensi sebagai drop-in tanpa build.

## 7. Form standard, validation & date

- Gunakan `Field` / `FieldLabel` / `FieldError` untuk field; form state bisa memakai Inertia `useForm` atau `react-hook-form + zod` bila sesuai kebutuhan (pilih pola konsisten per form).
- Wajib placeholder untuk Input/Textarea/Select/Combobox; untuk Date Picker tampil teks `Pilih tanggal` sebelum tanggal dipilih.
- Asterisk required: `<span aria-hidden="true" className="text-[#D92D20]">*</span>` ditambah atribut semantik `required`/`aria-required`.
- Preview file wajib tersedia sebelum submit; untuk images `URL.createObjectURL`, revoke object URL; PDF viewer modal dengan informasi halaman/ukuran bila tersedia.
- Multi-step proposal dengan `Tabs`/step navigation bukan berpindah page secara tidak sengaja; save draft on every step.
- Backend FormRequest tetap sumber kebenaran; tampilkan `errors.field` inline.
- Field tanggal menggunakan `Popover + Calendar` (shadcn) dengan nilai ISO `YYYY-MM-DD` di Inertia payload; time zona server konsisten `Asia/Jakarta` untuk tampilan Indonesia setelah keputusan region.

## 8. Sonner notification

**Sonner** dipakai sebagai notification layer (boleh instal library Sonner langsung sekalipun halaman shadcn sekarang menempatkan dokumentasi notification di bagian Toast). Pasang satu `<Toaster />` global; ubah warna melalui classNames/stylesheet berdasarkan type.

| Type | Icon | Background / text | Sample |
| --- | --- | --- | --- |
| Success | `CircleCheck` | hijau muda / `#16834A` | Proposal berhasil disimpan |
| Warning | `TriangleAlert` | oranye muda / `#C96B0D` | Evidence belum lengkap |
| Error / danger | `CircleX` | merah muda / `#D92D20` | Gagal menyimpan; periksa isian |
| Info | `Info` | putih / primary | Scoring sedang diproses |

- `toast.success(...)`, `toast.warning(...)`, `toast.error(...)` untuk event selesai/gagal; hindari success sebelum response server.
- Display toast max 4–5 detik, bisa di-dismiss, selalu muncul accessible.
- Request yang panjang menunjukkan progress dalam halaman; toast saja tidak cukup.

## 9. UI states

| State | Wajib |
| --- | --- |
| Loading | Skeleton list/card, progress upload AI task |
| Empty | Icon + judul + deskripsi + primary CTA bila punya izin |
| Error | Inline error, toast error, retry bila aman |
| Unauthorized | 403 page tanpa konten proyek sensitif |
| Insufficient evidence | Daftar evidence yang kurang dan tautan tambah evidence |
| Pending review | Badge + reviewer/ETA jika tersedia, tombol disabled untuk pelaku tak berhak |
| Approved | Read-only result, timestamp approver, audit history |
| Mobile | Sidebar Sheet, table overflow, stacked field grid |

## 10. Per-page UI checklist

`[ ] Breadcrumb` · `[ ] Title` · `[ ] Description` · `[ ] correct role actions` · `[ ] Responsive` · `[ ] Poppins` · `[ ] White canvas/surfaces` · `[ ] shadcn Base UI` · `[ ] no emoji` · `[ ] empty/loading/error` · `[ ] no N+1`.
