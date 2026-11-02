<?php
/**
 * Public callback request form.
 * Variables available: $trackingId, $email, $name (from CallbackController::form)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request a Callback</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📞</text></svg>">
<script src="https://cdn.tailwindcss.com"></script>
<style>
    body { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0f9ff 100%); }
    .form-card { box-shadow: 0 20px 60px -15px rgba(0,0,0,0.1); }
    input:focus, select:focus, textarea:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 font-sans antialiased">

<div class="w-full max-w-lg">
    <!-- Header -->
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
            <span class="text-3xl">📞</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">Request a Callback</h1>
        <p class="text-slate-500 mt-1 text-sm">Schedule a convenient time for us to call you back.</p>
    </div>

    <?php if (!empty($proposalInfo)): ?>
    <!-- Proposal Context Card -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
        <div class="flex items-start gap-3">
            <span class="text-lg">📧</span>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-blue-600 uppercase tracking-wider mb-1">Regarding Proposal</div>
                <div class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($proposalInfo['subject']) ?></div>
                <div class="text-xs text-slate-500 mt-0.5">Sent on <?= htmlspecialchars($proposalInfo['sent_at']) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="form-card bg-white rounded-2xl border border-slate-200 p-6 sm:p-8">
        <form id="callback-form" class="space-y-5">
            <!-- Hidden fields -->
            <input type="hidden" name="tracking_id" value="<?= htmlspecialchars($trackingId) ?>">

            <!-- Name -->
            <div>
                <label for="recipient_name" class="block text-sm font-semibold text-slate-700 mb-1.5">Your Name</label>
                <input type="text" id="recipient_name" name="recipient_name" 
                       value="<?= htmlspecialchars($name) ?>"
                       placeholder="John Smith"
                       class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm transition-all focus:border-blue-500">
            </div>

            <!-- Email -->
            <div>
                <label for="recipient_email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                <input type="email" id="recipient_email" name="recipient_email" 
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="you@example.com"
                       class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm transition-all focus:border-blue-500">
            </div>

            <!-- Callback Number -->
            <div>
                <label for="callback_number" class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Callback Number <span class="text-red-500">*</span>
                </label>
                <input type="tel" id="callback_number" name="callback_number" required
                       placeholder="(555) 123-4567"
                       class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm transition-all focus:border-blue-500">
            </div>

            <!-- Date & Time Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Date -->
                <div>
                    <label for="schedule_date" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Preferred Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="schedule_date" name="schedule_date" required
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm transition-all focus:border-blue-500">
                </div>

                <!-- Time -->
                <div>
                    <label for="schedule_time" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Preferred Time <span class="text-red-500">*</span>
                    </label>
                    <input type="time" id="schedule_time" name="schedule_time" required
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm transition-all focus:border-blue-500">
                </div>
            </div>

            <!-- Timezone -->
            <div>
                <label for="timezone" class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Time Zone <span class="text-red-500">*</span>
                </label>
                <select id="timezone" name="timezone" required
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm transition-all focus:border-blue-500 bg-white cursor-pointer">
                    <option value="EST">EST — Eastern Standard Time</option>
                    <option value="CST">CST — Central Standard Time</option>
                    <option value="MST">MST — Mountain Standard Time</option>
                    <option value="PST">PST — Pacific Standard Time</option>
                </select>
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-semibold text-slate-700 mb-1.5">Additional Notes</label>
                <textarea id="notes" name="notes" rows="3"
                          placeholder="Any specific topic you'd like to discuss..."
                          class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm transition-all focus:border-blue-500 resize-none"></textarea>
            </div>

            <!-- Submit -->
            <button type="submit" id="submit-btn"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold py-3 px-6 rounded-lg transition-all text-sm shadow-sm hover:shadow-md flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Schedule Callback
            </button>
        </form>

        <!-- Success State (hidden by default) -->
        <div id="success-state" class="hidden text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">Callback Scheduled!</h2>
            <p class="text-slate-500 text-sm">We've received your request and will call you at the scheduled time.</p>
        </div>
    </div>

    <!-- Footer -->
    <p class="text-center text-xs text-slate-400 mt-4">Powered by Proposal Studio</p>
</div>

<script>
(function() {
    const form = document.getElementById('callback-form');
    const btn = document.getElementById('submit-btn');
    const successState = document.getElementById('success-state');

    // Set minimum date to today
    const dateInput = document.getElementById('schedule_date');
    const today = new Date().toISOString().split('T')[0];
    dateInput.setAttribute('min', today);
    if (!dateInput.value) dateInput.value = today;

    // Auto-detect timezone based on browser offset
    const tzSelect = document.getElementById('timezone');
    const offset = new Date().getTimezoneOffset() / 60; // hours behind UTC
    const tzMap = { 5: 'EST', 6: 'CST', 7: 'MST', 8: 'PST' };
    if (tzMap[offset]) tzSelect.value = tzMap[offset];

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Submitting...';

        try {
            const formData = new FormData(form);
            const response = await fetch('<?= BASE_PATH ?>/callback', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.ok) {
                form.classList.add('hidden');
                successState.classList.remove('hidden');
            } else {
                alert(result.message || 'Something went wrong. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg> Schedule Callback';
            }
        } catch (err) {
            alert('Network error. Please check your connection and try again.');
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg> Schedule Callback';
        }
    });
})();
</script>

</body>
</html>
