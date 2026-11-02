<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposal Studio — Login</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📬</text></svg>">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:radial-gradient(ellipse at 60% 0%,#1e1b4b 0%,#0f172a 50%,#020617 100%);}
.grid-bg{position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(99,102,241,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.03) 1px,transparent 1px);background-size:40px 40px;}
.wrap{position:relative;width:100%;max-width:360px;padding:0 16px;}
.logo-box{text-align:center;margin-bottom:32px;}
.logo-icon{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#4f46e5,#818cf8);margin-bottom:16px;}
.logo-icon svg{width:28px;height:28px;color:#fff;stroke:#fff;}
.logo-title{font-size:1.4rem;font-weight:700;color:#fff;letter-spacing:-.02em;}
.logo-sub{color:#94a3b8;font-size:.8rem;margin-top:4px;}
.card{border-radius:20px;overflow:hidden;background:rgba(15,23,42,.85);backdrop-filter:blur(20px);box-shadow:0 0 0 1px rgba(99,102,241,.15),0 20px 60px -10px rgba(0,0,0,.6);}
.steps{display:flex;align-items:center;justify-content:center;gap:8px;padding:24px 0 8px;}
.dot{width:8px;height:8px;border-radius:50%;transition:all .3s;}
.dot-active{background:#818cf8;transform:scale(1.4);}
.dot-done{background:#4f46e5;}
.dot-idle{background:#334155;}
.line{height:1px;width:32px;}
.line-active{background:#818cf8;}
.line-idle{background:#334155;}
.body{padding:16px 32px 32px;}
.step-title{font-size:.95rem;font-weight:600;color:#fff;margin-bottom:4px;}
.step-sub{font-size:.75rem;color:#64748b;}
.alert{display:flex;align-items:flex-start;gap:8px;border-radius:12px;padding:10px 12px;font-size:.75rem;margin-top:16px;}
.alert svg{width:16px;height:16px;flex-shrink:0;margin-top:1px;}
.alert-err{background:rgba(127,29,29,.4);border:1px solid rgba(185,28,28,.4);color:#fca5a5;}
.alert-ok{background:rgba(6,78,59,.4);border:1px solid rgba(6,95,70,.4);color:#6ee7b7;}
.field{margin-top:20px;}
label{display:block;font-size:.72rem;font-weight:500;color:#94a3b8;margin-bottom:6px;}
input{width:100%;padding:10px 16px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:#f1f5f9;font-size:.875rem;font-family:inherit;transition:all .2s;outline:none;}
input::placeholder{color:#475569;}
input:focus{background:rgba(255,255,255,.09);border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2);}
.otp-input{letter-spacing:.45em;font-size:1.6rem;font-weight:700;text-align:center;padding:12px 16px;}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;margin-top:16px;padding:10px;border-radius:12px;border:none;cursor:pointer;font-size:.875rem;font-weight:600;color:#fff;background:linear-gradient(135deg,#4f46e5,#6366f1);transition:all .2s;}
.btn:hover{background:linear-gradient(135deg,#4338ca,#4f46e5);transform:translateY(-1px);box-shadow:0 4px 15px rgba(99,102,241,.4);}
.btn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none;}
.btn svg{width:16px;height:16px;stroke:#fff;}
.divider{display:flex;align-items:center;justify-content:space-between;margin-top:16px;padding-top:16px;border-top:1px solid rgba(51,65,85,.8);}
.link-btn{background:none;border:none;cursor:pointer;font-size:.75rem;font-family:inherit;display:flex;align-items:center;gap:4px;transition:color .2s;}
.link-btn svg{width:12px;height:12px;}
.link-resend{color:#818cf8;}.link-resend:hover{color:#a5b4fc;}
.link-back{color:#475569;}.link-back:hover{color:#94a3b8;}
.footer{text-align:center;color:#1e293b;font-size:.72rem;margin-top:20px;}
</style>
</head>
<body>
<div class="grid-bg"></div>
<div class="wrap">

    <div class="logo-box">
        <div class="logo-icon">
            <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div class="logo-title">Proposal Studio</div>
        <div class="logo-sub">Secure access portal</div>
    </div>

    <div class="card">
        <div class="steps">
            <div class="dot <?= $step === 'email' ? 'dot-active' : 'dot-done' ?>"></div>
            <div class="line <?= $step === 'otp' ? 'line-active' : 'line-idle' ?>"></div>
            <div class="dot <?= $step === 'otp' ? 'dot-active' : 'dot-idle' ?>"></div>
        </div>

        <div class="body">
            <div class="step-title"><?= $step === 'email' ? 'Verify your identity' : 'Enter verification code' ?></div>
            <div class="step-sub"><?= $step === 'email' ? 'Enter your email to receive a one-time password' : 'Check your inbox for the 6-digit OTP' ?></div>

            <?php if ($error): ?>
            <div class="alert alert-err">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            <?php if ($info): ?>
            <div class="alert alert-ok">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($info) ?>
            </div>
            <?php endif; ?>

            <?php if ($step === 'email'): ?>
            <form method="POST" id="form-email">
                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" required autofocus placeholder="your@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Optional for OTP users" autocomplete="current-password">
                </div>
                <button type="submit" name="send_otp" id="btn-send" class="btn">
                    Send OTP
                    <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>

            <?php else: ?>
            <form method="POST" id="form-otp">
                <div class="field">
                    <label>6-Digit OTP</label>
                    <input type="text" name="otp" required autofocus class="otp-input"
                        maxlength="6" inputmode="numeric" pattern="\d{6}"
                        placeholder="· · · · · ·"
                        oninput="this.value=this.value.replace(/\D/g,'')">
                </div>
                <button type="submit" name="verify_otp" id="btn-verify" class="btn">
                    <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Verify & Sign In
                </button>
            </form>

            <div class="divider">
                <form method="POST">
                    <button type="submit" name="resend_otp" class="link-btn link-resend">Resend OTP</button>
                </form>
                <form method="POST">
                    <button type="submit" name="back_to_email" class="link-btn link-back">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Different email
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>


</div>

<script>
(function(){
    var fe = document.getElementById('form-email');
    if(fe) fe.addEventListener('submit', function(e){
        var b = document.getElementById('btn-send');
        setTimeout(function(){ b.disabled = true; b.textContent = 'Sending…'; }, 0);
    });
    var fo = document.getElementById('form-otp');
    if(fo) fo.addEventListener('submit', function(e){
        var b = document.getElementById('btn-verify');
        setTimeout(function(){ b.disabled = true; b.textContent = 'Verifying…'; }, 0);
    });
})();
</script>
</body>
</html>
