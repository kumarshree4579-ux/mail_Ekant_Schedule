<?php
function tr_time(int $ts): string  { return date('Y-m-d, h:i A', $ts); }
function tr_ago(int $ts): string {
    $d = time() - $ts;
    if ($d < 60)    return 'Just now';
    if ($d < 3600)  return round($d / 60) . 'm ago';
    if ($d < 86400) return round($d / 3600) . 'h ago';
    return round($d / 86400) . 'd ago';
}

function tr_qs(array $override = [], array $remove = []): string {
    $p = array_merge($_GET, $override);
    foreach ($remove as $k) unset($p[$k]);
    unset($p['export']);
    return $p ? '?' . http_build_query($p) : '?';
}

function tr_th(string $label, string $col, string $align = 'left', string $extraClass = ''): string {
    $currentSort = $_GET['sort'] ?? 'sent_at';
    $currentDir = strtolower($_GET['dir'] ?? 'desc');
    $isSorted = $currentSort === $col;
    $newDir = ($isSorted && $currentDir === 'desc') ? 'asc' : 'desc';

    $qs = tr_qs(['sort' => $col, 'dir' => $newDir]);

    // Always show an arrow
    $icon = $isSorted
        ? ($currentDir === 'asc' ? '⬆' : '⬇')
        : '↓';

    $alignClass = $align === 'center'
        ? 'text-center justify-center'
        : ($align === 'right' ? 'text-right justify-end' : 'text-left');

    $flexClass = $align === 'center'
        ? 'justify-center'
        : ($align === 'right' ? 'justify-end' : '');

    return "<th class=\"px-5 py-3 {$alignClass} {$extraClass}\">
        <a href=\"{$qs}\" class=\"group inline-flex items-center gap-2 hover:text-blue-900 transition-colors cursor-pointer {$flexClass} w-full\">
            {$label}
            <span class=\"text-base font-extrabold w-4\">{$icon}</span>
        </a>
    </th>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Tracking — Proposal Studio</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📬</text></svg>">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/rrweb-player@1.0.0-alpha.4/dist/style.css" />
<script src="https://cdn.jsdelivr.net/npm/rrweb-player@1.0.0-alpha.4/dist/index.js"></script>
<style>
    /* Custom Scrollbar for a cleaner UI */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* Modern Badges */
    .badge-sent    { background:#d1fae5; color:#047857; border: 1px solid #a7f3d0; }
    .badge-draft   { background:#fef3c7; color:#b45309; border: 1px solid #fde68a; }
    .badge-none    { background:#f1f5f9; color:#64748b; border: 1px solid #e2e8f0; }
    
    .filter-active { border-color: #3b82f6 !important; background-color: #eff6ff !important; color: #1d4ed8 !important; }
    
    /* Resizable Floating Modal */
    .floating-modal {
        resize: both;
        overflow: hidden;
        min-width: 320px;
        min-height: 250px;
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    /* Pulse animation for loading skeleton */
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    .animate-pulse-fast { animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
</style>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800 text-sm antialiased">

<?php $currentUser = \App\Core\Auth::currentUser(); $isEmailTracker = ($currentUser['role'] ?? '') === 'email_tracker'; ?>
<header class="bg-slate-900 text-white px-4 sm:px-6 py-4 flex flex-col sm:flex-row justify-between items-center shadow-md sticky top-0 z-20 gap-4 sm:gap-0">
    <div class="flex items-center gap-3">
        <span class="text-xl">📊</span>
        <span class="font-bold tracking-wide text-lg sm:text-xl">Email Tracking</span>
        <span class="text-slate-400 text-xs hidden sm:inline border-l border-slate-700 pl-3 ml-1">Proposal Studio Pro</span>
    </div>
    <div class="flex flex-wrap justify-center items-center gap-2">
        <?php if (!$isEmailTracker): ?>
            <a href="<?= BASE_PATH ?>/studio" class="text-xs font-medium bg-slate-800 hover:bg-slate-700 transition-colors px-3 py-2 rounded-md border border-slate-700 text-slate-200">← Studio</a>
            <a href="<?= BASE_PATH ?>/admin"  class="text-xs font-medium bg-slate-800 hover:bg-slate-700 transition-colors px-3 py-2 rounded-md border border-slate-700 text-slate-200">Admin</a>
        <?php endif; ?>
        <button id="activity-toggle" class="text-xs font-medium bg-blue-600 hover:bg-blue-700 transition-colors px-3 py-2 rounded-md border border-blue-700 text-white flex items-center gap-1.5 shadow-sm sm:ml-2">
            <span class="animate-pulse">🔴</span> Activity
        </button>
        <button onclick="openCallbacksPanel()" class="text-xs font-medium bg-purple-600 hover:bg-purple-700 transition-colors px-3 py-2 rounded-md border border-purple-700 text-white flex items-center gap-1.5 shadow-sm">
            📞 Callbacks
        </button>
        <a href="<?= BASE_PATH ?>/logout" class="text-xs font-medium bg-red-500/10 hover:bg-red-500/20 text-red-400 transition-colors px-3 py-2 rounded-md border border-red-500/20 sm:ml-2">Logout</a>
    </div>
</header>

<main class="max-w-full mx-auto px-4 sm:px-6 py-8 space-y-6">

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $openRate = $stats['total_sent'] > 0 ? round($stats['total_opened'] / $stats['total_sent'] * 100, 1) : 0;
        $avgAttachTime = 0;
        if (!empty($stats['total_attach_time_ms']) && !empty($stats['total_attach_events']) && (int)$stats['total_attach_events'] > 0) {
            $avgAttachTime = round(((int)$stats['total_attach_time_ms'])/1000 / (int)$stats['total_attach_events'], 1);
        }
        $cards = [
            ['📨', 'Emails Processed', $total, 'bg-white'],
            ['🚀', 'Successfully Sent', $stats['total_sent'], 'bg-white'],
            ['👁️', 'Unique Opens', $stats['total_opened'], 'bg-white'],
            ['📈', 'Open Rate', $openRate . '%', 'bg-white'],
            // ['⏱️', 'Avg Attach Time', $avgAttachTime . 's', 'bg-white'],
        ];
        foreach ($cards as [$icon, $label, $val, $bg]):
        ?>
        <div class="<?= $bg ?> rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col gap-2 relative overflow-hidden group hover:border-blue-200 transition-colors">
            <div class="absolute -right-4 -top-4 text-6xl opacity-5 group-hover:opacity-10 transition-opacity transform group-hover:scale-110 duration-300"><?= $icon ?></div>
            <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider"><?= $label ?></span>
            <span class="text-3xl font-bold text-slate-800"><?= htmlspecialchars((string)$val) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!--<div class="bg-white rounded-xl border border-slate-200 px-5 py-3 text-xs text-slate-500">-->
    <!--    Note: tracking counts exclude internal/self-sentbox opens so only customer mailbox opens are shown.-->
    <!--</div>-->

    <form method="GET" action="<?= BASE_PATH ?>/tracking" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 sm:p-5 flex flex-wrap gap-4 items-end">
        
        <div class="flex flex-col gap-1.5 flex-[1_1_100%] sm:flex-[2_1_auto] min-w-[200px]">
            <label class="text-xs font-semibold text-slate-600">Search Emails</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">🔍</span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search subject or recipient..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            </div>
        </div>

        <div class="flex flex-col gap-1.5 flex-[1_1_100%] sm:flex-[1_1_auto] min-w-[250px]">
            <label class="text-xs font-semibold text-slate-600">Date Range</label>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="w-full sm:w-auto py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-slate-600">
                <span class="text-slate-400 text-xs text-center hidden sm:inline">to</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="w-full sm:w-auto py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-slate-600">
            </div>
        </div>

        <div class="flex flex-col gap-1.5 flex-[1_1_45%] sm:flex-none min-w-[120px]">
            <label class="text-xs font-semibold text-slate-600">Status</label>
            <select name="type" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-white cursor-pointer">
                <option value="all"   <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="sent"  <?= $typeFilter === 'sent' ? 'selected' : '' ?>>Sent Only</option>
                <option value="draft" <?= $typeFilter === 'draft' ? 'selected' : '' ?>>Drafts Only</option>
            </select>
        </div>

        <div class="flex flex-col gap-1.5 flex-[1_1_45%] sm:flex-none min-w-[130px]">
            <label class="text-xs font-semibold text-slate-600">Engagement</label>
            <select name="opened" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-white cursor-pointer">
                <option value="all"      <?= $openFilter === 'all' ? 'selected' : '' ?>>All / Any</option>
                <option value="opened"   <?= $openFilter === 'opened' ? 'selected' : '' ?>>Opened</option>
                <option value="unopened" <?= $openFilter === 'unopened' ? 'selected' : '' ?>>Unopened</option>
                <option value="closed"   <?= $openFilter === 'closed' ? 'selected' : '' ?>>Closed Deals</option>
                <option value="unclosed" <?= $openFilter === 'unclosed' ? 'selected' : '' ?>>Open Deals (Not Closed)</option>
            </select>
        </div>

        <div class="flex gap-2 w-full sm:w-auto sm:ml-auto">
            <button type="submit" class="flex-1 sm:flex-none bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors shadow-sm whitespace-nowrap text-center">
                Apply Filters
            </button>
            <?php if (!empty($_GET) && count($_GET) > 0): ?>
            <a href="<?= BASE_PATH ?>/tracking" class="flex-1 sm:flex-none bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium px-5 py-2 rounded-lg transition-colors border border-slate-200 text-center">
                Clear
            </a>
            <?php endif; ?>
        </div>

        <div class="w-full flex gap-2 pt-4 mt-2 border-t border-slate-100 overflow-x-auto pb-1">
            <span class="text-xs text-slate-400 py-1 font-medium uppercase tracking-wider mr-2">Quick Views:</span>
            <?php
            $shortcuts = [
                'Today'      => [date('Y-m-d'), date('Y-m-d')],
                'Yesterday'  => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
                'Last 7 Days'=> [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
                'This Month' => [date('Y-m-01'), date('Y-m-d')],
                'All Time'   => ['2000-01-01', date('Y-m-d')],
            ];
            foreach ($shortcuts as $label => [$f, $t]):
                $active = ($dateFrom === $f && $dateTo === $t && $search === '' && $typeFilter === 'all' && $openFilter === 'all');
            ?>
            <a href="<?= tr_qs(['date_from' => $f, 'date_to' => $t, 'page' => 1], ['search','type','opened']) ?>"
               class="whitespace-nowrap text-xs px-3 py-1.5 rounded-full border border-slate-200 hover:border-blue-300 transition-colors
                      <?= $active ? 'filter-active font-semibold shadow-sm' : 'bg-white text-slate-500' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div class="text-sm font-medium text-slate-600 flex items-center gap-2">
                Showing <?= count($rows) ?> of <?= $total ?> records
                <?php if ($dateFrom === $dateTo && $dateFrom === date('Y-m-d')): ?>
                    <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider">Today</span>
                <?php endif; ?>
            </div>
            <a href="<?= tr_qs(['export' => 'csv']) ?>&export=csv" class="flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </a>
            <a href="<?= tr_qs(['export' => 'csv', 'detail' => 'unread_fwd']) ?>&export=csv&detail=unread_fwd" class="flex items-center gap-2 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-700 text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                Unread FWD Report
            </a>
            <!--<a href="<?= tr_qs(['export' => 'csv', 'detail' => 'attachments']) ?>&export=csv&detail=attachments" class="flex items-center gap-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">-->
            <!--    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2"/></svg>-->
            <!--    Export Attachment Summary-->
            <!--</a>-->
            <!--<a href="<?= tr_qs(['export' => 'csv', 'detail' => 'attachment_events']) ?>&export=csv&detail=attachment_events" class="flex items-center gap-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">-->
            <!--    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2"/></svg>-->
            <!--    Export Attachment Events-->
            <!--</a>-->
        </div>

        <?php if (empty($rows)): ?>
        <div class="py-24 flex flex-col items-center justify-center text-center">
            <div class="bg-slate-100 h-20 w-20 rounded-full flex items-center justify-center mb-4">
                <span class="text-3xl">📭</span>
            </div>
            <h3 class="text-lg font-semibold text-slate-700">No emails found</h3>
            <p class="text-slate-500 mt-1 mb-4">Try adjusting your filters or search terms.</p>
            <a href="<?= BASE_PATH ?>/tracking" class="text-blue-600 hover:text-blue-700 font-medium text-sm hover:underline">Clear all filters</a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full table-auto text-sm text-left">
    <thead class="bg-slate-50 text-slate-500 uppercase text-[14px] font-semibold tracking-wider border-b border-slate-200">
        <tr>
            <?= tr_th('Sent At', 'sent_at', 'left', 'min-w-[110px]') ?>
            <?= tr_th('Last Open', 'last_opened_at', 'left', 'min-w-[130px]') ?>
            <?= tr_th('Recipients', 'recipients', 'min-w-[200px]') ?>
            <?= tr_th('Subject & Body', 'subject', 'left', 'min-w-[380px]') ?>
            <th class="px-3 py-3">Status</th>
            <?= tr_th('Opens', 'open_count', 'center') ?>
            <?= tr_th('Att. Opens', 'attach_open_count', 'center') ?>
            <?= tr_th('Track ID', 'custom_id', 'left', 'min-w-[85px] w-[90px]') ?>
            <?= tr_th('Status', 'is_closed', 'left', 'min-w-[140px] w-[150px]') ?>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        <?php foreach ($rows as $r): ?>
        <tr class="hover:bg-blue-50/30 transition-colors group">
            <td class="px-4 py-3 whitespace-nowrap align-top">
                <div class="font-medium text-slate-800"><?= tr_time((int)$r['sent_at']) ?></div>
                <div class="text-slate-400 text-xs mt-0.5"><?= tr_ago((int)$r['sent_at']) ?></div>
            </td>
            
            <td class="px-4 py-3 whitespace-nowrap align-top">
                <?php if ($r['last_opened_at']): ?>
                    <div class="text-blue-600 font-medium"><?= date('M j, g:i A', (int)$r['last_opened_at']) ?></div>
                    <div class="text-slate-400 text-xs mt-0.5"><?= tr_ago((int)$r['last_opened_at']) ?></div>
                <?php else: ?>
                    <span class="text-slate-300 text-xs italic">Unopened</span>
                <?php endif; ?>
            </td>
            
            <td class="px-4 py-3 align-top">
                <div class="flex flex-col gap-1">
                    <?php $recips = explode(',', $r['recipients']); ?>
                    <?php foreach ($recips as $rec): ?>
                        <div class="text-slate-600 font-medium break-all">
                            <?= htmlspecialchars(trim($rec)) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </td>
            
            <td class="px-5 py-3 align-top min-w-[380px]">
                <div class="text-slate-800 font-medium whitespace-normal break-words flex items-center justify-between gap-2">
                    <span class="text-slate-900 font-semibold leading-relaxed"><?= htmlspecialchars($r['subject']) ?></span>
                    <button type="button" class="view-mail-btn shrink-0 text-xs bg-slate-100 hover:bg-blue-100 hover:text-blue-700 border border-slate-200 hover:border-blue-300 rounded px-2 py-1 transition-all transform hover:scale-105 active:scale-95 cursor-pointer font-semibold whitespace-nowrap ml-2" data-tid="<?= htmlspecialchars($r['tracking_id'], ENT_QUOTES) ?>">
                        🔍 View Detail
                    </button>
                </div>
                <?php if (!empty($r['body'])): ?>
                    <div class="text-slate-500 text-xs mt-1.5 line-clamp-3 italic leading-relaxed" title="Email Body Preview">
                        <?= htmlspecialchars(mb_substr(trim(strip_tags($r['body'])), 0, 220)) ?>...
                    </div>
                <?php endif; ?>
            </td>
            
            <td class="px-3 py-3 whitespace-nowrap align-top">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $r['is_draft'] ? 'badge-draft' : 'badge-sent' ?>">
                    <?= $r['is_draft'] ? 'Draft' : 'Sent' ?>
                </span>
            </td>
            
            <td class="px-3 py-3 text-center whitespace-nowrap align-top">
                <?php if ((int)$r['open_count'] > 0): ?>
                    <button type="button" 
                            class="open-list-btn relative inline-flex items-center justify-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold text-blue-700 bg-blue-100 hover:bg-blue-200 border border-blue-200 hover:border-blue-300 transition-all cursor-pointer transform hover:scale-105 active:scale-95" 
                            data-tid="<?= htmlspecialchars($r['tracking_id'], ENT_QUOTES) ?>">
                        👁️ <?= (int)$r['open_count'] ?>
                    </button>
                <?php else: ?>
                    <span class="badge-none px-2 py-0.5 rounded-full text-xs font-medium">—</span>
                <?php endif; ?>
            </td>
            
            <td class="px-3 py-3 text-center align-top">
                <?php $atts = $rowAttachments[$r['tracking_id']] ?? []; ?>
                <?php $callbacks = $rowCallbacks[$r['tracking_id']] ?? []; ?>
                <?php if (empty($atts) && empty($callbacks)): ?>
                    <span class="badge-none px-2.5 py-0.5 rounded-full text-xs font-medium">—</span>
                <?php else: ?>
                    <div class="flex flex-col gap-1.5 items-center">
                        <?php foreach ($atts as $att): ?>
                            <?php 
                                $isOpen = (int)$att['open_count'] > 0;
                            ?>
                            <div class="flex items-center gap-1.5 justify-center">
                                <span class="text-xs text-slate-600 text-left truncate max-w-[130px]" title="<?= htmlspecialchars($att['name']) ?>">
                                    <?= htmlspecialchars($att['name']) ?>
                                </span>
                                <?php if ($isOpen): ?>
                                    <button type="button" onclick="window.playSessionReplay('<?= BASE_PATH ?>/Viewer/api.php?token=<?= $att['token'] ?>')" class="replay-btn shrink-0 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 uppercase font-bold whitespace-nowrap" data-replay-token="<?= $att['token'] ?>">
                                        ▶ Play <span class="replay-duration text-[9px] text-emerald-500 font-normal normal-case ml-0.5"></span>
                                    </button>
                                <?php else: ?>
                                    <span class="shrink-0 text-[9px] px-1.5 py-0.5 rounded bg-slate-50 text-slate-400 border border-slate-100 whitespace-nowrap">Unopened</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!empty($callbacks)): ?>
                            <button type="button" onclick="showCallbackDetails('<?= htmlspecialchars($r['tracking_id'], ENT_QUOTES) ?>')" class="mt-0.5 inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 font-bold cursor-pointer transition-colors whitespace-nowrap">
                                📞 Callbacks (<?= count($callbacks) ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </td>

            <td class="px-3 py-3 align-top whitespace-nowrap">
                <span class="text-slate-700 font-bold bg-slate-100 border border-slate-200 px-2 py-0.5 rounded text-[10px] uppercase tracking-wider inline-block">
                    <?= htmlspecialchars($r['custom_id'] ?: '—') ?>
                </span>
                <?php if (!empty($r['is_bounced'])): ?>
                    <span class="mt-1 flex items-center gap-1 text-[10px] font-bold text-red-700 bg-red-100 px-2 py-0.5 rounded-full uppercase tracking-wider w-fit">
                        🚨 Bounced
                    </span>
                <?php endif; ?>
            </td>

            <td class="px-3 py-3 align-top">
                <?php if ($r['is_closed']): ?>
                    <div class="flex flex-col gap-1 min-w-[130px] max-w-[180px]">
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold bg-white hover:bg-red-50 text-slate-700 hover:text-red-700 border border-slate-300 hover:border-red-400 px-2 py-1 rounded shadow-xs transition-all transform hover:scale-105 active:scale-95 whitespace-nowrap cursor-disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="display:inline;vertical-align:middle">
  <path d="M7 10V7.5A5 5 0 0 1 17 7.5V10" fill="none" stroke="#546E7A" stroke-width="2.5" stroke-linecap="round"/>
  <rect x="4" y="10" width="16" height="12" rx="2.5" fill="#FFCA28" stroke="#F57F17" stroke-width="1"/>
  <circle cx="12" cy="14.5" r="1.5" fill="#4E342E"/>
  <path d="M11.2 15h1.6l-0.4 3h-.8z" fill="#4E342E"/>
</svg>
 Closed <?= htmlspecialchars($r['closed_at'] ?: '') ?>
                        </span>
                        <?php if (!empty($r['closed_remark'])): ?>
                                <span class="font-bold text-emerald-900 text-[9px] uppercase block tracking-wide">Remark:
                                <?= htmlspecialchars($r['closed_remark']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <button type="button" 
                            onclick="openCloseModal('<?= htmlspecialchars($r['custom_id'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($r['tracking_id'], ENT_QUOTES) ?>')" 
                            class="inline-flex items-center gap-1 text-[11px] font-semibold bg-white hover:bg-blue-50 text-slate-700 hover:text-blue-700 border border-slate-300 hover:border-blue-400 px-2 py-1 rounded shadow-xs transition-all transform hover:scale-105 active:scale-95 whitespace-nowrap cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="display:inline;vertical-align:middle">
<path d="M17 10V5.5A4.5 4.5 0 0 0 8 5.5" transform="translate(24 0) scale(-1 1)" fill="none" stroke="#546E7A" stroke-width="2.5" stroke-linecap="round"/>
<rect x="4" y="10" width="16" height="12" rx="2.5" fill="#FFCA28" stroke="#F57F17" stroke-width="1"/>
 <circle cx="12" cy="14.5" r="1.5" fill="#4E342E"/>
                          <path d="M11.2 15h1.6l-0.4 3h-.8z" fill="#4E342E"/>
</svg>Open
                    </button>   
                <?php endif; ?>
                <?php if (empty($r['is_bounced']) && (\App\Core\Auth::currentUser()['role'] ?? '') === 'admin'): ?>
                    <button type="button" 
                            onclick="markBounced('<?= htmlspecialchars($r['tracking_id'], ENT_QUOTES) ?>')" 
                            class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold bg-white hover:bg-red-50 text-slate-700 hover:text-red-700 border border-slate-300 hover:border-red-400 px-2 py-1 rounded shadow-xs transition-all transform hover:scale-105 active:scale-95 whitespace-nowrap cursor-pointer">
                        🚨 Mark Bounced
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between flex-col sm:flex-row gap-4">
            <span class="text-sm text-slate-500 font-medium">
                Showing page <strong class="text-slate-700"><?= $page ?></strong> of <strong class="text-slate-700"><?= $totalPages ?></strong>
            </span>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= tr_qs(['page' => 1]) ?>" class="px-3 py-1.5 text-sm border border-slate-300 bg-white rounded-md hover:bg-slate-100 transition-colors shadow-sm">&laquo;</a>
                    <a href="<?= tr_qs(['page' => $page - 1]) ?>" class="px-3 py-1.5 text-sm border border-slate-300 bg-white rounded-md hover:bg-slate-100 transition-colors shadow-sm">Prev</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <a href="<?= tr_qs(['page' => $p]) ?>"
                       class="px-3.5 py-1.5 text-sm rounded-md transition-colors shadow-sm
                              <?= $p === $page ? 'bg-blue-600 text-white font-bold border border-blue-700' : 'bg-white border border-slate-300 hover:bg-slate-100 text-slate-600' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= tr_qs(['page' => $page + 1]) ?>" class="px-3 py-1.5 text-sm border border-slate-300 bg-white rounded-md hover:bg-slate-100 transition-colors shadow-sm">Next</a>
                    <a href="<?= tr_qs(['page' => $totalPages]) ?>" class="px-3 py-1.5 text-sm border border-slate-300 bg-white rounded-md hover:bg-slate-100 transition-colors shadow-sm">&raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<div id="opens-modal" class="fixed z-50 floating-modal bg-slate-50 border border-slate-300 rounded-xl flex flex-col scale-95 opacity-0 pointer-events-none origin-top-left">
    
    <div id="opens-modal-header" class="bg-gradient-to-r from-slate-800 to-slate-900 px-4 py-3 cursor-move flex justify-between items-start rounded-t-xl select-none group">
        <div class="flex-1 pr-6">
            <div class="text-sm font-bold text-white mb-1.5 flex items-center gap-2">
                <span class="text-blue-400">👁️</span> Open Activity Log
            </div>
            <div id="modal-meta-recipients" class="text-[11px] text-slate-300 font-medium truncate"></div>
            <div id="modal-meta-subject" class="text-[11px] text-slate-400 leading-tight mt-0.5 truncate max-w-[400px]"></div>
        </div>
        <button id="opens-modal-close" class="text-slate-400 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-full w-6 h-6 flex items-center justify-center transition-colors absolute top-3 right-3">✕</button>
    </div>
    
    <div id="opens-modal-body" class="p-3 text-sm flex-1 overflow-y-auto bg-slate-50 max-h-[400px]">
        </div>
</div>

<!-- Heatmap Modal -->
<div id="heatmap-modal" class="fixed z-60 floating-modal bg-white border border-slate-300 rounded-xl flex flex-col scale-95 opacity-0 pointer-events-none origin-top-left">
    <div class="px-4 py-3 flex justify-between items-center bg-slate-100 rounded-t-xl">
        <div>
            <div id="heatmap-title" class="text-sm font-bold text-slate-800">Attachment Heatmap</div>
            <div id="heatmap-sub" class="text-xs text-slate-500">Loading...</div>
        </div>
        <div class="flex items-center gap-2">
            <button id="heatmap-play" class="px-3 py-1 rounded bg-emerald-600 text-white text-sm">Play</button>
            <button id="heatmap-pause" class="px-3 py-1 rounded bg-slate-200 text-slate-700 text-sm">Pause</button>
            <button id="heatmap-close" class="px-3 py-1 rounded bg-red-100 text-red-600 text-sm">Close</button>
        </div>
    </div>
    <div id="heatmap-body" class="p-3 text-sm flex-1 overflow-y-auto bg-white max-h-[520px]">
        <div id="heatmap-controls" class="mb-3">
            <input id="heatmap-slider" type="range" min="0" max="100" value="0" class="w-full">
            <div class="text-xs text-slate-500 mt-1">Time: <span id="heatmap-time">--:--:--</span></div>
        </div>
        <div id="heatmap-list" class="space-y-3"></div>
    </div>
</div>

<script>
    (function(){
        const base = '<?= BASE_PATH ?>';
        const modal = document.getElementById('opens-modal');
        const body = document.getElementById('opens-modal-body');
        const header = document.getElementById('opens-modal-header');
        const metaRecipients = document.getElementById('modal-meta-recipients');
        const metaSubject = document.getElementById('modal-meta-subject');
        
        let activeAbortController = null;
        let hoverTimeout = null;
        
        // Formatter for timestamps
        function fmt(ts){
            const d = new Date(ts*1000);
            return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric'}) + ' • ' + 
                   d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }

        // Function for Mark as Closed modal
        window.openCloseModal = function(customId, trackingId) {
            const displayId = customId ? `Track ID: <b>${customId}</b>` : (trackingId ? `ID: <b>${trackingId}</b>` : '');
            Swal.fire({
                title: 'Mark as Closed',
                html: `
                    <div class="text-left">
                        <p class="text-sm text-slate-500 mb-4">${displayId ? 'Marking ' + displayId + ' as closed.' : 'Marking this email entry as closed.'}</p>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Closing Date *</label>
                        <input type="date" id="close-date" class="w-full border border-slate-300 rounded p-2 mb-3 text-sm" value="${new Date().toISOString().split('T')[0]}" required>
                        
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Remark (Optional)</label>
                        <textarea id="close-remark" class="w-full border border-slate-300 rounded p-2 text-sm" rows="3" placeholder="e.g. Contract signed, deal closed..."></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Mark Closed',
                confirmButtonColor: '#2563eb',
                preConfirm: () => {
                    const date = document.getElementById('close-date').value;
                    const remark = document.getElementById('close-remark').value;
                    if (!date) {
                        Swal.showValidationMessage('Closing date is required');
                        return false;
                    }
                    return { customId, trackingId, date, remark };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = new URLSearchParams();
                    if (result.value.customId) data.append('custom_id', result.value.customId);
                    if (result.value.trackingId) data.append('tracking_id', result.value.trackingId);
                    data.append('closed_date', result.value.date);
                    data.append('remark', result.value.remark);

                    fetch(base + '/tracking/close', {
                        method: 'POST',
                        body: data
                    })
                    .then(res => res.json())
                    .then(json => {
                        if (json.ok) {
                            Swal.fire('Closed!', json.message, 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Error', json.message || 'Something went wrong', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', err.message, 'error');
                    });
                }
            });
        };

        window.markBounced = function(trackingId) {
            Swal.fire({
                title: 'Mark as Bounced?',
                text: 'Are you sure you want to mark this email as bounced? It will be excluded from Auto Resend Scheduler and reminders.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, mark bounced'
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = new URLSearchParams();
                    data.append('tracking_id', trackingId);

                    fetch(base + '/tracking/bounced', {
                        method: 'POST',
                        body: data
                    })
                    .then(res => res.json())
                    .then(json => {
                        if (json.ok) {
                            Swal.fire('Marked!', json.message, 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Error', json.message || 'Something went wrong', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', err.message, 'error');
                    });
                }
            });
        };

        // Skeleton Loader HTML
        const skeletonHTML = `
            <div class="space-y-3 p-1">
                ${[1,2,3].map(() => `
                <div class="border border-slate-200 bg-white rounded-lg p-3 shadow-sm flex flex-col gap-2 animate-pulse-fast">
                    <div class="h-4 bg-slate-200 rounded w-1/2"></div>
                    <div class="h-3 bg-slate-100 rounded w-3/4"></div>
                </div>`).join('')}
            </div>
        `;

        // Core open function
        async function openTrackingModal(tid, btnRect) {
            // Cancel any pending fetch request if user hovered another button quickly
            if (activeAbortController) activeAbortController.abort();
            activeAbortController = new AbortController();

            // Set Loading UI
            body.innerHTML = skeletonHTML;
            metaRecipients.textContent = 'Loading recipient info...';
            metaSubject.textContent = '';
            
            // Positioning Logic (Mobile fallback vs Desktop Floating)
            const isMobile = window.innerWidth < 640;
            if (isMobile) {
                // Center fixed overlay style for mobile
                modal.style.top = '50%';
                modal.style.left = '50%';
                modal.style.transform = 'translate(-50%, -50%) scale(1)';
                modal.style.width = '90vw';
                modal.style.height = 'auto';
                modal.style.maxHeight = '80vh';
            } else {
                // Floating near the button for desktop
                let leftPos = btnRect.right + 15;
                let topPos = btnRect.top - 20;
                
                // Screen bounds collision detection
                if (leftPos + 380 > window.innerWidth) leftPos = btnRect.left - 390; 
                if (topPos + 300 > window.innerHeight) topPos = window.innerHeight - 320;
                if (topPos < 10) topPos = 10;
                
                modal.style.left = leftPos + 'px';
                modal.style.top = topPos + 'px';
                modal.style.transform = 'scale(1)';
                modal.style.width = '360px'; // default desktop width
            }

            modal.classList.remove('opacity-0', 'pointer-events-none');

            try {
                const res = await fetch(base + '/tracking/opens?tid=' + encodeURIComponent(tid), {
                    signal: activeAbortController.signal
                });
                const json = await res.json();
                
                if (!json.ok) throw new Error(json.message || 'Error fetching data');

                // Update Header Meta
                if (json.meta) {
                    metaRecipients.textContent = 'To: ' + (json.meta.recipients || 'Unknown');
                    metaSubject.textContent = 'Sub: ' + (json.meta.subject || 'No Subject');
                }

                // Render Events (email opens + attachment opens)
                const hasEmail = json.data && json.data.length;
                const hasAttach = json.attachments && json.attachments.length;
                if (!hasEmail && !hasAttach) {
                    body.innerHTML = '<div class="text-slate-500 py-6 text-center text-sm font-medium">No open events recorded yet.</div>';
                    return;
                }

                body.innerHTML = '';

                if (!hasEmail && hasAttach) {
                    const notice = document.createElement('div');
                    notice.className = 'p-2.5 mb-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-xs font-semibold text-emerald-800 flex items-center gap-2';
                    notice.innerHTML = '<span>📎</span> Document opened by recipient (Email marked as opened)';
                    body.appendChild(notice);
                }

                if (hasEmail) {
                    const list = document.createElement('div');
                    list.className = 'space-y-2';
                    json.data.forEach((ev, idx) => {
                        const el = document.createElement('div');
                        el.className = 'p-3 border border-slate-200 bg-white rounded-lg shadow-sm hover:border-blue-300 transition-colors relative';
                        const badge = idx === 0 ? `<span class="absolute top-3 right-3 text-[9px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-bold uppercase">Latest</span>` : '';
                        const sourceText = ev.is_internal == 1 ? 'Internal / Test mailbox' : 'Customer mailbox';
                        const sourceClass = ev.is_internal == 1 ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200';
                        const recipientText = ev.recipient_email ? ev.recipient_email : json.meta.recipients;
                        el.innerHTML = `
                            ${badge}
                            <div class="font-bold text-slate-800 text-xs mb-1 text-blue-900">${fmt(ev.opened_at)}</div>
                            <div class="mb-2"><span class="inline-block px-2 py-0.5 rounded-full text-[9px] font-bold uppercase ${sourceClass}">${sourceText}</span></div>
                            <div class="text-[11px] text-slate-500 flex flex-col gap-0.5 mt-1">
                                <span class="flex items-center gap-1"><b class="text-slate-700">Recipient:</b> ${recipientText}</span>
                                <span class="flex items-center gap-1"><b class="text-slate-700">IP:</b> ${ev.ip || 'Hidden'}</span>
                                <span class="flex items-start gap-1"><b class="text-slate-700">Device:</b> <span class="break-words text-slate-400 leading-tight">${ev.user_agent || 'Unknown Client'}</span></span>
                            </div>
                        `;
                        list.appendChild(el);
                    });
                    body.appendChild(list);
                }

                if (hasAttach) {
                    const sec = document.createElement('div');
                    sec.className = 'mt-3 space-y-2';
                    const header = document.createElement('div');
                    header.className = 'text-xs font-semibold text-slate-600';
                    header.textContent = 'Attachment Activity';
                    sec.appendChild(header);

                    // Render per-attachment summary if available
                    if (json.attachment_summary && json.attachment_summary.length) {
                        // build page map for quick lookup
                        const pageMap = {};
                        (json.attachment_page_summary || []).forEach(p => {
                            if (!pageMap[p.token]) pageMap[p.token] = [];
                            pageMap[p.token].push(p);
                        });

                        const sumWrap = document.createElement('div');
                        sumWrap.className = 'space-y-2 mb-2';
                        json.attachment_summary.forEach(s => {
                            const item = document.createElement('div');
                            item.className = 'p-2 border border-slate-100 bg-slate-50 rounded text-[12px]';
                            const left = document.createElement('div');
                            left.innerHTML = `<div class="font-medium text-slate-800">${s.filename || s.token}</div><div class="text-slate-500 text-xs">${s.events} events</div>`;
                            const right = document.createElement('div');
                            const secs = s.total_ms ? Math.round((s.total_ms || 0)/1000) : 0;
                            right.innerHTML = `<div class="text-slate-700 font-bold">${secs}s</div><div class="text-slate-400 text-xs">total view</div>`;
                            const row = document.createElement('div');
                            row.className = 'flex items-center justify-between gap-3';
                            const leftWrap = document.createElement('div'); leftWrap.appendChild(left);
                            const rightWrap = document.createElement('div'); rightWrap.appendChild(right);
                            row.appendChild(leftWrap); row.appendChild(rightWrap);
                            item.appendChild(row);

                            // pages toggle
                            const pages = pageMap[s.token] || [];
                            if (pages.length) {
                            const row = document.createElement('div');
                            row.className = 'flex items-center justify-between gap-2';
                            const toggle = document.createElement('button');
                            toggle.className = 'mt-2 inline-block text-xs text-blue-600 hover:underline';
                            toggle.textContent = 'Show page breakdown';
                            const heatBtn = document.createElement('button');
                            heatBtn.type = 'button';
                            heatBtn.className = 'mt-2 inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200';
                            heatBtn.textContent = 'Token heatmap';
                            heatBtn.addEventListener('click', () => openHeatmap(tid, s.token));
                            const details = document.createElement('div');
                            details.className = 'mt-2 hidden space-y-1 text-[12px]';
                            pages.forEach(p => {
                                const psecs = p.total_ms ? Math.round(p.total_ms/1000) : 0;
                                const el = document.createElement('div');
                                el.textContent = 'Page ' + p.page + ' — ' + p.events + ' events • ' + psecs + 's';
                                details.appendChild(el);
                            });
                            toggle.addEventListener('click', () => {
                                const shown = details.classList.contains('hidden') === false;
                                details.classList.toggle('hidden', shown);
                                toggle.textContent = shown ? 'Show page breakdown' : 'Hide page breakdown';
                            });
                            row.appendChild(toggle);
                            row.appendChild(heatBtn);
                            item.appendChild(row);
                            }

                            sumWrap.appendChild(item);
                        });
                        sec.appendChild(sumWrap);
                    }

                    json.attachments.forEach((a, idx) => {
                        const ae = document.createElement('div');
                        ae.className = 'p-3 border border-slate-200 bg-white rounded-lg shadow-sm';
                        const when = a.opened_at ? fmt(a.opened_at) : 'Unknown time';
                        const who = a.recipient_email || json.meta.recipients;
                        const event = a.event_type || 'open';
                        const page = a.page ? 'Page ' + a.page : '';
                        const dur = a.duration_ms ? Math.round(a.duration_ms / 1000) + 's' : '';
                        const docName = a.name || 'Document';
                        
                        let playBtn = '';
                        if (a.url) {
                            const isVideo = a.url.match(/\.(mp4|webm|ogg)$/i) || a.url.includes('youtube.com') || a.url.includes('vimeo.com');
                            const safeUrl = a.url.replace(/'/g, "\\'");
                            playBtn = `<button type="button" onclick="window.playSessionReplay('${base}/Viewer/api.php?token=${a.token}')" class="replay-btn-dynamic mt-2 inline-flex items-center gap-1 text-[10px] px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 uppercase font-bold" data-replay-token="${a.token}">
                                ▶ Play Replay <span class="replay-duration text-[9px] text-emerald-500 font-normal normal-case ml-1"></span>
                            </button>`;
                        }

                        ae.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-xs text-slate-700 font-bold mb-1">${when} <span class="text-slate-400 font-normal">${event} ${page ? '• ' + page : ''} ${dur ? '• ' + dur : ''}</span></div>
                                    <div class="text-[11px] text-slate-500 mb-1"><b class="text-slate-700">Document:</b> ${docName}</div>
                                    <div class="text-[11px] text-slate-500"><b class="text-slate-700">Recipient:</b> ${who}</div>
                                </div>
                                <div>${playBtn}</div>
                            </div>
                        `;
                        sec.appendChild(ae);
                    });
                    body.appendChild(sec);

                    // Fetch duration for dynamically created replay buttons
                    sec.querySelectorAll('.replay-btn-dynamic[data-replay-token]').forEach(btn => {
                        const token = btn.getAttribute('data-replay-token');
                        if (!token) return;
                        fetch(base + '/Viewer/api.php?token=' + encodeURIComponent(token))
                            .then(r => r.json())
                            .then(evts => {
                                const durationStr = window._getSessionDuration ? window._getSessionDuration(evts) : null;
                                if (!durationStr) return;
                                const span = btn.querySelector('.replay-duration');
                                if (span) span.textContent = '(' + durationStr + ')';
                            }).catch(() => {});
                    });
                }
                
            } catch (err) {
                if (err.name !== 'AbortError') {
                    body.innerHTML = `<div class="p-4 bg-red-50 text-red-600 rounded-lg border border-red-100 text-center text-sm">${err.message}</div>`;
                }
            }
        }

        // Attach Event Listeners to Buttons
        document.querySelectorAll('.open-list-btn').forEach(btn => {
            const tid = btn.getAttribute('data-tid');
            
            // HOVER INTENT: Only open if mouse stays on button for 300ms
            btn.addEventListener('mouseenter', (e) => {
                const rect = btn.getBoundingClientRect();
                hoverTimeout = setTimeout(() => openTrackingModal(tid, rect), 300);
            });
            
            btn.addEventListener('mouseleave', () => {
                clearTimeout(hoverTimeout);
            });

            // CLICK fallback (instant open & touch device support)
            btn.addEventListener('click', (e) => {
                clearTimeout(hoverTimeout);
                openTrackingModal(tid, btn.getBoundingClientRect());
            });
        });

        // Heatmap modal logic
        const heatmapModal = document.getElementById('heatmap-modal');
        const heatmapBody = document.getElementById('heatmap-body');
        const heatmapList = document.getElementById('heatmap-list');
        const heatmapTitle = document.getElementById('heatmap-title');
        const heatmapSub = document.getElementById('heatmap-sub');
        const heatmapSlider = document.getElementById('heatmap-slider');
        const heatmapTime = document.getElementById('heatmap-time');
        const playBtn = document.getElementById('heatmap-play');
        const pauseBtn = document.getElementById('heatmap-pause');
        const closeBtn = document.getElementById('heatmap-close');

        let heatmapData = null;
        let playTimer = null;
        let playStart = 0, playNow = 0, playDuration = 0;

        function closeHeatmap() {
            heatmapModal.classList.add('opacity-0', 'pointer-events-none');
            if (playTimer) { clearInterval(playTimer); playTimer = null; }
        }

        closeBtn.addEventListener('click', closeHeatmap);

        async function openHeatmap(tid, selectedToken = null) {
            heatmapList.innerHTML = '<div class="text-sm text-slate-500">Loading events</div>';
            heatmapTitle.textContent = 'Attachment Heatmap — ' + tid.slice(0,10) + '…';
            heatmapSub.textContent = '';
            heatmapModal.classList.remove('opacity-0', 'pointer-events-none');

            try {
                const res = await fetch(base + '/tracking/opens?tid=' + encodeURIComponent(tid));
                const json = await res.json();
                if (!json.ok) throw new Error(json.message || 'Failed to fetch');

                // Build per-token timelines
                const tokens = {};
                (json.attachments || []).forEach(ev => {
                    if (!tokens[ev.token]) tokens[ev.token] = {events: [], filename: ''};
                    tokens[ev.token].events.push(ev);
                });
                (json.attachment_summary || []).forEach(s => { if (tokens[s.token]) tokens[s.token].filename = s.filename; });

                // Flatten and determine timeline bounds
                let minT = Infinity, maxT = 0;
                Object.values(tokens).forEach(t => t.events.forEach(e => { minT = Math.min(minT, e.opened_at || Infinity); maxT = Math.max(maxT, e.opened_at || 0); }));
                if (!isFinite(minT)) { heatmapList.innerHTML = '<div class="text-sm text-slate-500">No attachment events.</div>'; return; }

                if (selectedToken) {
                    const tokenOnly = {};
                    if (tokens[selectedToken]) tokenOnly[selectedToken] = tokens[selectedToken];
                    Object.keys(tokens).forEach(token => { if (token !== selectedToken) delete tokens[token]; });
                }
                heatmapData = { tokens, minT, maxT };
                playDuration = Math.max(5, maxT - minT);
                playStart = minT;
                heatmapSlider.min = 0; heatmapSlider.max = playDuration; heatmapSlider.value = 0;

                if (selectedToken && tokens[selectedToken]) {
                    heatmapTitle.textContent = 'Attachment Heatmap — ' + (tokens[selectedToken].filename || selectedToken);
                }

                // Render list UI
                heatmapList.innerHTML = '';
                Object.keys(tokens).forEach(token => {
                    const wrap = document.createElement('div');
                    wrap.className = 'p-3 border border-slate-100 rounded';
                    wrap.innerHTML = `<div class="flex justify-between items-center"><div class="text-sm font-medium">${tokens[token].filename || token}</div><div class="text-xs text-slate-400">${tokens[token].events.length} events</div></div><div class="mt-2 h-6 bg-slate-100 rounded overflow-hidden relative"><div class="heat-fill" data-token="${token}" style="position:absolute;left:0;top:0;height:100%;width:0;background:linear-gradient(90deg,#fde68a,#f97316);"></div><div class="heat-ind" data-token="${token}" style="position:absolute;left:0;top:0;height:100%;width:2px;background:#111;opacity:.9"></div></div><div class="text-xs text-slate-500 mt-1"><span class="time-label" data-token="${token}">--:--:--</span></div>`;
                    heatmapList.appendChild(wrap);
                });

                // Update time label helper
                function fmtTs(ts){ const d = new Date((playStart + ts) * 1000); return d.toLocaleString(); }

                function renderAt(offset) {
                    // offset in seconds from playStart
                    heatmapTime.textContent = fmtTs(offset);
                    Object.keys(tokens).forEach(token => {
                        const evs = tokens[token].events;
                        // find last event <= playStart+offset
                        const at = playStart + offset;
                        let last = null;
                        for (let i=0;i<evs.length;i++){ if ((evs[i].opened_at||0) <= at) last = evs[i]; else break; }
                        const percent = last && last.percent ? Math.max(0, Math.min(100, last.percent)) : 0;
                        const fill = document.querySelector('.heat-fill[data-token="'+token+'"]');
                        const ind = document.querySelector('.heat-ind[data-token="'+token+'"]');
                        if (fill) fill.style.width = percent + '%';
                        if (ind) ind.style.left = percent + '%';
                        const label = document.querySelector('.time-label[data-token="'+token+'"]');
                        if (label) label.textContent = last ? new Date((last.opened_at||0)*1000).toLocaleString() : '--';
                    });
                }

                // Slider control
                heatmapSlider.addEventListener('input', (e) => { const v = Number(e.target.value); renderAt(v); });

                // Playback controls
                if (playTimer) clearInterval(playTimer);
                playNow = 0;
                playBtn.onclick = () => {
                    if (playTimer) return;
                    playTimer = setInterval(() => {
                        playNow += 1; if (playNow > playDuration) { clearInterval(playTimer); playTimer = null; return; }
                        heatmapSlider.value = playNow; renderAt(playNow);
                    }, 250);
                };
                pauseBtn.onclick = () => { if (playTimer) { clearInterval(playTimer); playTimer = null; } };

            } catch (err) {
                heatmapList.innerHTML = '<div class="text-sm text-red-600">' + (err.message||'Error') + '</div>';
            }
        }

        document.querySelectorAll('.heatmap-btn').forEach(btn => btn.addEventListener('click', (e) => { openHeatmap(btn.getAttribute('data-tid')); }));

        // Close logic
        function closeModal() {
            modal.style.transform = window.innerWidth < 640 ? 'translate(-50%, -50%) scale(0.95)' : 'scale(0.95)';
            modal.classList.add('opacity-0', 'pointer-events-none');
            if (activeAbortController) activeAbortController.abort();
        }
        
        document.getElementById('opens-modal-close').addEventListener('click', closeModal);
        // Also close on Escape key
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

        // ── Draggable Window Logic (Desktop Only) ──
        let isDragging = false, currentX, currentY, initialX, initialY;

        header.addEventListener('mousedown', (e) => {
            // Disable drag on mobile view where it is centered
            if (window.innerWidth < 640 || e.target.id === 'opens-modal-close') return;
            
            const rect = modal.getBoundingClientRect();
            initialX = e.clientX - rect.left;
            initialY = e.clientY - rect.top;
            isDragging = true;
            header.classList.add('cursor-grabbing');
            
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', stopDrag);
        });

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();
            modal.style.left = (e.clientX - initialX) + 'px';
            modal.style.top = (e.clientY - initialY) + 'px';
            modal.style.transform = 'none'; // Clear any centering transforms applied
        }

        function stopDrag() {
            isDragging = false;
            header.classList.remove('cursor-grabbing');
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('mouseup', stopDrag);
        }
    })();

    // Global callback details data & popup
    const _callbackData = <?= json_encode($rowCallbacks, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    window.showCallbackDetails = function(tid) {
        const callbacks = _callbackData[tid] || [];
        if (!callbacks.length) return;

        let html = '<div style="text-align:left;max-height:400px;overflow-y:auto;">';
        callbacks.forEach(cb => {
            const statusColors = { pending:'#fef3c7', confirmed:'#dbeafe', completed:'#dcfce7', cancelled:'#fee2e2' };
            const bg = statusColors[cb.status] || '#f1f5f9';
            const date = new Date(cb.created_at * 1000).toLocaleString();
            html += `<div style="background:${bg};border-radius:8px;padding:12px;margin-bottom:8px;border:1px solid #e2e8f0;">
                <div style="font-weight:700;font-size:13px;margin-bottom:4px;">${cb.recipient_name || 'Unknown'} <span style="font-size:10px;text-transform:uppercase;opacity:0.7;">${cb.status}</span></div>
                <div style="font-size:12px;color:#334155;">📱 <b>${cb.callback_number}</b></div>
                <div style="font-size:12px;color:#334155;">📅 ${cb.schedule_date} at ${cb.schedule_time} <b>${cb.timezone}</b></div>
                ${cb.notes ? '<div style="font-size:11px;color:#64748b;margin-top:4px;">Notes: ' + cb.notes + '</div>' : ''}
                <div style="font-size:10px;color:#94a3b8;margin-top:4px;">Submitted: ${date}</div>
            </div>`;
        });
        html += '</div>';

        Swal.fire({
            title: '📞 Callback Requests',
            html: html,
            width: 420,
            showCloseButton: true,
            showConfirmButton: false,
        });
    };

    // Global replay modal
    window.playSessionReplay = async function(url) {
        let modal = document.getElementById('preview-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'preview-modal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/80 hidden';
modal.innerHTML = `
    <div class="bg-white rounded-lg shadow-xl flex flex-col overflow-hidden"
         style="width: 900px; max-width: 95vw; max-height: 90vh; height: 90vh">

        <div class="flex justify-between items-center p-3 border-b border-slate-200 bg-slate-50 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="font-bold text-slate-700 text-sm">Session Replay</div>
                <span id="replay-duration" class="text-xs font-medium text-slate-500 bg-slate-200 px-2 py-0.5 rounded hidden"></span>
            </div>

            <button type="button"
                onclick="document.getElementById('preview-modal').classList.add('hidden'); document.getElementById('preview-content').innerHTML = ''; document.getElementById('replay-duration').classList.add('hidden');"
                class="text-slate-400 hover:text-slate-700">
                &times;
            </button>
        </div>

        <div id="preview-content"
             class="flex-1 min-h-0 bg-slate-100 overflow-auto flex items-center justify-center relative">
        </div>

    </div>
`;


            document.body.appendChild(modal);
            
            // Close on background click
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.classList.add('hidden');
        document.getElementById('preview-content').innerHTML = '';
        document.getElementById('replay-duration').classList.add('hidden');
    }
});

// Prevent the backdrop click event from bubbling up to the document listener
// This prevents the modal from closing twice when clicking the backdrop
modal.addEventListener('click', (e) => {
    e.stopPropagation();
});

// Close Session Replay with ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        modal.classList.add('hidden');
        document.getElementById('preview-content').innerHTML = '';
        document.getElementById('replay-duration').classList.add('hidden');
    }
});
        }
        
        modal.classList.remove('hidden');
        const content = document.getElementById('preview-content');
        content.innerHTML = '<h3 class="text-slate-500 font-medium">Loading replay data...</h3>';

        try {
            const response = await fetch(url);
            const events = await response.json();

            if(events.length < 2) {
                content.innerHTML = '<h3 class="text-red-500 font-medium">Not enough data recorded.</h3>';
                return;
            }
            
            let startIndex = 0;
            for (let i = events.length - 1; i >= 0; i--) {
                if (events[i].type === 4) { 
                    let j = i;
                    while (j > 0 && (events[j-1].type === 0 || events[j-1].type === 1)) j--;
                    startIndex = j;
                    break;
                }
            }
            const sessionEvents = events.slice(startIndex);

            // Calculate total duration from events
            const firstTs = sessionEvents[0].timestamp;
            const lastTs = sessionEvents[sessionEvents.length - 1].timestamp;
            const totalMs = lastTs - firstTs;
            const totalSec = Math.floor(totalMs / 1000);
            const mins = Math.floor(totalSec / 60);
            const secs = totalSec % 60;
            const durationStr = mins > 0 ? mins + 'm ' + secs + 's' : secs + 's';

            // Show duration badge
            const durationBadge = document.getElementById('replay-duration');
            durationBadge.textContent = '⏱ Total: ' + durationStr;
            durationBadge.classList.remove('hidden');

            content.innerHTML = '';
            new rrwebPlayer({
                target: content,
                props: { events: sessionEvents, autoPlay: true, width: 900, height: 600 }
            });
        } catch (err) {
            content.innerHTML = '<h3 class="text-red-500 font-medium">Error loading replay file.</h3>';
        }
    };
</script>

<!-- Activity Feed Drawer -->
<div id="activity-drawer" class="fixed inset-y-0 right-0 w-80 bg-white shadow-2xl border-l border-slate-200 transform translate-x-full transition-transform duration-300 z-50 flex flex-col">
    <div class="bg-slate-900 text-white px-4 py-3 flex justify-between items-center shadow-md">
        <h3 class="font-bold flex items-center gap-2 text-sm"><span class="text-blue-400">⚡</span> Live Activity</h3>
        <div class="flex items-center gap-1">
            <button id="activity-refresh" class="p-1.5 hover:bg-slate-800 rounded transition-colors text-slate-300 hover:text-white" title="Refresh">↻</button>
            <button id="activity-close" class="p-1.5 hover:bg-slate-800 rounded transition-colors text-slate-300 hover:text-white" title="Close">✕</button>
        </div>
    </div>
    <div id="activity-list" class="flex-1 overflow-y-auto p-4 bg-slate-50 space-y-3">
        <!-- JS will populate -->
        <div class="text-slate-400 text-xs text-center py-4">Click refresh to load...</div>
    </div>
</div>

<script>
    (function() {
        const toggle = document.getElementById('activity-toggle');
        const close = document.getElementById('activity-close');
        const refresh = document.getElementById('activity-refresh');
        const drawer = document.getElementById('activity-drawer');
        const list = document.getElementById('activity-list');
        const base = '<?= BASE_PATH ?>';

        function fmt(ts) {
            const d = new Date(ts * 1000);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function toggleDrawer() {
            const isClosed = drawer.classList.contains('translate-x-full');
            if (isClosed) {
                drawer.classList.remove('translate-x-full');
                fetchActivity();
            } else {
                drawer.classList.add('translate-x-full');
            }
        }

        async function fetchActivity() {
            list.innerHTML = '<div class="text-slate-400 text-xs text-center py-4">Loading...</div>';
            refresh.classList.add('animate-spin');
            
            try {
                const res = await fetch(base + '/mail/activity');
                const json = await res.json();
                
                if (json.data && json.data.length > 0) {
                    list.innerHTML = '';
                    json.data.forEach(ev => {
                        const icon = ev.type === 'email' ? '✉️' : '📎';
                        const el = document.createElement('div');
                        el.className = 'p-3 bg-white border border-slate-200 rounded-lg shadow-sm hover:border-blue-300 transition-colors';
                        el.innerHTML = `
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-[10px] bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded font-bold uppercase">${icon} ${ev.type}</span>
                                <span class="text-[10px] font-bold text-slate-400">${fmt(ev.opened_at)}</span>
                            </div>
                            <div class="text-xs font-semibold text-slate-700 truncate" title="${ev.sent_to}">${ev.sent_to}</div>
                            <div class="text-[11px] text-slate-500 truncate" title="${ev.subject}">${ev.subject}</div>
                            ${ev.type === 'attachment' ? `<div class="text-[10px] text-slate-400 mt-1 truncate">Doc: ${ev.filename}</div>` : ''}
                        `;
                        list.appendChild(el);
                    });
                } else {
                    list.innerHTML = '<div class="text-slate-400 text-xs text-center py-4">No recent activity.</div>';
                }
            } catch (e) {
                list.innerHTML = '<div class="text-red-400 text-xs text-center py-4">Failed to load.</div>';
            }
            setTimeout(() => refresh.classList.remove('animate-spin'), 300);
        }

        toggle.addEventListener('click', toggleDrawer);
        close.addEventListener('click', () => drawer.classList.add('translate-x-full'));
        refresh.addEventListener('click', fetchActivity);
    })();
</script>

<!-- Sent Email Detail Modal -->
<div id="email-detail-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">
        <!-- Header -->
        <div class="bg-slate-900 px-6 py-4 flex justify-between items-center flex-shrink-0 relative">
            <div>
                <h3 class="text-base font-bold text-white" id="edm-title">Sent Email Details</h3>
                <p class="text-xs text-slate-400 mt-0.5" id="edm-meta"></p>
            </div>
            <button type="button" onclick="closeEmailDetailModal()" class="text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-full w-8 h-8 flex items-center justify-center transition-colors">✕</button>
        </div>
        
        <!-- Content Area -->
        <div class="flex-1 flex flex-col md:flex-row overflow-hidden bg-slate-50">
            <!-- Sidebar: Studio Inputs Values -->
            <div class="w-full md:w-80 border-r border-slate-200 p-4 overflow-y-auto flex-shrink-0 bg-white">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Saved Studio Values</h4>
                <div class="space-y-3" id="edm-fields-container">
                    <!-- Fields populated dynamically -->
                </div>
            </div>
            
            <!-- Email Body Iframe Preview -->
            <div class="flex-1 flex flex-col p-4 overflow-hidden h-full">
                <div class="flex justify-between items-center mb-2 flex-shrink-0">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Exact Sent Copy</h4>
                    <button type="button" id="resend-reminder-btn" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Resend Reminder
                    </button>
                </div>
                <div class="flex-1 border border-slate-200 rounded-xl overflow-hidden bg-white shadow-inner relative">
                    <iframe id="edm-iframe" class="w-full h-full border-0 bg-white"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function closeEmailDetailModal() {
        document.getElementById('email-detail-modal').classList.add('hidden');
        document.getElementById('email-detail-modal').classList.remove('flex');
    }

    async function openEmailDetailModal(tid) {
        const modal = document.getElementById('email-detail-modal');
        const title = document.getElementById('edm-title');
        const meta = document.getElementById('edm-meta');
        const fieldsContainer = document.getElementById('edm-fields-container');
        const iframe = document.getElementById('edm-iframe');

        // Reset
        fieldsContainer.innerHTML = '<div class="text-slate-400 text-xs py-4 text-center">Loading fields...</div>';
        iframe.srcdoc = '';
        
        document.getElementById('resend-reminder-btn').setAttribute('data-tid', tid);

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            
            const res = await fetch('<?= BASE_PATH ?>/mail/details?tid=' + encodeURIComponent(tid), {
                signal: controller.signal
            });
            clearTimeout(timeout);
            
            if (res.status === 401) {
                fieldsContainer.innerHTML = '<div class="text-amber-600 text-xs py-4 text-center">Session expired. Please <a href="<?= BASE_PATH ?>/login" class="underline font-semibold">log in again</a>.</div>';
                iframe.srcdoc = '';
                return;
            }
            if (!res.ok) throw new Error('Server returned ' + res.status);
            
            const json = await res.json();
            
            if (!json.ok) throw new Error(json.message || 'Error fetching details');

            if (json.meta) {
                title.textContent = json.meta.subject || 'No Subject';
                
                // Formatter for timestamps
                const d = new Date(json.meta.sent_at * 1000);
                const dateStr = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric'}) + ' • ' + 
                               d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                               
                meta.textContent = `To: ${json.meta.recipients || 'Unknown'} • Sent: ${dateStr}`;
                
                // Render email body in iframe
                document.getElementById('resend-reminder-btn').setAttribute('data-email', json.meta.recipients || '');
                if (json.meta.body) {
                    iframe.srcdoc = json.meta.body;
                } else {
                    iframe.srcdoc = '<html><body style="font-family:sans-serif;color:#64748b;padding:20px;text-align:center;">No HTML copy stored for this email.</body></html>';
                }

                // Render studio fields
                let fieldsData = {};
                if (json.meta.studio_fields) {
                    try {
                        fieldsData = JSON.parse(json.meta.studio_fields);
                    } catch(e) {}
                }

                const labels = {
                    'Hotel_Name': 'Hotel Name',
                    'Address': 'Address',
                    'City': 'City',
                    'State': 'State',
                    'Zip': 'Zip',
                    'Rooms': '# of Rooms',
                    'Email': 'Contact Email',
                    'Hotel_Number': 'Hotel Phone',
                    'Contact_Person': 'Contact Person',
                    'Service_Provider': 'Service Provider',
                    'offeredProgramming': 'Programming',
                    'System': 'System',
                    'Bill': 'Monthly Bill ($)',
                    'Upfront': 'Upfront Cost ($)'
                };

                fieldsContainer.innerHTML = '';
                let hasFields = false;
                for (const key in labels) {
                    const val = fieldsData[key] !== undefined ? fieldsData[key] : '—';
                    if (val !== '—') hasFields = true;
                    
                    const row = document.createElement('div');
                    row.className = 'border-b border-slate-100 pb-2';
                    row.innerHTML = `
                        <div class="text-[10px] font-bold text-slate-400 uppercase">${labels[key]}</div>
                        <div class="text-xs font-semibold text-slate-700 mt-0.5 break-words">${val || '—'}</div>
                    `;
                    fieldsContainer.appendChild(row);
                }

                if (!hasFields) {
                    fieldsContainer.innerHTML = '<div class="text-slate-400 text-xs py-4 text-center">No studio inputs saved for this email.</div>';
                }

                // Render resend logs
                if (fieldsData['resend_logs'] && Array.isArray(fieldsData['resend_logs'])) {
                    const logs = fieldsData['resend_logs'];
                    if (logs.length > 0) {
                        // Remove the "no fields" message if it was added
                        if (!hasFields) fieldsContainer.innerHTML = '';
                        hasFields = true;
                        
                        const row = document.createElement('div');
                        row.className = 'border-t border-slate-200 mt-4 pt-4';
                        let html = '<div class="text-[10px] font-bold text-indigo-500 uppercase mb-2">Reminder Logs</div><ul class="space-y-1.5">';
                        logs.forEach(log => {
                            html += `<li class="text-xs text-slate-600 flex items-start gap-2">
                                        <svg class="w-3.5 h-3.5 text-indigo-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        ${log}
                                     </li>`;
                        });
                        html += '</ul>';
                        row.innerHTML = html;
                        fieldsContainer.appendChild(row);
                    }
                }
            }
        } catch (err) {
            const msg = err.name === 'AbortError' 
                ? 'Request timed out. The server may be overloaded — try again.' 
                : err.message;
            fieldsContainer.innerHTML = `<div class="text-red-500 text-xs py-4 text-center">Error: ${msg}</div>`;
        }
    }

    // Attach click listener for .view-mail-btn
    document.querySelectorAll('.view-mail-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const tid = btn.getAttribute('data-tid');
            openEmailDetailModal(tid);
        });
    });

    // Resend reminder logic
    document.getElementById('resend-reminder-btn').addEventListener('click', async function() {
        const tid = this.getAttribute('data-tid');
        const currentEmail = this.getAttribute('data-email') || '';
        if (!tid) return;
        
        const result = await Swal.fire({
            title: 'Resend Reminder?',
            html: `
                <p class="text-sm text-slate-500 mb-4">This will immediately send another copy of the exact same email.</p>
                <div class="text-left">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contact Email *</label>
                    <input type="email" id="resend-email-input" class="w-full border border-slate-300 rounded p-2 text-sm" value="${currentEmail.replace(/"/g, '&quot;')}" required>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Send Now',
            preConfirm: () => {
                const newEmail = document.getElementById('resend-email-input').value.trim();
                if (!newEmail) {
                    Swal.showValidationMessage('Contact email is required');
                    return false;
                }
                return newEmail;
            }
        });
        
        if (result.isConfirmed) {
            const newEmail = result.value;
            Swal.fire({
                title: 'Sending...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            try {
                const formData = new FormData();
                formData.append('tid', tid);
                formData.append('new_email', newEmail);
                
                const response = await fetch('<?= BASE_PATH ?>/tracking/resend', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reminder Sent!',
                        text: data.message,
                        confirmButtonColor: '#4f46e5',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Failed', data.message || 'Error resending email.', 'error');
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }
    });
</script>

<!-- Real-Time Notifications (Polling — no server load) -->
<div id="sse-toast-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" style="max-width:380px;"></div>

<script>
(function() {
    const base = '<?= BASE_PATH ?>';
    const toastContainer = document.getElementById('sse-toast-container');
    let lastId = 0;
    let pollTimer = null;
    const POLL_INTERVAL = 10000; // 10 seconds

    // ── Request browser notification permission ───────────────────────────────
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // ── Show browser desktop notification ─────────────────────────────────────
    function showDesktopNotification(data) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notif = new Notification('Email Opened', {
                body: `${data.recipient_email} opened "${data.subject}"\n${data.opened_at_fmt}`,
                icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📬</text></svg>',
                tag: 'email-open-' + data.id,
                requireInteraction: false
            });
            // setTimeout(() => notif.close(), 6000);
            notif.onclick = function() { window.focus(); notif.close(); };
        }
    }

    // ── Show in-app toast notification ────────────────────────────────────────
    function showToast(data) {
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-white border border-slate-200 rounded-xl shadow-2xl p-4 flex items-start gap-3';
        toast.style.cssText = 'animation: slideIn 0.3s ease-out; transition: opacity 0.3s, transform 0.3s;';
        toast.innerHTML = `
            <div class="text-2xl flex-shrink-0 mt-0.5">📬</div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-emerald-600 uppercase tracking-wide">Email Opened</div>
                <div class="text-sm font-semibold text-slate-800 truncate mt-0.5">${data.recipient_email}</div>
                <div class="text-xs text-slate-500 truncate">${data.subject}</div>
                <div class="text-[10px] text-slate-400 mt-1">${data.opened_at_fmt}</div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-500 text-lg leading-none flex-shrink-0">&times;</button>
        `;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 8000);
    }

    // ── Play notification sound ───────────────────────────────────────────────
    function playNotificationSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } catch(e) {}
    }

    // ── Lightweight polling (replaces SSE) ────────────────────────────────────
    async function pollNotifications() {
        try {
            const res = await fetch(base + '/notifications/stream?last_id=' + lastId);
            if (!res.ok) return;
            const json = await res.json();
            if (!json.ok) return;

            lastId = json.last_id || lastId;

            if (json.data && json.data.length > 0) {
                json.data.forEach(item => {
                    showDesktopNotification(item);
                    showToast(item);
                });
                playNotificationSound();
            }
        } catch(e) {
            // Network error — silently retry next interval
        }
    }

    // ── Start polling ─────────────────────────────────────────────────────────
    pollNotifications(); // Initial call to get baseline lastId
    pollTimer = setInterval(pollNotifications, POLL_INTERVAL);

    // Pause when tab is hidden, resume when visible
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(pollTimer);
            pollTimer = null;
        } else {
            pollNotifications(); // Check immediately
            pollTimer = setInterval(pollNotifications, POLL_INTERVAL);
        }
    });

    // Cleanup on unload
    window.addEventListener('beforeunload', function() {
        if (pollTimer) clearInterval(pollTimer);
    });
})();
</script>

<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateX(100%); }
    to { opacity: 1; transform: translateX(0); }
}
</style>

<script>
// Fetch and display total duration on each Play Replay button
(function(){
    const base = '<?= BASE_PATH ?>';
    const buttons = document.querySelectorAll('.replay-btn[data-replay-token]');

    function getSessionDuration(events) {
        if (!Array.isArray(events) || events.length < 2) return null;
        // Use same session-slicing logic as the player: find last type:4 meta event
        let startIndex = 0;
        for (let i = events.length - 1; i >= 0; i--) {
            if (events[i].type === 4) {
                let j = i;
                while (j > 0 && (events[j-1].type === 0 || events[j-1].type === 1)) j--;
                startIndex = j;
                break;
            }
        }
        const sessionEvents = events.slice(startIndex);
        if (sessionEvents.length < 2) return null;
        const totalMs = sessionEvents[sessionEvents.length - 1].timestamp - sessionEvents[0].timestamp;
        const totalSec = Math.floor(totalMs / 1000);
        const mins = Math.floor(totalSec / 60);
        const secs = totalSec % 60;
        return mins > 0 ? mins + 'm ' + secs + 's' : secs + 's';
    }

    // Expose helper globally for dynamic buttons
    window._getSessionDuration = getSessionDuration;

    buttons.forEach(btn => {
        const token = btn.getAttribute('data-replay-token');
        if (!token) return;
        
        fetch(base + '/Viewer/api.php?token=' + encodeURIComponent(token))
            .then(res => res.json())
            .then(events => {
                const durationStr = getSessionDuration(events);
                if (!durationStr) return;
                const span = btn.querySelector('.replay-duration');
                if (span) span.textContent = '(' + durationStr + ')';
            })
            .catch(() => { /* silently fail */ });
    });
})();
</script>

<!-- Callback Requests Panel -->
<div id="callbacks-modal" class="fixed inset-0 bg-black/70 z-[10001] hidden items-center justify-center" style="display:none;">
    <div class="bg-white rounded-xl shadow-2xl w-[95%] max-w-4xl max-h-[85vh] flex flex-col overflow-hidden">
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
        <div id="cb-body" class="flex-1 overflow-y-auto p-5 bg-slate-50">
            <div class="text-center text-slate-400 py-10">Loading...</div>
        </div>
    </div>
</div>

<script>
(function(){
    const base = '<?= BASE_PATH ?>';
    const cbModal = document.getElementById('callbacks-modal');
    const cbBody = document.getElementById('cb-body');
    const cbCount = document.getElementById('cb-count');

    window.openCallbacksPanel = function() {
        cbModal.style.display = 'flex';
        loadCallbacks();
    };
    window.closeCallbacksPanel = function() {
        cbModal.style.display = 'none';
    };
    cbModal.addEventListener('click', (e) => { if (e.target === cbModal) closeCallbacksPanel(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && cbModal.style.display === 'flex') closeCallbacksPanel(); });

    window.copyCallbackLink = function() {
        const link = '<?= rtrim(APP_URL, "/") ?>/callback';
        navigator.clipboard.writeText(link).then(() => {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Callback form link copied!', showConfirmButton: false, timer: 2000 });
        });
    };

    async function loadCallbacks() {
        cbBody.innerHTML = '<div class="text-center text-slate-400 py-10">Loading...</div>';
        try {
            const res = await fetch(base + '/api/callbacks');
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Failed');
            const rows = json.data || [];
            cbCount.textContent = rows.length + ' total';
            if (rows.length === 0) {
                cbBody.innerHTML = '<div class="text-center py-16"><div class="text-4xl mb-3">📞</div><p class="text-slate-500 font-medium">No callback requests yet.</p></div>';
                return;
            }
            let html = '<div class="space-y-3">';
            rows.forEach(r => {
                const date = new Date(r.created_at * 1000).toLocaleString();
                const statusColors = { pending:'bg-yellow-100 text-yellow-800 border-yellow-200', confirmed:'bg-blue-100 text-blue-800 border-blue-200', completed:'bg-green-100 text-green-800 border-green-200', cancelled:'bg-red-100 text-red-800 border-red-200' };
                const statusClass = statusColors[r.status] || statusColors.pending;
                html += `
                <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm hover:border-blue-200 transition-colors">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-slate-800 text-sm">${esc(r.recipient_name || 'Unknown')}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full border font-bold uppercase ${statusClass}">${esc(r.status)}</span>
                            </div>
                            <div class="text-xs text-slate-500 space-y-0.5">
                                ${r.recipient_email ? '<div><b>Email:</b> ' + esc(r.recipient_email) + '</div>' : ''}
                                <div><b>Phone:</b> <span class="text-blue-700 font-medium">${esc(r.callback_number)}</span></div>
                                <div><b>Schedule:</b> ${esc(r.schedule_date)} at ${esc(r.schedule_time)} <span class="font-bold text-slate-700">${esc(r.timezone)}</span></div>
                                ${r.notes ? '<div><b>Notes:</b> ' + esc(r.notes) + '</div>' : ''}
                                ${r.tracking_id ? '<div class="text-slate-400"><b>Track ID:</b> ' + esc(r.tracking_id) + '</div>' : ''}
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
            cbBody.innerHTML = html;
        } catch (err) {
            cbBody.innerHTML = '<div class="text-center text-red-500 py-10">' + (err.message || 'Error') + '</div>';
        }
    }

    window.updateCbStatus = async function(id, status) {
        const data = new URLSearchParams(); data.append('id', id); data.append('status', status);
        const res = await fetch(base + '/api/callbacks/status', { method: 'POST', body: data });
        const json = await res.json();
        if (json.ok) loadCallbacks();
        else Swal.fire('Error', json.message || 'Update failed', 'error');
    };

    function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
})();
</script>

</body>
</html>