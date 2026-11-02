<?php
// db_viewer.php
declare(strict_types=1);
session_start();

// ── Login password (change this to whatever you want) ───────────────────────
define('DB_VIEWER_PASSWORD', 'admin123');

// ── Handle logout ────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION['db_viewer_auth']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Handle login form submission ─────────────────────────────────────────────
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_viewer_login'])) {
    if ($_POST['db_viewer_password'] === DB_VIEWER_PASSWORD) {
        $_SESSION['db_viewer_auth'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $loginError = 'Incorrect password. Please try again.';
    }
}

// ── Render login page if not authenticated ───────────────────────────────────
if (empty($_SESSION['db_viewer_auth'])) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Viewer — Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33%  { transform: translateY(-14px) rotate(2deg); }
            66%  { transform: translateY(-7px) rotate(-1deg); }
        }
        .float1 { animation: float 6s ease-in-out infinite; }
        .float2 { animation: float 8s ease-in-out 1.2s infinite; }
        .float3 { animation: float 7s ease-in-out 0.6s infinite; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center relative overflow-hidden"
      style="background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 45%, #0369a1 100%);">

    <!-- Decorative blobs -->
    <div class="float1 absolute top-10 left-10 w-72 h-72 rounded-full pointer-events-none"
         style="background:rgba(96,165,250,.18);filter:blur(48px);"></div>
    <div class="float2 absolute bottom-12 right-14 w-96 h-96 rounded-full pointer-events-none"
         style="background:rgba(56,189,248,.15);filter:blur(56px);"></div>
    <div class="float3 absolute top-1/2 left-1/3 w-48 h-48 rounded-full pointer-events-none"
         style="background:rgba(129,140,248,.14);filter:blur(40px);"></div>

    <div class="glass rounded-2xl shadow-2xl p-10 w-full max-w-sm border border-white/40 z-10">
        <!-- Icon -->
        <div class="flex justify-center mb-6">
            <div class="bg-blue-600 p-4 rounded-2xl shadow-lg shadow-blue-700/40">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7
                             M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4
                             M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4
                             m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                </svg>
            </div>
        </div>

        <h1 class="text-2xl font-bold text-slate-800 text-center mb-1">DB Viewer</h1>
        <p class="text-slate-500 text-sm text-center mb-7">Enter your password to continue</p>

        <?php if ($loginError !== ''): ?>
            <div class="mb-5 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3
                             L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <input type="hidden" name="db_viewer_login" value="1">

            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2"
                   for="db_pw">Password</label>
            <div class="relative mb-5">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6
                                 a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <input id="db_pw" type="password" name="db_viewer_password" required autofocus
                       class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-300 rounded-lg
                              focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500
                              transition bg-white/80"
                       placeholder="Enter password…">
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-semibold
                           py-2.5 rounded-lg transition-all duration-150 shadow-md hover:shadow-blue-500/40
                           flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 16l-4-4m0 0l4-4m-4 4h14"/>
                </svg>
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
    <?php
    exit;
}

// ── Autoloader (mirrors public/index.php) ──────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = __DIR__;
    $file = $base . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    if (file_exists($file)) require $file;
});

// ── Locate and load .env ────────────────────────────────────────────────────
// The .env may live in the app root or one directory above it (parent folder).
$envCandidates = [
    __DIR__ . '/.env',
    dirname(__DIR__) . '/.env',
];
$envPath = null;
foreach ($envCandidates as $candidate) {
    if (file_exists($candidate)) { $envPath = $candidate; break; }
}

if ($envPath === null) {
    die("Configuration error: .env file not found. Looked in:<br>" . implode('<br>', array_map('htmlspecialchars', $envCandidates)));
}

try {
    \App\Config\Env::load($envPath);
} catch (\Throwable $e) {
    die("Configuration error: " . htmlspecialchars($e->getMessage()));
}

// ── Connect to the database (creates + migrates if needed) ──────────────────
try {
    $db = \App\Core\Database::get();
} catch (\Throwable $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

$tablesQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
$tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);

$selectedTable = $_GET['table'] ?? ($tables[0] ?? null);

$columns = [];
$rows    = [];
$error   = null;

// Filter / sort inputs
$search   = trim((string)($_GET['q'] ?? ''));
$sortCol  = (string)($_GET['sort'] ?? '');
$sortDir  = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$rowLimit = 1000;

// ── Handle delete ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rowid'], $_POST['table_name'])) {
    $tableToEdit = $_POST['table_name'];
    $rowid = $_POST['delete_rowid'];
    if (in_array($tableToEdit, $tables)) {
        try {
            $stmt = $db->prepare("DELETE FROM " . $tableToEdit . " WHERE rowid = ?");
            $stmt->execute([$rowid]);
            header("Location: ?table=" . urlencode($tableToEdit));
            exit;
        } catch (Exception $e) {
            $error = "Delete failed: " . $e->getMessage();
        }
    }
}

/**
 * Fetch the column names for a table (independent of whether it has rows).
 */
function db_viewer_columns(PDO $db, string $table): array
{
    $pragma = $db->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC);
    return array_column($pragma, 'name');
}

// ── Handle CSV export ─────────────────────────────────────────────────────────
if (isset($_GET['export_csv']) && $selectedTable && in_array($selectedTable, $tables)) {
    $exportCols   = db_viewer_columns($db, $selectedTable);
    $exportSearch = trim((string)($_GET['q'] ?? ''));
    $exportParams = [];
    $exportWhere  = '';

    if ($exportSearch !== '' && !empty($exportCols)) {
        $likes = [];
        foreach ($exportCols as $c) {
            $likes[]        = '"' . str_replace('"', '""', $c) . '" LIKE ?';
            $exportParams[] = '%' . $exportSearch . '%';
        }
        $exportWhere = ' WHERE ' . implode(' OR ', $likes);
    }

    $exportSortCol = (string)($_GET['sort'] ?? '');
    $exportSortDir = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $validExpSort  = in_array($exportSortCol, $exportCols, true) ? $exportSortCol : null;
    $exportOrder   = $validExpSort !== null
        ? ' ORDER BY "' . str_replace('"', '""', $validExpSort) . '" ' . $exportSortDir
        : '';

    $exportSql  = 'SELECT * FROM "' . str_replace('"', '""', $selectedTable) . '"' . $exportWhere . $exportOrder;
    $exportStmt = $db->prepare($exportSql);
    $exportStmt->execute($exportParams);
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $selectedTable)
              . '_export_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly

    if (!empty($exportRows)) {
        fputcsv($out, array_keys($exportRows[0]));
        foreach ($exportRows as $r) {
            fputcsv($out, array_map(fn($v) => $v ?? '', $r));
        }
    } elseif (!empty($exportCols)) {
        fputcsv($out, $exportCols);
    }

    fclose($out);
    exit;
}

// ── Fetch table rows ──────────────────────────────────────────────────────────
if ($selectedTable && in_array($selectedTable, $tables)) {
    try {
        $columns   = db_viewer_columns($db, $selectedTable);
        $validSort = in_array($sortCol, $columns, true) ? $sortCol : null;

        $whereSQL = '';
        $params   = [];
        if ($search !== '' && !empty($columns)) {
            $likes = [];
            foreach ($columns as $c) {
                $likes[]  = '"' . str_replace('"', '""', $c) . '" LIKE ?';
                $params[] = '%' . $search . '%';
            }
            $whereSQL = ' WHERE ' . implode(' OR ', $likes);
        }

        $orderSQL = '';
        if ($validSort !== null) {
            $orderSQL = ' ORDER BY "' . str_replace('"', '""', $validSort) . '" ' . $sortDir;
        }

        $sql = 'SELECT rowid as _internal_rowid, * FROM "' . str_replace('"', '""', $selectedTable) . '"'
             . $whereSQL . $orderSQL . ' LIMIT ' . $rowLimit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $columns = array_values(array_filter($columns, fn($c) => $c !== '_internal_rowid'));
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * Build a query string preserving current table + filters, with overrides.
 */
function db_viewer_qs(array $override = []): string
{
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scroller { overflow-y: auto; scrollbar-width: thin; }
        .scroller::-webkit-scrollbar { width: 6px; height: 6px; }
        .scroller::-webkit-scrollbar-track { background: transparent; }
        .scroller::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
        /* CSV button hover shimmer */
        @keyframes shimmer {
            0%   { background-position: -200% center; }
            100% { background-position:  200% center; }
        }
        .btn-csv {
            background: linear-gradient(90deg, #059669, #10b981, #059669);
            background-size: 200% auto;
            transition: background-position .4s, box-shadow .2s, transform .1s;
        }
        .btn-csv:hover { background-position: right center; box-shadow: 0 4px 14px rgba(16,185,129,.4); }
        .btn-csv:active { transform: scale(.97); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- ── Sidebar ── -->
    <div class="w-64 bg-white border-r border-slate-200 flex flex-col h-full shadow-sm">
        <div class="p-4 border-b border-slate-200 bg-slate-100 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7
                         M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4
                         M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4
                         m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
            </svg>
            <h1 class="text-lg font-bold text-slate-800 flex-1">DB Viewer</h1>
            <!-- Logout -->
            <a href="?logout=1" title="Sign out"
               onclick="return confirm('Sign out of DB Viewer?')"
               class="text-slate-400 hover:text-red-500 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6
                             a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/>
                </svg>
            </a>
        </div>
        <div class="flex-1 overflow-y-auto scroller p-2 space-y-1">
            <?php foreach ($tables as $table): ?>
                <a href="?table=<?= urlencode($table) ?>"
                   class="block px-3 py-2 rounded-md text-sm font-medium transition-colors
                          <?= $selectedTable === $table
                              ? 'bg-blue-50 text-blue-700'
                              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 <?= $selectedTable === $table ? 'text-blue-500' : 'text-slate-400' ?>"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8
                                     a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <?= htmlspecialchars($table) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Main Content ── -->
    <div class="flex-1 flex flex-col h-full bg-white relative">
        <?php if ($selectedTable): ?>

            <!-- Toolbar -->
            <div class="p-4 border-b border-slate-200 bg-white flex flex-wrap justify-between items-center gap-3 z-10 shadow-sm">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8
                                 a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <?= htmlspecialchars($selectedTable) ?>
                    <span class="text-sm font-normal text-slate-500 ml-2">
                        (<?= count($rows) ?> rows<?= count($rows) === $rowLimit ? ', max' : '' ?><?= $search !== '' ? ', filtered' : '' ?>)
                    </span>
                </h2>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search form -->
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
                        <?php if ($sortCol !== ''): ?>
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sortCol) ?>">
                            <input type="hidden" name="dir"  value="<?= htmlspecialchars(strtolower($sortDir)) ?>">
                        <?php endif; ?>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/>
                                </svg>
                            </span>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search all columns…"
                                   class="pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg
                                          focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 w-56">
                        </div>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                            Search
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="<?= htmlspecialchars(db_viewer_qs(['q' => null])) ?>"
                               class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors border border-slate-200">
                                Clear
                            </a>
                        <?php endif; ?>
                    </form>

                    <!-- Export CSV button -->
                    <a href="<?= htmlspecialchars(db_viewer_qs(['export_csv' => '1'])) ?>"
                       title="Download all rows as CSV"
                       class="btn-csv inline-flex items-center gap-1.5 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2
                                     M7 10l5 5m0 0l5-5m-5 5V4"/>
                        </svg>
                        Export CSV
                    </a>
                </div>
            </div>

            <!-- Table area -->
            <div class="flex-1 overflow-auto scroller bg-slate-50 p-4">
                <?php if ($error): ?>
                    <div class="p-4 bg-red-50 text-red-700 border border-red-200 rounded-md">
                        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow border border-slate-200 overflow-hidden">
                        <div class="overflow-x-auto scroller">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-100 sticky top-0">
                                    <tr>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider
                                                   whitespace-nowrap border-b border-slate-200 bg-slate-50 w-16">
                                            Actions
                                        </th>
                                        <?php foreach ($columns as $col): ?>
                                            <?php
                                                $isSorted = ($sortCol === $col);
                                                $nextDir  = ($isSorted && $sortDir === 'ASC') ? 'desc' : 'asc';
                                                $arrow    = $isSorted ? ($sortDir === 'ASC' ? '▲' : '▼') : '⇅';
                                                $sortUrl  = db_viewer_qs(['sort' => $col, 'dir' => $nextDir]);
                                            ?>
                                            <th scope="col"
                                                class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider
                                                       whitespace-nowrap border-b border-slate-200 bg-slate-50">
                                                <a href="<?= htmlspecialchars($sortUrl) ?>"
                                                   class="group inline-flex items-center gap-1.5 hover:text-blue-700 transition-colors">
                                                    <?= htmlspecialchars($col) ?>
                                                    <span class="text-[10px] <?= $isSorted ? 'text-blue-600' : 'text-slate-300 group-hover:text-slate-400' ?>">
                                                        <?= $arrow ?>
                                                    </span>
                                                </a>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-200">
                                    <?php if (count($rows) === 0): ?>
                                        <tr>
                                            <td colspan="<?= count($columns) + 1 ?>"
                                                class="px-6 py-10 text-center text-slate-500 text-sm">
                                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6
                                                             a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414
                                                             a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414
                                                             A1 1 0 006.586 13H4"/>
                                                </svg>
                                                No data found in table "<?= htmlspecialchars($selectedTable) ?>"
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $row): ?>
                                            <tr class="hover:bg-blue-50 transition-colors">
                                                <td class="px-4 py-2 text-sm text-slate-700 whitespace-nowrap border-r border-slate-100">
                                                    <?php if (isset($row['_internal_rowid'])): ?>
                                                        <form method="POST"
                                                              onsubmit="return confirm('Are you sure you want to delete this row?');"
                                                              class="inline">
                                                            <input type="hidden" name="table_name"   value="<?= htmlspecialchars($selectedTable) ?>">
                                                            <input type="hidden" name="delete_rowid" value="<?= htmlspecialchars((string)$row['_internal_rowid']) ?>">
                                                            <button type="submit" title="Delete"
                                                                    class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100
                                                                           p-1 rounded transition-colors focus:outline-none">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862
                                                                             a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6
                                                                             m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-slate-300 text-xs italic">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php foreach ($columns as $col): ?>
                                                    <td class="px-4 py-2 text-sm text-slate-700 whitespace-nowrap max-w-xs truncate"
                                                        title="<?= htmlspecialchars((string)($row[$col] ?? '')) ?>">
                                                        <?php
                                                            $val = $row[$col] ?? null;
                                                            if ($val === null) {
                                                                echo '<span class="text-slate-400 italic">NULL</span>';
                                                            } else {
                                                                echo htmlspecialchars((string)$val);
                                                            }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="flex-1 flex items-center justify-center bg-slate-50">
                <div class="text-center text-slate-500">
                    <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7
                                 M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4
                                 M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4
                                 m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                    <p class="text-lg">Select a table from the sidebar</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
