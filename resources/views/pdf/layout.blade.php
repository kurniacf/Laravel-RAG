<!DOCTYPE html>
{{--
    Layout dasar PDF untuk export ringkasan & kuis.
    DomPDF tidak memuat CSS eksternal/Tailwind, jadi semua gaya inline di sini.
    Font default DomPDF (DejaVu Sans) dipakai — mendukung karakter Bahasa
    Indonesia (é, ñ, em-dash, "·", dll) tanpa perlu embed font tambahan.
--}}
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'PintarBelajar AI' }}</title>
    <style>
        @page { margin: 28mm 18mm 24mm 18mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            color: #0f172a;
            line-height: 1.55;
        }

        h1 { font-size: 18pt; font-weight: bold; color: #064e3b; margin: 0 0 4mm 0; }
        h2 { font-size: 13pt; font-weight: bold; color: #065f46; margin: 6mm 0 2mm 0; padding-bottom: 1.5mm; border-bottom: 1px solid #d1fae5; }
        h3 { font-size: 11.5pt; font-weight: bold; color: #047857; margin: 4mm 0 1.5mm 0; }
        p  { margin: 0 0 2mm 0; }

        .doc-header { padding: 6mm 0 4mm 0; border-bottom: 2px solid #059669; margin-bottom: 6mm; }
        .doc-header .brand { font-size: 9pt; color: #059669; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; }
        .doc-header .meta  { font-size: 9.5pt; color: #475569; margin-top: 1.5mm; }
        .doc-header .meta strong { color: #0f172a; }
        .doc-header .subject-pill { display: inline-block; padding: 1mm 3mm; border-radius: 10pt; font-size: 8.5pt; font-weight: bold; }

        ul.points { padding-left: 6mm; margin: 0 0 3mm 0; }
        ul.points li { margin-bottom: 1.5mm; }

        .section-body { white-space: pre-line; }

        .question { margin-bottom: 5mm; page-break-inside: avoid; }
        .question .q-head { font-weight: bold; margin-bottom: 1.5mm; }
        .question .q-meta { font-size: 8.5pt; color: #64748b; margin-top: 1mm; }
        .question ol.options { padding-left: 8mm; margin: 1mm 0 2mm 0; }
        .question ol.options li { margin-bottom: 0.8mm; }
        .question .correct { background-color: #d1fae5; padding: 0 1.5mm; border-radius: 2pt; }
        .question .answer-box { border: 1px dashed #cbd5e1; min-height: 12mm; padding: 2mm; margin-top: 1.5mm; font-style: italic; color: #94a3b8; }
        .question .explanation { font-size: 9.5pt; color: #334155; background-color: #f1f5f9; border-left: 3px solid #059669; padding: 2mm 3mm; margin-top: 1.5mm; }
        .question .explanation strong { color: #064e3b; }

        .key-section { margin-top: 8mm; padding-top: 4mm; border-top: 2px dashed #94a3b8; page-break-before: always; }

        footer {
            position: fixed;
            left: 0; right: 0; bottom: -16mm;
            font-size: 8pt;
            color: #64748b;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 2mm;
        }
        footer .brand { color: #059669; font-weight: bold; }
        footer .page::after { content: counter(page) ' dari ' counter(pages); }

        .ai-note { font-size: 8pt; color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>

    <footer>
        <span class="brand">PintarBelajar AI</span>
        · Dokumen dihasilkan oleh AI, dapat berisi ketidakakuratan
        · Halaman <span class="page"></span>
    </footer>

    @yield('content')

</body>
</html>
