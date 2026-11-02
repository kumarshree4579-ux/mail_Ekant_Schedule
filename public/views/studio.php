<?php
/**
 * View File: public/views/studio.php
 * Included by StudioController.php
 * Variables provided: $availableTemplates, $placeholderFields, $statusMessage
 * Constants provided: APP_EMAIL, APP_PASSWORD, ADMIN_EMAIL, OTP_EXPIRY, SESSION_TTL, REFRESH_TTL, MAX_FAILS, BLOCK_TIME, CLD_CLOUD, CLD_PRESET, CLD_API_KEY, DB_PATH
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Studio Pro</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📬</text></svg>">
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .scroller { overflow-y: auto; scrollbar-width: thin; }
        .scroller::-webkit-scrollbar { width: 6px; }
        .scroller::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
        .canvas-area { outline: none; min-height: 800px; padding: 2rem; background: #ffffff; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        #proposal_canvas h1, #proposal_canvas h2, #proposal_canvas h3, #proposal_canvas h4, #proposal_canvas h5, #proposal_canvas h6,
        #proposal_canvas p, #proposal_canvas ul, #proposal_canvas ol, #proposal_canvas li, #proposal_canvas table, #proposal_canvas blockquote,
        #proposal_canvas pre, #proposal_canvas a, #proposal_canvas b, #proposal_canvas strong, #proposal_canvas i, #proposal_canvas em {
            all: revert;
        }
        .canvas-area:focus { outline: 2px solid #3b82f6; }
        .preview-active { background-color: #f3f4f6; cursor: not-allowed; opacity: 0.9; }
        img.img-selected { outline: 3px solid #3b82f6; outline-offset: 2px; }
        #ctx-menu { position: fixed; background: #1e293b; color: white; border-radius: 0.375rem; padding: 0.5rem 0; z-index: 9999; display: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); min-width: 160px; }
        #ctx-menu button { display: block; width: 100%; text-align: left; padding: 0.5rem 1rem; color: #e2e8f0; font-size: 0.875rem; border: none; background: transparent; cursor: pointer; transition: background 0.2s; }
        #ctx-menu button:hover { background: #334155; color: #fff; }
        #ctx-menu hr { border: 0; border-top: 1px solid #475569; margin: 0.25rem 0; }
        #img-panel { position: fixed; background: #1e293b; color: white; border-radius: 0.5rem; padding: 1rem; z-index: 9998; width: 240px; display: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); }
        #gallery-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 10000; display: none; align-items: center; justify-content: center; }
        #gallery-inner { background: white; border-radius: 0.5rem; width: 90%; max-width: 800px; max-height: 80vh; display: flex; flex-direction: column; overflow: hidden; }
        .gallery-thumb { cursor: pointer; border-radius: 0.375rem; overflow: hidden; border: 2px solid transparent; transition: all 0.2s; position: relative; }
        .gallery-thumb:hover { border-color: #3b82f6; transform: scale(1.05); }
        .gallery-thumb img { width: 100%; height: 120px; object-fit: cover; }
        .gallery-thumb .del-btn { position: absolute; top: 4px; right: 4px; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; border: none; cursor: pointer; }
        .tb-btn { padding: 0.25rem 0.5rem; font-size: 0.875rem; border-radius: 0.25rem; background: #f1f5f9; color: #334155; transition: all 0.2s; cursor: pointer; border: 1px solid #cbd5e1; }
        .tb-btn:hover { background: #e2e8f0; }
        
        .sec-hdr { font-weight: 600; font-size: 0.75rem; color: #1e293b; display: flex; justify-between: space-between; align-items: center; cursor: pointer; padding: 0.15rem 0.5rem; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: 0.375rem; margin-bottom: 0.125rem; border-left: 3px solid #3b82f6; user-select: none; }
        .sec-hdr:hover { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); }
        .sec-body { padding: 0.125rem; display: flex; flex-direction: column; gap: 0.125rem; }
        .field-label { font-size: 0.7rem; font-weight: 500; color: #3b82f6; margin-bottom: 1px; cursor: pointer; text-decoration: underline dotted; }
        .field-label:hover { color: #2563eb; }
        .field-input { width: 100%; border: 1px solid #e2e8f0; border-radius: 4px; padding: 3px 6px; font-size: 0.8rem; background: #fff; transition: border-color 0.2s; box-sizing: border-box; }
        .field-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59,130,246,0.1); }
        .upload-zone {
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            border:2px dashed #cbd5e1; border-radius:12px; padding:12px 10px; cursor:pointer;
            background:linear-gradient(180deg,#f8fafc,#f1f5f9); transition:all 0.2s ease;
            font-size:0.72rem; color:#475569; text-align:center; gap:4px; min-height:64px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color:#2563eb; background:linear-gradient(180deg,#eff6ff,#dbeafe); color:#1d4ed8;
            transform: translateY(-1px);
        }
        .upload-zone strong { font-size:0.76rem; }
        .upload-zone small { opacity:0.8; }
        .csv-input-meta {
            font-size:0.68rem; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:8px; padding:6px 8px; display:flex; justify-content:space-between; gap:8px;
        }
        .action-btn-green { background:#dcfce7; border:1px solid #86efac; color:#15803d; border-radius:4px; padding:6px 0; font-size:0.8rem; font-weight:600; cursor:pointer; transition:all 0.2s; width:100%; }
        .action-btn-green:hover { background:#bbf7d0; }
        .action-btn-yellow { background:#fef9c3; border:1px solid #fde047; color:#854d0e; border-radius:4px; padding:6px 0; font-size:0.8rem; font-weight:600; cursor:pointer; transition:all 0.2s; width:100%; }
        .action-btn-yellow:hover { background:#fef08a; }
        #toolbar_content.hidden { display: none !important; }
        .email-tag { display:inline-flex;align-items:center;gap:3px;background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd;border-radius:3px;padding:1px 5px;font-size:0.7rem;font-weight:500; }
        .email-tag .rm { cursor:pointer;font-size:0.65rem;color:#3b82f6;margin-left:2px;line-height:1; }
        .email-tag .rm:hover { color:#dc2626; }
        .pdf-btn-1 { display:inline-block;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:12px 28px;border-radius:6px;font-weight:700;font-size:14px;text-decoration:none;font-family:Arial,sans-serif;letter-spacing:0.3px; }
        .pdf-btn-2 { display:inline-block;background:#fff;color:#1d4ed8;padding:11px 26px;border-radius:6px;font-weight:700;font-size:14px;text-decoration:none;font-family:Arial,sans-serif;border:2px solid #1d4ed8; }
        /* =========================================================
   PDF MODAL — PREVENT CONTENT FROM BEING HIDDEN
   ========================================================= */

#pdf-modal {
    position: fixed !important;
    inset: 0 !important;
    z-index: 10001 !important;
    display: none;
    pointer-events: none;
}

#pdf-modal-panel {
    display: flex !important;
    flex-direction: column !important;
    box-sizing: border-box !important;

    width: min(520px, calc(100vw - 24px)) !important;
    height: min(680px, calc(100vh - 40px)) !important;

    max-height: calc(100vh - 40px) !important;
    min-height: 420px !important;

    overflow: hidden !important;

    position: fixed !important;
    right: 18px !important;
    top: 20px !important;

    pointer-events: auto !important;
}

/* Header + tabs must never shrink */
#pdf-modal-panel > div:first-child,
#pdf-modal-panel > div:nth-child(2) {
    flex-shrink: 0 !important;
}

/* Both tab panels */
#pdfpanel-attach,
#pdfpanel-link {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    box-sizing: border-box !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    -webkit-overflow-scrolling: touch !important;
}

/* Cloudinary Link tab */
#pdfpanel-link {
    display: none;
    flex-direction: column !important;
}

/* Inner Link tab content */
#pdfpanel-link > div {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}

/* Cloudinary upload cards */
#pdfpanel-link > div > div {
    max-width: 100% !important;
    box-sizing: border-box !important;
}

/* Long Cloudinary URLs must not expand the modal */
#pdf-manual-url,
#pdf-manual-name {
    max-width: 100% !important;
    box-sizing: border-box !important;
}

/* Gallery must stay inside the scroll area */
#pdf-gallery-panel {
    border: 1px solid #b32d0c !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

#pdf-gallery {
    max-width: 100% !important;
    box-sizing: border-box !important;
}

/* Mobile */
@media (max-width: 640px) {
    #pdf-modal-panel {
        width: calc(100vw - 16px) !important;
        max-width: calc(100vw - 16px) !important;
        height: calc(100vh - 20px) !important;
        max-height: calc(100vh - 20px) !important;
        top: 10px !important;
        right: 8px !important;
        min-width: 0 !important;
    }
}
        .pdf-btn-3 { display:inline-block;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;padding:12px 28px;border-radius:6px;font-weight:700;font-size:14px;text-decoration:none;font-family:Arial,sans-serif;border-left:4px solid #f59e0b; }

        /* Draggable placeholder chips in token view */
        .ph-chip {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            padding: 2px 8px;
            border-radius: 999px;
            margin: 0 2px;
            cursor: grab;
            user-select: none;
            box-shadow: 0 1px 4px rgba(37,99,235,0.25);
            border: 1.5px solid rgba(255,255,255,0.25);
            transition: opacity 0.15s, box-shadow 0.15s;
            vertical-align: middle;
        }
        .ph-chip:hover {
            box-shadow: 0 2px 8px rgba(37,99,235,0.45);
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
        }
        .ph-chip:active { cursor: grabbing; }
        .ph-chip[draggable="true"]:focus { outline: 2px solid #f59e0b; }

        /* Count badge on sidebar field labels */
        .ph-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 16px;
            height: 16px;
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col h-screen overflow-hidden text-sm">

    <header class="bg-slate-900 text-white p-3 flex-shrink-0">
        <div class="flex justify-between items-center">
            <div class="font-bold text-lg">⚡ Proposal Studio Pro
                </div>
                <small class="font-regular text-gray">V 1.0.0.8</small>
            <div class="flex items-center gap-4">
                <span class="text-slate-300"><?= htmlspecialchars(APP_EMAIL, ENT_QUOTES) ?></span>
                <button type="button" onclick="openSentEmailsModal()" class="text-orange-400 hover:text-orange-300 text-sm">📤 Resend</button>
                <button type="button" onclick="openBulkModal()" class="text-yellow-400 hover:text-yellow-300 text-sm">📊 Bulk Send</button>
                <button type="button" onclick="openSavedListsModal()" class="text-blue-400 hover:text-blue-300 text-sm">💾 Saved Data</button>
                <button type="button" onclick="openSchedulerModal()" class="text-teal-400 hover:text-teal-300 text-sm">🕒 Scheduler</button>
                <a href="<?= BASE_PATH ?>/tracking" target="_blank" class="text-green-400 hover:text-green-300 text-sm">📊 Tracking</a>
                <button type="button" onclick="openCallbacksPanel()" class="text-purple-400 hover:text-purple-300 text-sm">📞 Callbacks</button>
                <a href="<?= BASE_PATH ?>/admin" class="text-blue-400 hover:text-blue-300 text-sm">Admin</a>
                <a href="<?= BASE_PATH ?>/logout" class="text-red-400 hover:text-red-300 text-sm">Logout</a>
            </div>
        </div>
        <?php if (!empty($statusMessage)): ?>
            <div class="mt-2 p-2 rounded text-xs <?= strpos(strtolower($statusMessage), 'error') !== false ? 'bg-red-500/20 text-red-200' : 'bg-green-500/20 text-green-200' ?>">
                <?= htmlspecialchars($statusMessage, ENT_QUOTES) ?>
            </div>
        <?php endif; ?>
    </header>

    
    <main class="flex flex-row flex-1 overflow-hidden">
        
        <aside class="w-[320px] flex-shrink-0 bg-slate-50 border-r border-slate-200 flex flex-col scroller p-2">
            
            <!-- Top Action -->
            <div class="mb-1.5">
                <button type="button" class="w-full bg-blue-50 hover:bg-blue-100 border border-blue-200 text-blue-700 rounded-md py-1.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2" onclick="openSentEmailsModal()">
                    🔍 Search
                </button>
            </div>

            <!-- SECTION 0: Recipient -->
            <div>
                <div class="sec-hdr" onclick="this.nextElementSibling.classList.toggle('hidden')">📧 Recipient <span>▼</span></div>
                <div class="sec-body">
                    <div class="mb-0.5">
                        <label class="field-label">To (separate multiple with , or ; or /)</label>
                        <div id="email_tags_container" style="display:flex;flex-wrap:wrap;gap:2px;border:1px solid #e2e8f0;border-radius:4px;padding:2px 4px;background:#fff;min-height:28px;cursor:text;" onclick="document.getElementById('email_tag_input').focus()">
                            <input type="text" id="email_tag_input" style="border:none;outline:none;font-size:0.8rem;min-width:120px;flex:1;padding:2px;" placeholder="email@example.com">
                        </div>
                        <input type="hidden" id="client_email" value="">
                    </div>
                    <div>
                        <input type="text" id="email_subject" class="field-input" value="Dish Network Exclusive Discount Offer for {{Hotel_Name}} {{City}}, {{State}} {{Zip}}">
                        
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Property Data -->
            <div>
                <div class="sec-hdr" onclick="this.nextElementSibling.classList.toggle('hidden')">
                    <span>🏨 Property Data</span> 
                    <div>
                        <button id="toggle_ph_view" title="Toggle tokens / values" style="background:none;border:none;cursor:pointer;font-size:14px;padding:0 4px;opacity:1;line-height:1;" onclick="togglePhView(event)">👁</button>
                        <span>▼</span>
                    </div>
                </div>
                <div class="sec-body">
                    <?php 
                    $groups = [
                        'Property' => ['Hotel_Name', 'Address', ['City', 'State', 'Zip']],
                        'Contact'  => [['Rooms', 'Extra'], 'Email', 'Hotel_Number', 'Contact_Person', 'First_Name' ],
                        'Deal'     => ['Service_Provider', 'System','offeredProgramming',  'Bill', 'Upfront']
                    ];
                    $rendered = [];

                    if (!function_exists('renderStudioField')) {
                        function renderStudioField($key, $label) {
                            $type = ($key === 'Service_Provider') ? 'provider' : (($key === 'System') ? 'system' : 'programming');
                            $html = '<label class="field-label whitespace-nowrap overflow-hidden text-ellipsis" title="Click to insert {{' . $key . '}} into focused field" onclick="insertField(\'{{' . $key . '}}\')">'
                                . htmlspecialchars($label, ENT_QUOTES)
                                . ' <span id="ph_cnt_' . $key . '" class="ph-count-badge" style="display:none;background:#3b82f6;color:#fff;font-size:9px;font-weight:700;border-radius:9px;padding:1px 5px;margin-left:3px;vertical-align:middle;"></span>'
                                . '</label>';
                            if (in_array($key, ['Service_Provider', 'System', 'offeredProgramming'], true)) {
                                $html .= '<div class="relative flex items-center">';
                                $html .= '<input type="text" id="p_' . $key . '" list="dl_' . $key . '" class="field-input flex-1 pr-14" placeholder="Select or type..." onchange="onStudioFieldChange(\'' . $key . '\')" onkeydown="if(event.key===\'Enter\'){event.preventDefault();inlineAddOption(\'' . $type . '\',\'dl_' . $key . '\',\'p_' . $key . '\');}">';
                                $html .= '<datalist id="dl_' . $key . '"></datalist>';
                                $html .= '<div class="absolute right-1 flex gap-0.5">';
                                $html .= '<button type="button" onclick="inlineAddOption(\'' . $type . '\',\'dl_' . $key . '\',\'p_' . $key . '\')" class="text-slate-400 hover:text-blue-600 transition-colors bg-transparent border-none cursor-pointer p-1" title="Save this option"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg></button>';
                                $html .= '<button type="button" onclick="inlineDeleteOption(\'' . $type . '\',\'p_' . $key . '\')" class="text-slate-400 hover:text-red-500 transition-colors bg-transparent border-none cursor-pointer p-1" title="Delete this option"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>';
                                $html .= '</div></div>';
                            } else {
                                $html .= '<input type="text" id="p_' . $key . '" class="field-input">';
                            }
                            return $html;
                        }
                    }

                    foreach($groups as $grp => $keys) {
                        if ($grp !== 'Property') {
                            echo '<hr style="border:0;border-top:1px solid #e2e8f0;margin:2px 0;">';
                        }
                        foreach($keys as $key_or_group) {
                            if (is_array($key_or_group)) {
                                echo '<div class="flex flex-row gap-1 mb-0.5">';
                                foreach($key_or_group as $key) {
                                    if (isset($placeholderFields[$key])) {
                                        $rendered[] = $key;
                                        echo '<div class="flex flex-col gap-0 flex-1 min-w-0">';
                                        echo renderStudioField($key, $placeholderFields[$key]);
                                        echo '</div>';
                                    }
                                }
                                echo '</div>';
                            } else {
                                $key = $key_or_group;
                                if (isset($placeholderFields[$key])) {
                                    $rendered[] = $key;
                                    echo '<div class="flex flex-col gap-0 mb-0.5">';
                                    echo renderStudioField($key, $placeholderFields[$key]);
                                    echo '</div>';
                                }
                            }
                        }
                    }
                    foreach($placeholderFields as $key => $label) {
                        if (!in_array($key, $rendered)) {
                            echo '<div class="flex flex-col gap-0 mb-0.5">';
                            echo renderStudioField($key, $label);
                            echo '</div>';
                        }
                    }
                    ?>
                    
                    <div id="track_id_live_alert" class="hidden mt-2 p-2.5 rounded-lg border text-xs leading-snug"></div>
                </div>
            </div>



            <!-- SECTION 4: Send / Draft -->
            <div>
                <!-- <div class="sec-hdr">✉️ Send</div> -->
                 <hr>
                <div class="sec-body">
                    <div id="send_status" class="hidden text-xs p-2 rounded font-medium text-center"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" id="draft_btn" class="action-btn-yellow" onclick="processEmail(true)">📝 Draft</button>
                        <button type="button" id="send_btn" class="action-btn-green" onclick="processEmail(false)">🚀 Send</button>
                    </div>
                    <div class="relative" id="attach_dropdown_container">
                        <button type="button" class="upload-zone" style="height:36px;flex-direction:row;gap:6px;margin-top:6px;width:100%;" onclick="toggleAttachDropdown(event)">
                            <span style="font-size:1rem;">📎</span>
                            <span id="attachment_label_text">Attach</span>
                        </button>
                        <div id="attach_dropdown" class="hidden absolute top-full left-0 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg z-50 overflow-hidden text-sm flex-col">
                            <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-100 text-slate-700 border-b border-slate-100" onclick="attachFromLibrary()">📚 Browse Library</button>
                            <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-100 text-slate-700" onclick="document.getElementById('sys_attach_input').click()">💻 This PC</button>
                        </div>
                        <input type="file" id="sys_attach_input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp" multiple style="display:none;" onchange="onAttachFileChange(this)">
                    </div>
                    <div id="selected-attachments" style="display:flex;flex-direction:column;gap:6px;max-height:170px;overflow:auto;padding:4px 0;margin-top:6px;"></div>
                </div>
            </div>

        </aside>

        <section class="flex-1 flex flex-col bg-slate-100 relative">
            
           <div class="sticky top-0 bg-white border-b border-slate-200 z-10 shadow-sm">
    <!-- Tools Row (collapsible) -->
    <div id="toolbar_content" class="flex flex-wrap gap-1.5 items-center px-2 py-1.5 border-b border-slate-100">
        <!-- Formatting Tools -->
        <select onchange="execCmd('fontName', this.value)" class="border border-slate-200 rounded p-1 text-sm bg-slate-50 text-slate-700 outline-none focus:border-blue-500">
            <option value="Arial">Arial</option>
            <option value="Georgia">Georgia</option>
            <option value="Verdana">Verdana</option>
            <option value="Times New Roman">Times New Roman</option>
            <option value="Courier New">Courier New</option>
        </select>
        
        <select onchange="applyFontSize(this.value)" class="border border-slate-200 rounded p-1 text-sm bg-slate-50 text-slate-700 outline-none focus:border-blue-500">
            <option value="1">XS</option>
            <option value="2">S</option>
            <option value="3" selected>M</option>
            <option value="4">L</option>
            <option value="5">XL</option>
            <option value="6">XXL</option>
        </select>
        
        <div class="w-px h-6 bg-slate-300 mx-1"></div>
        
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded font-bold font-serif w-8 h-8 flex items-center justify-center" onclick="execCmd('bold')">B</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded italic font-serif w-8 h-8 flex items-center justify-center" onclick="execCmd('italic')">I</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded underline font-serif w-8 h-8 flex items-center justify-center" onclick="execCmd('underline')">U</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded line-through font-serif w-8 h-8 flex items-center justify-center" onclick="execCmd('strikeThrough')">S</button>
        
        <div class="w-px h-6 bg-slate-300 mx-1"></div>
        
        <div class="flex items-center gap-1 bg-slate-50 border border-slate-200 rounded px-1 h-8">
            <span class="text-xs font-bold text-slate-600">A</span>
            <input type="color" id="text_color" value="#000000" class="w-5 h-5 p-0 border-0 cursor-pointer bg-transparent">
        </div>
        <div class="flex items-center gap-1 bg-slate-50 border border-slate-200 rounded px-1 h-8">
            <span class="text-xs font-bold text-slate-600">H</span>
            <input type="color" id="highlight_color" value="#fef08a" class="w-5 h-5 p-0 border-0 cursor-pointer bg-transparent">
        </div>
        <div class="flex items-center gap-1 bg-slate-50 border border-slate-200 rounded px-1 h-8">
            <span class="text-xs font-bold text-slate-600">BG</span>
            <input type="color" id="canvas_bg" value="#ffffff" class="w-5 h-5 p-0 border-0 cursor-pointer bg-transparent">
        </div>
        
        <div class="w-px h-6 bg-slate-300 mx-1"></div>
        
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded w-8 h-8 flex items-center justify-center" onclick="execCmd('justifyLeft')">⇤</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded w-8 h-8 flex items-center justify-center" onclick="execCmd('justifyCenter')">↔</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded w-8 h-8 flex items-center justify-center" onclick="execCmd('justifyRight')">⇥</button>
        
        <div class="w-px h-6 bg-slate-300 mx-1"></div>
        
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded px-2 py-1 text-sm flex items-center" onclick="execCmd('insertUnorderedList')">• List</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded px-2 py-1 text-sm flex items-center" onclick="execCmd('insertOrderedList')">1. List</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded w-8 h-8 flex items-center justify-center" onclick="execCmd('indent')">⇥</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded w-8 h-8 flex items-center justify-center" onclick="execCmd('outdent')">⇤</button>

        <div class="w-px h-6 bg-slate-300 mx-1"></div>
        
        <button type="button" class="tb-btn bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-100 rounded px-2 py-1 text-sm transition-colors" onclick="insertTableComponent()">+ Table</button>
        <button type="button" class="tb-btn bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-100 rounded px-2 py-1 text-sm transition-colors" onclick="insertDividerComponent()">— Divider</button>
        <button type="button" class="tb-btn bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-100 rounded px-2 py-1 text-sm transition-colors" onclick="openGallery()">🖼️ Insert Image</button>
        
        <div class="relative group" id="pdf_link_dropdown_container">
            <button type="button" class="tb-btn bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-100 rounded px-2 py-1 text-sm transition-colors flex items-center gap-1" onclick="togglePdfLinkDropdown(event)">🔗 Insert PDF Link</button>
            <div id="pdf_link_dropdown" class="hidden absolute top-full left-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 w-40 overflow-hidden text-sm flex-col">
                <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-100 text-slate-700 border-b border-slate-100" onclick="insertPdfLinkFromLibrary()">📚 Browse Library</button>
                <button type="button" class="w-full text-left px-3 py-2 hover:bg-slate-100 text-slate-700" onclick="document.getElementById('cld_pdf_input').click()">☁️ Upload New</button>
            </div>
        </div>

        <button type="button" class="tb-btn bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded px-2 py-1 text-sm transition-colors flex items-center gap-1" onclick="insertCallbackLink()">📞 Callback Link</button>
        
        <div class="w-px h-6 bg-slate-300 mx-1"></div>
        
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded w-8 h-8 flex items-center justify-center" onclick="customUndo()">↩</button>
        <button type="button" class="tb-btn text-slate-700 hover:bg-slate-100 rounded w-8 h-8 flex items-center justify-center" onclick="customRedo()">↪</button>

        <div class="w-px h-6 bg-slate-300 mx-1"></div>

        <!-- Template Tools -->
        <div class="flex items-center gap-2">
            <span class="text-slate-500 text-sm" title="Templates">🎨</span>
            <select id="template_select" class="bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded focus:ring-blue-500 focus:border-blue-500 py-1 px-2 outline-none cursor-pointer w-36" onchange="loadSelectedTemplate()">
                  <?php foreach($availableTemplates as $k => $t): ?>
                    <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>"><?= htmlspecialchars($t['title'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <form id="tpl_upload_form" method="POST" enctype="multipart/form-data" class="m-0 flex">
            <input type="hidden" name="action" value="upload_template">
            <label class="cursor-pointer flex items-center gap-1 text-slate-700 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200 px-2 py-1 rounded transition-colors text-sm font-medium shadow-sm">
                <span>📄</span>
                <span>Upload</span>
                <input type="file" id="template_file_input" name="template_file" accept=".html" class="hidden" required>
            </label>
        </form>

        <div class="flex items-center relative">
            <div class="flex shadow-sm rounded-md">
                <input type="text" id="new_template_name" placeholder="Save as..." class="bg-slate-50 border border-slate-200 border-r-0 text-slate-700 text-sm rounded-l-md focus:ring-blue-500 focus:border-blue-500 py-1 px-2 w-32 outline-none placeholder-slate-400">
                <button type="button" id="save_tpl_btn" onclick="saveNewTemplate(null)" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-r-md text-sm font-medium transition-colors flex items-center gap-1" title="Choose whether to save with placeholders or values">
                    💾 Save
                </button>
            </div>
            <button type="button" onclick="deleteTemplate()" class="ml-2 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-md text-sm font-medium transition-colors" title="Delete selected template">
                🗑 Delete
            </button>
            <button type="button" onclick="resetStudioState()" class="ml-2 bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-1 rounded-md text-sm font-medium transition-colors" title="Ctrl+Q to reset">
                ↺ Reset
            </button>
            
            <div id="save_tpl_status" class="hidden absolute top-full left-0 mt-1 text-xs px-2 py-1 rounded bg-white border border-slate-200 text-slate-700 z-50 shadow-lg whitespace-nowrap"></div>
        </div>
    </div>

    <div id="delete_confirm_banner" class="hidden fixed bottom-4 right-4 p-3 bg-red-50 text-red-700 border border-red-200 rounded text-xs z-50 shadow-xl"></div>
</div>

            <div id="canvas_statusbar" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:2px 12px;font-size:0.65rem;color:#94a3b8;display:flex;gap:16px;flex-shrink:0;align-items:center;">
                <span id="preview_alert" class="hidden" style="color:#15803d;font-weight:bold;background:#dcfce7;padding:2px 6px;border-radius:3px;">👁️ PREVIEW MODE — Editing disabled</span>
                <span>Words: <span id="sb_words">0</span></span>
                <span>Chars: <span id="sb_chars">0</span></span>
                <span id="sb_unsaved" style="color:#f59e0b;display:none;">● Unsaved changes</span>
            </div>
            <div id="subject_preview" style=" font-size:1.5rem; color:#64748b; padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-top:6px; word-break:break-word; min-height:20px; text-align:center; line-height:1.4; box-sizing:border-box; relative "> 
                <span style="color:#94a3b8;">Preview: </span> <span id="subject_preview_text"></span> 
                <div id="sent_date_indicator" style="display:none; font-size:0.9rem; color:#0369a1; background:#e0f2fe; border-radius:4px; padding:4px 8px; margin-top:8px; border:1px solid #bae6fd; font-weight:bold;"></div>
            </div>

            <div id="canvas_scroll_area" class="flex-1 overflow-y-auto p-8 scroller bg-slate-200">
                <div id="proposal_canvas" contenteditable="true" class="canvas-area mx-auto max-w-4xl text-base text-gray-800 font-sans" style="background-color: #ffffff;">
                    <div style="text-align: center; color: #9ca3af; padding: 50px;">Select a template or start typing here...</div>
                </div>
            </div>

        </section>
    </main>

    <div id="ctx-menu">
        <button onclick="ctxReplaceImage()">🔄 Replace Image</button>
        <button onclick="ctxOpenGallery()">🖼️ Replace from Gallery</button>
        <hr>
        <button onclick="ctxOpenResizePanel()">↔️ Resize / Style</button>
        <hr>
        <button onclick="ctxDeleteImage()" class="text-red-400 hover:bg-red-500 hover:text-white">🗑️ Delete Image</button>
    </div>

    <div id="ctx-menu-pdf" style="position:fixed;background:#1e293b;color:white;border-radius:6px;padding:4px 0;z-index:9999;display:none;box-shadow:0 10px 15px -3px rgba(0,0,0,0.5);min-width:170px;">
        <button onclick="ctxOpenPdf()" style="display:block;width:100%;text-align:left;padding:6px 12px;color:#60a5fa;font-size:0.8rem;border:none;background:transparent;cursor:pointer;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">📄 Open PDF (No Token)</button>
        <div style="padding:6px 12px 4px;font-size:0.65rem;color:#94a3b8;font-weight:700;letter-spacing:0.05em;">CHANGE BUTTON STYLE</div>
        <button onclick="applyPdfBtnStyle('1')" style="display:block;width:100%;text-align:left;padding:6px 12px;color:#e2e8f0;font-size:0.8rem;border:none;background:transparent;cursor:pointer;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">🔴 Red Gradient</button>
        <button onclick="applyPdfBtnStyle('2')" style="display:block;width:100%;text-align:left;padding:6px 12px;color:#e2e8f0;font-size:0.8rem;border:none;background:transparent;cursor:pointer;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">🔵 Blue Outline</button>
        <button onclick="applyPdfBtnStyle('3')" style="display:block;width:100%;text-align:left;padding:6px 12px;color:#e2e8f0;font-size:0.8rem;border:none;background:transparent;cursor:pointer;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">⚫ Dark + Gold</button>
        <button onclick="applyPdfBtnStyle('4')" style="display:block;width:100%;text-align:left;padding:6px 12px;color:#e2e8f0;font-size:0.8rem;border:none;background:transparent;cursor:pointer;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">🟢 Green Success</button>
        <button onclick="applyPdfBtnStyle('5')" style="display:block;width:100%;text-align:left;padding:6px 12px;color:#e2e8f0;font-size:0.8rem;border:none;background:transparent;cursor:pointer;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">🟣 Purple Premium</button>
        <hr style="border:0;border-top:1px solid #475569;margin:4px 0;">
        <button onclick="ctxDeletePdfBtn()" style="display:block;width:100%;text-align:left;padding:6px 12px;color:#f87171;font-size:0.8rem;border:none;background:transparent;cursor:pointer;" onmouseover="this.style.background='#ef4444';this.style.color='#fff'" onmouseout="this.style.background='transparent';this.style.color='#f87171'">🗑️ Remove Button</button>
    </div>

    <div id="img-panel">
        <div class="flex justify-between items-center mb-4 border-b border-slate-600 pb-2">
            <h3 class="font-bold text-sm">Image Properties</h3>
            <button onclick="closeImgPanel()" class="text-slate-400 hover:text-white">✕</button>
        </div>
        <div class="flex flex-col gap-3 text-xs">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-slate-300 mb-1">Width</label>
                    <input type="text" id="img_width" class="w-full bg-slate-700 text-white border-0 rounded p-1" placeholder="e.g. 100% or 300px">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1">Height</label>
                    <input type="text" id="img_height" class="w-full bg-slate-700 text-white border-0 rounded p-1" placeholder="auto or px">
                </div>
            </div>
            <div>
                <label class="block text-slate-300 mb-1">Border Radius (px)</label>
                <input type="number" id="img_radius" class="w-full bg-slate-700 text-white border-0 rounded p-1" placeholder="0">
            </div>
            <div>
                <label class="block text-slate-300 mb-1">Alignment</label>
                <div class="flex bg-slate-700 rounded overflow-hidden">
                    <button class="flex-1 py-1 text-slate-300 hover:text-white hover:bg-slate-600" onclick="setImgAlign('left')">Left</button>
                    <button class="flex-1 py-1 text-slate-300 hover:text-white hover:bg-slate-600 border-l border-r border-slate-600" onclick="setImgAlign('center')">Center</button>
                    <button class="flex-1 py-1 text-slate-300 hover:text-white hover:bg-slate-600" onclick="setImgAlign('right')">Right</button>
                </div>
            </div>
            <div>
                <label class="block text-slate-300 mb-1">Object Fit</label>
                <select id="img_fit" class="w-full bg-slate-700 text-white border-0 rounded p-1">
                    <option value="cover">cover</option>
                    <option value="contain">contain</option>
                    <option value="fill">fill</option>
                    <option value="none">none</option>
                </select>
            </div>
            <div>
                <label class="block text-slate-300 mb-1">Box Shadow</label>
                <select id="img_shadow" class="w-full bg-slate-700 text-white border-0 rounded p-1">
                    <option value="None">None</option>
                    <option value="Light">Light</option>
                    <option value="Medium">Medium</option>
                    <option value="Heavy">Heavy</option>
                </select>
            </div>
            <button onclick="applyImgProps()" class="w-full bg-blue-500 hover:bg-blue-600 text-white rounded p-1.5 mt-2 font-bold">✓ Apply</button>
        </div>
    </div>

    <div id="gallery-modal">
        <div id="gallery-inner">
            <div class="flex justify-between items-center bg-slate-800 text-white p-3">
                <div class="font-bold">🖼️ Asset Gallery <span id="gallery-count" class="text-slate-400 font-normal ml-2 text-xs"></span> </div>
                <div>
                    <button onclick="insertImageFromUrl()" class="text-slate-300 hover:text-white text-lg leading-none cursor-pointer">💻+ Add Image</button>
                </div>
                <button onclick="closeGallery()" class="text-slate-300 hover:text-white text-lg leading-none cursor-pointer">✕</button>
            </div>
            <div id="gallery-grid" class="flex-1 overflow-y-auto p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 bg-slate-100">
            </div>
            <div class="bg-slate-200 p-2 text-center text-xs text-slate-600 border-t border-slate-300">
                Click image to insert at cursor. Right-click image in canvas to replace.
            </div>
        </div>
    </div>

    <!-- Sent Emails Modal -->
    <div id="sent-emails-modal" style="position:fixed;inset:0;z-index:10001;display:none;pointer-events:none;">
        <div id="sent-emails-panel" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(600px,calc(100vw - 24px));height:min(70vh,600px);background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 25px 80px rgba(15,23,42,0.5);border:1px solid rgba(148,163,184,0.4);pointer-events:auto;display:flex;flex-direction:column;">
            <div id="sent-emails-header" style="background:#0f172a;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;cursor:grab;">
                <span style="color:#fff;font-weight:700;font-size:0.88rem;pointer-events:none;">📤 Resend Sent Emails</span>
                <button onclick="closeSentEmailsModal()" style="background:none;border:none;color:#94a3b8;font-size:1.2rem;cursor:pointer;line-height:1;">✕</button>
            </div>
            <div style="padding:16px;flex-shrink:0;border-bottom:1px solid #e2e8f0;">
                <input type="text" id="sent_emails_search" onkeyup="filterSentEmails()" placeholder="Search by subject or email..." style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem;outline:none;">
            </div>
            <div id="sent_emails_list" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;">
                <div style="text-align:center;color:#64748b;font-size:0.85rem;">Loading...</div>
            </div>
        </div>
    </div>


    <!-- Saved Bulk Lists Modal -->
    <div id="saved-lists-modal" style="position:fixed;inset:0;z-index:10001;display:none;background:rgba(15,23,42,0.18);pointer-events:none;">
        <div style="position:absolute;right:18px;top:80px;width:min(600px,calc(100vw - 24px));height:min(78vh,760px);background:#fff;border-radius:14px 14px 0 0;overflow:hidden;box-shadow:0 25px 80px rgba(15,23,42,0.5);border:1px solid rgba(148,163,184,0.4);resize:both;min-width:400px;min-height:420px;max-width:calc(100vw - 24px);max-height:calc(100vh - 40px);pointer-events:auto;display:flex;flex-direction:column;">
            <div style="background:#0f172a;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;cursor:move;user-select:none;touch-action:none;flex-shrink:0;">
                <span style="color:#fff;font-weight:700;font-size:0.88rem;">💾 Saved Data</span>
                <button onclick="closeSavedListsModal()" style="background:none;border:none;color:#94a3b8;font-size:1.2rem;cursor:pointer;line-height:1;">✕</button>
            </div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:10px;height:calc(100% - 54px);overflow:auto;box-sizing:border-box;">
                <div id="saved_lists_container" style="flex:1;overflow-y:auto;">
                    <div style="text-align:center;color:#64748b;font-size:0.85rem;">Loading lists...</div>
                </div>
                <div id="saved_list_items_container" class="hidden" style="flex:1;display:flex;flex-direction:column;gap:10px;overflow:hidden;">
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button onclick="backToSavedLists()" class="tb-btn bg-slate-200" style="padding:4px 8px;">← Back</button>
                        <input type="text" id="saved_items_filter" placeholder="Filter this page by email..." style="flex:1;padding:4px 8px;border:1px solid #cbd5e1;border-radius:4px;" onkeyup="filterSavedItems()">
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" id="select_all_saved_items" onchange="toggleAllSavedItems(this.checked)"> <label for="select_all_saved_items" style="font-size:0.8rem;font-weight:bold;">Select All on Page</label>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.75rem;color:#475569;">
                        <button type="button" id="saved_items_prev" class="tb-btn bg-slate-100" style="padding:4px 8px;" onclick="changeSavedItemsPage(-1)">« Previous</button>
                        <span id="saved_items_page_info">Page 1</span>
                        <button type="button" id="saved_items_next" class="tb-btn bg-slate-100" style="padding:4px 8px;" onclick="changeSavedItemsPage(1)">Next »</button>
                    </div>
                    <div style="font-size:0.68rem;color:#64748b;">Selections apply to this page only. Changing pages clears the current selection.</div>
                    <div id="saved_items_list" style="flex:1;overflow-y:auto;border:1px solid #cbd5e1;border-radius:4px;padding:8px;font-size:0.8rem;"></div>
                    <button type="button" class="bg-green-100 border border-green-300 text-green-800 rounded p-2 text-sm font-bold hover:bg-green-200" onclick="sendSelectedSavedItems()">🚀 Send Selected</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Bulk Send Modal -->
    <div id="bulk-modal" style="position:fixed;inset:0;z-index:10001;display:none;background:rgba(15,23,42,0.18);pointer-events:none;">
        <div id="bulk-panel" style="position:absolute;right:18px;top:80px;width:min(420px,calc(100vw - 24px));height:min(78vh,760px);background:#fff;border-radius:14px 14px 0 0;overflow:hidden;box-shadow:0 25px 80px rgba(15,23,42,0.5);border:1px solid rgba(148,163,184,0.4);resize:both;min-width:320px;min-height:420px;max-width:calc(100vw - 24px);max-height:calc(100vh - 40px);pointer-events:auto;display:flex;flex-direction:column;">
            <div id="bulk-panel-header" style="background:#0f172a;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;cursor:move;user-select:none;touch-action:none;flex-shrink:0;">
                <span style="color:#fff;font-weight:700;font-size:0.88rem;">📊 Bulk Send</span>
                <button onclick="closeBulkModal()" style="background:none;border:none;color:#94a3b8;font-size:1.2rem;cursor:pointer;line-height:1;">✕</button>
            </div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:10px;height:calc(100% - 54px);overflow:auto;box-sizing:border-box;min-height:0;flex:1 1 auto;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <div>
                        <div style="font-size:0.72rem;font-weight:700;color:#334155;">Bulk CSV Import</div>
                        <div style="font-size:0.64rem;color:#64748b;">Preview first 250 rows; sending and saving process the full file</div>
                    </div>
                    <button type="button" class="tb-btn bg-slate-100" style="padding:7px 10px;" onclick="downloadCSVSample()">⬇ Sample</button>
                </div>
                <div class="csv-input-meta">
                    <span>Max size: 50 MB</span>
                    <span>Preview: 250 rows / 1M chars · Max row: 64K chars</span>
                </div>
                <label id="csv_upload_zone" class="upload-zone" style="height:62px;">
                    <strong id="csv_label_text">Choose CSV file</strong>
                    <small>or drag &amp; drop</small>
                    <input type="file" id="csv_file" accept=".csv" style="display:none;">
                </label>
                <div id="csv_status" class="hidden text-[11px] px-2 py-1.5 rounded border border-blue-200 bg-blue-50 text-blue-700 font-medium"></div>
                <div id="csv_errors" class="hidden bg-red-50 border border-red-200 rounded p-2 text-red-700 max-h-32 overflow-y-auto" style="font-size:0.75rem;"></div>
                <div id="csv_preview_controls" class="hidden flex justify-between items-center bg-slate-200 p-1.5 rounded text-xs font-bold">
                    <button type="button" class="tb-btn py-0 px-2 bg-white" onclick="prevCSV()">«</button>
                    <span id="csv_record_count"></span>
                    <button type="button" class="tb-btn py-0 px-2 bg-white" onclick="nextCSV()">»</button>
                </div>
                <div id="csv_mapping_container" class="hidden border border-slate-200 bg-slate-50 rounded p-2">
                    <div style="font-size:0.68rem;font-weight:700;color:#334155;margin-bottom:6px;">Map CSV columns</div>
                    <div id="csv_mapping_fields" style="display:flex;flex-direction:column;gap:6px;"></div>
                </div>
                <div id="csv_actions" class="hidden grid grid-cols-5 gap-1">
                    <button type="button" class="bg-red-100 border border-red-300 text-red-700 rounded p-1 text-xs font-bold w-full hover:bg-red-200" onclick="exitPreview()">Exit</button>
                    <button type="button" id="bulk_draft_btn" class="bg-yellow-100 border border-yellow-300 text-yellow-800 rounded p-1 text-xs font-bold w-full hover:bg-yellow-200" onclick="processBulkEmails(true)">📝 Draft</button>
                    <button type="button" id="bulk_send_btn" class="bg-green-100 border border-green-300 text-green-800 rounded p-1 text-xs font-bold w-full hover:bg-green-200 disabled:opacity-60 disabled:cursor-not-allowed" onclick="processBulkEmails(false)">🚀 Send</button>
                    <button type="button" id="bulk_test_btn" class="bg-indigo-100 border border-indigo-300 text-indigo-800 rounded p-1 text-xs font-bold w-full hover:bg-indigo-200" onclick="sendBulkTestMail()">🧪 Test</button>
                    <button type="button" class="bg-blue-100 border border-blue-300 text-blue-800 rounded p-1 text-xs font-bold w-full hover:bg-blue-200" onclick="saveBulkList()">💾 Save</button>
                </div>
                <div id="bulk_progress" class="hidden text-xs p-2 rounded bg-slate-100 border border-slate-300 font-mono leading-relaxed break-words" style="min-height:42px; width:100%; box-sizing:border-box; margin-top:auto; flex-shrink:0;"></div>
            </div>
        </div>
    </div>

    <!-- PDF Library Modal (used for Browse) -->
    <div id="pdf-library-modal" style="position:fixed;inset:0;display:none;z-index:10001;pointer-events:none;">
        <div id="pdf-library-panel" class="pdf-modal-panel" style="background:#fff;border-radius:18px;width:min(520px,calc(100vw - 24px));height:min(680px,calc(100vh - 40px));overflow:hidden;box-sizing:border-box;pointer-events:auto;position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
            <!-- Header -->
            <div style="background:linear-gradient(135deg,#0f172a,#1e293b);padding:14px 18px;display:flex;justify-content:space-between;align-items:center; border-radius:18px 18px 0 0;flex-shrink:0;">
                <span style="color:#fff;font-weight:700;font-size:0.9rem;">📚 PDF Library</span>
                <button onclick="closePdfLibraryModal()" aria-label="Close" style="background:none;border:none;color:#cbd5e1;font-size:1.2rem;cursor:pointer;line-height:1;padding:2px 6px;border-radius:6px;">✕</button>
            </div>
            
            <div style="padding:16px;flex:1;overflow-y:auto;display:flex;flex-direction:column;">
                <div id="pdf-gallery-panel" style="border:1px solid #e2e8f0;border-radius:10px;background:#fff;flex:1;display:flex;flex-direction:column;overflow:hidden;">
                    <div id="pdf-gallery" style="display:flex;flex-direction:column;gap:6px;overflow-y:auto;padding:8px;flex:1;"></div>
                    <div id="pdf-gallery-empty" style="display:none;font-size:0.7rem;color:#94a3b8;padding:10px 12px;text-align:center;">No PDFs found in library.</div>
                    <div id="pdf-gallery-more" style="display:none;padding:8px 10px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                        <button type="button" onclick="loadMorePdfGallery()" style="width:100%;padding:7px 10px;border:none;border-radius:6px;background:#e2e8f0;color:#0f172a;font-size:0.7rem;font-weight:700;cursor:pointer;">Load more</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Inputs -->
    <input type="file" id="cld_pdf_input" accept=".pdf" style="display:none;" onchange="onCldPdfSelect(this)">
    <input type="file" id="replace_img_input" accept="image/*" style="display:none">

    <input type="file" id="editor_insert_img_input" accept="image/*" style="display:none">

    <div id="scheduler-modal" style="position:fixed;inset:0;z-index:10001;display:none;background:rgba(15,23,42,0.6);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;width:400px;max-width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);overflow:hidden;font-family:ui-sans-serif, system-ui, sans-serif;">
            <div style="background:#f8fafc;padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:1.1rem;color:#1e293b;font-weight:600;">🕒 Auto Resend Scheduler</h3>
                <button type="button" onclick="closeSchedulerModal()" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div style="padding:20px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:0.85rem;font-weight:600;color:#334155;margin-bottom:6px;">1st Scheduler (After 48 Hrs) Time</label>
                    <input type="time" id="sched_48_time" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.95rem;color:#0f172a;">
                    <small style="display:block;margin-top:4px;color:#64748b;font-size:0.75rem;">Emails 48-72h old, subject not FW. New Subject: FW:-</small>
                </div>
                <div style="margin-bottom:24px;">
                    <label style="display:block;font-size:0.85rem;font-weight:600;color:#334155;margin-bottom:6px;">2nd Scheduler (After 72 Hrs) Time</label>
                    <input type="time" id="sched_72_time" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.95rem;color:#0f172a;">
                    <small style="display:block;margin-top:4px;color:#64748b;font-size:0.75rem;">Emails >72h since 1st resend, subject FW:. New Subject: FWD:-</small>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:12px;">
                    <button type="button" onclick="closeSchedulerModal()" style="padding:8px 16px;background:#fff;border:1px solid #cbd5e1;border-radius:6px;color:#475569;font-weight:500;cursor:pointer;">Cancel</button>
                    <button type="button" id="sched_save_btn" onclick="saveSchedulerConfig()" style="padding:8px 16px;background:#3b82f6;border:none;border-radius:6px;color:#fff;font-weight:500;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.05);">Save Configuration</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // A. PHP → JS CONSTANTS
    const CLD_CLOUD   = '<?= htmlspecialchars(CLD_CLOUD,  ENT_QUOTES) ?>';
    const CLD_PRESET  = '<?= htmlspecialchars(CLD_PRESET, ENT_QUOTES) ?>';
    const CLD_API_KEY = '<?= htmlspecialchars(CLD_API_KEY, ENT_QUOTES) ?>';
    const CLD_API_SECRET_CONFIGURED = <?= CLD_API_SECRET ? 'true' : 'false' ?>;
    const APP_EMAIL   = '<?= htmlspecialchars(APP_EMAIL,  ENT_QUOTES) ?>';
    const CSRF_TOKEN  = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
    const APP_URL     = '<?= htmlspecialchars(APP_URL,     ENT_QUOTES) ?>';
    const IS_LOCALHOST = <?= (str_contains(APP_URL, 'localhost') || str_contains(APP_URL, '127.0.0.1')) ? 'true' : 'false' ?>;
    const VIEWER_URL  = '<?= htmlspecialchars(VIEWER_URL, ENT_QUOTES) ?>';

    // B. PHP → JS TEMPLATE KEYS (content + subject loaded on demand)
    const rawTemplates = {};
    const DEFAULT_TEMPLATE_SUBJECT = 'Dish Network Exclusive Discount Offer for {{Hotel_Name}} {{City}}, {{State}} {{Zip}}';

    function extractTemplateSubject(html) {
        const match = html.match(/<!--\s*EMAIL_SUBJECT:\s*([\s\S]*?)\s*-->/i);
        return match ? match[1].trim() : '';
    }

    function removeTemplateSubjectMarker(html) {
        return html.replace(/<!--\s*EMAIL_SUBJECT:\s*[\s\S]*?\s*-->/i, '').trim();
    }

    // C. STATE VARIABLES
    const CSV_LIMIT_BYTES = 50 * 1024 * 1024;
    const CSV_PREVIEW_ROWS = 250;
    const CSV_PREVIEW_MAX_CHARS = 1024 * 1024;
    const CSV_READ_CHUNK_BYTES = 64 * 1024;
    const CSV_MAX_RECORD_CHARS = 64 * 1024;

    let csvData = [], currentPreviewIndex = 0, editorContentBackup = '', isPreviewMode = false;
    let csvSourceFile = null;
    let csvDetectedHeaders = [], csvFieldMapping = {};
    let selectedImg = null, selectedPdfBtn = null, galleryImages = [], galleryMode = 'insert', lastRange = null;
    let undoStack = [], redoStack = [], _lastSnapshot = '';
    let lastFocusedField = 'canvas', subjectCaret = 0;
    let liveCanvasBase = '';
    let tokenBase = ''; // always holds {{token}} version of canvas
    let phViewMode = 'values';
    let bulkConfirmed = false, bulkConfirmTimeout = null;
    let deleteConfirmed = false;

    // D. GREETING / SIGNOFF BUILDERS
    function buildGreeting() {
        return `Dear {{Contact_Person}},<br>Thank you for considering our services for {{Hotel_Name}} located in {{City}}, {{State}} {{Zip}}.`;
    }

    function buildSignoff() {
        return `Sincerely,<br><br><strong>Roger</strong><br>E-Voxx Team`;
    }

    // E. TEMPLATE SYSTEM
    function rebuildTemplates() {}

    async function loadSelectedTemplate() {
        if (isPreviewMode) exitPreview();
        const sel = document.getElementById('template_select');
        const selectedValue = sel ? sel.value : '';
        
        document.querySelectorAll('input[id^="p_"]').forEach(el => {
            el.removeAttribute('readonly');
            el.classList.remove('bg-slate-100', 'cursor-not-allowed', 'text-slate-500');
        });
        const ind = document.getElementById('sent_date_indicator');
        if (ind) {
            ind.style.display = 'none';
            ind.innerHTML = '';
        }

        if (!selectedValue) return;

        window.__studioInitializing = true;
        editorContentBackup = ''; // Clear backup to avoid stale templates during bulk send
        try {
            let template = rawTemplates[selectedValue];
            if (!template) {
                try {
                    const res = await fetch('?get_template=' + encodeURIComponent(selectedValue));
                    if (!res.ok) throw new Error('Template load failed');
                    const rawHtml = await res.text();
                    template = {
                        html: removeTemplateSubjectMarker(rawHtml),
                        subject: extractTemplateSubject(rawHtml) || DEFAULT_TEMPLATE_SUBJECT
                    };
                    rawTemplates[selectedValue] = template;
                } catch(e) {
                    showToast('Failed to load template.', 'error');
                    return;
                }
            }

            document.getElementById('email_subject').value = template.subject || DEFAULT_TEMPLATE_SUBJECT;
            updateSubjectPreview();
            setStudioDirty(false);

            let content = template.html;
            content = content.split('${greetingBlock}').join('<div data-block="greeting">' + buildGreeting() + '</div>');
            content = content.split('${signoffBlock}').join('<div data-block="signoff">' + buildSignoff() + '</div>');
            const canvas = document.getElementById('proposal_canvas');
            
            canvas.innerHTML = content;
            bindCanvasImageEvents();
            tokenBase = canvas.innerHTML;
            liveCanvasBase = canvas.innerHTML;
            _lastSnapshot = canvas.innerHTML;
            undoStack = [];
            redoStack = [];
            saveTemplateSelection();
            updateStatusBar();
            if (phViewMode === 'values') liveApplyAll();
            updatePlaceholderCounts();
        } finally {
            window.__studioInitializing = false;
            setStudioDirty(false);
        }
    }

    const PERSIST_FIELDS = ['Hotel_Name','Address','City','State','Zip','Rooms','Extra','Email', 'Hotel_Number','Contact_Person','First_Name','Service_Provider','offeredProgramming','System','Bill','Upfront'];
    const TEMPLATE_SELECTION_KEY = 'studio_selected_template';

    function saveTemplateSelection() {
        const sel = document.getElementById('template_select');
        if (sel && sel.value) localStorage.setItem(TEMPLATE_SELECTION_KEY, sel.value);
    }

    function getStoredTemplateSelection() {
        return localStorage.getItem(TEMPLATE_SELECTION_KEY) || '';
    }

    function setStudioDirty(isDirty) {
        const unsaved = document.getElementById('sb_unsaved');
        if (!unsaved) return;
        if (window.__studioInitializing) {
            unsaved.style.display = 'none';
            return;
        }
        unsaved.style.display = isDirty ? 'inline' : 'none';
    }

    function resetStudioState() {
        const sel = document.getElementById('template_select');
        const canvas = document.getElementById('proposal_canvas');
        const subjectEl = document.getElementById('email_subject');
        const nameEl = document.getElementById('new_template_name');

        if (nameEl) nameEl.value = '';
        if (emailTagInput) { emailTags = []; renderEmailTags(); }
        if (document.getElementById('client_email')) document.getElementById('client_email').value = '';

        PERSIST_FIELDS.forEach(key => {
            const el = document.getElementById('p_' + key);
            if (el) el.value = '';
        });

        if (subjectEl) {
            const currentTemplate = sel && sel.value ? rawTemplates[sel.value] : null;
            subjectEl.value = currentTemplate?.subject || DEFAULT_TEMPLATE_SUBJECT;
        }

        if (sel && sel.value) {
            loadSelectedTemplate();
        } else if (canvas) {
            canvas.innerHTML = '<div style="text-align: center; color: #9ca3af; padding: 50px;">Select a template or start typing here...</div>';
            tokenBase = canvas.innerHTML;
            liveCanvasBase = canvas.innerHTML;
            _lastSnapshot = canvas.innerHTML;
        }

        updateSubjectPreview();
        saveFormData();
        localStorage.removeItem('studio_autosave');
        const banner = document.getElementById('autosave_banner');
        if (banner) banner.remove();
        bindCanvasImageEvents();
        updateStatusBar();
        showToast('Studio reset to the current template.', 'success');
    }

    // ── Math calculator for Bill & Upfront fields ─────────────────────────────
    function evalMathExpr(expr) {
        // Strip $ and commas, allow digits, operators, parens, dots, spaces, %
        let e = expr.replace(/[$,]/g, '').trim();
        if (!e) return null;
        // Handle percentage: replace X% with (X/100)
        e = e.replace(/(\d+(?:\.\d+)?)%/g, '($1/100)');
        // Handle (base+X%) pattern like (500+10%) => 500*(1+10/100)
        // Already handled above since % becomes /100
        // Validate: only allow safe math characters
        if (!/^[\d\s+\-*/().]+$/.test(e)) return null;
        try {
            // eslint-disable-next-line no-new-func
            const result = Function('"use strict"; return (' + e + ')')();
            if (!isFinite(result)) return null;
            return Math.round(result * 100) / 100;
        } catch { return null; }
    }

    function setupMathField(fieldId) {
        const el = document.getElementById(fieldId);
        if (!el) return;
        // Create hint element
        const hint = document.createElement('div');
        hint.id = fieldId + '_hint';
        hint.style.cssText = 'font-size:0.65rem;color:#16a34a;padding:2px 4px;min-height:14px;font-weight:600;';
        el.parentNode.insertBefore(hint, el.nextSibling);

        el.addEventListener('input', function() {
            const raw = this.value;
            if (/[+\-*/%()]/.test(raw)) {
                const result = evalMathExpr(raw);
                hint.textContent = result !== null ? '= ' + result.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
                delete this.dataset.formula;
            } else {
                if (this.dataset.formula) {
                    const result = evalMathExpr(this.dataset.formula);
                    if (result !== null && parseFloat(raw) == result) {
                        hint.textContent = this.dataset.formula + ' = ' + result.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                    } else {
                        hint.textContent = '';
                        delete this.dataset.formula;
                    }
                } else {
                    hint.textContent = '';
                }
            }
        });

        el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const raw = this.value;
                const result = evalMathExpr(raw);
                if (result !== null && /[+\-*/%()]/.test(raw)) {
                    this.dataset.formula = raw;
                    this.value = result;
                    hint.textContent = raw + ' = ' + result.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                    this.dispatchEvent(new Event('input'));
                } else if (result !== null) {
                    this.value = result;
                    hint.textContent = '';
                    delete this.dataset.formula;
                    this.dispatchEvent(new Event('input'));
                }
            }
        });

        el.addEventListener('blur', function() {
            const raw = this.value;
            const result = evalMathExpr(raw);
            if (result !== null && /[+\-*/%()]/.test(raw)) {
                this.dataset.formula = raw;
                this.value = result;
                hint.textContent = raw + ' = ' + result.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                this.dispatchEvent(new Event('input'));
            } else if (result !== null) {
                this.value = result;
                if (!this.dataset.formula) hint.textContent = '';
                this.dispatchEvent(new Event('input'));
            }
        });
    }

    setupMathField('p_Bill');
    setupMathField('p_Upfront');

    // Excel paste autofill — paste tab-separated row into any field, fills subsequent fields in order
    PERSIST_FIELDS.forEach((key, startIdx) => {
        const el = document.getElementById('p_' + key);
        if (!el) return;
        el.addEventListener('paste', function(e) {
            const text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text.includes('\t')) return; // single value, let browser handle normally
            e.preventDefault();
            window._isPastingRow = true;
            const values = text.split('\t').map(v => v.trim().replace(/[\r\n]+/g, ''));
            values.forEach((val, i) => {
                const fieldKey = PERSIST_FIELDS[startIdx + i];
                if (!fieldKey) return;
                const target = document.getElementById('p_' + fieldKey);
                if (target) {
                    target.value = val;
                    target.dispatchEvent(new Event('input'));
                }
            });
            window._isPastingRow = false;
            // Focus the field after the last filled one
            const nextKey = PERSIST_FIELDS[startIdx + values.length];
            const nextEl = nextKey ? document.getElementById('p_' + nextKey) : null;
            if (nextEl) nextEl.focus(); else el.blur();
        });
    });
    const PERSIST_IDS = ['client_email','email_subject'];

    function saveFormData() {
        const data = {};
        const sel = document.getElementById('template_select');
        PERSIST_FIELDS.forEach(k => { 
            const el = document.getElementById('p_' + k); 
            if (el) {
                data['p_' + k] = el.value; 
                if (el.dataset.formula) data['p_' + k + '_formula'] = el.dataset.formula;
            }
        });
        PERSIST_IDS.forEach(id => { const el = document.getElementById(id); if (el) data[id] = el.value; });
        data['email_tags'] = emailTags;
        if (sel && sel.value) data['selected_template'] = sel.value;
        localStorage.setItem('studio_formdata', JSON.stringify(data));
        saveTemplateSelection();
    }

    function restoreFormData() {
        try {
            const data = JSON.parse(localStorage.getItem('studio_formdata') || '{}');
            PERSIST_FIELDS.forEach(k => { 
                const el = document.getElementById('p_' + k); 
                if (el && data['p_' + k] !== undefined) {
                    el.value = data['p_' + k]; 
                    if (data['p_' + k + '_formula']) {
                        el.dataset.formula = data['p_' + k + '_formula'];
                        const result = evalMathExpr(el.dataset.formula);
                        const hint = document.getElementById('p_' + k + '_hint');
                        if (hint && result !== null) {
                            hint.textContent = el.dataset.formula + ' = ' + result.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                        }
                    }
                }
            });
            PERSIST_IDS.forEach(id => { const el = document.getElementById(id); if (el && data[id] !== undefined) el.value = data[id]; });
            if (Array.isArray(data['email_tags'])) { emailTags = data['email_tags']; renderEmailTags(); }
            const sel = document.getElementById('template_select');
            const savedTemplate = data['selected_template'] || getStoredTemplateSelection();
            if (sel && savedTemplate && [...sel.options].some(o => o.value === savedTemplate)) {
                sel.value = savedTemplate;
                saveTemplateSelection();
            }
        } catch(e) {}
    }

    function hydrateTemplateSelection() {
        const sel = document.getElementById('template_select');
        if (!sel || !sel.options.length) return;
        const stored = getStoredTemplateSelection();
        const hasStored = stored && [...sel.options].some(o => o.value === stored);
        if (hasStored) {
            sel.value = stored;
            saveTemplateSelection();
            return;
        }
        sel.value = sel.options[0].value;
        saveTemplateSelection();
    }

    window.addEventListener('load', () => {
        // Warn if tracking pixel will not work (localhost not reachable by recipients)
        if (IS_LOCALHOST) {
            const warn = document.createElement('div');
            warn.style.cssText = 'background:#fef3c7;border-bottom:2px solid #f59e0b;padding:6px 14px;font-size:0.72rem;color:#92400e;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;';
            warn.innerHTML = '<span>&#9888;&#65039; <strong>Tracking disabled:</strong> APP_URL is set to <code style="background:#fde68a;padding:1px 4px;border-radius:3px;">' + APP_URL + '</code> &mdash; recipients cannot reach localhost. Set a public URL in <code>.env</code> to enable open tracking.</span><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:1rem;color:#92400e;margin-left:8px;">&times;</button>';
            document.querySelector('header').after(warn);
        }

        const sel = document.getElementById('template_select');
        restoreFormData();
        if (sel && sel.options.length > 0) {
            hydrateTemplateSelection();
            loadSelectedTemplate();
        }
        setStudioDirty(false);
        loadGalleryFromStorage();
        updateSubjectPreview();
        if (phViewMode === 'values') liveApplyAll();
        window.__studioPageReady = true;

        const autosave = localStorage.getItem('studio_autosave');
        if (autosave) {
            const banner = document.createElement('div');
            banner.id = 'autosave_banner';
            banner.innerHTML = `<div style="background:#1e293b;color:#e2e8f0;font-size:0.75rem;padding:8px 14px;display:flex;justify-content:space-between;align-items:center;border-radius:6px;margin-bottom:8px;">
              <span>↩ Auto-saved draft found.</span>
              <span style="display:flex;gap:8px;">
                <button onclick="restoreAutosave()" style="background:#3b82f6;color:#fff;border:none;border-radius:4px;padding:2px 10px;cursor:pointer;font-size:0.75rem;">Restore</button>
                <button onclick="dismissAutosave()" style="background:#475569;color:#fff;border:none;border-radius:4px;padding:2px 10px;cursor:pointer;font-size:0.75rem;">Dismiss</button>
              </span></div>`;
            document.getElementById('canvas_scroll_area').insertBefore(banner, document.getElementById('proposal_canvas'));
        }

        // Toolbar focus/selection loss prevention
        document.querySelectorAll('.tb-btn').forEach(btn => {
            btn.addEventListener('mousedown', function(e) {
                saveCursor();
                e.preventDefault(); // prevent canvas from losing focus
            });
        });

        // Color Inputs Configuration
        document.getElementById('text_color').addEventListener('mousedown', saveCursor);
        document.getElementById('highlight_color').addEventListener('mousedown', saveCursor);
        document.getElementById('canvas_bg').addEventListener('mousedown', saveCursor);

        document.getElementById('text_color').addEventListener('input', function() {
            execCmd('foreColor', this.value);
        });
        document.getElementById('highlight_color').addEventListener('input', function() {
            execCmd('hiliteColor', this.value);
        });
        document.getElementById('canvas_bg').addEventListener('input', function() {
            setCanvasBg(this.value);
        });
        
        // Setup Drag & Drop for all upload zones
        document.querySelectorAll('.upload-zone').forEach(zone => {
            const input = zone.querySelector('input[type="file"]');
            if(!input) return;
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                zone.addEventListener(eventName, () => zone.classList.add('drag-over'), false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, () => zone.classList.remove('drag-over'), false);
            });
            
            zone.addEventListener('drop', e => {
                let dt = e.dataTransfer;
                if(dt.files.length) {
                    input.files = dt.files;
                    input.dispatchEvent(new Event('change'));
                }
            }, false);
        });
        
        document.getElementById('template_file_input').addEventListener('change', function() {
            if(this.files.length) document.getElementById('tpl_upload_form').submit();
        });
        
        document.getElementById('sys_attach_input').addEventListener('change', function() {
            onAttachFileChange(this);
        });
        
        document.getElementById('csv_file').addEventListener('change', async function(e) {
            const label = document.getElementById('csv_label_text');
            const errBox = document.getElementById('csv_errors');
            const statusBox = document.getElementById('csv_status');
            csvSourceFile = null;
            csvData = [];
            document.getElementById('csv_preview_controls').classList.add('hidden');
            document.getElementById('csv_actions').classList.add('hidden');
            document.getElementById('csv_mapping_container').classList.add('hidden');
            errBox.classList.add('hidden');
            errBox.innerHTML = '';
            statusBox.classList.add('hidden');
            statusBox.textContent = '';

            if (this.files.length) label.textContent = this.files[0].name;
            else label.textContent = 'Choose CSV file';

            const file = e.target.files[0];
            const bp = document.getElementById('bulk_progress');
            bp.classList.add('hidden');
            bp.innerText = '';
            bulkConfirmed = false;

            if (!file) return;

            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'csv' || (file.type && !file.type.includes('csv') && !file.type.includes('text'))) {
                errBox.innerHTML = '<b>❌ Not a CSV file.</b> Please upload a .csv file.';
                errBox.classList.remove('hidden');
                this.value = '';
                label.textContent = 'Choose CSV file';
                statusBox.classList.remove('hidden');
                statusBox.className = 'text-[11px] px-2 py-1.5 rounded border border-red-200 bg-red-50 text-red-700 font-medium';
                statusBox.textContent = 'CSV rejected: invalid file type.';
                return;
            }

            if (file.size > CSV_LIMIT_BYTES) {
                errBox.innerHTML = '<b>❌ File too large.</b> CSV must be 50 MB or smaller. Split the file into smaller batches.';
                errBox.classList.remove('hidden');
                this.value = '';
                label.textContent = 'Choose CSV file';
                statusBox.classList.remove('hidden');
                statusBox.className = 'text-[11px] px-2 py-1.5 rounded border border-red-200 bg-red-50 text-red-700 font-medium';
                statusBox.textContent = 'CSV rejected: file exceeds 50 MB limit.';
                return;
            }

            statusBox.classList.remove('hidden');
            statusBox.className = 'text-[11px] px-2 py-1.5 rounded border border-blue-200 bg-blue-50 text-blue-700 font-medium';
            statusBox.textContent = 'Reading CSV preview… please wait.';

            try {
                const preview = await readCsvPreview(file, CSV_PREVIEW_ROWS);
                csvData = preview.rows;
                csvDetectedHeaders = preview.headers.filter(Boolean);
                if (!csvDetectedHeaders.length && csvData[0]) {
                    csvDetectedHeaders = Object.keys(csvData[0]).filter(Boolean);
                }

                if (!csvDetectedHeaders.length || csvData.length === 0) {
                    errBox.innerHTML = '<b>❌ Empty CSV.</b> File must have a header row and at least one data row.';
                    errBox.classList.remove('hidden');
                    statusBox.className = 'text-[11px] px-2 py-1.5 rounded border border-red-200 bg-red-50 text-red-700 font-medium';
                    statusBox.textContent = 'CSV read failed: empty file.';
                    return;
                }

                csvSourceFile = file;
                if (preview.hasMore) {
                    errBox.innerHTML = '<b>ℹ️ Large file detected.</b> Preview is limited to 250 rows / 1M chars; Send, Draft, and Save will process the full CSV in small chunks.';
                    errBox.classList.remove('hidden');
                }

                const errors = [];
                const knownHeaders = Object.keys(<?php echo json_encode($placeholderFields); ?>).map(normalizeCsvHeader);
                const csvHeaders = (csvData[0] ? Object.keys(csvData[0]) : []).map(normalizeCsvHeader);
                const missingHeaders = knownHeaders.filter(h => !csvHeaders.includes(h));
                const hasEmailColumn = csvHeaders.some(h => h === 'email' || h === 'email_address' || h === 'client_email');
                if (!hasEmailColumn) errors.push('❌ Missing required column: <b>Email</b>');
                if (missingHeaders.length) errors.push('⚠️ Missing optional columns (will be blank): <b>' + knownHeaders.filter(h => missingHeaders.includes(h)).map(h => h === 'email' ? 'Email' : h).join(', ') + '</b>');

                csvData.forEach((row, i) => {
                    const rowNum = i + 2;
                    const rawEmail = getCsvEmailValue(row);
                    if (!rawEmail) {
                        errors.push(`❌ Row ${rowNum}: No email address`);
                        return;
                    }
                    const emails = parseEmailList(rawEmail);
                    const badEmails = emails.filter(em => !isValidEmail(em));
                    if (badEmails.length) errors.push(`❌ Row ${rowNum} (${decodeHtmlEntities(row['Hotel_Name']||row['Hotel Name']||row['hotel_name']||'?')}): Invalid email(s): <b>${badEmails.join(', ')}</b>`);
                });

                if (errors.length) {
                    errBox.innerHTML = (preview.hasMore ? errBox.innerHTML + '<br>' : '') + errors.join('<br>');
                    errBox.classList.remove('hidden');
                }

                const validRows = csvData.filter(r => r['Email']);
                if (validRows.length === 0 && !preview.hasMore) {
                    document.getElementById('csv_preview_controls').classList.add('hidden');
                    document.getElementById('csv_actions').classList.add('hidden');
                    csvSourceFile = null;
                    statusBox.className = 'text-[11px] px-2 py-1.5 rounded border border-amber-200 bg-amber-50 text-amber-700 font-medium';
                    statusBox.textContent = 'CSV loaded but no valid rows were found.';
                    return;
                }

                editorContentBackup = document.getElementById('proposal_canvas').innerHTML;
                document.getElementById('csv_preview_controls').classList.remove('hidden');
                document.getElementById('csv_actions').classList.remove('hidden');
                csvFieldMapping = {};
                const autoFieldNames = Object.keys(<?php echo json_encode($placeholderFields); ?>);
                autoFieldNames.forEach((key) => {
                    const match = csvDetectedHeaders.find((header) => normalizeCsvHeader(header) === normalizeCsvHeader(key));
                    if (match) csvFieldMapping[key] = match;
                });
                buildCsvMappingControls();
                currentPreviewIndex = 0;
                statusBox.className = 'text-[11px] px-2 py-1.5 rounded border border-emerald-200 bg-emerald-50 text-emerald-700 font-medium';
                statusBox.textContent = 'CSV read successfully. Previewing ' + validRows.length + (preview.hasMore ? ' valid rows in the preview; full file will be processed.' : ' valid rows.');
                if (csvData.length) previewCSVRow(currentPreviewIndex);
            } catch (error) {
                console.error('CSV preview failed', error);
                csvSourceFile = null;
                statusBox.className = 'text-[11px] px-2 py-1.5 rounded border border-red-200 bg-red-50 text-red-700 font-medium';
                statusBox.textContent = error instanceof Error ? error.message : 'CSV read failed. Please try again.';
                errBox.innerHTML = '<b>❌ Unable to read CSV.</b> Please check the file format or try a smaller file.';
                errBox.classList.remove('hidden');
            }
        });
    });

    

    function restoreAutosave() {
        const html = localStorage.getItem('studio_autosave');
        if (html) {
            const canvas = document.getElementById('proposal_canvas');
            undoStack.push(canvas.innerHTML);
            if(undoStack.length > 100) undoStack.shift();
            canvas.innerHTML = html;
            bindCanvasImageEvents();
            tokenBase = html;
            liveCanvasBase = html;
            _lastSnapshot = html;
            if (phViewMode === 'values') liveApplyAll();
            updateStatusBar();
        }
        dismissAutosave();
    }

    function dismissAutosave() {
        localStorage.removeItem('studio_autosave');
        const banner = document.getElementById('autosave_banner');
        if (banner) banner.remove();
    }

    // CSRF helper — appends token to any FormData
    function csrfForm(fd) {
        fd.append('csrf_token', CSRF_TOKEN);
        return fd;
    }

    // EMAIL TAG INPUT
    const emailTagInput = document.getElementById('email_tag_input');
    const emailTagContainer = document.getElementById('email_tags_container');
    let emailTags = [];

    function parseEmailList(str) {
        return str.split(/[,;/]+/).map(e => e.trim()).filter(e => e.length > 0);
    }

    function isValidEmail(e) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
    }

    function renderEmailTags() {
        emailTagContainer.querySelectorAll('.email-tag').forEach(t => t.remove());
        emailTags.forEach((email, i) => {
            const tag = document.createElement('span');
            tag.className = 'email-tag';
            tag.innerHTML = `${email} <span class="rm" data-i="${i}">✕</span>`;
            tag.querySelector('.rm').onclick = () => { emailTags.splice(i, 1); renderEmailTags(); syncEmailHidden(); };
            emailTagContainer.insertBefore(tag, emailTagInput);
        });
        syncEmailHidden();
    }

    function syncEmailHidden() {
        document.getElementById('client_email').value = emailTags.join(',');
        saveFormData();
    }

    function addEmailTags(raw) {
        parseEmailList(raw).forEach(e => {
            if (e && !emailTags.includes(e)) emailTags.push(e);
        });
        renderEmailTags();
        emailTagInput.value = '';
    }

    emailTagInput.addEventListener('keydown', async function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const rawVal = this.value.trim();
            if (rawVal) {
                const emails = parseEmailList(rawVal);
                addEmailTags(rawVal);
                if (emails.length > 0 && typeof window.checkSentEmailsForAutoLoad === 'function') {
                    await window.checkSentEmailsForAutoLoad(emails[emails.length - 1]);
                }
            } else if (emailTags.length > 0 && typeof window.checkSentEmailsForAutoLoad === 'function') {
                await window.checkSentEmailsForAutoLoad(emailTags[emailTags.length - 1]);
            }
        } else if ([',', ';', '/'].includes(e.key)) {
            e.preventDefault();
            if (this.value.trim()) addEmailTags(this.value);
        } else if (e.key === 'Backspace' && !this.value && emailTags.length) {
            emailTags.pop();
            renderEmailTags();
        }
    });
    emailTagInput.addEventListener('blur', function() {
        if (this.value.trim()) addEmailTags(this.value);
    });
    emailTagInput.addEventListener('paste', function(e) {
        e.preventDefault();
        addEmailTags(e.clipboardData.getData('text'));
    });

    function setEmailTagsFromString(str) {
        emailTags = [];
        addEmailTags(str);
    }

    // HTML ENTITY DECODER
    function decodeHtmlEntities(str) {
        const txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    // F. GREETING / SIGNOFF INSERT
    function insertGreeting() {
        if (isPreviewMode) return;
        insertHtmlAtCursor('<div data-block="greeting">' + buildGreeting() + '</div><br>');
    }

    function insertSignoff() {
        if (isPreviewMode) return;
        insertHtmlAtCursor('<div data-block="signoff">' + buildSignoff() + '</div><br>');
    }

    // G. PLACEHOLDER INSERT & APPLY
    function insertField(tag) {
        if (isPreviewMode) return;
        
        Swal.fire({
            title: 'Insert Placeholder',
            text: `Insert ${tag} into the proposal?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#e2e8f0',
            confirmButtonText: 'Yes, insert',
            cancelButtonText: '<span style="color:#475569">Cancel</span>'
        }).then((result) => {
            if (result.isConfirmed) {
                if (lastFocusedField === 'subject') {
                    const el = document.getElementById('email_subject');
                    el.setRangeText(tag, subjectCaret, subjectCaret, 'end');
                    subjectCaret = el.selectionStart;
                    el.dispatchEvent(new Event('input'));
                } else {
                    let html = `<span style="background-color:#fef08a; padding:2px 4px; border:1px dashed #eab308; border-radius:2px;">${tag}</span>`;
                    restoreCursorAndInsert(html);
                }
            }
        });
    }

    function liveApplyAll() {
        if (!tokenBase) return;
        let html = tokenBase;
        const fields = ['Hotel_Name', 'Address', 'City', 'State', 'Zip', 'Rooms', 'Extra','Email', 'Hotel_Number', 'Contact_Person', 'First_Name', 'Service_Provider', 'offeredProgramming', 'System', 'Bill', 'Upfront'];
        fields.forEach(key => {
            let el = document.getElementById('p_' + key);
            if (el && el.value !== '') {
                let val = el.value;
                if ((key === 'Bill' || key === 'Upfront') && val) {
                    let num = parseFloat(val.replace(/[^0-9.-]+/g, ""));
                    if (!isNaN(num)) val = new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(num);
                }
                html = html.split('{{' + key + '}}').join(val);
            }
        });
        const canvas = document.getElementById('proposal_canvas');
        canvas.innerHTML = html;
        liveCanvasBase = html;
        bindCanvasImageEvents();
    }

    function updateSubjectPreview() {
        let subject = document.getElementById('email_subject').value;
        const fields = ['Hotel_Name', 'Address', 'City', 'State', 'Zip', 'Rooms', 'Extra', 'Email','Hotel_Number', 'Contact_Person', 'First_Name', 'Service_Provider', 'offeredProgramming', 'System', 'Bill', 'Upfront'];
        fields.forEach(key => {
            let el = document.getElementById('p_' + key);
            if (el) {
                let val = el.value;
                if ((key === 'Bill' || key === 'Upfront') && val) {
                    let num = parseFloat(val.replace(/[^0-9.-]+/g, ""));
                    if (!isNaN(num)) val = new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(num);
                }
                subject = subject.split('{{' + key + '}}').join(val);
            }
        });
        document.getElementById('subject_preview_text').innerText = subject;
    }

    function togglePhView(e) {
        e.stopPropagation();
        const btn = document.getElementById('toggle_ph_view');
        const canvas = document.getElementById('proposal_canvas');
        if (phViewMode === 'values') {
            // Restore token base and render draggable chips
            canvas.innerHTML = renderTokenChips(tokenBase);
            bindCanvasImageEvents();
            bindTokenChipDrag(canvas);
            btn.style.opacity = '0.35';
            phViewMode = 'tokens';
        } else {
            // Unwrap chips back to plain tokens before switching
            unwrapChipsToTokens(canvas);
            liveApplyAll();
            btn.style.opacity = '1';
            phViewMode = 'values';
        }
        updatePlaceholderCounts();
    }

    /** Wrap {{token}} patterns in canvas HTML as draggable chip spans */
    function renderTokenChips(html) {
        return html.replace(/\{\{([^}]+)\}\}/g, (match, key) => {
            const safeKey = escapeHtml(key);
            const safeMatch = escapeHtml(match);
            return `<span class="ph-chip" contenteditable="false" draggable="true" data-token="${safeMatch}" title="Drag to move {{${safeKey}}}">${safeMatch}</span>`;
        });
    }

    /** Unwrap chip spans back to plain text {{token}} in a canvas element */
    function unwrapChipsToTokens(canvas) {
        canvas.querySelectorAll('.ph-chip').forEach(chip => {
            chip.replaceWith(document.createTextNode(chip.dataset.token || chip.textContent));
        });
        // After unwrapping, update tokenBase from canvas
        tokenBase = canvas.innerHTML;
    }

    /** Bind HTML5 drag-and-drop for .ph-chip elements in canvas */
    let _dragChipToken = null;
    function bindTokenChipDrag(canvas) {
        canvas.querySelectorAll('.ph-chip').forEach(chip => {
            chip.addEventListener('dragstart', (e) => {
                _dragChipToken = chip.dataset.token;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', chip.dataset.token);
                chip.style.opacity = '0.4';
            });
            chip.addEventListener('dragend', () => {
                chip.style.opacity = '1';
                _dragChipToken = null;
            });
        });

        // Allow dropping anywhere in the canvas
        canvas.addEventListener('dragover', (e) => {
            if (!_dragChipToken) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }, { capture: false });

        canvas.addEventListener('drop', (e) => {
            if (!_dragChipToken) return;
            e.preventDefault();

            // Find the chip being dragged and remove it
            const dragging = canvas.querySelector('.ph-chip[data-token="' + _dragChipToken.replace(/"/g,'&quot;') + '"][style*="0.4"]');
            if (!dragging) return;
            dragging.parentNode.removeChild(dragging);

            // Determine drop position via caret
            let range;
            if (document.caretRangeFromPoint) {
                range = document.caretRangeFromPoint(e.clientX, e.clientY);
            } else if (document.caretPositionFromPoint) {
                const pos = document.caretPositionFromPoint(e.clientX, e.clientY);
                if (pos) {
                    range = document.createRange();
                    range.setStart(pos.offsetNode, pos.offset);
                }
            }

            // Create a new chip at the drop position
            const safeKey = escapeHtml(_dragChipToken.replace(/[{}]/g,''));
            const newChip = document.createElement('span');
            newChip.className = 'ph-chip';
            newChip.contentEditable = 'false';
            newChip.draggable = true;
            newChip.dataset.token = _dragChipToken;
            newChip.title = 'Drag to move ' + _dragChipToken;
            newChip.textContent = _dragChipToken;

            if (range) {
                range.insertNode(newChip);
                range.collapse(false);
            } else {
                canvas.appendChild(newChip);
            }

            // Re-bind drag events on the new chip
            bindTokenChipDrag(canvas);

            // Update tokenBase (unwrap then re-read)
            const tempCanvas = canvas.cloneNode(true);
            unwrapChipsToTokens(tempCanvas);
            tokenBase = tempCanvas.innerHTML;
        }, { capture: false });
    }

    /** Count {{token}} occurrences in tokenBase and update sidebar badges */
    function updatePlaceholderCounts() {
        const src = tokenBase || '';
        PERSIST_FIELDS.forEach(key => {
            const badge = document.getElementById('ph_cnt_' + key);
            if (!badge) return;
            const matches = src.match(new RegExp('\\{\\{' + key + '\\}\\}', 'gi'));
            const count = matches ? matches.length : 0;
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    <?php foreach($placeholderFields as $key => $label): ?>
        if (document.getElementById('p_<?= htmlspecialchars($key, ENT_QUOTES) ?>')) {
            document.getElementById('p_<?= htmlspecialchars($key, ENT_QUOTES) ?>').addEventListener('input', function() {
                <?php if ($key === 'Contact_Person'): ?>
                const firstNameInput = document.getElementById('p_First_Name');
                if (firstNameInput && (document.activeElement === this || window._isPastingRow)) {
                    firstNameInput.value = this.value.split(' ')[0] || '';
                    firstNameInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                <?php endif; ?>
                if (phViewMode === 'values') liveApplyAll();
                updateSubjectPreview();
                saveFormData();
                <?php if ($key === 'Email'): ?>
                setEmailTagsFromString(this.value); // Syncs to client_email tags instantly
                <?php endif; ?>
            });
        }
    <?php endforeach; ?>

    document.getElementById('email_subject').addEventListener('input', () => { updateSubjectPreview(); saveFormData(); });

    const subjEl = document.getElementById('email_subject');
    function trackSubjectFocus(e) {
        lastFocusedField = 'subject';
        subjectCaret = e.target.selectionStart;
    }
    subjEl.addEventListener('focus', trackSubjectFocus);
    subjEl.addEventListener('keyup', trackSubjectFocus);
    subjEl.addEventListener('click', trackSubjectFocus);

    const canvasEl = document.getElementById('proposal_canvas');
    canvasEl.addEventListener('focus', function() {
        lastFocusedField = 'canvas';
        _lastSnapshot = this.innerHTML;
    });
    canvasEl.addEventListener('input', function() {
        undoStack.push(_lastSnapshot);
        if (undoStack.length > 100) undoStack.shift();
        _lastSnapshot = this.innerHTML;
        liveCanvasBase = this.innerHTML;

        // When in values mode the canvas shows rendered values (e.g. "Hilton")
        // not {{Hotel_Name}}.  We must reverse-map those values back to tokens
        // so that liveApplyAll() can still find and replace them later.
        if (phViewMode === 'values') {
            let tb = this.innerHTML;
            const fields = ['Hotel_Name','Address','City','State','Zip','Rooms','Extra','Email','Hotel_Number','Contact_Person','First_Name','Service_Provider','offeredProgramming','System','Bill','Upfront'];
            fields.forEach(key => {
                const el = document.getElementById('p_' + key);
                if (!el || !el.value) return;
                let val = el.value;
                if ((key === 'Bill' || key === 'Upfront') && val) {
                    let num = parseFloat(val.replace(/[^0-9.-]+/g, ""));
                    if (!isNaN(num)) val = new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(num);
                }
                if (val) {
                    const safe = val.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    let regexStr = safe;
                    if (/^\w/.test(val)) regexStr = '\\b' + regexStr;
                    if (/\w$/.test(val)) regexStr = regexStr + '\\b';
                    tb = tb.replace(new RegExp(regexStr, 'gi'), '{{' + key + '}}');
                }
            });
            tokenBase = tb;
        } else {
            tokenBase = this.innerHTML;
        }

        redoStack = [];
        updateStatusBar();
    });

    document.addEventListener('keydown', function(e) {
        const canvas = document.getElementById('proposal_canvas');
        const isCanvas = (document.activeElement === canvas || canvas.contains(document.activeElement));
        const activeTag = document.activeElement.tagName;
        const isInput = activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT';

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'q') {
            e.preventDefault();
            resetStudioState();
            return;
        }

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            if (isCanvas) {
                e.preventDefault();
                customUndo();
            }
        } else if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) {
            if (isCanvas) {
                e.preventDefault();
                customRedo();
            }
        }

        if (!isInput || isCanvas) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                let nameEl = document.getElementById('new_template_name');
                if (nameEl.value.trim() !== '') {
                    saveNewTemplate();
                } else {
                    nameEl.focus();
                    nameEl.style.outline = '2px solid #eab308';
                    setTimeout(() => nameEl.style.outline = '', 800);
                }
            } else if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'p') {
                e.preventDefault();
                exportCanvasToPDF();
            }
        }
    });

    function customUndo() {
        const canvas = document.getElementById('proposal_canvas');
        if (undoStack.length > 0) {
            redoStack.push(canvas.innerHTML);
            let state = undoStack.pop();
            canvas.innerHTML = state;
            _lastSnapshot = state;
            bindCanvasImageEvents();
            tokenBase = state;
            liveCanvasBase = state;
            if (phViewMode === 'values') liveApplyAll();
            updateStatusBar();
        }
    }

    function exportFailedRowsCsv(failedRows) {
        if (!failedRows || !failedRows.length) return;
        const headers = ['Email', ...Object.keys(<?php echo json_encode($placeholderFields); ?>), 'FailureReason'];
        const csvRows = [headers];
        failedRows.forEach(row => {
            const values = headers.map(header => {
                const value = header === 'FailureReason' ? (row[header] || '') : (row[header] || '');
                const escaped = String(value).replace(/"/g, '""');
                return '"' + escaped + '"';
            });
            csvRows.push(values);
        });

        const csvDataString = csvRows.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvDataString], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'failed_bulk_emails_' + Date.now() + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function customRedo() {
        const canvas = document.getElementById('proposal_canvas');
        if (redoStack.length > 0) {
            undoStack.push(canvas.innerHTML);
            let state = redoStack.pop();
            canvas.innerHTML = state;
            _lastSnapshot = state;
            bindCanvasImageEvents();
            tokenBase = state;
            liveCanvasBase = state;
            if (phViewMode === 'values') liveApplyAll();
            updateStatusBar();
        }
    }

    function updateStatusBar() {
        const canvas = document.getElementById('proposal_canvas');
        const text = canvas.innerText || '';
        const chars = text.length;
        const words = text.split(/\s+/).filter(w => w.length > 0).length;
        document.getElementById('sb_words').innerText = words;
        document.getElementById('sb_chars').innerText = chars;
        if (!window.__studioInitializing) {
            setStudioDirty(true);
        }
    }

    function applyPlaceholders() {
        if (isPreviewMode) return;
        const canvas = document.getElementById('proposal_canvas');
        undoStack.push(canvas.innerHTML);
        if(undoStack.length > 100) undoStack.shift();

        let html = canvas.innerHTML;
        let subject = document.getElementById('email_subject').value;
        let emailVal = '';
        
        const fields = ['Hotel_Name', 'Address', 'City', 'State', 'Zip', 'Rooms', 'Extra', 'Email','Hotel_Number', 'Contact_Person', 'First_Name', 'Service_Provider', 'offeredProgramming', 'System', 'Bill', 'Upfront'];
        
        fields.forEach(key => {
            let el = document.getElementById('p_' + key);
            if (el) {
                let val = el.value;
                if (key === 'Email' && val) emailVal = val;
                if ((key === 'Bill' || key === 'Upfront') && val) {
                    let num = parseFloat(val.replace(/[^0-9.-]+/g, ""));
                    if (!isNaN(num)) val = new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(num);
                }
                html = html.split('{{' + key + '}}').join(val);
                subject = subject.split('{{' + key + '}}').join(val);
            }
        });

        canvas.innerHTML = html;
        document.getElementById('email_subject').value = subject;
        if (emailVal) document.getElementById('client_email').value = emailVal;
        
        bindCanvasImageEvents();
        liveCanvasBase = canvas.innerHTML; 
        _lastSnapshot = canvas.innerHTML;
        updateStatusBar();
        updateSubjectPreview();
    }

    // H. CLOUDINARY UPLOAD (preserved logic for potential backend use, button removed in UI)
    async function uploadToCloudinary() {
        const status = document.getElementById('cld_status');
        if (!status) return;
        status.classList.remove('hidden','bg-red-100','text-red-700','bg-green-100','text-green-700','bg-blue-100','text-blue-700');

        if (!CLD_CLOUD || !CLD_PRESET) {
            status.classList.add('bg-red-100','text-red-700');
            status.classList.remove('hidden');
            status.innerText = '❌ Cloudinary not configured in .env';
            return;
        }
        const fileInput = document.getElementById('cld_file_input');
        if (!fileInput || !fileInput.files.length) {
            status.classList.add('bg-red-100','text-red-700');
            status.classList.remove('hidden');
            status.innerText = '❌ Select an image first';
            return;
        }
        const file = fileInput.files[0];
        const fd   = new FormData();
        fd.append('file', file);
        fd.append('upload_preset', CLD_PRESET);

        status.classList.add('bg-blue-100','text-blue-700');
        status.classList.remove('hidden');
        status.innerText = '⏳ Uploading...';

        try {
            const res  = await fetch(`https://api.cloudinary.com/v1_1/${CLD_CLOUD}/image/upload`, { method:'POST', body:fd });
            const data = await res.json();
            if (data.secure_url) {
                status.classList.remove('bg-blue-100','text-blue-700');
                status.classList.add('bg-green-100','text-green-700');
                status.innerText = '✅ Uploaded!';
                addToGallery(data.secure_url, data.public_id || '');
                fileInput.value = '';
            } else {
                throw new Error(data.error?.message || 'Upload failed');
            }
        } catch (err) {
            status.classList.remove('bg-blue-100','text-blue-700');
            status.classList.add('bg-red-100','text-red-700');
            status.innerText = '❌ ' + err.message;
        }
    }

    // I. GALLERY MANAGEMENT
    function addToGallery(url, publicId) {
        fetch('', {
            method: 'POST',
            body: (() => { const fd = csrfForm(new FormData()); fd.append('action','add_asset'); fd.append('url', url); fd.append('public_id', publicId||''); return fd; })()
        }).then(r => r.json()).then(data => {
            if (data.status === 'success') {
                galleryImages.unshift({ id: data.id, url, publicId: publicId||'' });
                renderGallery();
            }
        });
    }

    function saveGalleryToStorage() {} // no-op, DB is source of truth

    let _galleryLoaded = false;
    function loadGalleryFromStorage() {
        if (_galleryLoaded) { renderGallery(); return; }
        const grid = document.getElementById('gallery-grid');
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px;color:#94a3b8;font-size:0.8rem;">Loading...</div>';
        fetch('?action=get_assets')
            .then(r => r.json())
            .then(rows => {
                _galleryLoaded = true;
                galleryImages = rows.map(r => ({ id: r.id, url: r.url, publicId: r.public_id }));
                renderGallery();
            })
            .catch(() => { grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px;color:#ef4444;font-size:0.8rem;">Failed to load.</div>'; });
    }

    function renderGallery() {
        document.getElementById('gallery-count').innerText = `(${galleryImages.length})`;
        const grid = document.getElementById('gallery-grid');
        grid.innerHTML = '';
        if (!galleryImages.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px;color:#94a3b8;font-size:0.8rem;">No images yet.</div>';
            return;
        }
        const frag = document.createDocumentFragment();
        galleryImages.forEach((img, index) => {
            const div = document.createElement('div');
            div.className = 'gallery-thumb';
            const imgEl = document.createElement('img');
            imgEl.loading = 'lazy';
            imgEl.src = img.url;
            imgEl.onclick = () => galleryPickImage(img.url);
            const delBtn = document.createElement('button');
            delBtn.className = 'del-btn';
            delBtn.innerText = '✕';
            delBtn.onclick = (e) => { e.stopPropagation(); removeFromGallery(index); };
            div.appendChild(imgEl);
            div.appendChild(delBtn);
            frag.appendChild(div);
        });
        grid.appendChild(frag);
    }

    function removeFromGallery(index) {
        const item = galleryImages[index];
        if (!item) return;
        galleryImages.splice(index, 1);
        renderGallery();
        fetch('', {
            method: 'POST',
            body: (() => { const fd = csrfForm(new FormData()); fd.append('action','delete_asset'); fd.append('id', item.id); return fd; })()
        });
    }

    function openGallery() {
        galleryMode = 'insert';
        document.getElementById('gallery-modal').style.display = 'flex';
    }

    function closeGallery() {
        document.getElementById('gallery-modal').style.display = 'none';
    }

    function galleryPickImage(url) {
        if (galleryMode === 'replace' && selectedImg) {
            selectedImg.src = url;
            selectedImg.classList.remove('img-selected');
            selectedImg = null;
        } else {
            restoreCursorAndInsert(`<img src="${url}" style="max-width:100%; height:auto; display:block; margin:8px auto;">`);
        }
        closeGallery();
        bindCanvasImageEvents();
    }

    document.getElementById('gallery-modal').addEventListener('click', (e) => {
        if (e.target.id === 'gallery-modal') closeGallery();
    });

    // J. CANVAS IMAGE EVENTS
    function bindCanvasPdfBtnEvents() {
        const canvas = document.getElementById('proposal_canvas');
        canvas.querySelectorAll('a[data-pdf-url]').forEach(link => {
            link.oncontextmenu = function(e) {
                e.preventDefault();
                e.stopPropagation();
                selectedPdfBtn = link;
                showCtxMenuPdf(e.clientX, e.clientY);
            };
            link.onclick = function(e) {
                // In edit mode, prevent navigation so the link can be repositioned/edited
                if (!isPreviewMode) {
                    e.preventDefault();
                }
            };
        });
    }

    function bindCanvasImageEvents() {
        const canvas = document.getElementById('proposal_canvas');
        canvas.querySelectorAll('img').forEach(img => {
            img.oncontextmenu = (e) => {
                e.preventDefault();
                selectImage(img);
                showCtxMenu(e.clientX, e.clientY);
            };
            img.onclick = (e) => {
                e.stopPropagation();
                selectImage(img);
            };
        });
        bindCanvasPdfBtnEvents();
    }

    function selectImage(img) {
        const canvas = document.getElementById('proposal_canvas');
        canvas.querySelectorAll('img').forEach(i => i.classList.remove('img-selected'));
        img.classList.add('img-selected');
        selectedImg = img;
        if (document.getElementById('img-panel').style.display === 'block') {
            populateImgPanel(img);
        }
    }

    document.addEventListener('click', (e) => {
        const ctxMenu = document.getElementById('ctx-menu');
        const ctxMenuPdf = document.getElementById('ctx-menu-pdf');
        const imgPanel = document.getElementById('img-panel');
        if (ctxMenu.style.display === 'block' && !ctxMenu.contains(e.target)) hideCtxMenu();
        if (ctxMenuPdf && ctxMenuPdf.style.display === 'block' && !ctxMenuPdf.contains(e.target)) hideCtxMenuPdf();
        if (imgPanel.style.display === 'block' && !imgPanel.contains(e.target) && !ctxMenu.contains(e.target) && e.target !== selectedImg) {
            closeImgPanel();
        }
        const canvas = document.getElementById('proposal_canvas');
        if (canvas.contains(e.target) && e.target.tagName !== 'IMG') {
            canvas.querySelectorAll('img').forEach(i => i.classList.remove('img-selected'));
            selectedImg = null;
        }
    });

    // K. CONTEXT MENU
    function showCtxMenu(x, y) {
        const menu = document.getElementById('ctx-menu');
        menu.style.display = 'block';
        let ww = window.innerWidth;
        let wh = window.innerHeight;
        if (x + menu.offsetWidth > ww) x = ww - menu.offsetWidth;
        if (y + menu.offsetHeight > wh) y = wh - menu.offsetHeight;
        menu.style.left = Math.max(0, x) + 'px';
        menu.style.top = Math.max(0, y) + 'px';
    }

    function hideCtxMenu() {
        document.getElementById('ctx-menu').style.display = 'none';
        let btn = document.querySelector('#ctx-menu button.text-red-400');
        if(btn) btn.innerHTML = "🗑️ Delete Image";
        imgDeleteConfirmed = false;
    }

    function showCtxMenuPdf(x, y) {
        const menu = document.getElementById('ctx-menu-pdf');
        if (!menu) return;
        menu.style.display = 'block';
        let ww = window.innerWidth;
        let wh = window.innerHeight;
        if (x + menu.offsetWidth > ww) x = ww - menu.offsetWidth;
        if (y + menu.offsetHeight > wh) y = wh - menu.offsetHeight;
        menu.style.left = Math.max(0, x) + 'px';
        menu.style.top = Math.max(0, y) + 'px';
    }

    function hideCtxMenuPdf() {
        const menu = document.getElementById('ctx-menu-pdf');
        if (menu) menu.style.display = 'none';
        selectedPdfBtn = null;
    }

    function ctxOpenPdf() {
        if (selectedPdfBtn) {
            let url = selectedPdfBtn.getAttribute('data-pdf-url');
            if (url) {
                window.open(url, '_blank');
            }
        }
        hideCtxMenuPdf();
    }

    function applyPdfBtnStyle(styleNum) {
        if (!selectedPdfBtn) { hideCtxMenuPdf(); return; }
        const styles = {
            '1': "background:linear-gradient(90deg,#ef4444,#dc2626);color:#ffffff;border:none;border-radius:6px;padding:12px 24px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 4px 6px -1px rgba(220,38,38,0.3);",
            '2': "background:transparent;color:#2563eb;border:2px solid #3b82f6;border-radius:6px;padding:10px 22px;font-weight:bold;text-decoration:none;display:inline-block;",
            '3': "background:linear-gradient(135deg,#0f172a,#1e293b);color:#fbbf24;border:1px solid #fbbf24;border-radius:8px;padding:12px 26px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 10px 15px -3px rgba(0,0,0,0.4);letter-spacing:0.02em;",
            '4': "background:#10b981;color:#ffffff;border:none;border-radius:24px;padding:12px 28px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 4px 6px -1px rgba(16,185,129,0.3);",
            '5': "background:#ffffff;color:#7c3aed;border:none;border-radius:8px;padding:14px 28px;font-weight:800;text-decoration:none;display:inline-block;box-shadow:0 10px 25px -5px rgba(124,58,237,0.25);"
        };
        if (styles[styleNum]) {
            selectedPdfBtn.style.cssText = styles[styleNum];
        }
        hideCtxMenuPdf();
    }

    function ctxDeletePdfBtn() {
        if (selectedPdfBtn) {
            let parent = selectedPdfBtn.parentElement;
            if (parent && parent.tagName === 'DIV' && parent.children.length === 1 && parent.style.textAlign === 'center') {
                parent.remove();
            } else {
                selectedPdfBtn.remove();
            }
            selectedPdfBtn = null;
        }
        hideCtxMenuPdf();
    }

    document.getElementById('proposal_canvas').addEventListener('contextmenu', (e) => {
        e.preventDefault();
    });

    function ctxReplaceImage() {
        hideCtxMenu();
        document.getElementById('replace_img_input').click();
    }

    document.getElementById('replace_img_input').addEventListener('change', async function() {
        if (!selectedImg || this.files.length === 0) return;
        if (!CLD_CLOUD || !CLD_PRESET) {
            showToast('❌ Cloudinary not configured in .env — set CLOUDINARY_CLOUD and CLOUDINARY_PRESET', 'error');
            this.value = '';
            return;
        }
        const file     = this.files[0];
        const targetImg = selectedImg;
        this.value = '';

        targetImg.style.opacity = '0.4';
        targetImg.style.outline = '3px dashed #f59e0b';

        try {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('upload_preset', CLD_PRESET);
            const res  = await fetch(`https://api.cloudinary.com/v1_1/${CLD_CLOUD}/image/upload`, { method:'POST', body:fd });
            const data = await res.json();
            if (data.secure_url) {
                targetImg.src = data.secure_url;
                targetImg.removeAttribute('data-cid');
                addToGallery(data.secure_url, data.public_id || '');
                showToast('✅ Image replaced & saved to gallery');
            } else {
                throw new Error(data.error?.message || 'Upload failed');
            }
        } catch (e) {
            showToast('❌ ' + e.message, 'error');
        } finally {
            targetImg.style.opacity = '';
            targetImg.style.outline = '';
            selectedImg = null;
        }
    });

    document.getElementById('editor_insert_img_input').addEventListener('change', async function() {
        if (this.files.length === 0) return;
        if (!CLD_CLOUD || !CLD_PRESET) {
            showToast('❌ Cloudinary not configured in .env', 'error');
            this.value = '';
            return;
        }
        const file = this.files[0];
        this.value = '';

        const fd = new FormData();
        fd.append('file', file);
        
        let useSignedUpload = !!CLD_API_KEY && CLD_API_SECRET_CONFIGURED;
        let signedParams = null;

        if (!!CLD_API_KEY && !CLD_API_SECRET_CONFIGURED) {
            showToast('❌ Cloudinary API key is configured but API Secret is missing.', 'error');
            return;
        }

        if (useSignedUpload) {
            try {
                const signForm = csrfForm(new FormData());
                signForm.append('action', 'cloudinary_sign');
                if (CLD_PRESET) signForm.append('upload_preset', CLD_PRESET);
                const signRes = await fetch('', { method: 'POST', body: signForm });
                signedParams = await signRes.json();
                if (signedParams.status !== 'success') {
                    throw new Error(signedParams.message || 'Cloudinary signing failed');
                }
            } catch (err) {
                showToast('❌ ' + err.message, 'error');
                return;
            }
        } else if (!CLD_PRESET) {
            showToast('❌ Cloudinary unsigned upload requires CLOUDINARY_PRESET.', 'error');
            return;
        }

        if (signedParams) {
            fd.append('api_key', signedParams.api_key);
            fd.append('timestamp', signedParams.timestamp);
            fd.append('signature', signedParams.signature);
            if (CLD_PRESET) fd.append('upload_preset', CLD_PRESET);
        } else {
            fd.append('upload_preset', CLD_PRESET);
        }

        showToast('⏳ Uploading image...', 'info');

        try {
            const res  = await fetch(`https://api.cloudinary.com/v1_1/${CLD_CLOUD}/image/upload`, { method:'POST', body:fd });
            const data = await res.json();
            if (data.secure_url) {
                addToGallery(data.secure_url, data.public_id || '');
                restoreCursorAndInsert(`<img src="${data.secure_url}" style="max-width:100%; height:auto; display:block; margin:8px auto;">`);
                showToast('✅ Image inserted!');
            } else {
                throw new Error(data.error?.message || 'Upload failed');
            }
        } catch (e) {
            showToast('❌ ' + e.message, 'error');
        }
    });

    function ctxOpenGallery() {
        hideCtxMenu();
        galleryMode = 'replace';
        document.getElementById('gallery-modal').style.display = 'flex';
    }

    function ctxOpenResizePanel() {
        hideCtxMenu();
        if (!selectedImg) return;
        populateImgPanel(selectedImg);
        const panel = document.getElementById('img-panel');
        panel.style.display = 'block';
        const rect = selectedImg.getBoundingClientRect();
        let x = rect.right + 10;
        let y = rect.top;
        if (x + panel.offsetWidth > window.innerWidth) x = rect.left - panel.offsetWidth - 10;
        if (y + panel.offsetHeight > window.innerHeight) y = window.innerHeight - panel.offsetHeight;
        panel.style.left = Math.max(0, x) + 'px';
        panel.style.top = Math.max(0, y) + 'px';
    }

    let imgDeleteConfirmed = false;
    function ctxDeleteImage() {
        if (!imgDeleteConfirmed) {
            const btn = document.querySelector('#ctx-menu button.text-red-400');
            btn.innerText = "⚠️ Click again to delete";
            imgDeleteConfirmed = true;
            setTimeout(() => { if(btn) btn.innerHTML = "🗑️ Delete Image"; imgDeleteConfirmed = false; }, 3000);
            return;
        }
        hideCtxMenu();
        if (selectedImg) {
            selectedImg.remove();
            selectedImg = null;
            closeImgPanel();
        }
        imgDeleteConfirmed = false;
        const btn = document.querySelector('#ctx-menu button.text-red-400');
        if(btn) btn.innerHTML = "🗑️ Delete Image";
    }

    function deleteTemplate() {
        const sel = document.getElementById('template_select');
        const key = sel.value;
        if (sel.options.length <= 1) {
            showToast("❌ Cannot delete the last template", "error");
            return;
        }
        
        if (!deleteConfirmed) {
            const banner = document.getElementById('delete_confirm_banner');
            banner.classList.remove('hidden');
            banner.innerHTML = `<span>Delete ${key}?</span> <div class="flex gap-2"><button onclick="confirmDeleteTemplate('${key}')" class="bg-red-500 text-white px-2 py-1 rounded">Yes</button><button onclick="cancelDeleteTemplate()" class="bg-slate-300 px-2 py-1 rounded text-slate-800">No</button></div>`;
            deleteConfirmed = true;
            return;
        }
    }
    
    async function confirmDeleteTemplate(key) {
        let formData = csrfForm(new FormData());
        formData.append('action', 'delete_template');
        formData.append('template_name', key);
        try {
            let res = await fetch('', { method: 'POST', body: formData });
            let data = await res.json();
            if (data.status === 'success') {
                showToast("✅ Deleted", "success");
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message || "❌ Error", "error");
            }
        } catch(e) {
            showToast("❌ Network error", "error");
        }
        cancelDeleteTemplate();
    }
    
    function cancelDeleteTemplate() {
        deleteConfirmed = false;
        document.getElementById('delete_confirm_banner').classList.add('hidden');
    }

    // L. IMAGE PROPERTIES PANEL
    function populateImgPanel(img) {
        document.getElementById('img_width').value = img.style.width || '';
        document.getElementById('img_height').value = img.style.height || '';
        document.getElementById('img_radius').value = parseInt(img.style.borderRadius) || '';
        document.getElementById('img_fit').value = img.style.objectFit || 'fill';
        
        let shadow = 'None';
        if (img.style.boxShadow.includes('0px 2px')) shadow = 'Light';
        else if (img.style.boxShadow.includes('0px 4px')) shadow = 'Medium';
        else if (img.style.boxShadow.includes('0px 10px')) shadow = 'Heavy';
        document.getElementById('img_shadow').value = shadow;
    }

    function applyImgProps() {
        if (!selectedImg) return;
        let w = document.getElementById('img_width').value;
        let h = document.getElementById('img_height').value;
        let r = document.getElementById('img_radius').value;
        let fit = document.getElementById('img_fit').value;
        let shadow = document.getElementById('img_shadow').value;

        if (w && !isNaN(w)) w += 'px';
        if (h && !isNaN(h)) h += 'px';

        selectedImg.style.width = w;
        selectedImg.style.height = h;
        if (r) selectedImg.style.borderRadius = r + 'px';
        else selectedImg.style.borderRadius = '';
        
        selectedImg.style.objectFit = fit;

        let shadowStr = 'none';
        if (shadow === 'Light') shadowStr = '0 2px 4px rgba(0,0,0,0.1)';
        else if (shadow === 'Medium') shadowStr = '0 4px 6px rgba(0,0,0,0.1)';
        else if (shadow === 'Heavy') shadowStr = '0 10px 15px rgba(0,0,0,0.2)';
        selectedImg.style.boxShadow = shadowStr;
        selectedImg.style.display = 'block';
    }

    function setImgAlign(align) {
        if (!selectedImg) return;
        selectedImg.style.display = 'block';
        if (align === 'center') selectedImg.style.margin = '8px auto';
        if (align === 'left') selectedImg.style.margin = '8px auto 8px 0';
        if (align === 'right') selectedImg.style.margin = '8px 0 8px auto';
    }

    function closeImgPanel() {
        document.getElementById('img-panel').style.display = 'none';
    }

    // M. TOOLBAR & CURSOR
    function execCmd(cmd, val = null) {
        if (isPreviewMode) return;
        const canvas = document.getElementById('proposal_canvas');
        canvas.focus();
        if (lastRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(lastRange);
        }
        document.execCommand(cmd, false, val);
    }

    function applyFontSize(v) {
        execCmd('fontSize', v);
    }

    function setCanvasBg(color) {
        document.getElementById('proposal_canvas').style.backgroundColor = color;
    }

    document.getElementById('proposal_canvas').addEventListener('keyup', saveCursor);
    document.getElementById('proposal_canvas').addEventListener('mouseup', saveCursor);

    function saveCursor() {
        let sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount > 0) {
            lastRange = sel.getRangeAt(0).cloneRange();
        }
    }

    function restoreCursor() {
        document.getElementById('proposal_canvas').focus();
        if (lastRange) {
            let sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(lastRange);
        }
    }

    function restoreCursorAndInsert(html) {
        restoreCursor();
        insertHtmlAtCursor(html);
    }

    // N. INSERT HTML AT CURSOR
    function insertHtmlAtCursor(html) {
        const canvas = document.getElementById('proposal_canvas');
        canvas.focus();
        let sel = window.getSelection();
        if (!sel || !canvas.contains(sel.anchorNode) && !canvas.contains(sel.focusNode)) {
            const range = document.createRange();
            range.selectNodeContents(canvas);
            range.collapse(false);
            sel.removeAllRanges();
            sel.addRange(range);
        }
        if (sel && sel.getRangeAt && sel.rangeCount) {
            let range = sel.getRangeAt(0);
            range.deleteContents();
            let el = document.createElement('div');
            el.innerHTML = html;
            let frag = document.createDocumentFragment(), node, lastNode;
            while ( (node = el.firstChild) ) {
                lastNode = frag.appendChild(node);
            }
            range.insertNode(frag);
            if (lastNode) {
                range = range.cloneRange();
                range.setStartAfter(lastNode);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        } else {
            canvas.innerHTML += html;
        }

        bindCanvasImageEvents();
        updateStatusBar();

        // Sync tokenBase after inserting new content
        if (phViewMode === 'values') {
            let tb = canvas.innerHTML;
            const fields = ['Hotel_Name','Address','City','State','Zip','Rooms','Extra','Email','Hotel_Number','Contact_Person','First_Name','Service_Provider','offeredProgramming','System','Bill','Upfront'];
            fields.forEach(key => {
                const el = document.getElementById('p_' + key);
                if (!el || !el.value) return;
                let val = el.value;
                if ((key === 'Bill' || key === 'Upfront') && val) {
                    let num = parseFloat(val.replace(/[^0-9.-]+/g, ""));
                    if (!isNaN(num)) val = new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(num);
                }
                if (val) {
                    const safe = val.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    let regexStr = safe;
                    if (/^\w/.test(val)) regexStr = '\\b' + regexStr;
                    if (/\w$/.test(val)) regexStr = regexStr + '\\b';
                    tb = tb.replace(new RegExp(regexStr, 'gi'), '{{' + key + '}}');
                }
            });
            tokenBase = tb;
        } else {
            tokenBase = canvas.innerHTML;
        }
        liveCanvasBase = canvas.innerHTML;
        _lastSnapshot = canvas.innerHTML;
    }

    // O. INSERT COMPONENTS

    function insertTableComponent() {
        if (isPreviewMode) return;
        let html = `<table width="100%" border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; border:1px solid #cbd5e1; margin:16px 0;">
            <thead><tr style="background-color:#f1f5f9;"><th>Header 1</th><th>Header 2</th></tr></thead>
            <tbody><tr><td>Data 1</td><td>Data 2</td></tr></tbody>
        </table>`;
        insertHtmlAtCursor(html);
    }

    function insertDividerComponent() {
        if (isPreviewMode) return;
        insertHtmlAtCursor(`<hr style="border:0; border-top:3px solid #cbd5e1; margin:24px 0;">`);
    }

    function insertCallbackLink() {
        if (isPreviewMode) return;
        const callbackUrl = '<?= rtrim(APP_URL, "/") ?>/callback?tid={{TRACKING_ID}}&name={{Contact_Person}}&email={{Email}}';
        const html = `
<div style="text-align:center; margin:30px 0; padding:25px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">
    <p style="font-size:16px; font-weight:600; color:#0c4a6e; margin:0 0 12px 0;">Want to discuss this proposal?</p>
    <a href="${callbackUrl}" style="display:inline-block; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#ffffff; padding:14px 32px; border-radius:8px; font-weight:700; font-size:14px; text-decoration:none; font-family:Arial,sans-serif; letter-spacing:0.3px; box-shadow:0 4px 12px rgba(37,99,235,0.3);">📞 Schedule a Callback</a>
</div>`;
        insertHtmlAtCursor(html);
    }

    function insertImageFromUrl() {
        if (isPreviewMode) return;
        saveCursor();
        document.getElementById('editor_insert_img_input').click();
    }

    // P. CSV BULK
    function normalizeCsvHeader(value = '') {
        return String(value || '')
            .replace(/\uFEFF/g, '')
            .replace(/^['"]|['"]$/g, '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function canonicalCsvHeader(value = '') {
        const lookup = {
            email: 'Email',
            email_address: 'Email',
            client_email: 'Email',
            hotel_name: 'Hotel_Name',
            hotel: 'Hotel_Name',
            property_name: 'Hotel_Name',
            address: 'Address',
            city: 'City',
            state: 'State',
            zip: 'Zip',
            zip_code: 'Zip',
            rooms: 'Rooms',
            extra: 'Extra',
            hotel_number: 'Hotel_Number',
            property_no: 'Hotel_Number',
            contact_person: 'Contact_Person',
            first_name: 'First_Name',
            contact: 'Contact_Person',
            person_name: 'Contact_Person',
            offeredprogramming: 'offeredProgramming',
            offered_programming: 'offeredProgramming',
            system: 'System',
            bill: 'Bill',
            upfront: 'Upfront',
            upfront_amount: 'Upfront'
        };
        const normalized = normalizeCsvHeader(value);
        return lookup[normalized] || String(value || '').trim();
    }

    function getCsvEmailValue(row = {}) {
        const candidates = [
            row['Email'],
            row['email'],
            row['Email Address'],
            row['email_address'],
            row['client_email'],
            row['Client Email']
        ];
        return (candidates.find(v => typeof v === 'string' && v.trim()) || '').trim();
    }

    async function* iterateCsvRecords(file) {
        const decoder = new TextDecoder('utf-8');
        let offset = 0;
        let buffer = '';
        let row = [];
        let cell = '';
        let inQuotes = false;
        let recordChars = 0;

        const consume = (text, final = false) => {
            buffer += text;
            const records = [];
            let i = 0;

            for (; i < buffer.length; i++) {
                const ch = buffer[i];
                const next = buffer[i + 1];
                recordChars++;
                if (recordChars > CSV_MAX_RECORD_CHARS) {
                    throw new Error('A CSV row exceeds the 64K character limit.');
                }
                if (ch === '"') {
                    if (inQuotes && next === '"') {
                        cell += '"';
                        i++;
                    } else if (i === buffer.length - 1 && !final) {
                        break;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (ch === ',' && !inQuotes) {
                    row.push(cell);
                    cell = '';
                } else if ((ch === '\n' || ch === '\r') && !inQuotes) {
                    if (ch === '\r' && i === buffer.length - 1 && !final) break;
                    row.push(cell);
                    records.push(row);
                    row = [];
                    cell = '';
                    recordChars = 0;
                    if (ch === '\r' && next === '\n') i++;
                } else {
                    cell += ch;
                }
            }

            buffer = buffer.slice(i);
            if (final) {
                if (cell.length > 0 || row.length > 0 || buffer.length > 0) {
                    cell += buffer;
                    row.push(cell);
                    records.push(row);
                }
                buffer = '';
            }
            return records;
        };

        while (offset < file.size) {
            const end = Math.min(offset + CSV_READ_CHUNK_BYTES, file.size);
            const text = decoder.decode(await file.slice(offset, end).arrayBuffer(), { stream: true });
            offset = end;
            for (const record of consume(text)) yield record;
        }
        for (const record of consume(decoder.decode(), true)) yield record;
    }

    function csvRecordToObject(headers, record) {
        const obj = {};
        headers.forEach((header, index) => {
            const value = String(record[index] || '').trim();
            const key = canonicalCsvHeader(header);
            obj[key] = value;
            if (header) obj[header] = value;
        });
        return obj;
    }

    function hasCsvValues(row) {
        return Object.keys(row).some(key => String(row[key] || '').trim());
    }

    async function readCsvPreview(file, maxRows) {
        const iterator = iterateCsvRecords(file)[Symbol.asyncIterator]();
        let headers = null;
        const rows = [];
        let previewChars = 0;

        while (true) {
            const next = await iterator.next();
            if (next.done) return { headers: headers || [], rows, hasMore: false };

            if (headers === null) {
                if (next.value.every(value => !String(value || '').trim())) continue;
                headers = next.value.map(value => String(value || '').replace(/^\uFEFF/, '').trim());
                continue;
            }

            const record = csvRecordToObject(headers, next.value);
            if (!hasCsvValues(record)) continue;
            const recordChars = next.value.reduce((total, value) => total + String(value || '').length, 0);
            if (rows.length && previewChars + recordChars > CSV_PREVIEW_MAX_CHARS) {
                return { headers, rows, hasMore: true };
            }
            rows.push(record);
            previewChars += recordChars;
            if (rows.length === maxRows || previewChars >= CSV_PREVIEW_MAX_CHARS) {
                const extra = await iterator.next();
                return { headers, rows, hasMore: !extra.done };
            }
        }
    }

    async function* iterateCsvData(file) {
        const iterator = iterateCsvRecords(file)[Symbol.asyncIterator]();
        let headers = null;

        while (true) {
            const next = await iterator.next();
            if (next.done) return;

            if (headers === null) {
                if (next.value.every(value => !String(value || '').trim())) continue;
                headers = next.value.map(value => String(value || '').replace(/^\uFEFF/, '').trim());
                continue;
            }

            const record = csvRecordToObject(headers, next.value);
            if (hasCsvValues(record)) yield record;
        }
    }

    async function* getBulkRows() {
        if (csvSourceFile) {
            yield* iterateCsvData(csvSourceFile);
            return;
        }
        for (const row of csvData) yield row;
    }

    function replacePlaceholdersInHtml(html, row) {
        let res = html;
        for (let key in row) {
            let val = decodeHtmlEntities(row[key] || '');
            if ((key === 'Bill' || key === 'Upfront') && val) {
                let num = parseFloat(val.replace(/[^0-9.-]+/g, ""));
                if (!isNaN(num)) {
                    val = new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(num);
                }
            }
            res = res.split('{{' + key + '}}').join(val);
        }
        return res;
    }

    function resolveMappedCsvValue(row, targetKey) {
        const sourceKey = csvFieldMapping[targetKey];
        if (sourceKey && row[sourceKey] !== undefined) return row[sourceKey];
        if (row[targetKey] !== undefined) return row[targetKey];
        const aliases = [
            targetKey,
            canonicalCsvHeader(targetKey),
            normalizeCsvHeader(targetKey),
            targetKey.toLowerCase(),
            targetKey.replace(/_/g, ' '),
            targetKey.replace(/_/g, '')
        ];
        for (const alias of aliases) {
            if (row[alias] !== undefined) return row[alias];
        }
        return '';
    }

    function buildCsvMappingControls() {
        const container = document.getElementById('csv_mapping_fields');
        const mappingBox = document.getElementById('csv_mapping_container');
        if (!container || !mappingBox) return;

        const fieldNames = Object.keys(<?php echo json_encode($placeholderFields); ?>);
        const options = [...new Set(csvDetectedHeaders.filter(Boolean))];
        if (!options.length) {
            mappingBox.classList.add('hidden');
            container.innerHTML = '';
            return;
        }

        mappingBox.classList.remove('hidden');
        container.innerHTML = '';
        fieldNames.forEach((key) => {
            const rowEl = document.createElement('div');
            rowEl.style.display = 'flex';
            rowEl.style.alignItems = 'center';
            rowEl.style.gap = '6px';

            const label = document.createElement('label');
            label.textContent = key;
            label.style.fontSize = '0.65rem';
            label.style.color = '#475569';
            label.style.minWidth = '88px';

            const select = document.createElement('select');
            select.style.flex = '1';
            select.style.fontSize = '0.68rem';
            select.style.padding = '4px 6px';
            select.style.border = '1px solid #cbd5e1';
            select.style.borderRadius = '4px';
            select.style.background = '#fff';

            const auto = document.createElement('option');
            auto.value = '';
            auto.textContent = 'Auto detect';
            select.appendChild(auto);

            options.forEach((header) => {
                const opt = document.createElement('option');
                opt.value = header;
                opt.textContent = header;
                select.appendChild(opt);
            });

            const detected = Object.keys(csvFieldMapping).includes(key) ? csvFieldMapping[key] : '';
            const matching = detected || options.find((header) => normalizeCsvHeader(header) === normalizeCsvHeader(key)) || '';
            select.value = matching;
            select.onchange = () => {
                csvFieldMapping[key] = select.value || '';
                if (currentPreviewIndex >= 0 && csvData[currentPreviewIndex]) previewCSVRow(currentPreviewIndex);
            };

            rowEl.appendChild(label);
            rowEl.appendChild(select);
            container.appendChild(rowEl);
        });
    }

    function previewCSVRow(index) {
        if (csvData.length === 0) return;

        const canvas = document.getElementById('proposal_canvas');
        const previewAlert = document.getElementById('preview_alert');

        if (!editorContentBackup) {
            editorContentBackup = canvas.innerHTML;
            sessionStorage.setItem('csv_backup', editorContentBackup);
        }

        if (previewAlert) previewAlert.classList.add('hidden');
        if (canvas) {
            canvas.classList.remove('preview-active');
            canvas.contentEditable = 'true';
        }

        document.getElementById('csv_record_count').innerText = `Record ${index + 1} of ${csvData.length}`;
        let row = csvData[index];

        const mappedRow = { ...row };
            Object.keys(<?php echo json_encode($placeholderFields); ?>).forEach((key) => {
                const val = resolveMappedCsvValue(row, key);
                mappedRow[key] = val;
            });

        const emailVal = resolveMappedCsvValue(row, 'Email');
        if (emailVal) mappedRow.Email = emailVal;

        let replacedHtml = replacePlaceholdersInHtml(editorContentBackup, mappedRow);
        canvas.innerHTML = replacedHtml;

        <?php foreach($placeholderFields as $key => $label): ?>
            const value_<?= htmlspecialchars($key, ENT_QUOTES) ?> = resolveMappedCsvValue(row, '<?= htmlspecialchars($key, ENT_QUOTES) ?>');
            let el_<?= htmlspecialchars($key, ENT_QUOTES) ?> = document.getElementById('p_<?= htmlspecialchars($key, ENT_QUOTES) ?>');
            if (el_<?= htmlspecialchars($key, ENT_QUOTES) ?>) el_<?= htmlspecialchars($key, ENT_QUOTES) ?>.value = value_<?= htmlspecialchars($key, ENT_QUOTES) ?> || '';
        <?php endforeach; ?>

        const subjectInput = document.getElementById('email_subject');
        const subjectBase = subjectInput ? subjectInput.value : '';
        const renderedSubject = subjectBase ? replacePlaceholdersInHtml(subjectBase, mappedRow) : '';
        const subjectPreview = document.getElementById('subject_preview_text');
        if (subjectPreview) subjectPreview.innerText = renderedSubject;

        if (emailVal) {
            setEmailTagsFromString(emailVal);
        }
    }

    function nextCSV() {
        if (currentPreviewIndex < csvData.length - 1) {
            currentPreviewIndex++;
            previewCSVRow(currentPreviewIndex);
        }
    }

    function prevCSV() {
        if (currentPreviewIndex > 0) {
            currentPreviewIndex--;
            previewCSVRow(currentPreviewIndex);
        }
    }

    function exitPreview() {
        if (!editorContentBackup && !sessionStorage.getItem('csv_backup')) return;
        if (!editorContentBackup) editorContentBackup = sessionStorage.getItem('csv_backup') || '';
        sessionStorage.removeItem('csv_backup');
        const canvas = document.getElementById('proposal_canvas');
        if (canvas) {
            canvas.innerHTML = editorContentBackup;
            canvas.contentEditable = 'true';
            canvas.classList.remove('preview-active');
        }
        const previewAlert = document.getElementById('preview_alert');
        if (previewAlert) previewAlert.classList.add('hidden');
        document.getElementById('csv_preview_controls').classList.add('hidden');
        document.getElementById('csv_actions').classList.add('hidden');
        document.getElementById('csv_mapping_container').classList.add('hidden');
        document.getElementById('csv_mapping_fields').innerHTML = '';
        document.getElementById('csv_errors').classList.add('hidden');
        document.getElementById('csv_errors').innerHTML = '';
        const csvFile = document.getElementById('csv_file');
        if (csvFile) csvFile.value = '';
        document.getElementById('csv_label_text').textContent = 'Drop CSV or click to upload';
        csvData = [];
        csvSourceFile = null;
        isPreviewMode = false;
        bulkConfirmed = false;
        document.getElementById('bulk_progress').classList.add('hidden');
        document.getElementById('bulk_progress').innerText = '';
        editorContentBackup = ''; // Clear backup so subsequent edits are recognized as the template
        bindCanvasImageEvents();
        closeBulkModal();
    }

    function downloadCSVSample() {
        const headers = [];
        <?php foreach($placeholderFields as $key => $label): ?>
            headers.push('<?= htmlspecialchars($key, ENT_QUOTES) ?>');
        <?php endforeach; ?>
        
        let csvContent = headers.join(',') + "\n";
        let row1 = headers.map(h => h === 'Email' ? 'test1@example.com' : 'Sample1').join(',');
        let row2 = headers.map(h => h === 'Email' ? 'test2@example.com' : 'Sample2').join(',');
        csvContent += row1 + "\n" + row2;
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "proposal_contacts_format.csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    
    async function processBulkEmails(isDraft) {
        if (csvData.length === 0 && !csvSourceFile) { showToast('Upload a CSV file first', 'error'); return; }

        const dBtn = document.getElementById('bulk_draft_btn');
        const sBtn = document.getElementById('bulk_send_btn');
        const tBtn = document.getElementById('bulk_test_btn');
        const bp   = document.getElementById('bulk_progress');

        if (!bulkConfirmed) {
            bp.classList.remove('hidden');
            bp.style.color = '#92400e';
            const recordsLabel = csvSourceFile ? 'the entire selected CSV file' : `${csvData.length} records`;
            bp.innerText = `⚠️ Confirm: click ${isDraft ? 'Draft' : 'Send'} again to process ${recordsLabel}.`;
            if (sBtn && !isDraft) {
                sBtn.innerText = '⚠️ Confirm send';
                sBtn.disabled = false;
            }
            if (dBtn && isDraft) {
                dBtn.innerText = '⚠️ Confirm draft';
                dBtn.disabled = false;
            }
            bulkConfirmed = true;
            if (bulkConfirmTimeout) clearTimeout(bulkConfirmTimeout);
            bulkConfirmTimeout = setTimeout(() => {
                bulkConfirmed = false;
                if (sBtn && !isDraft) {
                    sBtn.innerText = '🚀 Send';
                }
                if (dBtn && isDraft) {
                    dBtn.innerText = '📝 Draft';
                }
                bp.innerText = '';
                bp.classList.add('hidden');
            }, 4000);
            return;
        }
        if (bulkConfirmTimeout) clearTimeout(bulkConfirmTimeout);
        bulkConfirmed = false;

        dBtn.disabled = true; sBtn.disabled = true; tBtn.disabled = true;
        if (sBtn && !isDraft) {
            sBtn.innerText = '📨 Sending…';
        }
        if (dBtn && isDraft) {
            dBtn.innerText = '📨 Saving…';
        }
        bp.classList.remove('hidden');
        bp.style.color = '#334155';

        undoStack = []; redoStack = [];
        tokenBase = ''; liveCanvasBase = ''; _lastSnapshot = '';
        sessionStorage.removeItem('csv_backup');
        const templateHtml = editorContentBackup || document.getElementById('proposal_canvas').innerHTML;
        editorContentBackup = templateHtml;

        let successCount = 0, failCount = 0, processedCount = 0, omittedFailedRows = 0;
        const failedRows = [];
        const maxFailedRowsToExport = 500;
        const maxFailedCharsToRetain = 1024 * 1024;
        let failedCharsRetained = 0;
        const baseSubject = (document.getElementById('email_subject')?.value || '').trim();
        const fileInput   = document.getElementById('sys_attach_input');
        const attachments = selectedAttachmentFiles.length > 0 ? selectedAttachmentFiles : (fileInput && fileInput.files.length > 0 ? Array.from(fileInput.files) : []);
        const total       = csvSourceFile ? null : csvData.length;
        const recordFailure = (row) => {
            const serializedLength = JSON.stringify(row).length;
            if (failedRows.length < maxFailedRowsToExport && failedCharsRetained + serializedLength <= maxFailedCharsToRetain) {
                failedRows.push(row);
                failedCharsRetained += serializedLength;
            } else {
                omittedFailedRows++;
            }
        };

        let streamError = '';
        try {
            for await (const row of getBulkRows()) {
                processedCount++;
                const mappedRow = { ...row };
                Object.keys(<?php echo json_encode($placeholderFields); ?>).forEach((key) => {
                    const val = resolveMappedCsvValue(row, key);
                    mappedRow[key] = val;
                });
                const rawEmail = resolveMappedCsvValue(row, 'Email') || '';

                bp.innerText = `📨 Sending (${rawEmail || 'unknown'}) ${processedCount}${total === null ? ' rows processed' : ' of ' + total} — ✅ ${successCount} sent ❌ ${failCount} failed`;

                if (!rawEmail) {
                    failCount++;
                    recordFailure({ ...mappedRow, Email: rawEmail || '', FailureReason: 'Missing Email' });
                    continue;
                }
                const emails = parseEmailList(rawEmail).filter(isValidEmail);
                if (!emails.length) {
                    failCount++;
                    recordFailure({ ...mappedRow, Email: rawEmail, FailureReason: 'Invalid Email' });
                    continue;
                }

                const htmlContent = replacePlaceholdersInHtml(templateHtml, mappedRow);
                const subject = replacePlaceholdersInHtml(baseSubject || 'Proposal', mappedRow);

                const formData = csrfForm(new FormData());
                formData.append('action', 'process_email');
                emails.forEach(em => formData.append('client_email[]', em));
                formData.append('email_subject', subject);
                formData.append('html_content', htmlContent);
                if (typeof isDraft !== 'undefined' && isDraft) formData.append('is_draft', '1');
                attachments.forEach(file => formData.append('attachment[]', file));

                try {
                    const res = await fetch('', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (res.ok && data.status === 'success') successCount++;
                    else {
                        failCount++;
                        recordFailure({ ...mappedRow, Email: rawEmail, FailureReason: data.message || 'Send failed' });
                    }
                } catch {
                    failCount++;
                    recordFailure({ ...mappedRow, Email: rawEmail, FailureReason: 'Network error' });
                }
            }
        } catch (error) {
            streamError = error instanceof Error ? error.message : 'CSV processing failed.';
        }

        bp.style.color = failCount === 0 && !streamError ? '#15803d' : '#b45309';
        bp.innerText   = streamError
            ? `⚠️ Stopped after ${processedCount} rows — ${successCount} sent, ${failCount} failed. ${streamError}`
            : `✅ Done — ${successCount} sent, ${failCount} failed across ${processedCount} rows`;
        if (sBtn && !isDraft) sBtn.innerText = '🚀 Send';
        if (dBtn && isDraft) dBtn.innerText = '📝 Draft';
        dBtn.disabled  = false;
        sBtn.disabled  = false;
        tBtn.disabled  = false;

        if (failCount > 0) {
            exportFailedRowsCsv(failedRows);
            showToast(`${failCount} rows failed; ${failedRows.length} included in the failure CSV${omittedFailedRows ? ` and ${omittedFailedRows} omitted to protect memory` : ''}.`, 'error');
        }
        if (streamError) {
            showToast('Processing stopped early. Re-upload a corrected CSV before continuing; completed rows were not rolled back.', 'error');
        }

        const canvas = document.getElementById('proposal_canvas');
        if (canvas) {
            canvas.innerHTML = templateHtml;
            canvas.contentEditable = 'true';
            canvas.classList.remove('preview-active');
        }
        tokenBase = templateHtml;
        liveCanvasBase = templateHtml;
        csvData = [];
        isPreviewMode = false;
        const previewAlert = document.getElementById('preview_alert');
        if (previewAlert) previewAlert.classList.add('hidden');
        document.getElementById('csv_preview_controls').classList.add('hidden');
        document.getElementById('csv_actions').classList.add('hidden');
        document.getElementById('csv_file').value = '';
        document.getElementById('csv_label_text').textContent = 'Drop CSV or click to upload';
        csvSourceFile = null;
        bindCanvasImageEvents();
    }

    async function sendBulkTestMail() {
        if (csvData.length === 0) { showToast('Upload a CSV file first', 'error'); return; }
        const row = csvData[0];
        const mappedRow = {};
        Object.keys(<?php echo json_encode($placeholderFields); ?>).forEach((key) => {
            mappedRow[key] = resolveMappedCsvValue(row, key);
        });

        const templateHtml = editorContentBackup || document.getElementById('proposal_canvas').innerHTML;
        const baseSubject = (document.getElementById('email_subject')?.value || '').trim();
        const htmlContent = replacePlaceholdersInHtml(templateHtml, mappedRow);
        const subject = replacePlaceholdersInHtml(baseSubject || 'Proposal', mappedRow);
        const formData = csrfForm(new FormData());
        formData.append('action', 'process_email');
        formData.append('client_email[]', APP_EMAIL);
        formData.append('email_subject', subject);
        formData.append('html_content', htmlContent);

        const bp = document.getElementById('bulk_progress');
        bp.classList.remove('hidden');
        bp.style.color = '#334155';
        bp.innerText = `🧪 Sending test mail to ${APP_EMAIL} using the first mapped row...`;

        try {
            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();
            if (res.ok && data.status === 'success') {
                bp.style.color = '#15803d';
                bp.innerText = `✅ Test mail sent to ${APP_EMAIL}`;
                showToast('Test mail sent successfully.', 'success');
            } else {
                bp.style.color = '#b91c1c';
                bp.innerText = data.message || 'Test mail failed.';
                showToast(data.message || 'Test mail failed.', 'error');
            }
        } catch (err) {
            bp.style.color = '#b91c1c';
            bp.innerText = 'Test mail failed due to a network error.';
            showToast('Test mail failed due to a network error.', 'error');
        }
    }

    // Helper for escaping HTML strings
    function escSafe(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // Live Track ID Status Checker
    let trackIdCheckTimer = null;
    async function checkCurrentTrackId() {
        clearTimeout(trackIdCheckTimer);
        trackIdCheckTimer = setTimeout(async () => {
            const providerVal = document.getElementById('p_Service_Provider') ? document.getElementById('p_Service_Provider').value.trim() : '';
            const addressVal = document.getElementById('p_Address') ? document.getElementById('p_Address').value.trim() : '';
            const alertEl = document.getElementById('track_id_live_alert');
            if (!alertEl) return;

            let customId = '';
            if (providerVal || addressVal) {
                customId = (providerVal + '-' + addressVal).replace(/^-|-$/g, '').trim();
            }

            if (!customId) {
                alertEl.classList.add('hidden');
                alertEl.innerHTML = '';
                return;
            }

            try {
                const res = await fetch('<?= BASE_PATH ?>/studio/check-track-id?custom_id=' + encodeURIComponent(customId));
                const data = await res.json();
                if (data.ok && data.found) {
                    alertEl.classList.remove('hidden');
                    if (data.is_closed) {
                        alertEl.className = 'mt-2 p-2.5 rounded-lg bg-amber-50 border border-amber-300 text-amber-950 text-xs shadow-xs';
                        alertEl.innerHTML = `
                            <div class="flex items-center justify-between font-bold mb-1">
                                <span class="text-[11px] uppercase tracking-wide text-amber-800">⚠️ Track ID Marked Closed</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold">${escSafe(data.closed_at || 'Closed')}</span>
                            </div>
                            <div class="text-[11px] font-mono text-slate-700 mb-1"><b>ID:</b> ${escSafe(customId)}</div>
                            ${data.closed_remark ? `<div class="bg-white/90 border border-amber-200 rounded p-1.5 mt-1 text-[11px] text-slate-800 leading-snug"><b>Remark:</b> ${escSafe(data.closed_remark)}</div>` : ''}
                        `;
                    } else if (data.closed_remark) {
                        alertEl.className = 'mt-2 p-2.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-950 text-xs shadow-xs';
                        alertEl.innerHTML = `
                            <div class="font-bold text-[11px] uppercase tracking-wide text-blue-800 mb-1">ℹ️ Prior Activity / Remark</div>
                            <div class="text-[11px] font-mono text-slate-700 mb-1"><b>ID:</b> ${escSafe(customId)}</div>
                            <div class="bg-white/90 border border-blue-200 rounded p-1.5 mt-1 text-[11px] text-slate-800 leading-snug"><b>Remark:</b> ${escSafe(data.closed_remark)}</div>
                        `;
                    } else {
                        alertEl.className = 'mt-2 p-2 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 text-[11px]';
                        alertEl.innerHTML = `<span>ℹ️ Track ID: <b class="font-mono">${escSafe(customId)}</b> (${data.count} prior send${data.count > 1 ? 's' : ''})</span>`;
                    }
                } else {
                    alertEl.classList.add('hidden');
                    alertEl.innerHTML = '';
                }
            } catch (e) {
                alertEl.classList.add('hidden');
            }
        }, 350);
    }

    // Attach live Track ID check to property inputs
    document.addEventListener('DOMContentLoaded', () => {
        ['p_Service_Provider', 'p_Address'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', checkCurrentTrackId);
                el.addEventListener('change', checkCurrentTrackId);
            }
        });
        checkCurrentTrackId();
    });

    // Q. SINGLE SEND / DRAFT
    let isProcessingEmail = false;

    async function processEmail(isDraft) {
        if (isProcessingEmail) return;
        isProcessingEmail = true;

        const draftBtn = document.getElementById('draft_btn');
        const sendBtn = document.getElementById('send_btn');
        
        const disableBtn = (btn) => {
            if (!btn) return;
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        };
        const enableBtn = (btn) => {
            if (!btn) return;
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        };

        disableBtn(draftBtn);
        disableBtn(sendBtn);

        try {
            await _processEmail(isDraft);
        } finally {
            isProcessingEmail = false;
            enableBtn(draftBtn);
            enableBtn(sendBtn);
        }
    }

    async function _processEmail(isDraft) {
        if (isPreviewMode) {
            showToast('Exit preview mode to send single emails.', 'error');
            return;
        }

        // Flush any pending tag input before reading
        if (emailTagInput.value.trim()) addEmailTags(emailTagInput.value);
        const emails = emailTags.filter(isValidEmail);
        if (!emails.length) {
            showToast('Please provide at least one valid email.', 'error');
            return;
        }

        // Compute custom searchable ID
        const providerVal = document.getElementById('p_Service_Provider') ? document.getElementById('p_Service_Provider').value : '';
        const addressVal = document.getElementById('p_Address') ? document.getElementById('p_Address').value : '';
        let customId = '';
        if (providerVal || addressVal) {
            customId = (providerVal + '-' + addressVal).replace(/^-|-$/g, '').trim();
        }

        // If Track ID in DB is marked as closed or has remark, prompt before sending
        if (!isDraft && customId) {
            try {
                const checkRes = await fetch('<?= BASE_PATH ?>/studio/check-track-id?custom_id=' + encodeURIComponent(customId));
                const checkData = await checkRes.json();
                if (checkData.ok && checkData.found && (checkData.is_closed || checkData.closed_remark)) {
                    const confirmProceed = await new Promise((resolve) => {
                        Swal.fire({
                            title: '⚠️ Track ID Marked / Closed',
                            html: `
                                <div class="text-left space-y-3">
                                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900">
                                        <div class="flex items-center justify-between">
                                            <span><b>Track ID:</b> <code class="font-bold text-amber-950">${escSafe(customId)}</code></span>
                                            ${checkData.is_closed ? `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 uppercase">Closed on ${escSafe(checkData.closed_at || 'Recorded Date')}</span>` : ''}
                                        </div>
                                    </div>
                                    ${checkData.closed_remark ? `
                                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-800">
                                        <b class="text-slate-500 uppercase text-[10px] block mb-1">Previous Remark:</b>
                                        <div class="font-medium text-slate-800 leading-relaxed">${escSafe(checkData.closed_remark)}</div>
                                    </div>` : ''}
                                    <div class="text-xs text-slate-500">
                                        <b>Prior Send:</b> ${checkData.recipients ? escSafe(checkData.recipients) : 'Recorded'} (${checkData.count} prior send${checkData.count > 1 ? 's' : ''})
                                    </div>
                                    <p class="text-xs font-semibold text-slate-700">This Track ID has previous closed records / remarks in the database. Are you sure you want to send this email?</p>
                                </div>
                            `,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Send Anyway',
                            confirmButtonColor: '#2563eb',
                            cancelButtonText: 'Cancel Send',
                            cancelButtonColor: '#64748b',
                            reverseButtons: true
                        }).then((r) => resolve(r.isConfirmed));
                    });

                    if (!confirmProceed) {
                        showToast('Email sending cancelled.', 'info');
                        return;
                    }
                }
            } catch (e) {
                console.error('Track ID verification error:', e);
            }
        }

        if (!isDraft) {
            const sendDecision = await new Promise((resolve) => {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'info',
                    title: 'Sending email...',
                    showConfirmButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Undo',
                    confirmButtonColor: '#ef4444',
                    cancelButtonText: 'Send Now',
                    cancelButtonColor: '#3b82f6',
                    timer: 6000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        resolve('undo');
                    } else {
                        resolve('send');
                    }
                });
            });

            if (sendDecision === 'undo') {
                showToast('Email sending cancelled.', 'success');
                return;
            }
        }

        let formData = csrfForm(new FormData());
        formData.append('action', 'process_email');
        emails.forEach(em => formData.append('client_email[]', em));
        formData.append('email_subject', document.getElementById('subject_preview_text').innerText.trim() || document.getElementById('email_subject').value);

        // Serialize all studio inputs
        const fields = ['Hotel_Name', 'Address', 'City', 'State', 'Zip', 'Rooms', 'Extra', 'Email', 'Hotel_Number', 'Contact_Person', 'First_Name', 'Service_Provider', 'offeredProgramming', 'System', 'Bill', 'Upfront'];
        let studioFieldsVal = {};
        fields.forEach(key => {
            const el = document.getElementById('p_' + key);
            if (el) {
                studioFieldsVal[key] = el.value;
                if (el.dataset.formula) {
                    studioFieldsVal[key + '_formula'] = el.dataset.formula;
                }
            }
        });
        formData.append('studio_fields', JSON.stringify(studioFieldsVal));
        formData.append('custom_id', customId);
        
        let htmlContent = document.getElementById('proposal_canvas').innerHTML;

        if (!isDraft && typeof VIEWER_URL !== 'undefined' && (VIEWER_URL.includes('localhost') || VIEWER_URL.includes('127.0.0.1'))) {
            if (htmlContent.includes('localhost') || htmlContent.includes('127.0.0.1')) {
                if (!confirm("⚠️ WARNING: Your email contains 'localhost' links.\n\nEmails with localhost links are heavily filtered by Gmail and other providers and are likely to be silently dropped (not delivered) even if they say 'Sent'.\n\nDo you want to send anyway?")) {
                    return;
                }
            }
        }

        formData.append('html_content', htmlContent);
        if (isDraft) formData.append('is_draft', '1');

        if (selectedAttachmentFiles.length > 0) {
            selectedAttachmentFiles.forEach(file => formData.append('attachment[]', file));
        } else {
            let fileInput = document.getElementById('sys_attach_input');
            if (fileInput && fileInput.files.length > 0) {
                Array.from(fileInput.files).forEach(file => formData.append('attachment[]', file));
            }
        }

        let statusEl = document.getElementById('send_status');
        statusEl.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
        statusEl.innerText = isDraft ? "Saving Draft..." : "Sending...";
        statusEl.classList.add('bg-blue-100', 'text-blue-700');
        statusEl.style.display = 'block';

        try {
            let response = await fetch('', { method: 'POST', body: formData });
            let result = await response.json();
            
            if (result.imap_logs) {
                console.log('--- IMAP Sent Box Scrubbing Logs ---');
                result.imap_logs.forEach(log => console.log(log));
            }
            
            statusEl.classList.remove('bg-blue-100', 'text-blue-700');
            if (response.ok && result.status === 'success') {
                if (result.imap_success === false && !isDraft) {
                    statusEl.innerText = `Sent to ${emails.join(', ')} but Sent-Box scrub failed! Check console.`;
                    statusEl.classList.add('bg-yellow-100', 'text-yellow-800');
                } else {
                    statusEl.innerText = isDraft ? `Draft saved successfully for ${emails.join(', ')}!` : `Email sent successfully to ${emails.join(', ')}!`;
                    statusEl.classList.add('bg-green-100', 'text-green-700');
                }
            } else {
                statusEl.innerText = "Error: " + (result.message || 'Unknown error');
                statusEl.classList.add('bg-red-100', 'text-red-700');
            }
        } catch (err) {
            statusEl.classList.remove('bg-blue-100', 'text-blue-700');
            statusEl.innerText = "Error: " + err.message;
            statusEl.classList.add('bg-red-100', 'text-red-700');
        }
    }

    function normalizeCanvasForTemplateSave(mode = 'values') {
        const fields = ['Hotel_Name', 'Address', 'City', 'State', 'Zip', 'Rooms', 'Extra', 'Email', 'Hotel_Number', 'Contact_Person', 'First_Name', 'Service_Provider', 'offeredProgramming', 'System', 'Bill', 'Upfront'];
        const subject = document.getElementById('email_subject').value || '';

        let htmlContent = document.getElementById('proposal_canvas').innerHTML;
        let finalSubject = subject;

        if (mode === 'placeholder') {
            fields.forEach(key => {
                const fieldEl = document.getElementById('p_' + key);
                const value = fieldEl ? fieldEl.value : '';
                if (value) {
                    const safeValue = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    const valuePattern = new RegExp(safeValue, 'gi');
                    htmlContent = htmlContent.replace(valuePattern, '{{' + key + '}}');
                    finalSubject = finalSubject.replace(new RegExp(safeValue, 'gi'), '{{' + key + '}}');
                }
            });

            const emailValue = document.getElementById('client_email')?.value || '';
            if (emailValue) {
                const safeEmail = emailValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                htmlContent = htmlContent.replace(new RegExp(safeEmail, 'gi'), '{{Email}}');
                finalSubject = finalSubject.replace(new RegExp(safeEmail, 'gi'), '{{Email}}');
            }
        }

        htmlContent = htmlContent.replace(/<!--\s*EMAIL_SUBJECT:\s*[\s\S]*?\s*-->/i, '');
        htmlContent = '<!-- EMAIL_SUBJECT: ' + finalSubject.replace(/-->/g, '--&gt;') + ' -->\n' + htmlContent.trim();
        return { htmlContent, subject: finalSubject };
    }

    function promptSaveTemplateMode() {
        const statusEl = document.getElementById('save_tpl_status');
        if (!statusEl) return;
        statusEl.classList.remove('hidden');
        statusEl.classList.remove('bg-green-100','text-green-700','bg-red-100','text-red-700');
        statusEl.classList.add('bg-white','text-slate-700');
        statusEl.style.display = 'block';
        statusEl.innerHTML = `
            <div class="mb-2 font-semibold text-slate-700">Save template as:</div>
            <div class="flex gap-2">
                <button type="button" onclick="saveNewTemplate('placeholder')" class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded">With Placeholders</button>
                <button type="button" onclick="saveNewTemplate('values')" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-2 py-1 rounded">With Values</button>
            </div>
        `;
    }

    async function saveNewTemplate(mode = null) {
        if (isPreviewMode) return;
        let name = document.getElementById('new_template_name').value.trim();
        if (!name) { showToast('Please enter a template name.', 'error'); return; }

        const resolvedMode = mode || 'values';
        if (!mode) {
            promptSaveTemplateMode();
            return;
        }

        const { htmlContent, subject } = normalizeCanvasForTemplateSave(resolvedMode);

        let formData = csrfForm(new FormData());
        formData.append('action', 'save_new_template');
        formData.append('template_name', name);
        formData.append('html_content', htmlContent);
        formData.append('email_subject', subject);
        formData.append('save_mode', resolvedMode);

        let statusEl = document.getElementById('save_tpl_status');
        statusEl.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
        statusEl.innerText = "Saving...";
        statusEl.classList.add('bg-blue-100', 'text-blue-700');
        statusEl.style.display = 'block';

        try {
            let response = await fetch('', { method: 'POST', body: formData });
            const payload = await response.json().catch(() => ({}));
            statusEl.classList.remove('bg-blue-100', 'text-blue-700');
            if (response.ok && payload.status === 'success') {
                statusEl.innerText = payload.message || 'Template saved!';
                statusEl.classList.add('bg-green-100', 'text-green-700');
                document.getElementById('sb_unsaved').style.display = 'none';
                setTimeout(() => window.location.reload(), 1500);
            } else {
                statusEl.innerText = payload.message || 'Error saving template.';
                statusEl.classList.add('bg-red-100', 'text-red-700');
            }
        } catch (err) {
            statusEl.classList.remove('bg-blue-100', 'text-blue-700');
            statusEl.innerText = "Network Error.";
            statusEl.classList.add('bg-red-100', 'text-red-700');
        }
    }

    // S. COPY & EXPORT
    function exportCanvasToPDF() {
        const canvas = document.getElementById('proposal_canvas');
        // Temporarily remove preview styles so PDF is clean
        const wasPreview = canvas.classList.contains('preview-active');
        canvas.classList.remove('preview-active');
        canvas.style.cursor = '';
        canvas.style.opacity = '';
        html2pdf().set({
            margin: 8,
            filename: 'proposal.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).from(canvas).save().then(() => {
            if (wasPreview) canvas.classList.add('preview-active');
        });
    }

    // TOAST NOTIFICATION
    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = [
            'position:fixed', 'bottom:24px', 'right:24px', 'z-index:99999',
            'padding:10px 18px', 'border-radius:8px', 'font-size:13px', 'font-weight:600',
            'color:#fff', 'box-shadow:0 4px 20px rgba(0,0,0,.3)', 'transition:opacity .4s',
            'background:' + (type === 'error' ? '#dc2626' : '#16a34a')
        ].join(';');
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
    }

    // U. BULK MODAL
    let bulkModalDrag = null;

    function openBulkModal() {
        const modal = document.getElementById('bulk-modal');
        const panel = document.getElementById('bulk-panel');
        modal.style.display = 'block';
        modal.style.background = 'transparent';
        modal.style.pointerEvents = 'none';
        panel.style.pointerEvents = 'auto';
        panel.style.right = '18px';
        panel.style.top = '80px';
    }

    function closeBulkModal() {
        const modal = document.getElementById('bulk-modal');
        modal.style.display = 'none';
        modal.style.background = 'transparent';
        modal.style.pointerEvents = 'none';
    }

    function setupBulkPanelDrag() {
        const panel = document.getElementById('bulk-panel');
        const header = document.getElementById('bulk-panel-header');
        if (!panel || !header) return;

        header.addEventListener('pointerdown', function (e) {
            if (e.target.closest('button')) return;
            const rect = panel.getBoundingClientRect();
            bulkModalDrag = {
                startX: e.clientX,
                startY: e.clientY,
                left: rect.left,
                top: rect.top
            };
            header.setPointerCapture(e.pointerId);
        });

        header.addEventListener('pointermove', function (e) {
            if (!bulkModalDrag) return;
            const dx = e.clientX - bulkModalDrag.startX;
            const dy = e.clientY - bulkModalDrag.startY;
            panel.style.left = (bulkModalDrag.left + dx) + 'px';
            panel.style.top = (bulkModalDrag.top + dy) + 'px';
            panel.style.right = 'auto';
        });

        header.addEventListener('pointerup', function () {
            bulkModalDrag = null;
        });
        header.addEventListener('pointerleave', function () {
            bulkModalDrag = null;
        });
    }

    document.getElementById('bulk-modal').addEventListener('click', e => {
        if (e.target.id === 'bulk-modal') closeBulkModal();
    });

    setupBulkPanelDrag();

    // T. MUTATION OBSERVER (debounced)
    let _observerTimer = null;
    const observer = new MutationObserver(() => {
        clearTimeout(_observerTimer);
        _observerTimer = setTimeout(bindCanvasImageEvents, 300);
    });
    observer.observe(document.getElementById('proposal_canvas'), { childList: true, subtree: true });

    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (!window.__studioPageReady || window.__studioInitializing) return;
        const unsaved = document.getElementById('sb_unsaved');
        if (unsaved && unsaved.style.display !== 'none') {
            e.preventDefault();
            e.returnValue = '';
        }
    });

        // ── NEW PDF LINK / ATTACH LOGIC ──────────────────────────────────────────
    let pdfGallery = [];
    let pdfGalleryVisibleCount = 0;
    let pdfGalleryPageSize = 40;
    let pendingCldPdfFile = null;
    let selectedAttachmentFiles = [];
    let pdfLibraryAction = ''; // 'attach' or 'link'
    let activeDropdown = null;

    function toggleAttachDropdown(e) {
        e.stopPropagation();
        if (activeDropdown && activeDropdown !== 'attach') closeAllDropdowns();
        const dd = document.getElementById('attach_dropdown');
        if (dd.classList.contains('hidden')) {
            dd.classList.remove('hidden');
            activeDropdown = 'attach';
        } else {
            dd.classList.add('hidden');
            activeDropdown = null;
        }
    }

    function togglePdfLinkDropdown(e) {
        e.stopPropagation();
        if (activeDropdown && activeDropdown !== 'link') closeAllDropdowns();
        const dd = document.getElementById('pdf_link_dropdown');
        if (dd.classList.contains('hidden')) {
            dd.classList.remove('hidden');
            activeDropdown = 'link';
        } else {
            dd.classList.add('hidden');
            activeDropdown = null;
        }
    }

    function closeAllDropdowns() {
        document.getElementById('attach_dropdown')?.classList.add('hidden');
        document.getElementById('pdf_link_dropdown')?.classList.add('hidden');
        activeDropdown = null;
    }
    
    document.addEventListener('click', () => closeAllDropdowns());

    function attachFromLibrary() {
        closeAllDropdowns();
        openPdfLibraryModal('attach');
    }

    function insertPdfLinkFromLibrary() {
        closeAllDropdowns();
        openPdfLibraryModal('link');
    }

    function openPdfLibraryModal(action) {
        pdfLibraryAction = action;
        const modal = document.getElementById('pdf-library-modal');
        if (modal) {
            modal.style.display = 'block';
            loadPdfGalleryFromDb();
        }
    }
    
    function closePdfLibraryModal() {
        const modal = document.getElementById('pdf-library-modal');
        if (modal) modal.style.display = 'none';
    }

    document.getElementById('pdf-library-modal')?.addEventListener('click', e => {
        if (e.target.id === 'pdf-library-modal') closePdfLibraryModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePdfLibraryModal();
    });

    // ── ATTACHMENT LOGIC ──
    function onAttachFileChange(input) {
        if (!input.files || input.files.length === 0) return;
        for (let i = 0; i < input.files.length; i++) {
            selectedAttachmentFiles.push(input.files[i]);
        }
        input.value = ''; // reset
        renderAttachmentList();
        closeAllDropdowns();
    }

    async function addCloudinaryPdfAsAttachment(url, name) {
        try {
            showToast('⏳ Fetching PDF from library...', 'info');
            const res = await fetch(url);
            const blob = await res.blob();
            const filename = name.toLowerCase().endsWith('.pdf') ? name : name + '.pdf';
            const file = new File([blob], filename, { type: 'application/pdf' });
            
            selectedAttachmentFiles.push(file);
            renderAttachmentList();
            showToast('✅ PDF attached!');
        } catch (e) {
            showToast('❌ Failed to fetch PDF for attachment', 'error');
        }
    }

    function renderAttachmentList() {
        const container = document.getElementById('selected-attachments');
        if (!container) return;
        container.innerHTML = '';
        if (selectedAttachmentFiles.length === 0) {
            container.innerHTML = '<div style="font-size:0.72rem;color:#94a3b8;padding:8px 0;">No attachments selected yet.</div>';
            document.getElementById('attachment_label_text').textContent = 'Attach';
            return;
        }

        selectedAttachmentFiles.forEach((file, index) => {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border:1px solid #e2e8f0;padding:6px 10px;border-radius:6px;font-size:0.75rem;color:#334155;';
            
            const nameSpan = document.createElement('span');
            nameSpan.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:65%;';
            nameSpan.textContent = file.name;
            
            const actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:8px;align-items:center;';
            
            if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                const viewBtn = document.createElement('button');
                viewBtn.innerHTML = '👁️ <span style="text-decoration:underline;">View</span>';
                viewBtn.style.cssText = 'background:none;border:none;color:#1d4ed8;cursor:pointer;font-size:0.75rem;font-weight:600;padding:0;';
                viewBtn.onclick = () => {
                    const objUrl = URL.createObjectURL(file);
                    window.open(objUrl, '_blank');
                    setTimeout(() => URL.revokeObjectURL(objUrl), 60000); 
                };
                actions.appendChild(viewBtn);
            }
            
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '✕';
            removeBtn.style.cssText = 'background:none;border:none;color:#ef4444;cursor:pointer;font-size:0.9rem;line-height:1;margin-left:4px;';
            removeBtn.onclick = () => {
                selectedAttachmentFiles.splice(index, 1);
                renderAttachmentList();
            };
            
            actions.appendChild(removeBtn);
            row.appendChild(nameSpan);
            row.appendChild(actions);
            container.appendChild(row);
        });
        
        document.getElementById('attachment_label_text').textContent = `Attach (${selectedAttachmentFiles.length})`;
    }

    // ── PDF LINK BUTTON INSERT LOGIC ──
    function onCldPdfSelect(input) {
        if (input.files.length) {
            closeAllDropdowns();
            pendingCldPdfFile = input.files[0];
            uploadPdfToCloudinary();
        }
    }

    async function uploadPdfToCloudinary() {
        if (!CLD_CLOUD) { showToast('Cloudinary cloud name is not configured in .env', 'error'); return; }
        if (!pendingCldPdfFile) { showToast('Select a PDF first', 'error'); return; }

        const fd = new FormData();
        fd.append('file', pendingCldPdfFile);

        let useSignedUpload = !!CLD_API_KEY && CLD_API_SECRET_CONFIGURED;
        let signedParams = null;

        if (!!CLD_API_KEY && !CLD_API_SECRET_CONFIGURED) {
            showToast('❌ Cloudinary API key configured but Secret is missing.', 'error');
            return;
        }

        if (useSignedUpload) {
            try {
                const signForm = csrfForm(new FormData());
                signForm.append('action', 'cloudinary_sign');
                if (CLD_PRESET) signForm.append('upload_preset', CLD_PRESET);
                const signRes = await fetch('', { method: 'POST', body: signForm });
                signedParams = await signRes.json();
                if (signedParams.status !== 'success') {
                    throw new Error(signedParams.message || 'Cloudinary signing failed');
                }
            } catch (err) {
                showToast('❌ ' + err.message, 'error');
                return;
            }
        } else if (!CLD_PRESET) {
            showToast('❌ Cloudinary unsigned upload requires CLOUDINARY_PRESET.', 'error');
            return;
        }

        if (signedParams) {
            fd.append('api_key', signedParams.api_key);
            fd.append('timestamp', signedParams.timestamp);
            fd.append('signature', signedParams.signature);
            if (CLD_PRESET) fd.append('upload_preset', CLD_PRESET);
        } else {
            fd.append('upload_preset', CLD_PRESET);
        }

        showToast('⏳ Uploading PDF...', 'info');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `https://api.cloudinary.com/v1_1/${CLD_CLOUD}/raw/upload`, true);
        
        xhr.onload = function () {
            try {
                const data = JSON.parse(xhr.responseText || '{}');
                if (xhr.status >= 200 && xhr.status < 300 && data.secure_url) {
                    const name = pendingCldPdfFile.name.replace(/\.pdf$/i, '');
                    savePdfToGallery(data.secure_url, name);
                    showToast('✅ Uploaded. Ready to insert.', 'success');
                    
                    pendingCldPdfFile = null;
                    document.getElementById('cld_pdf_input').value = '';
                    
                    promptAndInsertPdfLink(data.secure_url, name);
                } else {
                    throw new Error(data.error?.message || 'Upload failed');
                }
            } catch (e) {
                showToast('❌ ' + e.message, 'error');
            }
        };

        xhr.onerror = function () {
            showToast('❌ Upload failed.', 'error');
        };

        xhr.send(fd);
    }

    async function promptAndInsertPdfLink(url, defaultName) {
        if (typeof isPreviewMode !== 'undefined' && isPreviewMode) {
            showToast("Cannot insert in preview mode.", "error");
            return;
        }
        
        let label = prompt("Enter button label:", "Click here to see "+defaultName || "View Proposal");
        if (!label) return;
        
        let styleStr = prompt("Enter style number (1=Red Gradient, 2=Blue Outline, 3=Dark+Gold, 4=Green Success, 5=Purple Premium):", "2");
        if (!['1','2','3','4','5'].includes(styleStr)) styleStr = "2";
        
        const styles = {
            '1': { s: "background:linear-gradient(90deg,#ef4444,#dc2626);color:#ffffff;border:none;border-radius:6px;padding:12px 24px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 4px 6px -1px rgba(220,38,38,0.3);", i: "📄" },
            '2': { s: "background:transparent;color:#2563eb;border:2px solid #3b82f6;border-radius:6px;padding:10px 22px;font-weight:bold;text-decoration:none;display:inline-block;", i: "📑" },
            '3': { s: "background:linear-gradient(135deg,#0f172a,#1e293b);color:#fbbf24;border:1px solid #fbbf24;border-radius:8px;padding:12px 26px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 10px 15px -3px rgba(0,0,0,0.4);letter-spacing:0.02em;", i: "✨" },
            '4': { s: "background:#10b981;color:#ffffff;border:none;border-radius:24px;padding:12px 28px;font-weight:bold;text-decoration:none;display:inline-block;box-shadow:0 4px 6px -1px rgba(16,185,129,0.3);", i: "✅" },
            '5': { s: "background:#ffffff;color:#7c3aed;border:none;border-radius:8px;padding:14px 28px;font-weight:800;text-decoration:none;display:inline-block;box-shadow:0 10px 25px -5px rgba(124,58,237,0.25);", i: "🚀" }
        };
        const def = styles[styleStr];
        const safeUrl = url.replace(/"/g, '&quot;');
        const safeLabel = escapeHtml(label);
        
        let viewerUrl = '#';
        try {
            const fd = csrfForm(new FormData());
            fd.append('action', 'create_pdf_link_token');
            fd.append('url', url);
            fd.append('name', label);
            const r = await fetch('', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.status === 'success' && d.token) {
                const base = VIEWER_URL.replace(/\/$/, '');
                viewerUrl = base + '/index.php?url=' + encodeURIComponent(url) + '&token=' + encodeURIComponent(d.token);
            }
        } catch (_) {}

        const html = `<div style="text-align:center;margin:16px 0;">
            <a href="${viewerUrl}" target="_blank" data-pdf-url="${safeUrl}" data-pdf-name="${safeLabel}" style="${def.s}">
                ${def.i} ${safeLabel}
            </a>
        </div>`;
        
        restoreCursorAndInsert(html);
        showToast('✅ Link inserted!', 'success');
    }

    function selectPdfFromGallery(url, name) {
        if (pdfLibraryAction === 'link') {
            closePdfLibraryModal();
            promptAndInsertPdfLink(url, name);
        } else if (pdfLibraryAction === 'attach') {
            closePdfLibraryModal();
            addCloudinaryPdfAsAttachment(url, name);
        }
    }

    // ── GALLERY DATA ──
    function savePdfToGallery(url, name) {
        fetch('', {
            method: 'POST',
            body: (() => { const fd = csrfForm(new FormData()); fd.append('action','add_pdf_asset'); fd.append('url', url); fd.append('name', name); return fd; })()
        }).then(r => r.json()).then(data => {
            if (data.status === 'success') {
                _pdfGalleryLoaded = true;
                pdfGallery = pdfGallery.filter(p => p.url !== url);
                pdfGallery.unshift({ id: data.id, url, name });
                renderPdfGallery();
            }
        });
    }

    let _pdfGalleryLoaded = false;
    function loadPdfGalleryFromDb() {
        const container = document.getElementById('pdf-gallery');
        if (!container) return;
        container.innerHTML = '<span style="font-size:0.7rem;color:#94a3b8;">Loading...</span>';
        fetch('?action=get_pdf_assets')
            .then(r => r.json())
            .then(rows => {
                _pdfGalleryLoaded = true;
                pdfGallery = rows.map(r => ({ id: r.id, url: r.url, name: r.name, is_pinned: r.is_pinned || 0 }));
                pdfGalleryVisibleCount = Math.min(pdfGalleryPageSize, pdfGallery.length || 1);
                renderPdfGallery();
            })
            .catch(() => { container.innerHTML = '<span style="font-size:0.7rem;color:#ef4444;">Failed to load.</span>'; });
    }

    function renderPdfGallery() {
        const container = document.getElementById('pdf-gallery');
        const empty = document.getElementById('pdf-gallery-empty');
        const more = document.getElementById('pdf-gallery-more');
        if (!container) return;
        container.innerHTML = '';
        if (!pdfGallery.length) {
            empty.style.display = 'block';
            if (more) more.style.display = 'none';
            return;
        }
        empty.style.display = 'none';
        
        pdfGallery.slice(0, pdfGalleryVisibleCount).forEach(p => {
            const isPinned = p.is_pinned == 1;
            const row = document.createElement('div');
            row.style.cssText = `display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:${isPinned ? '#eff6ff' : '#f8fafc'};border:1px solid ${isPinned ? '#93c5fd' : '#e2e8f0'};border-radius:10px;font-size:0.8rem;transition:all 0.15s;cursor:pointer;`;
            row.onmouseover = () => { row.style.borderColor = '#94a3b8'; row.style.background = '#f1f5f9'; };
            row.onmouseout = () => { row.style.borderColor = isPinned ? '#93c5fd' : '#e2e8f0'; row.style.background = isPinned ? '#eff6ff' : '#f8fafc'; };
            
            const nameDiv = document.createElement('div');
            nameDiv.style.cssText = 'flex:1;display:flex;align-items:center;gap:10px;overflow:hidden;';
            nameDiv.innerHTML = `<span style="font-size:1.4rem;">${isPinned ? '📌' : '📄'}</span> <span style="font-weight:600;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(p.name)}</span>`;
            
            const actionsDiv = document.createElement('div');
            actionsDiv.style.cssText = 'display:flex;gap:6px;align-items:center;';

            const pinBtn = document.createElement('button');
            pinBtn.innerHTML = isPinned ? '📌' : '📍';
            pinBtn.title = isPinned ? 'Unpin' : 'Pin to top';
            pinBtn.style.cssText = `background:${isPinned ? '#dbeafe' : 'none'};border:1px solid ${isPinned ? '#93c5fd' : 'transparent'};color:#64748b;cursor:pointer;font-size:0.9rem;padding:4px 6px;border-radius:6px;transition:all 0.15s;`;
            pinBtn.onmouseover = () => { pinBtn.style.background = '#dbeafe'; pinBtn.style.borderColor = '#93c5fd'; };
            pinBtn.onmouseout = () => { pinBtn.style.background = isPinned ? '#dbeafe' : 'none'; pinBtn.style.borderColor = isPinned ? '#93c5fd' : 'transparent'; };
            pinBtn.onclick = (e) => { e.stopPropagation(); togglePinPdf(p.id); };
            
            const openBtn = document.createElement('button');
            openBtn.innerHTML = '👁️';
            openBtn.title = 'Open to view';
            openBtn.style.cssText = 'background:none;border:1px solid transparent;color:#64748b;cursor:pointer;font-size:0.9rem;padding:4px 6px;border-radius:6px;transition:all 0.15s;';
            openBtn.onmouseover = () => { openBtn.style.background = '#e0f2fe'; openBtn.style.borderColor = '#7dd3fc'; };
            openBtn.onmouseout = () => { openBtn.style.background = 'none'; openBtn.style.borderColor = 'transparent'; };
            openBtn.onclick = (e) => { e.stopPropagation(); window.open(p.url, '_blank', 'noopener'); };

            const renameBtn = document.createElement('button');
            renameBtn.innerHTML = '✏️';
            renameBtn.title = 'Rename';
            renameBtn.style.cssText = 'background:none;border:1px solid transparent;color:#64748b;cursor:pointer;font-size:0.9rem;padding:4px 6px;border-radius:6px;transition:all 0.15s;';
            renameBtn.onmouseover = () => { renameBtn.style.background = '#fef9c3'; renameBtn.style.borderColor = '#fde047'; };
            renameBtn.onmouseout = () => { renameBtn.style.background = 'none'; renameBtn.style.borderColor = 'transparent'; };
            renameBtn.onclick = (e) => { e.stopPropagation(); renamePdf(p.id, p.name); };

            const selBtn = document.createElement('button');
            selBtn.textContent = 'Select';
            selBtn.style.cssText = 'background:#1d4ed8;color:#fff;border:none;border-radius:6px;padding:6px 12px;font-size:0.75rem;font-weight:700;cursor:pointer;';
            selBtn.onclick = (e) => { e.stopPropagation(); selectPdfFromGallery(p.url, p.name); };
            
            const delBtn = document.createElement('button');
            delBtn.innerHTML = '🗑️';
            delBtn.style.cssText = 'background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1rem;padding:4px;';
            delBtn.onclick = (e) => { e.stopPropagation(); deletePdfFromGallery(p.id, p.url); };
            
            actionsDiv.appendChild(pinBtn);
            actionsDiv.appendChild(openBtn);
            actionsDiv.appendChild(renameBtn);
            actionsDiv.appendChild(selBtn);
            actionsDiv.appendChild(delBtn);
            
            row.appendChild(nameDiv);
            row.appendChild(actionsDiv);
            row.onclick = () => selectPdfFromGallery(p.url, p.name);
            container.appendChild(row);
        });
        
        if (more) more.style.display = (pdfGalleryVisibleCount < pdfGallery.length) ? 'block' : 'none';
    }

    function loadMorePdfGallery() {
        pdfGalleryVisibleCount = Math.min(pdfGallery.length, pdfGalleryVisibleCount + pdfGalleryPageSize);
        renderPdfGallery();
    }

    function deletePdfFromGallery(id, url) {
        if (!confirm('Delete this PDF from gallery?')) return;
        fetch('', {
            method: 'POST',
            body: (() => { const fd = csrfForm(new FormData()); fd.append('action','delete_pdf_asset'); fd.append('id', id); return fd; })()
        }).then(r => r.json()).then(data => {
            if (data.status === 'success') {
                pdfGallery = pdfGallery.filter(p => p.id !== id);
                renderPdfGallery();
            }
        });
    }

    function togglePinPdf(id) {
        fetch('', {
            method: 'POST',
            body: (() => { const fd = csrfForm(new FormData()); fd.append('action','toggle_pin_pdf_asset'); fd.append('id', id); return fd; })()
        }).then(r => r.json()).then(data => {
            if (data.status === 'success') {
                const item = pdfGallery.find(p => p.id === id);
                if (item) item.is_pinned = data.is_pinned;
                // Re-sort: pinned first, then by original order
                pdfGallery.sort((a, b) => (b.is_pinned || 0) - (a.is_pinned || 0));
                renderPdfGallery();
            }
        });
    }

    function renamePdf(id, currentName) {
        Swal.fire({
            title: 'Rename Attachment',
            input: 'text',
            inputValue: currentName || '',
            inputPlaceholder: 'Enter new name...',
            showCancelButton: true,
            confirmButtonText: 'Save',
            confirmButtonColor: '#1d4ed8',
            // PDF Library modal has z-index 10001, so lift the dialog above it
            didOpen: () => {
                const c = document.querySelector('.swal2-container');
                if (c) c.style.zIndex = '20050';
            },
            inputValidator: (value) => {
                if (!value || !value.trim()) return 'Name cannot be empty';
            }
        }).then((result) => {
            if (!result.isConfirmed) return;
            const newName = result.value.trim();
            fetch('', {
                method: 'POST',
                body: (() => { const fd = csrfForm(new FormData()); fd.append('action','rename_pdf_asset'); fd.append('id', id); fd.append('name', newName); return fd; })()
            }).then(r => r.json()).then(data => {
                const liftZ = () => { const c = document.querySelector('.swal2-container'); if (c) c.style.zIndex = '20050'; };
                if (data.status === 'success') {
                    const item = pdfGallery.find(p => p.id === id);
                    if (item) item.name = data.name || newName;
                    renderPdfGallery();
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Renamed', showConfirmButton: false, timer: 1500, didOpen: liftZ });
                } else {
                    Swal.fire({ title: 'Error', text: data.message || 'Rename failed', icon: 'error', didOpen: liftZ });
                }
            }).catch(() => Swal.fire({ title: 'Error', text: 'Network error', icon: 'error', didOpen: () => { const c = document.querySelector('.swal2-container'); if (c) c.style.zIndex = '20050'; } }));
        });
    }

</script>

    <!-- Add Option Popup Modal -->
    <div id="add_option_modal" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:20000; display:none; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:8px; width:90%; max-width:400px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 id="add_option_title" style="font-size:1rem; font-weight:bold; color:#1e293b; margin:0;">Add Option</h3>
                <button type="button" onclick="closeAddOptionPopup()" style="color:#64748b; font-size:1.25rem; background:none; border:none; cursor:pointer;">✕</button>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label id="add_option_label" style="font-size:0.75rem; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Option Name</label>
                    <input type="text" id="add_option_input" style="width:100%; border:1px solid #cbd5e1; border-radius:4px; padding:6px 10px; font-size:0.875rem;" placeholder="Enter name...">
                </div>
                <div id="add_option_parent_provider_container" style="display:none;">
                    <label style="font-size:0.75rem; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Parent Service Provider</label>
                    <select id="add_option_parent_provider" style="width:100%; border:1px solid #cbd5e1; border-radius:4px; padding:6px 10px; font-size:0.875rem; color:#334155;">
                    </select>
                </div>
                <div id="add_option_parent_system_container" style="display:none;">
                    <label style="font-size:0.75rem; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Parent System</label>
                    <select id="add_option_parent_system" style="width:100%; border:1px solid #cbd5e1; border-radius:4px; padding:6px 10px; font-size:0.875rem; color:#334155;">
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                    <button type="button" onclick="closeAddOptionPopup()" style="padding:6px 12px; background:#e2e8f0; color:#475569; border-radius:4px; font-size:0.875rem; border:none; cursor:pointer;">Cancel</button>
                    <button type="button" onclick="submitAddOption()" style="padding:6px 12px; background:#2563eb; color:white; border-radius:4px; font-size:0.875rem; font-weight:600; border:none; cursor:pointer;">Add</button>
                </div>
            </div>
        </div>
    </div>

<script>
    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    let studioOptionsData = { providers: [], systems: [], programming: [] };

    // ── Load options from server ──────────────────────────────────────────────
    async function loadStudioOptions() {
        try {
            let formData = csrfForm(new FormData());
            formData.append('action', 'get_studio_options');
            let res = await fetch('', { method: 'POST', body: formData });
            let data = await res.json();
            if (data.status === 'success') {
                studioOptionsData.providers = data.options.filter(o => o.type === 'provider').map(o => o.name);
                studioOptionsData.systems   = data.options.filter(o => o.type === 'system');
                studioOptionsData.programming = data.options.filter(o => o.type === 'programming' || o.type === 'offeredProgramming');
                populateProviders();
                // Restore saved select values AFTER options are loaded
                restoreSelectValues();
            }
        } catch (err) {
            console.error('Error loading studio options:', err);
        }
    }

    /** Restore values from localStorage after options have been populated */
    function restoreSelectValues() {
        try {
            const saved = JSON.parse(localStorage.getItem('studio_formdata') || '{}');
            const keys = ['p_Service_Provider', 'p_System', 'p_offeredProgramming'];
            keys.forEach(id => {
                const el = document.getElementById(id);
                if (el && saved[id] !== undefined && saved[id] !== '') {
                    el.value = saved[id];
                }
            });
            // Cascade after restoring provider
            updateSystems();
        } catch(e) {}
    }

    // ── Populate selects ──────────────────────────────────────────────────────
    function populateProviders() {
        const input = document.getElementById('p_Service_Provider');
        const datalist = document.getElementById('dl_Service_Provider');
        if (!input || !datalist) return;
        datalist.innerHTML = '';
        studioOptionsData.providers.forEach(p => {
            datalist.innerHTML += `<option value="${escapeHtml(p)}">`;
        });
        updateSystems();
    }

    function updateSystems(autoSelect = false) {
        const providerInput = document.getElementById('p_Service_Provider');
        const systemInput   = document.getElementById('p_System');
        const datalist      = document.getElementById('dl_System');
        if (!providerInput || !systemInput || !datalist) return;

        const provider = providerInput.value;
        const currentVal = systemInput.value;

        datalist.innerHTML = '';
        systemInput.disabled  = false;

        if (provider) {
            const filtered = studioOptionsData.systems.filter(s => s.parent_provider === provider);
            filtered.forEach(s => {
                datalist.innerHTML += `<option value="${escapeHtml(s.name)}">`;
            });
            
            if (autoSelect) {
                systemInput.value = filtered.length > 0 ? filtered[0].name : '';
            }
        }

        updateProgramming(autoSelect);
    }

    function updateProgramming(autoSelect = false) {
        const providerInput = document.getElementById('p_Service_Provider');
        const progInput     = document.getElementById('p_offeredProgramming');
        const datalist      = document.getElementById('dl_offeredProgramming');
        if (!providerInput || !progInput || !datalist) return;

        const provider    = providerInput.value;
        datalist.innerHTML = '';

        if (provider) {
            // Show only provider-specific options exactly like System
            const filtered = studioOptionsData.programming.filter(p => p.parent_provider === provider);

            filtered.forEach(p => {
                datalist.innerHTML += `<option value="${escapeHtml(p.name)}">`;
            });
            
            if (autoSelect) {
                progInput.value = filtered.length > 0 ? filtered[0].name : '';
            }
        }
    }

    // ── onchange handler ──────────────────────────────────────────────────────
    function onStudioFieldChange(key) {
        if (key === 'Service_Provider') {
            updateSystems(true);
        } else if (key === 'System') {
            updateProgramming();
        }
        liveApplyAll();
        updateSubjectPreview();
        updatePlaceholderCounts();
        saveFormData();
    }

    // ── Inline Add Option (replaces popup) ────────────────────────────────────
    async function inlineAddOption(type, selectId, inputId) {
        const input  = document.getElementById(inputId);
        const select = document.getElementById(selectId);
        if (!input || !select) return;

        const name = input.value.trim();
        if (!name) { showToast('Please type a name first', 'error'); input.focus(); return; }

        // Check for duplicates
        const existingOptions = Array.from(select.options).map(o => o.value);
        if (existingOptions.includes(name)) {
            showToast('This option already exists in the list', 'error');
            return;
        }

        // Determine parent_provider for systems and programming
        let parentProvider = '';
        if (type === 'system' || type === 'programming') {
            parentProvider = document.getElementById('p_Service_Provider')?.value || '';
            if (!parentProvider) {
                showToast(`Please select a Service Provider before adding a ${type}`, 'error');
                return;
            }
        }

        let formData = csrfForm(new FormData());
        formData.append('action', 'add_studio_option');
        formData.append('type', type);
        formData.append('name', name);
        if (parentProvider) formData.append('parent_provider', parentProvider);

        try {
            let res  = await fetch('', { method: 'POST', body: formData });
            let data = await res.json();
            if (data.status === 'success') {
                showToast(`"${name}" added to list!`, 'success');

                // Reload options, input already has the value
                await loadStudioOptions();
                onStudioFieldChange(
                    inputId === 'p_Service_Provider' ? 'Service_Provider' :
                    inputId === 'p_System'           ? 'System' : 'offeredProgramming'
                );
            } else {
                showToast(data.message || 'Failed to add', 'error');
            }
        } catch (err) {
            showToast('Network error', 'error');
        }
    }

    // ── Inline Delete Option ──────────────────────────────────────────────────
    async function inlineDeleteOption(type, inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const name = input.value.trim();
        if (!name) { showToast('Please type a name to delete first', 'error'); input.focus(); return; }

        if (!confirm(`Are you sure you want to completely delete "${name}" from the list?`)) return;

        let parentProvider = '';
        if (type === 'system' || type === 'programming') {
            parentProvider = document.getElementById('p_Service_Provider')?.value || '';
            if (!parentProvider) {
                showToast(`Please select a Service Provider before deleting a ${type}`, 'error');
                return;
            }
        }

        let formData = csrfForm(new FormData());
        formData.append('action', 'delete_studio_option');
        formData.append('type', type);
        formData.append('name', name);
        if (parentProvider) formData.append('parent_provider', parentProvider);

        try {
            let res  = await fetch('', { method: 'POST', body: formData });
            let data = await res.json();
            if (data.status === 'success') {
                showToast(`"${name}" deleted from list!`, 'success');
                input.value = '';
                
                // Reload options
                await loadStudioOptions();
                onStudioFieldChange(
                    inputId === 'p_Service_Provider' ? 'Service_Provider' :
                    inputId === 'p_System'           ? 'System' : 'offeredProgramming'
                );
            } else {
                showToast(data.message || 'Failed to delete', 'error');
            }
        } catch (err) {
            showToast('Network error', 'error');
        }
    }

    // ── Init on DOMContentLoaded ───────────────────────────────────────────────
    window.addEventListener('DOMContentLoaded', () => {
        loadStudioOptions();
        
        // Make datalists show all options on click by temporarily clearing value
        ['p_Service_Provider', 'p_System', 'p_offeredProgramming'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('mousedown', function() {
                    if (this.value) {
                        this.dataset.oldValue = this.value;
                        this.value = '';
                    }
                });
                el.addEventListener('blur', function() {
                    if (this.value === '' && this.dataset.oldValue) {
                        this.value = this.dataset.oldValue;
                    }
                    this.dataset.oldValue = '';
                });
                // If they type, clear the old value so it doesn't restore on blur
                el.addEventListener('input', function() {
                    this.dataset.oldValue = '';
                });
            }
        });
    });
</script>
<!-- Callback Requests Panel -->
<div id="callbacks-modal" class="fixed inset-0 bg-black/70 z-[10001] hidden items-center justify-center" style="display:none;">
    <div class="bg-white rounded-xl shadow-2xl w-[95%] max-w-4xl max-h-[85vh] flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex justify-between items-center px-5 py-4 border-b border-slate-200 bg-slate-50">
            <div class="flex items-center gap-3">
                <span class="text-xl">📞</span>
                <h2 class="text-lg font-bold text-slate-800">Callback Requests</h2>
                <span id="cb-count" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold"></span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="copyCallbackLink()" class="text-xs bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 px-3 py-1.5 rounded-lg font-medium transition-colors">📋 Copy Form Link</button>
                <button onclick="closeCallbacksPanel()" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
            </div>
        </div>
        <!-- Body -->
        <div id="cb-body" class="flex-1 overflow-y-auto p-5 bg-slate-50">
            <div class="text-center text-slate-400 py-10">Loading...</div>
        </div>
    </div>
</div>

<script>
(function(){
    const base = '<?= BASE_PATH ?>';
    const modal = document.getElementById('callbacks-modal');
    const body = document.getElementById('cb-body');
    const countBadge = document.getElementById('cb-count');

    window.openCallbacksPanel = function() {
        modal.style.display = 'flex';
        loadCallbacks();
    };

    window.closeCallbacksPanel = function() {
        modal.style.display = 'none';
    };

    // Close on backdrop click
    modal.addEventListener('click', (e) => { if (e.target === modal) closeCallbacksPanel(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.style.display === 'flex') closeCallbacksPanel(); });

    window.copyCallbackLink = function() {
        const link = '<?= rtrim(APP_URL, "/") ?>/callback';
        navigator.clipboard.writeText(link).then(() => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Callback form link copied!', showConfirmButton: false, timer: 2000 });
        });
    };

    async function loadCallbacks() {
        body.innerHTML = '<div class="text-center text-slate-400 py-10">Loading...</div>';
        try {
            const res = await fetch(base + '/api/callbacks');
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Failed');

            const rows = json.data || [];
            countBadge.textContent = rows.length + ' total';

            if (rows.length === 0) {
                body.innerHTML = `
                    <div class="text-center py-16">
                        <div class="text-4xl mb-3">📞</div>
                        <p class="text-slate-500 font-medium">No callback requests yet.</p>
                        <p class="text-slate-400 text-sm mt-1">Share the callback form link with recipients.</p>
                    </div>`;
                return;
            }

            let html = '<div class="space-y-3">';
            rows.forEach(r => {
                const date = new Date(r.created_at * 1000).toLocaleString();
                const statusColors = {
                    pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
                    confirmed: 'bg-blue-100 text-blue-800 border-blue-200',
                    completed: 'bg-green-100 text-green-800 border-green-200',
                    cancelled: 'bg-red-100 text-red-800 border-red-200'
                };
                const statusClass = statusColors[r.status] || statusColors.pending;

                html += `
                <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm hover:border-blue-200 transition-colors">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-slate-800 text-sm">${escHtml(r.recipient_name || 'Unknown')}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full border font-bold uppercase ${statusClass}">${escHtml(r.status)}</span>
                            </div>
                            <div class="text-xs text-slate-500 space-y-0.5">
                                ${r.recipient_email ? '<div><b>Email:</b> ' + escHtml(r.recipient_email) + '</div>' : ''}
                                <div><b>Phone:</b> <span class="text-blue-700 font-medium">${escHtml(r.callback_number)}</span></div>
                                <div><b>Schedule:</b> ${escHtml(r.schedule_date)} at ${escHtml(r.schedule_time)} <span class="font-bold text-slate-700">${escHtml(r.timezone)}</span></div>
                                ${r.notes ? '<div><b>Notes:</b> ' + escHtml(r.notes) + '</div>' : ''}
                                ${r.tracking_id ? '<div class="text-slate-400"><b>Track ID:</b> ' + escHtml(r.tracking_id) + '</div>' : ''}
                            </div>
                            <div class="text-[10px] text-slate-400 mt-1">Submitted: ${date}</div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <select onchange="updateCbStatus(${r.id}, this.value)" class="text-xs border border-slate-200 rounded px-2 py-1 bg-white cursor-pointer">
                                <option value="pending" ${r.status==='pending'?'selected':''}>Pending</option>
                                <option value="confirmed" ${r.status==='confirmed'?'selected':''}>Confirmed</option>
                                <option value="completed" ${r.status==='completed'?'selected':''}>Completed</option>
                                <option value="cancelled" ${r.status==='cancelled'?'selected':''}>Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>`;
            });
            html += '</div>';
            body.innerHTML = html;
        } catch (err) {
            body.innerHTML = '<div class="text-center text-red-500 py-10">' + (err.message || 'Error loading callbacks') + '</div>';
        }
    }

    window.updateCbStatus = async function(id, status) {
        try {
            const data = new URLSearchParams();
            data.append('id', id);
            data.append('status', status);
            const res = await fetch(base + '/api/callbacks/status', { method: 'POST', body: data });
            const json = await res.json();
            if (json.ok) {
                loadCallbacks(); // refresh list
            } else {
                Swal.fire('Error', json.message || 'Update failed', 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Network error', 'error');
        }
    };

    function escHtml(str) {
        if (!str && str !== 0) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    
    // --- Scheduler Logic ---
    window.openSchedulerModal = async function() {
        document.getElementById('scheduler-modal').style.display = 'flex';
        try {
            const res = await fetch('<?= BASE_PATH ?>' + '/api/scheduler/config');
            if (res.ok) {
                const config = await res.json();
                document.getElementById('sched_48_time').value = config.scheduler_48h_time || '10:00';
                document.getElementById('sched_72_time').value = config.scheduler_72h_time || '14:30';
            }
        } catch (e) {
            console.error('Failed to load scheduler config', e);
        }
    };

    window.closeSchedulerModal = function() {
        document.getElementById('scheduler-modal').style.display = 'none';
    };

    window.saveSchedulerConfig = async function() {
        const btn = document.getElementById('sched_save_btn');
        btn.textContent = 'Saving...';
        btn.disabled = true;
        try {
            const data = {
                scheduler_48h_time: document.getElementById('sched_48_time').value,
                scheduler_72h_time: document.getElementById('sched_72_time').value
            };
            const res = await fetch('<?= BASE_PATH ?>' + '/api/scheduler/config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.ok) {
                Swal.fire({ title: 'Saved!', text: 'Scheduler configuration updated.', icon: 'success', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
                closeSchedulerModal();
            } else {
                Swal.fire('Error', json.message || 'Failed to save', 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'Network error', 'error');
        } finally {
            btn.textContent = 'Save Configuration';
            btn.disabled = false;
        }
    };

    // --- Sent Emails Logic ---
    let sentEmailsCache = [];
    let sentEmailsSearchTimeout = null;

    window.openSentEmailsModal = function() {
        document.getElementById('sent-emails-modal').style.display = 'block';
        const q = document.getElementById('sent_emails_search').value.trim();
        fetchSentEmails(q);
    };

    window.closeSentEmailsModal = function() {
        document.getElementById('sent-emails-modal').style.display = 'none';
    };

    window.checkSentEmailsForAutoLoad = async function(email) {
        if (!email) return;
        email = email.toLowerCase().trim();
        
        let matches = [];
        try {
            const formData = new FormData();
            formData.append('action', 'get_sent_emails');
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('search_query', email);
            const res = await fetch('', { method: 'POST', body: formData });
            const text = await res.text();
            const json = JSON.parse(text);
            if (json.status === 'success') {
                matches = json.emails || [];
            }
        } catch(e) {}
        
        if (matches.length === 0) {
            Swal.fire({
                title: 'No History',
                text: 'No past sent emails found for this address.',
                icon: 'info',
                timer: 2000,
                toast: true,
                position: 'top-end',
                showConfirmButton: false
            });
        } else if (matches.length === 1) {
            window.loadSentEmail(matches[0].id);
        } else if (matches.length > 1) {
            document.getElementById('sent_emails_search').value = email;
            window.openSentEmailsModal();
        }
    };

    async function fetchSentEmails(query = '') {
        const listEl = document.getElementById('sent_emails_list');
        listEl.innerHTML = '<div style="text-align:center;color:#64748b;font-size:0.85rem;">Loading...</div>';
        try {
            const formData = new FormData();
            formData.append('action', 'get_sent_emails');
            formData.append('csrf_token', CSRF_TOKEN);
            if (query) {
                formData.append('search_query', query);
            }
            const res = await fetch('', { method: 'POST', body: formData });
            let text = await res.text();
            try {
                const json = JSON.parse(text);
                if (json.status === 'success') {
                    sentEmailsCache = json.emails || [];
                    renderSentEmails(sentEmailsCache);
                } else {
                    listEl.innerHTML = '<div class="text-red-500 text-sm p-4 text-center">' + escapeHtml(json.message || 'Error fetching emails') + '</div>';
                }
            } catch (je) {
                listEl.innerHTML = '<div class="text-red-500 text-sm p-4 text-left whitespace-pre-wrap overflow-auto max-h-64">Invalid JSON: ' + escapeHtml(text) + '</div>';
            }
        } catch (e) {
            listEl.innerHTML = '<div class="text-red-500 text-sm p-4 text-left whitespace-pre-wrap overflow-auto max-h-64">Network error: ' + escapeHtml(e.message) + '\n\n' + escapeHtml(e.stack) + '</div>';
        }
    }

    window.filterSentEmails = function() {
        const q = document.getElementById('sent_emails_search').value.toLowerCase().trim();
        clearTimeout(sentEmailsSearchTimeout);
        sentEmailsSearchTimeout = setTimeout(() => {
            fetchSentEmails(q);
        }, 400);
    };

    function renderSentEmails(emails) {
        const listEl = document.getElementById('sent_emails_list');
        if (emails.length === 0) {
            listEl.innerHTML = '<div style="text-align:center;color:#64748b;font-size:0.85rem;">No emails found.</div>';
            return;
        }
        let html = '';
        emails.forEach(e => {
            const d = new Date(e.sent_at * 1000).toLocaleString();
            html += `
            <div class="bg-white p-3 rounded border border-slate-200 shadow-sm flex flex-col gap-2 relative cursor-pointer hover:border-blue-400 hover:shadow-md transition-all select-none" onclick="window.loadSentEmail(${e.id})" ondblclick="window.closeSentEmailsModal()">
                <div class="font-bold text-slate-800 text-sm">${escapeHtml(e.subject)}</div>
                <div class="text-xs text-slate-500">
                    <div><b>To:</b> ${escapeHtml(e.recipient_email)}</div>
                    <div><b>Sent:</b> ${d}</div>
                </div>
            </div>`;
        });
        listEl.innerHTML = html;
    }
    window.loadSentEmail = function(id) {
        const e = sentEmailsCache.find(x => x.id === id);
        if (!e) return;
        
        if (e.recipient_email) setEmailTagsFromString(e.recipient_email);
        if (e.subject) {
            document.getElementById('email_subject').value = e.subject;
            document.getElementById('email_subject').dispatchEvent(new Event('input'));
        }
        
        if (e.studio_fields) {
            try {
                const fields = JSON.parse(e.studio_fields);
                for (const key in fields) {
                    const el = document.getElementById('p_' + key);
                    if (el && !key.endsWith('_formula')) {
                        el.value = fields[key];
                        if (fields[key + '_formula']) {
                            el.dataset.formula = fields[key + '_formula'];
                            const result = evalMathExpr(el.dataset.formula);
                            const hint = document.getElementById('p_' + key + '_hint');
                            if (hint && result !== null) {
                                hint.textContent = el.dataset.formula + ' = ' + result.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                            }
                        }
                        el.dispatchEvent(new Event('input'));
                    }
                }
            } catch(err) {}
        }

        document.querySelectorAll('input[id^="p_"]').forEach(el => {
            if (el.id !== 'p_Email') {
                el.setAttribute('readonly', 'true');
                el.classList.add('bg-slate-100', 'cursor-not-allowed', 'text-slate-500');
            } else {
                el.removeAttribute('readonly');
                el.classList.remove('bg-slate-100', 'cursor-not-allowed', 'text-slate-500');
            }
        });
        
        if (e.body) {
            const canvas = document.getElementById('proposal_canvas');
            let bodyContent = e.body;
            bodyContent = bodyContent.replace(/<img[^>]+src=["\'][^"\']*\/track\?tid=[^"\']*["\'][^>]*>/i, '');
            
            canvas.innerHTML = bodyContent;
            tokenBase = bodyContent;
            liveCanvasBase = bodyContent;
            _lastSnapshot = bodyContent;
            editorContentBackup = ''; // Clear backup to avoid stale templates during bulk send
            
            if (typeof phViewMode !== 'undefined' && phViewMode === 'values') {
                liveApplyAll();
            } else {
                canvas.innerHTML = renderTokenChips(bodyContent);
                bindTokenChipDrag(canvas);
            }
            bindCanvasImageEvents();
            updatePlaceholderCounts();
        }
        
        const dateStr = new Date(e.sent_at * 1000).toLocaleString();
        const ind = document.getElementById('sent_date_indicator');
        if (ind) {
            ind.innerHTML = '<span style="vertical-align: middle; font-size:1.1rem; margin-right: 4px;">🕒</span> Previewing past email sent on: <span style="color:#0284c7;">' + dateStr + '</span>';
            ind.style.display = 'inline-block';
        }
        
        Swal.fire({
            title: 'Loaded',
            text: 'Sent email from ' + dateStr + ' loaded. Please review.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    };

    setupSentEmailsPanelDrag();

    function setupSentEmailsPanelDrag() {
        const panel = document.getElementById('sent-emails-panel');
        const header = document.getElementById('sent-emails-header');
        if (!panel || !header) return;
        let dragCtx = null;

        header.addEventListener('pointerdown', function (e) {
            if (e.target.closest('button')) return;
            const rect = panel.getBoundingClientRect();
            
            panel.style.transform = 'none';
            panel.style.left = rect.left + 'px';
            panel.style.top = rect.top + 'px';

            dragCtx = {
                startX: e.clientX,
                startY: e.clientY,
                left: rect.left,
                top: rect.top
            };
            header.setPointerCapture(e.pointerId);
        });

        header.addEventListener('pointermove', function (e) {
            if (!dragCtx) return;
            const dx = e.clientX - dragCtx.startX;
            const dy = e.clientY - dragCtx.startY;
            panel.style.left = (dragCtx.left + dx) + 'px';
            panel.style.top = (dragCtx.top + dy) + 'px';
        });

        header.addEventListener('pointerup', function () {
            dragCtx = null;
        });
        header.addEventListener('pointerleave', function () {
            dragCtx = null;
        });
    }
})();

    let currentSavedItems = [];
    let currentSavedListId = 0, currentSavedPage = 0, currentSavedTotal = 0;
    const SAVED_ITEMS_PAGE_SIZE = 25;
    window.safeEscHtml = function(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    };
    
    window.openSavedListsModal = function() {
        document.getElementById('saved-lists-modal').style.display = 'block';
        document.getElementById('saved_lists_container').classList.remove('hidden');
        document.getElementById('saved_list_items_container').classList.add('hidden');
        loadSavedLists();
    };

    window.closeSavedListsModal = function() {
        document.getElementById('saved-lists-modal').style.display = 'none';
    };

    window.backToSavedLists = function() {
        document.getElementById('saved_lists_container').classList.remove('hidden');
        document.getElementById('saved_list_items_container').classList.add('hidden');
    };

    async function loadSavedLists() {
        const container = document.getElementById('saved_lists_container');
        container.innerHTML = '<div style="text-align:center;color:#64748b;">Loading...</div>';
        try {
            const formData = new FormData();
            formData.append('action', 'get_db_lists');
            formData.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                if (!data.lists.length) {
                    container.innerHTML = '<div style="text-align:center;color:#64748b;">No saved data found.</div>';
                    return;
                }
                let html = '<div style="display:flex;flex-direction:column;gap:8px;">';
                data.lists.forEach(l => {
                    const date = new Date(l.created_at * 1000).toLocaleString();
                    html += `<div style="border:1px solid #e2e8f0;padding:10px;border-radius:6px;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;">
                        <div style="cursor:pointer;flex:1;" onclick="loadSavedListItems(${l.id})">
                            <div style="font-weight:bold;color:#0f172a;">${safeEscHtml(l.name)}</div>
                            <div style="font-size:0.75rem;color:#64748b;">${l.item_count} items • ${date}</div>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <button onclick="editSavedList(${l.id}, '${safeEscHtml(l.name).replace(/'/g, "\\'")}')" class="tb-btn" style="padding:4px 8px;font-size:0.7rem;">✏️ Edit</button>
                            <button onclick="deleteSavedList(${l.id})" class="tb-btn" style="padding:4px 8px;font-size:0.7rem;background:#fee2e2;color:#b91c1c;border-color:#fca5a5;">🗑️ Del</button>
                        </div>
                    </div>`;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div style="color:red;">${safeEscHtml(data.message)}</div>`;
            }
        } catch (e) {
            container.innerHTML = `<div style="color:red;">Client Error: ${e.message}</div>`;
            console.error(e);
        }
    }

    async function loadSavedListItems(listId, page = 0) {
        document.getElementById('saved_lists_container').classList.add('hidden');
        document.getElementById('saved_list_items_container').classList.remove('hidden');
        const container = document.getElementById('saved_items_list');
        container.innerHTML = 'Loading items...';
        currentSavedItems = [];
        
        try {
            const formData = new FormData();
            formData.append('action', 'get_db_list_items');
            formData.append('list_id', listId);
            formData.append('offset', page * SAVED_ITEMS_PAGE_SIZE);
            formData.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                if (!data.items.length && page > 0) {
                    await loadSavedListItems(listId, page - 1);
                    return;
                }
                currentSavedItems = data.items;
                currentSavedListId = listId;
                currentSavedPage = page;
                currentSavedTotal = data.total;
                document.getElementById('saved_items_page_info').textContent =
                    `Page ${page + 1} of ${Math.max(1, Math.ceil(currentSavedTotal / SAVED_ITEMS_PAGE_SIZE))} · ${currentSavedTotal} items`;
                document.getElementById('saved_items_prev').disabled = page === 0;
                document.getElementById('saved_items_next').disabled =
                    (page + 1) * SAVED_ITEMS_PAGE_SIZE >= currentSavedTotal;
                document.getElementById('select_all_saved_items').checked = false;
                renderSavedItems();
            } else {
                container.innerHTML = `<div style="color:red;">${safeEscHtml(data.message)}</div>`;
            }
        } catch(e) {
            container.innerHTML = `<div style="color:red;">Client Error: ${e.message}</div>`;
            console.error(e);
        }
    }

    window.changeSavedItemsPage = function(direction) {
        if (document.querySelector('.saved-item-cb:checked') &&
            !confirm('Changing pages clears the selected rows. Continue?')) return;
        const nextPage = currentSavedPage + direction;
        const pageCount = Math.ceil(currentSavedTotal / SAVED_ITEMS_PAGE_SIZE);
        if (nextPage < 0 || nextPage >= pageCount) return;
        loadSavedListItems(currentSavedListId, nextPage);
    };

    window.renderSavedItems = function() {
        const container = document.getElementById('saved_items_list');
        const filter = document.getElementById('saved_items_filter').value.toLowerCase();
        let html = '';
        currentSavedItems.forEach((item, idx) => {
            const email = (item.email || '').toLowerCase();
            if (filter && !email.includes(filter)) return;
            
            html += `<div style="display:flex;align-items:center;gap:8px;padding:6px;border-bottom:1px solid #f1f5f9;">
                <input type="checkbox" class="saved-item-cb" value="${idx}" id="si_${idx}">
                <label for="si_${idx}" style="flex:1;cursor:pointer;word-break:break-all;">
                    <div style="font-weight:bold;">${safeEscHtml(item.email)}</div>
                    <div style="font-size:0.7rem;color:#64748b;">Status: ${item.status}</div>
                </label>
                <div style="display:flex;gap:4px;">
                    <button onclick="editSavedListItem(${item.id}, '${safeEscHtml(item.email).replace(/'/g, "\\'")}')" class="tb-btn" style="padding:2px 6px;font-size:0.65rem;">✏️</button>
                    <button onclick="deleteSavedListItem(${item.id})" class="tb-btn" style="padding:2px 6px;font-size:0.65rem;background:#fee2e2;color:#b91c1c;border-color:#fca5a5;">🗑️</button>
                </div>
            </div>`;
        });
        container.innerHTML = html || '<div style="color:#64748b;">No items match filter.</div>';
    };

    window.editSavedList = async function(id, oldName) {
        const newName = prompt("Enter new list name:", oldName);
        if (!newName || newName === oldName) return;
        try {
            const fd = new FormData(); fd.append('action', 'update_db_list'); fd.append('csrf_token', CSRF_TOKEN); fd.append('list_id', id); fd.append('name', newName);
            await fetch('', { method: 'POST', body: fd });
            loadSavedLists();
        } catch(e) { showToast('Error', 'error'); }
    };

    window.deleteSavedList = async function(id) {
        if (!confirm("Delete this entire list and all its items?")) return;
        try {
            const fd = new FormData(); fd.append('action', 'delete_db_list'); fd.append('csrf_token', CSRF_TOKEN); fd.append('list_id', id);
            await fetch('', { method: 'POST', body: fd });
            loadSavedLists();
        } catch(e) { showToast('Error', 'error'); }
    };

    window.editSavedListItem = async function(id, oldEmail) {
        const newEmail = prompt("Enter new email:", oldEmail);
        if (!newEmail || newEmail === oldEmail) return;
        try {
            const fd = new FormData(); fd.append('action', 'update_db_list_item'); fd.append('csrf_token', CSRF_TOKEN); fd.append('item_id', id); fd.append('email', newEmail);
            await fetch('', { method: 'POST', body: fd });
            if(currentSavedListId) loadSavedListItems(currentSavedListId, currentSavedPage);
        } catch(e) { showToast('Error', 'error'); }
    };

    window.deleteSavedListItem = async function(id) {
        if (!confirm("Delete this item?")) return;
        try {
            const fd = new FormData(); fd.append('action', 'delete_db_list_item'); fd.append('csrf_token', CSRF_TOKEN); fd.append('item_id', id);
            await fetch('', { method: 'POST', body: fd });
            if(currentSavedListId) loadSavedListItems(currentSavedListId, currentSavedPage);
        } catch(e) { showToast('Error', 'error'); }
    };

    window.filterSavedItems = function() {
        renderSavedItems();
    };

    window.toggleAllSavedItems = function(checked) {
        document.querySelectorAll('.saved-item-cb').forEach(cb => { cb.checked = checked; });
    };

    window.sendSelectedSavedItems = function() {
        const checkboxes = document.querySelectorAll('.saved-item-cb:checked');
        if (!checkboxes.length) { showToast('Select at least one item.', 'error'); return; }
        
        csvSourceFile = null;
        const selectedIdxs = Array.from(checkboxes).map(cb => parseInt(cb.value));
        csvData = selectedIdxs.map(idx => {
            const item = currentSavedItems[idx];
            let parsedData = {};
            try {
                parsedData = typeof item.data === 'string' ? JSON.parse(item.data) : (item.data || {});
            } catch(e) {
                console.error("Error parsing item data:", e);
            }
            return { ...parsedData, Email: item.email };
        });
        
        csvDetectedHeaders = [];
        if (csvData.length > 0) {
            csvDetectedHeaders = Object.keys(csvData[0]).filter(Boolean);
        }
        
        csvFieldMapping = {};
        const autoFieldNames = Object.keys(<?php echo json_encode($placeholderFields); ?>);
        autoFieldNames.forEach((key) => {
            const match = csvDetectedHeaders.find((header) => {
                const h = (header||'').toLowerCase().replace(/[^a-z0-9]/g, '');
                const k = (key||'').toLowerCase().replace(/[^a-z0-9]/g, '');
                return h === k;
            });
            if (match) csvFieldMapping[key] = match;
        });
        
        closeSavedListsModal();
        openBulkModal();
        
        document.getElementById('csv_status').classList.remove('hidden');
        document.getElementById('csv_status').className = 'text-[11px] px-2 py-1.5 rounded border border-emerald-200 bg-emerald-50 text-emerald-700 font-medium';
        document.getElementById('csv_status').innerText = `Loaded ${csvData.length} records from Saved Data`;
        
        document.getElementById('csv_actions').classList.remove('hidden');
        document.getElementById('csv_preview_controls').classList.remove('hidden');
        
        if (typeof buildCsvMappingControls === 'function') buildCsvMappingControls();
        
        currentPreviewIndex = 0;
        if (typeof previewCSVRow === 'function') previewCSVRow(0);
    };

    async function saveBulkList() {
        if (csvData.length === 0 && !csvSourceFile) { showToast('Upload a CSV file first', 'error'); return; }
        const listName = prompt("Enter a name for this list (letters, numbers, hyphens):");
        if (!listName) return;
        
        const bp = document.getElementById('bulk_progress');
        bp.classList.remove('hidden');
        bp.style.color = '#334155';
        
        let listId = null;
        let successCount = 0, processedCount = 0;
        const total = csvSourceFile ? null : csvData.length;
        const chunkSize = 250;
        const maxChunkBytes = 512 * 1024;
        let chunkRows = [];
        let chunkBytes = 0;

        const saveChunk = async () => {
            if (chunkRows.length === 0) return;
            try {
                const formData = new FormData();
                formData.append('action', 'save_mapped_list');
                formData.append('csrf_token', CSRF_TOKEN);
                if (!listId) formData.append('list_name', listName);
                if (listId) formData.append('list_id', listId);
                formData.append('csv_data', JSON.stringify(chunkRows));
                
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    if (data.list_id) listId = data.list_id;
                    successCount += chunkRows.length;
                    chunkRows = [];
                    chunkBytes = 0;
                } else {
                    throw new Error(data.message || 'Save failed');
                }
            } catch (e) {
                console.error('Failed to save CSV chunk', e);
                throw e;
            }
        };

        try {
            for await (const row of getBulkRows()) {
                processedCount++;
                const rawEmail = resolveMappedCsvValue(row, 'Email') || '';
                if (!rawEmail || !parseEmailList(rawEmail).some(isValidEmail)) continue;

                const mappedRow = { ...row };
                Object.keys(<?php echo json_encode($placeholderFields); ?>).forEach(key => {
                    mappedRow[key] = resolveMappedCsvValue(row, key) || '';
                });
                mappedRow['Email'] = rawEmail;
                const rowBytes = new TextEncoder().encode(JSON.stringify(mappedRow)).length;
                if (rowBytes > maxChunkBytes) {
                    throw new Error('A row is too large to save (maximum 512 KB per row).');
                }
                if (chunkRows.length && (chunkRows.length >= chunkSize || chunkBytes + rowBytes > maxChunkBytes)) {
                    bp.innerText = `⏳ Saving chunks... ${processedCount}${total === null ? ' rows processed' : ' of ' + total}`;
                    await saveChunk();
                }
                chunkRows.push(mappedRow);
                chunkBytes += rowBytes;

                if (chunkRows.length >= chunkSize || chunkBytes >= maxChunkBytes) {
                    bp.innerText = `⏳ Saving chunks... ${processedCount}${total === null ? ' rows processed' : ' of ' + total}`;
                    await saveChunk();
                }
            }
            await saveChunk();
        } catch (e) {
            bp.style.color = '#b91c1c';
            bp.innerText = `❌ Save stopped after ${successCount} valid rows: ${e.message} Check the saved list before retrying.`;
            csvSourceFile = null;
            csvData = [];
            document.getElementById('csv_file').value = '';
            document.getElementById('csv_label_text').textContent = 'Drop CSV or click to upload';
            document.getElementById('csv_preview_controls').classList.add('hidden');
            showToast('List save stopped early; some chunks may already be saved.', 'error');
            return;
        }
        
        if (successCount > 0) {
            bp.style.color = '#15803d';
            bp.innerText = `✅ Successfully saved ${successCount} valid rows from ${processedCount} rows!`;
            showToast("List saved to database successfully!", 'success');
        } else {
            bp.style.color = '#b91c1c';
            bp.innerText = `❌ No valid rows saved.`;
            showToast("No valid emails found to save.", "error");
        }
        
        setTimeout(() => {
            bp.classList.add('hidden');
            bp.innerText = '';
        }, 4000);
    }
</script>