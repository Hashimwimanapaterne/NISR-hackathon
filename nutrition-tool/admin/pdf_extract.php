<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/pdf_extraction.php';

start_secure_session();
send_security_headers();
require_admin_login();

load_env_file(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');
load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

$llamaParseConfigured = getenv('LLAMA_CLOUD_API_KEY') !== false && trim((string) getenv('LLAMA_CLOUD_API_KEY')) !== '';
$tableRequest = 'Tables 6, 9, 10, 11, and 13: crop cultivated area, harvested area, yield, and production by district';
$errors = [];
$localText = '';
$llamaParseText = '';
$geminiText = '';
$groqText = '';
$runLocal = !$llamaParseConfigured;
$runLlamaParse = $llamaParseConfigured;
$runGemini = false;
$runGroqFallback = false;
$geminiFailure = null;
$pageRangeInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(270);
    verify_csrf($_POST['csrf_token'] ?? '');
    $runLocal = isset($_POST['run_local']);
    $runLlamaParse = isset($_POST['run_llamaparse']);
    $runGemini = isset($_POST['run_gemini']);
    $runGroqFallback = isset($_POST['run_groq_fallback']);
    $tableRequest = trim((string) ($_POST['tables'] ?? ''));
    $pageRangeInput = trim((string) ($_POST['page_range'] ?? ''));
    $firstPage = null;
    $lastPage = null;
    if ($tableRequest === '' || strlen($tableRequest) > 500) {
        $errors[] = 'Specify the tables or sections to transcribe (1–500 characters).';
    }
    if ($pageRangeInput !== '') {
        if (!preg_match('/^(\d{1,4})(?:\s*-\s*(\d{1,4}))?$/D', $pageRangeInput, $pageMatches)) {
            $errors[] = 'Enter PDF page numbers as a single page (for example 37) or range (for example 37-42).';
        } else {
            $firstPage = (int) $pageMatches[1];
            $lastPage = isset($pageMatches[2]) && $pageMatches[2] !== ''
                ? (int) $pageMatches[2]
                : $firstPage;
            $maximumRange = ($runLlamaParse || $runGemini || $runGroqFallback) ? 100 : 20;
            if ($firstPage < 1 || $lastPage < $firstPage || $lastPage - $firstPage >= $maximumRange) {
                $errors[] = 'Choose a valid page range within the limit: 100 pages for AI extraction, 20 pages for local-only extraction.';
            }
        }
    }
    if (!$runLocal && !$runLlamaParse && !$runGemini) {
        $errors[] = 'Select at least one extraction method.';
    }
    if ($runLlamaParse && (getenv('LLAMA_CLOUD_API_KEY') === false || trim((string) getenv('LLAMA_CLOUD_API_KEY')) === '')) {
        $errors[] = 'LlamaParse was selected but LLAMA_CLOUD_API_KEY is not configured on the server.';
    }
    if ($runGemini && (getenv('GEMINI_API_KEY') === false || trim((string) getenv('GEMINI_API_KEY')) === '')) {
        $errors[] = 'Gemini was selected but GEMINI_API_KEY is not configured on the server.';
    }
    if (($runLlamaParse || $runGemini || $runGroqFallback) && ($firstPage === null || $lastPage === null)) {
        $errors[] = 'AI extraction requires a specific PDF page range of at most 100 pages to control processing time and provider usage.';
    }
    if ($runGemini && !$runLocal && !$runLlamaParse) {
        $errors[] = 'Gemini needs text from either local Poppler extraction or LlamaParse. Select one of those methods.';
    }
    if ($runGroqFallback && (!$runGemini || (!$runLocal && !$runLlamaParse))) {
        $errors[] = 'Groq fallback requires Gemini and extracted text from Poppler or LlamaParse.';
    }
    if ($runGroqFallback && (getenv('GROQ_API_KEY') === false || trim((string) getenv('GROQ_API_KEY')) === '')) {
        $errors[] = 'Groq fallback was selected but GROQ_API_KEY is not configured on the server.';
    }

    $file = $_FILES['pdf'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $errors[] = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The PDF exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The PDF upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing its temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not store the uploaded PDF temporarily.',
            default => 'Choose a PDF file to extract.',
        };
    } elseif (($file['size'] ?? 0) < 1 || ($file['size'] ?? 0) > PDF_UPLOAD_MAX_BYTES) {
        $errors[] = 'The PDF must be between 1 byte and 10 MB.';
    } elseif (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        $errors[] = 'The uploaded file could not be verified.';
    } elseif (filesize((string) $file['tmp_name']) > PDF_UPLOAD_MAX_BYTES) {
        $errors[] = 'The uploaded PDF exceeds the 10 MB limit.';
    } else {
        $handle = fopen((string) $file['tmp_name'], 'rb');
        $signature = $handle === false ? '' : (string) fread($handle, 5);
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            $errors[] = 'The uploaded file does not have a valid PDF signature.';
        }
    }

    if ($errors === []) {
        $localProcess = null;
        $localPipes = [];
        if ($runLocal) {
            try {
                [$localProcess, $localPipes] = start_pdf_text_extraction(
                    (string) $file['tmp_name'],
                    $firstPage,
                    $lastPage
                );
            } catch (Throwable $error) {
                $errors[] = 'Local extraction: ' . $error->getMessage();
            }
        }

        if (is_resource($localProcess)) {
            try {
                $localText = finish_pdf_text_extraction($localProcess, $localPipes);
            } catch (Throwable $error) {
                $errors[] = 'Local extraction: ' . $error->getMessage();
            }
        }

        if ($runLlamaParse) {
            try {
                $llamaParseText = extract_pdf_with_llamaparse(
                    (string) $file['tmp_name'],
                    (int) $firstPage,
                    (int) $lastPage
                );
            } catch (Throwable $error) {
                $errors[] = 'LlamaParse extraction: ' . $error->getMessage();
            }
        }

        $aiSourceText = $llamaParseText !== '' ? $llamaParseText : $localText;
        if ($runGemini && $aiSourceText !== '') {
            try {
                $geminiText = extract_pdf_text_with_gemini($aiSourceText, $tableRequest);
            } catch (Throwable $error) {
                $geminiFailure = $error;
            }
        } elseif ($runGemini) {
            $errors[] = 'Gemini could not run because neither Poppler nor LlamaParse returned extracted text.';
        }

        if ($geminiFailure instanceof Throwable) {
            if ($runGroqFallback
                && $geminiFailure instanceof PdfAiProviderException
                && $geminiFailure->isTransient
            ) {
                if ($aiSourceText === '') {
                    $errors[] = 'Gemini extraction: ' . $geminiFailure->getMessage()
                        . ' Groq fallback could not run because no extracted text was available.';
                } else {
                    try {
                        $groqText = extract_pdf_text_with_groq($aiSourceText, $tableRequest);
                    } catch (Throwable $error) {
                        $errors[] = 'Gemini was temporarily unavailable. Groq fallback failed: ' . $error->getMessage();
                    }
                }
            } else {
                $errors[] = 'Gemini extraction: ' . $geminiFailure->getMessage();
            }
        }
    }
}

$geminiConfigured = getenv('GEMINI_API_KEY') !== false && trim((string) getenv('GEMINI_API_KEY')) !== '';
$groqConfigured = getenv('GROQ_API_KEY') !== false && trim((string) getenv('GROQ_API_KEY')) !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF data extraction — Umurima Data admin</title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">
<nav class="admin-nav" aria-label="Admin navigation">
    <a href="index.php">Foods &amp; prices</a>
    <a href="agro_list.php">Crop suitability</a>
    <a href="pdf_extract.php" aria-current="page">PDF extraction</a>
    <span class="spacer"></span>
    <span>Signed in as <?= h($_SESSION['admin_username'] ?? '') ?></span>
    <a href="logout.php">Sign out</a>
</nav>
<main class="wrap">
    <h1>Extract tables from an NISR PDF</h1>
    <p class="result-note">
        This admin tool creates draft transcriptions only. It never imports extracted values into the database.
        Verify every value, unit, footnote, district, and missing cell against the PDF before using the existing
        data-import workflow.
    </p>
    <?php foreach ($errors as $error): ?>
        <p class="alert-error" role="alert"><?= h($error) ?></p>
    <?php endforeach; ?>

    <section class="form-panel">
        <form id="pdf-extraction-form" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label for="pdf">PDF report (maximum 10 MB)</label>
            <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>
            <label for="tables">Tables or sections to transcribe</label>
            <textarea id="tables" name="tables" rows="3" maxlength="500" required><?= h($tableRequest) ?></textarea>
            <label for="page_range">PDF page range</label>
            <input id="page_range" name="page_range" type="text" inputmode="numeric" pattern="[0-9]+(\s*-\s*[0-9]+)?" maxlength="12" placeholder="For example: 37-42" value="<?= h($pageRangeInput) ?>">
            <p id="page-range-error" class="alert-error" role="alert" hidden></p>
            <p class="form-help">Use PDF viewer page numbers, not printed report labels. AI extraction supports up to 100 pages; local-only extraction allows up to 20 pages. Large ranges can take longer and use more provider quota. LlamaParse receives the uploaded PDF directly. Gemini and Groq receive extracted text, preferring LlamaParse output when selected.</p>
            <label class="form-help"><input type="checkbox" name="run_local" value="1" <?= $runLocal ? 'checked' : '' ?>>
                Optional: extract local text with Poppler (pdftotext). Without it, you can still use LlamaParse for scanned PDFs.</label>
            <label class="form-help"><input type="checkbox" name="run_llamaparse" value="1" <?= $runLlamaParse ? 'checked' : '' ?> <?= $llamaParseConfigured ? '' : 'disabled' ?>>
                Parse this PDF with LlamaParse<?= $llamaParseConfigured ? '' : ' (not configured)' ?>. The PDF is sent to LlamaCloud for layout-aware table/OCR parsing; review its current terms and your data policy.</label>
            <label class="form-help"><input type="checkbox" name="run_gemini" value="1" <?= $runGemini ? 'checked' : '' ?> <?= $geminiConfigured ? '' : 'disabled' ?>>
                Transcribe extracted text into draft JSON with Gemini<?= $geminiConfigured ? '' : ' (not configured)' ?>. It uses LlamaParse output when available, otherwise Poppler text.</label>
            <label class="form-help"><input type="checkbox" name="run_groq_fallback" value="1" <?= $runGroqFallback ? 'checked' : '' ?> <?= $groqConfigured ? '' : 'disabled' ?>>
                If Gemini returns a temporary capacity/rate-limit/server error, try Groq with extracted text<?= $groqConfigured ? '' : ' (not configured)' ?>.
                Groq receives text from LlamaParse or Poppler, not the PDF file.</label>
            <button id="pdf-extraction-submit" class="btn" type="submit" disabled>Extract draft data</button>
            <div id="pdf-extraction-progress" class="pdf-extraction-progress" role="status" aria-live="polite">
                <p id="pdf-extraction-status">Loading progress tracker… If this message does not change, refresh the page before submitting.</p>
                <progress id="pdf-upload-progress" max="100" value="0" hidden>0%</progress>
                <p id="pdf-extraction-elapsed">Elapsed: 0:00</p>
            </div>
        </form>
    </section>

    <?php if ($localText !== ''): ?>
        <section class="form-panel">
            <h2>Local Poppler text</h2>
            <p>Layout-oriented text extraction for searching and manual checking; this is not structured table data.</p>
            <textarea rows="18" readonly><?= h($localText) ?></textarea>
        </section>
    <?php endif; ?>
    <?php if ($llamaParseText !== ''): ?>
        <section class="form-panel">
            <h2>LlamaParse layout transcription</h2>
            <p>Parser output is unverified. Check all table values, units, footnotes, district names, and missing cells against the original PDF.</p>
            <textarea rows="18" readonly><?= h($llamaParseText) ?></textarea>
        </section>
    <?php endif; ?>
    <?php if ($geminiText !== ''): ?>
        <section class="form-panel">
            <h2>Gemini draft transcription</h2>
            <p>AI can misread tables. Treat every field as unverified; null means missing or not confidently transcribed.</p>
            <textarea rows="18" readonly><?= h($geminiText) ?></textarea>
        </section>
    <?php endif; ?>
    <?php if ($groqText !== ''): ?>
        <section class="form-panel">
            <h2>Groq fallback draft transcription</h2>
            <p>Gemini was temporarily unavailable. Groq received extracted text, not the PDF. Verify every value against the original report; this is not imported data.</p>
            <textarea rows="18" readonly><?= h($groqText) ?></textarea>
        </section>
    <?php endif; ?>
</main>
<script src="assets/pdf_extract.js?v=20261003-3"></script>
</body>
</html>
