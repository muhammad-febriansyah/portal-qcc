<?php

return [
    'disk' => env('QCC_EVIDENCE_DISK', 'evidence_private'),
    'max_file_size_kb' => (int) env('QCC_EVIDENCE_MAX_FILE_SIZE_KB', 51200),
    'max_image_pixels' => (int) env('QCC_EVIDENCE_MAX_IMAGE_PIXELS', 12000000),
    'pdf_text_extractor' => env('QCC_PDFTOTEXT_BINARY', 'pdftotext'),
    'pdf_extraction_timeout' => (int) env('QCC_PDF_EXTRACTION_TIMEOUT', 60),
    'max_extracted_text_bytes' => (int) env('QCC_EVIDENCE_MAX_EXTRACTED_TEXT_BYTES', 2097152),
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'],
];
