<?php
function fmt_time(int $ts): string { return date('Y-m-d H:i:s', $ts); }
function time_ago(int $ts): string {
    $d = time() - $ts;
    if ($d < 60)    return $d . 's ago';
    if ($d < 3600)  return round($d/60) . 'm ago';
    if ($d < 86400) return round($d/3600) . 'h ago';
    return round($d/86400) . 'd ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Proposal Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen font-sans text-slate-800">
    <header class="bg-slate-900 text-white p-3 flex justify-between items-center shadow">
        <h1 class="font-bold text-base">🛡️ Admin Panel</h1>
        <div class="flex gap-2">
            <a href="<?= BASE_PATH ?>/studio" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded">← App</a>
            <a href="<?= BASE_PATH ?>/logout" class="text-xs bg-red-700 hover:bg-red-600 px-3 py-1.5 rounded">Logout</a>
        </div>
    </header>

    <main class="max-w-6xl mx-auto p-4 space-y-4">
        <?php $tab = $tab ?? 'sessions'; ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-2 flex flex-wrap gap-2">
            <a href="?tab=sessions" class="px-3 py-2 text-xs font-semibold rounded-md <?= $tab === 'sessions' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Active & Past Sessions</a>
            <a href="?tab=login-attempts" class="px-3 py-2 text-xs font-semibold rounded-md <?= $tab === 'login-attempts' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Login Attempts</a>
            <a href="?tab=login-users" class="px-3 py-2 text-xs font-semibold rounded-md <?= $tab === 'login-users' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Login Users</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($info)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg p-3"><?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <?php if (!$adminOk): ?>
            <div class="bg-white rounded-xl shadow p-6 max-w-sm mx-auto mt-12">
                <div class="text-center mb-4">
                    <div class="text-3xl">🔐</div>
                    <h2 class="font-bold text-slate-800 mt-1">Admin Verification Required</h2>
                    <p class="text-xs text-slate-500">OTP required every time you open this panel.</p>
                </div>

                <?php if (empty($_SESSION['admin_otp_pending'])): ?>
                    <form method="POST">
                        <button type="submit" name="admin_send_otp" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg text-sm">
                            Send OTP to Email →
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="space-y-3">
                        <input type="text" name="otp" required maxlength="6" pattern="\d{6}" autofocus class="w-full px-3 py-2 border border-slate-300 rounded-lg text-center tracking-widest text-lg font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="000000">
                        <button type="submit" name="admin_verify_otp" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg text-sm">
                            Verify OTP ✓
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow p-4 flex items-center justify-between">
                <div>
                    <div class="font-bold text-sm text-slate-700">📧 Email Tracking</div>
                    <div class="text-xs text-slate-500 mt-0.5">View send history, open rates, and export CSV</div>
                </div>
                <a href="<?= BASE_PATH ?>/tracking" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded">Open Tracking →</a>
            </div>

            <?php if ($tab === 'sessions'): ?>
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="p-3 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap">
                        <h2 class="font-bold text-sm text-slate-700">Active & Past Sessions</h2>
                        <div class="flex items-center gap-2 flex-wrap">
                            <form method="GET" class="flex items-end gap-2 flex-wrap">
                                <input type="hidden" name="tab" value="sessions">
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 mb-1">From</label>
                                    <input type="date" name="session_date_from" value="<?= htmlspecialchars($sessionDateFrom ?? '') ?>" class="text-xs px-2 py-1.5 border border-slate-300 rounded bg-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 mb-1">To</label>
                                    <input type="date" name="session_date_to" value="<?= htmlspecialchars($sessionDateTo ?? '') ?>" class="text-xs px-2 py-1.5 border border-slate-300 rounded bg-white">
                                </div>
                                <button type="submit" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded font-semibold">Apply</button>
                                <a href="?tab=sessions" class="text-xs text-slate-600 px-3 py-1.5 border border-slate-300 rounded">Clear</a>
                            </form>
                            <form method="POST" onsubmit="return confirm('Terminate ALL sessions? You will be logged out.')">
                                <button type="submit" name="terminate_all" class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded font-semibold">⚠️ Terminate All</button>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                                <tr>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-left">User</th>
                                    <th class="px-3 py-2 text-left">IP</th>
                                    <th class="px-3 py-2 text-left">Device</th>
                                    <th class="px-3 py-2 text-left">Lat / Lng</th>
                                    <th class="px-3 py-2 text-left">Created</th>
                                    <th class="px-3 py-2 text-left">Last Active</th>
                                    <th class="px-3 py-2 text-left">Expires</th>
                                    <th class="px-3 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($sessions as $s): ?>
                                    <?php $isCurrent = ($s['id'] ?? '') === ($sid ?? ''); ?>
                                    <?php $active = !($s['terminated'] ?? 0) && (time() - (int)($s['last_active'] ?? 0) < SESSION_TTL); ?>
                                    <tr class="<?= $isCurrent ? 'bg-blue-50' : (($s['terminated'] ?? 0) ? 'opacity-40' : '') ?>">
                                        <td class="px-3 py-2">
                                            <?php if (($s['terminated'] ?? 0)): ?>
                                                <span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px]">Terminated</span>
                                            <?php elseif ($active): ?>
                                                <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-[10px]">Active<?= $isCurrent ? ' (You)' : '' ?></span>
                                            <?php else: ?>
                                                <span class="bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded text-[10px]">Idle</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($s['user_name'] ?? '—') ?><?php if (($s['user_role'] ?? '') === 'admin'): ?><span class="bg-purple-100 text-purple-700 px-1 rounded text-[9px] ml-1">admin</span><?php endif; ?></td>
                                        <td class="px-3 py-2 font-mono"><?= htmlspecialchars($s['ip'] ?? '') ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($s['device'] ?? '') ?></td>
                                        <td class="px-3 py-2 font-mono"><?= htmlspecialchars((string)($s['lat'] ?? '')) ?>, <?= htmlspecialchars((string)($s['lng'] ?? '')) ?></td>
                                        <td class="px-3 py-2"><?= fmt_time((int)($s['created_at'] ?? 0)) ?></td>
                                        <td class="px-3 py-2"><?= fmt_time((int)($s['last_active'] ?? 0)) ?></td>
                                        <td class="px-3 py-2"><?= fmt_time((int)($s['refresh_exp'] ?? 0)) ?></td>
                                        <td class="px-3 py-2">
                                            <?php if (!($s['terminated'] ?? 0)): ?>
                                                <form method="POST" onsubmit="return confirm('Terminate this session?')">
                                                    <input type="hidden" name="terminate" value="<?= htmlspecialchars((string)($s['id'] ?? '')) ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Kill</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 py-2 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-500">
                        <span>Page <?= $sessionPage ?> / <?= $sessionTotalPages ?></span>
                        <div class="flex gap-2">
                            <?php if ($sessionPage > 1): ?>
                                <a href="?tab=sessions&session_page=<?= max(1, $sessionPage-1) ?>&session_date_from=<?= urlencode($sessionDateFrom ?? '') ?>&session_date_to=<?= urlencode($sessionDateTo ?? '') ?>" class="px-2 py-1 border border-slate-200 rounded">Prev</a>
                            <?php endif; ?>
                            <?php if ($sessionPage < $sessionTotalPages): ?>
                                <a href="?tab=sessions&session_page=<?= min($sessionTotalPages, $sessionPage+1) ?>&session_date_from=<?= urlencode($sessionDateFrom ?? '') ?>&session_date_to=<?= urlencode($sessionDateTo ?? '') ?>" class="px-2 py-1 border border-slate-200 rounded">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'login-attempts'): ?>
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="p-3 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap">
                        <h2 class="font-bold text-sm text-slate-700">Login Attempts</h2>
                        <div class="flex items-center gap-2 flex-wrap">
                            <form method="GET" class="flex items-end gap-2 flex-wrap">
                                <input type="hidden" name="tab" value="login-attempts">
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 mb-1">From</label>
                                    <input type="date" name="login_date_from" value="<?= htmlspecialchars($loginDateFrom ?? '') ?>" class="text-xs px-2 py-1.5 border border-slate-300 rounded bg-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 mb-1">To</label>
                                    <input type="date" name="login_date_to" value="<?= htmlspecialchars($loginDateTo ?? '') ?>" class="text-xs px-2 py-1.5 border border-slate-300 rounded bg-white">
                                </div>
                                <button type="submit" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded font-semibold">Apply</button>
                                <a href="?tab=login-attempts" class="text-xs text-slate-600 px-3 py-1.5 border border-slate-300 rounded">Clear</a>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                                <tr>
                                    <th class="px-3 py-2 text-left">Result</th>
                                    <th class="px-3 py-2 text-left">Email</th>
                                    <th class="px-3 py-2 text-left">IP</th>
                                    <th class="px-3 py-2 text-left">Device</th>
                                    <th class="px-3 py-2 text-left">Lat / Lng</th>
                                    <th class="px-3 py-2 text-left">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($logs as $l): ?>
                                    <tr class="<?= !empty($l['success']) ? '' : 'bg-red-50' ?>">
                                        <td class="px-3 py-2">
                                            <?php if (!empty($l['success'])): ?>
                                                <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-[10px]">✓ Success</span>
                                            <?php else: ?>
                                                <span class="bg-red-100 text-red-700 px-1.5 py-0.5 rounded text-[10px]">✗ Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 font-mono"><?= htmlspecialchars($l['email'] ?? '') ?></td>
                                        <td class="px-3 py-2 font-mono"><?= htmlspecialchars($l['ip'] ?? '') ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($l['device'] ?? '') ?></td>
                                        <td class="px-3 py-2 font-mono"><?= htmlspecialchars((string)($l['lat'] ?? '')) ?>, <?= htmlspecialchars((string)($l['lng'] ?? '')) ?></td>
                                        <td class="px-3 py-2"><?= fmt_time((int)($l['created_at'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 py-2 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-500">
                        <span>Page <?= $loginPage ?> / <?= $loginTotalPages ?></span>
                        <div class="flex gap-2">
                            <?php if ($loginPage > 1): ?>
                                <a href="?tab=login-attempts&login_page=<?= max(1, $loginPage-1) ?>&login_date_from=<?= urlencode($loginDateFrom ?? '') ?>&login_date_to=<?= urlencode($loginDateTo ?? '') ?>" class="px-2 py-1 border border-slate-200 rounded">Prev</a>
                            <?php endif; ?>
                            <?php if ($loginPage < $loginTotalPages): ?>
                                <a href="?tab=login-attempts&login_page=<?= min($loginTotalPages, $loginPage+1) ?>&login_date_from=<?= urlencode($loginDateFrom ?? '') ?>&login_date_to=<?= urlencode($loginDateTo ?? '') ?>" class="px-2 py-1 border border-slate-200 rounded">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="p-3 border-b border-slate-100">
                        <h2 class="font-bold text-sm text-slate-700">Login Users</h2>
                    </div>
                    <form method="POST" class="flex gap-2 p-3 border-b border-slate-100 bg-slate-50 flex-wrap">
                        <input type="text" name="new_name" placeholder="Full Name" required class="text-xs px-2 py-1.5 border border-slate-300 rounded min-w-[140px] flex-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <input type="email" name="new_email" placeholder="Email Address" required class="text-xs px-2 py-1.5 border border-slate-300 rounded min-w-[180px] flex-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <input type="password" name="new_password" placeholder="Password" class="text-xs px-2 py-1.5 border border-slate-300 rounded min-w-[140px] flex-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <select name="new_role" class="text-xs px-2 py-1.5 border border-slate-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-indigo-400">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="email_tracker">Email Tracker</option>
                        </select>
                        <label class="text-xs flex items-center gap-2 text-slate-600">
                            <input type="checkbox" name="new_otp_required" checked>
                            OTP required
                        </label>
                        <button type="submit" name="add_user" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded font-semibold shrink-0">+ Add User</button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                                <tr>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-left">Email</th>
                                    <th class="px-3 py-2 text-left">Role</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-left">Added</th>
                                    <th class="px-3 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($users as $u): ?>
                                    <tr class="<?= !empty($u['active']) ? '' : 'opacity-40' ?>">
                                        <td class="px-3 py-2 font-medium"><?= htmlspecialchars($u['name'] ?? '') ?></td>
                                        <td class="px-3 py-2 font-mono"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                                        <td class="px-3 py-2">
                                            <?php
                                                $role = $u['role'] ?? 'user';
                                                if ($role === 'admin') {
                                                    echo '<span class="bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-[10px] font-semibold">Admin</span>';
                                                } elseif ($role === 'email_tracker') {
                                                    echo '<span class="bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded text-[10px] font-semibold">Email Tracker</span>';
                                                } else {
                                                    echo '<span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px]">User</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-3 py-2"><?= !empty($u['active']) ? '<span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-[10px]">Active</span>' : '<span class="bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded text-[10px]">Disabled</span>' ?></td>
                                        <td class="px-3 py-2"><?= fmt_time((int)($u['created_at'] ?? 0)) ?></td>
                                        <td class="px-3 py-2 flex flex-col gap-2">
                                            <label class="inline-flex items-center gap-2 text-[10px] text-slate-600">
                                                <input type="checkbox" data-user-otp-toggle="<?= (int)($u['id'] ?? 0) ?>" <?= ((int)($u['otp_required'] ?? 1) === 1) ? 'checked' : '' ?>>
                                                OTP required
                                            </label>
                                            <div class="flex gap-2">
                                                <form method="POST">
                                                    <input type="hidden" name="toggle_user" value="<?= (int)($u['id'] ?? 0) ?>">
                                                    <button type="submit" class="text-amber-600 hover:text-amber-800 font-semibold"><?= !empty($u['active']) ? 'Disable' : 'Enable' ?></button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Delete this user?')">
                                                    <input type="hidden" name="delete_user" value="<?= (int)($u['id'] ?? 0) ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                    <tr><td colspan="6" class="px-3 py-4 text-center text-slate-400">No users yet. Add one above.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
      document.querySelectorAll('[data-user-otp-toggle]').forEach(function (el) {
        el.addEventListener('change', function () {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = '<input type="hidden" name="toggle_otp_required" value="' + el.dataset.userOtpToggle + '"><input type="hidden" name="otp_required" value="' + (this.checked ? 1 : 0) + '">';
          document.body.appendChild(form);
          form.submit();
        });
      });
    </script>
</body>
</html>
