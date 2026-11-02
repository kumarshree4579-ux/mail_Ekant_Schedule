<?php
// Variables provided: $token, $url, $filename, $trackingId, $sendId
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($filename ?: 'PDF') ?></title>
  <script src="https://unpkg.com/pdfjs-dist@3.9.179/build/pdf.min.js"></script>
  <style>body{margin:0;font-family:Arial,Helvetica,sans-serif}#toolbar{background:#111827;color:#fff;display:flex;align-items:center;gap:8px;padding:10px}#viewer{width:100%;height:calc(100vh - 52px);overflow:auto;background:#888}</style>
</head>
<body>
  <div id="toolbar">
    <div style="flex:1;display:flex;align-items:center;gap:12px">
      <button id="prev">◀ Prev</button>
      <button id="next">Next ▶</button>
      <span id="pageInfo">-</span>
    </div>
    <div style="font-size:13px;opacity:.9">Viewing: <?= htmlspecialchars($filename) ?></div>
  </div>
  <div id="viewer"></div>

  <script>
    const token = <?= json_encode($token) ?>;
    // Load PDF via raw endpoint on this host to avoid cross-origin/proxy issues
    const pdfUrl = '<?= BASE_PATH ?>' + '/attachment/raw?token=' + encodeURIComponent(<?= json_encode($token) ?>);
    const trackUrl = '<?= BASE_PATH ?>/attachment/track';
    const viewer = document.getElementById('viewer');
    let pdfDoc = null, pageNum = 1, pageCount = 0;
    const timePerPage = {};
    let pageStart = Date.now();
    let visible = true;
    let lastScrollPercent = 0;
    let scrollTimeout = null;

    // PDF.js setup
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://unpkg.com/pdfjs-dist@3.9.179/build/pdf.worker.min.js';

    function renderPage(num){
      pageStart = Date.now();
      pdfDoc.getPage(num).then(page => {
        const viewport = page.getViewport({scale:1.2});
        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        const ctx = canvas.getContext('2d');
        const renderTask = page.render({canvasContext: ctx, viewport: viewport});
        renderTask.promise.then(() => {
          viewer.innerHTML = '';
          viewer.appendChild(canvas);
          document.getElementById('pageInfo').textContent = num + ' / ' + pageCount;
          // notify server of page view
          sendBeacon({token: token, event_type: 'page_view', page: num});
        });
      });
    }

    function loadPdf(){
      fetch(pdfUrl).then(r => r.arrayBuffer()).then(buf => {
        return pdfjsLib.getDocument({data:buf}).promise;
      }).then(pdf => {
        pdfDoc = pdf; pageCount = pdf.numPages; renderPage(pageNum);
      }).catch(err => {
        viewer.innerHTML = '<div style="padding:20px;color:#fff;background:#b91c1c">Error loading PDF</div>';
      });
    }

    document.getElementById('next').addEventListener('click', () => {
      recordPageDuration();
      if (pageNum < pageCount) pageNum++; renderPage(pageNum);
    });
    document.getElementById('prev').addEventListener('click', () => {
      recordPageDuration();
      if (pageNum > 1) pageNum--; renderPage(pageNum);
    });

    function recordPageDuration(){
      const now = Date.now();
      const dur = now - pageStart;
      timePerPage[pageNum] = (timePerPage[pageNum] || 0) + dur;
      // send small beacon with page time
      sendBeacon({token: token, event_type: 'page_time', page: pageNum, duration_ms: dur});
      pageStart = now;
    }

    function sendBeacon(payload){
      try{
        const blob = new Blob([JSON.stringify(payload)], {type: 'application/json'});
        navigator.sendBeacon(trackUrl, blob);
      }catch(e){
        // fallback
        try{ navigator.sendBeacon(trackUrl, JSON.stringify(payload)); }catch(_){}
      }
    }

    // Periodic heartbeat to record active viewing
    setInterval(() => {
      if (!visible) return;
      const now = Date.now();
      const dur = now - pageStart; // ms
      sendBeacon({token: token, event_type: 'heartbeat', page: pageNum, duration_ms: dur});
      pageStart = now;
    }, 5000);

    window.addEventListener('beforeunload', () => {
      recordPageDuration();
      sendBeacon({token: token, event_type: 'closed'});
    });

    // Visibility handling
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        visible = false; recordPageDuration(); sendBeacon({token: token, event_type: 'inactive'});
      } else {
        visible = true; pageStart = Date.now(); sendBeacon({token: token, event_type: 'active'});
      }
    });

    // Scroll tracking (debounced)
    viewer.addEventListener('scroll', () => {
      if (scrollTimeout) clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        const el = viewer.firstElementChild;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const vh = viewer.clientHeight;
        // percent of canvas visible from top
        const visibleHeight = Math.max(0, Math.min(rect.bottom, vh) - Math.max(rect.top, 0));
        const percent = Math.round((visibleHeight / rect.height) * 100);
        if (percent !== lastScrollPercent) {
          lastScrollPercent = percent;
          sendBeacon({token: token, event_type: 'scroll', page: pageNum, percent: percent});
        }
      }, 250);
    }, {passive:true});

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') { recordPageDuration(); if (pageNum < pageCount) pageNum++; renderPage(pageNum); }
      if (e.key === 'ArrowLeft')  { recordPageDuration(); if (pageNum > 1) pageNum--; renderPage(pageNum); }
    });

    // Initial load
    loadPdf();
    // Record initial open
    navigator.sendBeacon(trackUrl, JSON.stringify({token: token, event_type: 'open'}));
  </script>
</body>
</html>
