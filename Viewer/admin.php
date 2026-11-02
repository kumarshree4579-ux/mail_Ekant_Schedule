<?php
// admin.php - Admin Dashboard for Replays
session_start();

// Simple Hardcoded Password (Change this!)
$adminPassword = "password123";

if (isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Incorrect password!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// ---------------- LOGIN PAGE ---------------- //
if (empty($_SESSION['admin_logged_in'])) {
?>
    <!DOCTYPE html>
    <html><head><title>Admin Login</title>
    <style>body{font-family:sans-serif; background:#f4f4f5; display:flex; justify-content:center; align-items:center; height:100vh;} .box{background:white; padding:30px; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1); text-align:center;} input{padding:10px; width:200px; margin-bottom:10px;} button{padding:10px 20px; background:#3b82f6; color:white; border:none; border-radius:4px; cursor:pointer;}</style>
    </head><body>
        <div class="box">
            <h2>Admin Login</h2>
            <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter password" required><br>
                <button type="submit">Login</button>
            </form>
        </div>
    </body></html>
<?php
    exit;
}
// ---------------- END LOGIN PAGE ---------------- //

// Fetch saved sessions
$sessionDir = __DIR__ . '/sessions';
$sessions = [];
if (is_dir($sessionDir)) {
    $files = glob($sessionDir . '/*.json');
    foreach ($files as $file) {
        $sessions[] = [
            'token' => basename($file, '.json'),
            'date' => date("Y-m-d H:i:s", filemtime($file)),
            'file_url' => 'api.php?token=' . basename($file, '.json')
        ];
    }
    // Sort by newest first
    usort($sessions, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/rrweb-player@1.0.0-alpha.4/dist/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/rrweb-player@1.0.0-alpha.4/dist/index.js"></script>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: white; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 20px; margin-bottom: 20px; }
        a.btn { background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #0f172a; }
        .play-btn { background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        
        /* Modal */
        #modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 100; justify-content: center; align-items: center; flex-direction: column; }
        #close-btn { margin-bottom: 20px; background: #ef4444; border: none; color: white; padding: 10px 20px; cursor: pointer; font-size: 16px; border-radius: 4px; }
        #player { background: white; border-radius: 8px; overflow: hidden; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Document Analytics Dashboard</h1>
        <a href="?logout=1" class="btn">Logout</a>
    </div>

    <table>
        <tr>
            <th>Visitor Token</th>
            <th>Last Active</th>
            <th>Action</th>
        </tr>
        <?php if(empty($sessions)): ?>
            <tr><td colspan="3">No tracking data found yet. Send a link to a user first!</td></tr>
        <?php else: ?>
            <?php foreach($sessions as $s): ?>
            <tr>
                <td><strong><?php echo $s['token']; ?></strong></td>
                <td><?php echo $s['date']; ?></td>
                <td><button class="play-btn" onclick="playSession('<?php echo $s['file_url']; ?>')">▶ Play Replay</button></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <!-- Video Player Modal -->
    <div id="modal">
        <button id="close-btn" onclick="document.getElementById('modal').style.display='none'; document.getElementById('player').innerHTML='';">Close Replay</button>
        <div id="player"></div>
    </div>

    <script>
        async function playSession(fileUrl) {
            document.getElementById('modal').style.display = 'flex';
            document.getElementById('player').innerHTML = '<h2 style="padding:40px; color:black;">Loading replay data...</h2>';
            
            try {
                const response = await fetch(fileUrl);
                const events = await response.json();

                if(events.length < 2) {
                    document.getElementById('player').innerHTML = '<h3 style="padding:40px; color:red;">Not enough data recorded for this session.</h3>';
                    return;
                }
                
                // Get only the most recent recording session to prevent crash on multiple appended sessions
                let startIndex = 0;
                for (let i = events.length - 1; i >= 0; i--) {
                    if (events[i].type === 4) { // type 4 is Meta event
                        let j = i;
                        while (j > 0 && (events[j-1].type === 0 || events[j-1].type === 1)) {
                            j--;
                        }
                        startIndex = j;
                        break;
                    }
                }
                const sessionEvents = events.slice(startIndex);

                document.getElementById('player').innerHTML = '';
                new rrwebPlayer({
                    target: document.getElementById('player'),
                    props: { events: sessionEvents, autoPlay: true, width: 900, height: 600 }
                });
            } catch (err) {
                alert("Error loading replay file.");
            }
        }
    </script>
</body>
</html>