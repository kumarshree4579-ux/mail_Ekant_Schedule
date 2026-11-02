<?php
// index.php - Server-Side Rendered Public Viewer
// Accepts: ?url=PDF_URL&token=TRACKING_TOKEN
$pdfUrl = $_GET['url'] ?? '';
$token  = $_GET['token'] ?? uniqid('guest_');

if (empty($pdfUrl)) {
    die("<!DOCTYPE html><html><body style='display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f172a;font-family:system-ui,sans-serif;'>
        <div style='text-align:center;color:#94a3b8;'>
            <div style='font-size:3rem;margin-bottom:16px;'>📄</div>
            <h2 style='color:#e2e8f0;margin-bottom:8px;'>No document URL provided</h2>
            <p>Please open this viewer through a valid document link.</p>
        </div></body></html>");
}

// ------------------------------------------------------------------
// BACKEND PDF FETCH & CONVERSION ENGINE
// ------------------------------------------------------------------
$docHash      = md5($pdfUrl);
$renderDir    = __DIR__ . '/renders/' . $docHash;
$webRenderDir = 'renders/' . $docHash;
$imagePages   = [];
$renderError  = '';

// Check if cached renders already exist
if (is_dir($renderDir) && count(glob($renderDir . '/*.webp')) > 0) {
    $files = glob($renderDir . '/*.webp');
    natsort($files);
    foreach ($files as $f) {
        $imagePages[] = $webRenderDir . '/' . basename($f);
    }
} else {
    if (!is_dir($renderDir)) {
        mkdir($renderDir, 0777, true);
    }

    $tempPdfPath = $renderDir . '/document.pdf';

    // Fetch PDF securely on the server side (bypasses browser CORS)
    $ch = curl_init($pdfUrl);
    $fp = fopen($tempPdfPath, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if ($httpCode !== 200 || !file_exists($tempPdfPath) || filesize($tempPdfPath) === 0) {
        $renderError = 'Unable to download PDF from source URL (HTTP ' . $httpCode . '). ' . $curlError;
    } else {
        // Convert PDF pages to WebP images
        try {
            if (class_exists('Imagick')) {
                $imagick = new Imagick();
                $imagick->setResolution(150, 150);
                $imagick->readImage($tempPdfPath);

                foreach ($imagick as $pageIndex => $page) {
                    $page->setImageFormat('webp');
                    $page->setImageCompressionQuality(80);
                    $fileName   = sprintf('page_%04d.webp', $pageIndex + 1);
                    $outputPath = $renderDir . '/' . $fileName;
                    $page->writeImage($outputPath);
                    $imagePages[] = $webRenderDir . '/' . $fileName;
                }

                $imagick->clear();
                $imagick->destroy();
            } else {
                // CLI Fallback (pdftoppm / Ghostscript)
                $outputPrefix = $renderDir . '/page';
                exec('pdftoppm -webp -r 150 ' . escapeshellarg($tempPdfPath) . ' ' . escapeshellarg($outputPrefix));

                $files = glob($renderDir . '/page-*.webp');
                natsort($files);
                foreach ($files as $f) {
                    $imagePages[] = $webRenderDir . '/' . basename($f);
                }
            }
        } catch (Exception $e) {
            $renderError = 'Error rendering PDF: ' . htmlspecialchars($e->getMessage());
        }
    }

    // Cleanup local temp PDF binary file
    if (file_exists($tempPdfPath)) {
        unlink($tempPdfPath);
    }
}

// Derive document name from URL for display
$docName = urldecode(basename(parse_url($pdfUrl, PHP_URL_PATH) ?: 'document.pdf'));
$docName = preg_replace('/\.pdf$/i', '', $docName) ?: 'Document';

// Total pages
$totalPages = count($imagePages);

// Safe values for HTML/JS
$safeToken   = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
$safeName    = htmlspecialchars($docName, ENT_QUOTES, 'UTF-8');
$safePdfUrl  = htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8');

// Main app tracking endpoint (same origin routing via .htaccess → public/index.php)
// Adjust APP_TRACK_URL to match your install's BASE_PATH
$appBasePath = rtrim('/Mail', '/');
$trackUrl    = $appBasePath . '/pdf-viewer/track';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $safeName ?> — Document Viewer</title>

<!-- Self-Hosted Tracking Script (rrweb session replay) -->
<script src="app-core.js"></script>

<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --toolbar-h: 56px;
        --bg-dark:   #0f172a;
        --bg-panel:  #1e293b;
        --border:    #334155;
        --accent:    #3b82f6;
        --text-dim:  #94a3b8;
        --text-main: #e2e8f0;
    }

    body {
        font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
        background: var(--bg-dark);
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        color: var(--text-main);
    }

    /* ── Toolbar ──────────────────────────────────────────────── */
    .viewer-toolbar {
        height: var(--toolbar-h);
        background: var(--bg-panel);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        padding: 0 20px;
        gap: 12px;
        flex-shrink: 0;
        position: relative;
        z-index: 10;
    }

    .toolbar-icon {
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .toolbar-title {
        flex: 1;
        font-size: 0.875rem;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--text-main);
    }

    .toolbar-meta {
        font-size: 0.72rem;
        color: var(--text-dim);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .tb-btn {
        background: rgba(255,255,255,0.06);
        border: 1px solid var(--border);
        color: var(--text-dim);
        padding: 6px 13px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: background 0.15s, color 0.15s;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .tb-btn:hover { background: rgba(255,255,255,0.12); color: var(--text-main); }
    .tb-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    .tb-btn.primary:hover { background: #2563eb; }

    .zoom-display {
        font-size: 0.72rem;
        color: var(--text-dim);
        min-width: 42px;
        text-align: center;
        flex-shrink: 0;
    }

    /* ── PDF container ────────────────────────────────────────── */
    #pdf-container {
        flex: 1;
        overflow-y: auto;
        padding: 32px 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        scroll-behavior: smooth;
        /* Scrollbar styling */
        scrollbar-width: thin;
        scrollbar-color: #475569 transparent;
    }
    #pdf-container::-webkit-scrollbar { width: 8px; }
    #pdf-container::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }

    #pdf-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0;
        width: 100%;
    }

    .pdf-page-card {
        background: #ffffff;
        box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        margin-bottom: 20px;
        max-width: 900px;
        width: 100%;
        border-radius: 4px;
        overflow: hidden;
        transition: width 0.12s ease-out;
        position: relative;
    }

    .pdf-page-card img {
        width: 100%;
        height: auto;
        display: block;
    }

    .page-badge {
        position: absolute;
        bottom: 8px;
        right: 10px;
        background: rgba(0,0,0,0.55);
        color: #fff;
        font-size: 0.62rem;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 20px;
        pointer-events: none;
    }

    /* ── Error state ──────────────────────────────────────────── */
    .error-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1;
        text-align: center;
        padding: 40px 24px;
        color: var(--text-dim);
    }
    .error-state .err-icon { font-size: 3.5rem; margin-bottom: 20px; }
    .error-state h2 { color: var(--text-main); margin-bottom: 10px; font-size: 1.2rem; }
    .error-state p { font-size: 0.875rem; margin-bottom: 24px; max-width: 400px; }

    /* ── Loading overlay ──────────────────────────────────────── */
    #loading-overlay {
        position: fixed;
        inset: var(--toolbar-h) 0 0 0;
        background: var(--bg-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 16px;
        z-index: 5;
    }
    .spinner {
        width: 40px; height: 40px;
        border: 3px solid rgba(59,130,246,0.2);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.75s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Mobile responsive */
    @media (max-width: 600px) {
        .viewer-toolbar { padding: 0 12px; gap: 8px; }
        .toolbar-title  { font-size: 0.8rem; }
        .toolbar-meta   { display: none; }
        #pdf-container  { padding: 16px 8px; }
    }
</style>
</head>
<body>

<!-- ── Toolbar ──────────────────────────────────────────────────────── -->
<div class="viewer-toolbar">
    <span class="toolbar-icon">📄</span>
    <span class="toolbar-title" title="<?= $safeName ?>"><?= $safeName ?></span>

    <?php if ($totalPages > 0): ?>
    <span class="toolbar-meta" id="page-info">
        <?= $totalPages ?> page<?= $totalPages !== 1 ? 's' : '' ?>
    </span>
    <?php endif; ?>

    <!-- Zoom controls -->
    <button class="tb-btn" onclick="adjustZoom(-0.15)" title="Zoom out">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </button>
    <span class="zoom-display" id="zoom-pct">100%</span>
    <button class="tb-btn" onclick="adjustZoom(0.15)" title="Zoom in">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </button>

    <!-- Download button -->
    <a href="<?= $safePdfUrl ?>" target="_blank" class="tb-btn primary" download>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download
    </a>
</div>

<!-- ── Content ───────────────────────────────────────────────────────── -->
<?php if ($renderError): ?>
<div class="error-state">
    <div class="err-icon">⚠️</div>
    <h2>Unable to load document</h2>
    <p><?= htmlspecialchars($renderError) ?></p>
    <a href="<?= $safePdfUrl ?>" target="_blank" class="tb-btn primary" style="font-size:0.8rem;padding:10px 24px;">
        ⬇ Download PDF instead
    </a>
</div>

<?php elseif (empty($imagePages)): ?>
<div class="error-state">
    <div class="err-icon">📄</div>
    <h2>No pages found</h2>
    <p>The document appears to be empty or could not be converted.</p>
    <a href="<?= $safePdfUrl ?>" target="_blank" class="tb-btn primary" style="font-size:0.8rem;padding:10px 24px;">
        ⬇ Download PDF
    </a>
</div>

<?php else: ?>
<div id="loading-overlay">
    <div class="spinner"></div>
    <span style="color:var(--text-dim);font-size:0.8rem;">Loading document…</span>
</div>

<div id="pdf-container">
    <div id="pdf-wrapper">
        <?php foreach ($imagePages as $index => $imagePath): ?>
        <div class="pdf-page-card" data-base-width="900" data-page="<?= $index + 1 ?>">
            <img
                src="<?= htmlspecialchars($imagePath) ?>"
                alt="Page <?= $index + 1 ?>"
                loading="<?= $index < 3 ? 'eager' : 'lazy' ?>"
                onload="<?= $index === 0 ? 'onFirstPageLoad()' : '' ?>"
            />
            <span class="page-badge"><?= $index + 1 ?> / <?= $totalPages ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
    // ── Constants ─────────────────────────────────────────────────
    const token    = <?= json_encode($token) ?>;
    const trackUrl = <?= json_encode($trackUrl) ?>;
    const totalPages = <?= (int)$totalPages ?>;

    // ── Loading overlay ───────────────────────────────────────────
    function onFirstPageLoad() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.style.display = 'none';
    }
    // Fallback: hide overlay after 6s even if onload doesn't fire
    setTimeout(() => {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.style.display = 'none';
    }, 6000);

    // ── Zoom ──────────────────────────────────────────────────────
    let currentZoom = 1.0;
    function adjustZoom(delta) {
        currentZoom = Math.max(0.3, Math.min(3.0, currentZoom + delta));
        document.getElementById('zoom-pct').textContent = Math.round(currentZoom * 100) + '%';
        document.querySelectorAll('.pdf-page-card').forEach(card => {
            const baseW = parseFloat(card.dataset.baseWidth || '900');
            card.style.width = (baseW * currentZoom) + 'px';
        });
    }

    // ── Main App Tracking Beacon ──────────────────────────────────
    function sendMainAppEvent(eventType, page) {
        if (!token) return;
        const body = JSON.stringify({ token, event_type: eventType, page: page || 1 });
        try {
            navigator.sendBeacon(trackUrl, new Blob([body], { type: 'application/json' }));
        } catch (_) {
            fetch(trackUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body,
                keepalive: true
            }).catch(() => {});
        }
    }

    // Fire open event immediately
    sendMainAppEvent('open', 1);

    // Fire closed event on page leave
    window.addEventListener('beforeunload', () => sendMainAppEvent('closed'));

    // Fire active/inactive on visibility change
    document.addEventListener('visibilitychange', () => {
        sendMainAppEvent(document.hidden ? 'inactive' : 'active');
    });

    // ── rrweb Session Replay Tracking ─────────────────────────────
    let eventsChunk = [];

    if (typeof rrweb !== 'undefined') {
        rrweb.record({
            emit: function(event) {
                eventsChunk.push(event);
            }
        });

        // Batch send tracking data to local Viewer/api.php every 3 seconds
        setInterval(() => {
            if (eventsChunk.length > 0) {
                const dataToSend = eventsChunk;
                eventsChunk = [];
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: token, events: dataToSend })
                }).catch(console.error);
            }
        }, 3000);
    } else {
        console.warn('Session replay script blocked or unavailable.');
    }
</script>
</body>
</html>