<?php
require_once 'db.php';
check_auth();
$user_id = $_SESSION['user_id'];

// 1. Fetch User Profile
$stmt_u = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt_u->execute([$user_id]);
$user_data = $stmt_u->fetch();

// 2. Fetch/Create Global Settings
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch();
if (!$settings) {
    $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$user_id]);
    $settings = ['sleep_target' => 7.5];
}

$msg = ''; $msg_class = 'alert-success';

// Handle Demo Error Redirects
if(isset($_GET['err']) && $_GET['err'] == 'demo') {
    $msg = "<i class='bi bi-shield-lock me-2'></i> DEMO MODE ACTIVE: Profile updates and field deletions are restricted.";
    $msg_class = "alert-warning border-warning border-start border-4 fw-bold shadow-sm";
}

// 3. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile Update
    if (isset($_POST['update_profile'])) {
        // DEMO RESTRICTION: Prevent changing email or password
        if ($user_id == 1) { header("Location: settings.php?err=demo"); exit; }

        $new_name = trim($_POST['full_name']);
        $new_email = trim($_POST['email']);
        $new_pass = $_POST['new_password'];

        try {
            if (!empty($new_pass)) {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET full_name=?, email=?, password_hash=? WHERE id=?")->execute([$new_name, $new_email, $hash, $user_id]);
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=? WHERE id=?")->execute([$new_name, $new_email, $user_id]);
            }
            $_SESSION['full_name'] = $new_name; // Update session
            $msg = "Profile updated successfully.";
            $user_data['full_name'] = $new_name; $user_data['email'] = $new_email;
        } catch (PDOException $e) {
            $msg = "Error updating profile. Email might already be in use."; $msg_class = 'alert-danger';
        }
    }
    // Settings Update
    elseif (isset($_POST['update_globals'])) {
        $pdo->prepare("UPDATE user_settings SET sleep_target=? WHERE user_id=?")->execute([$_POST['s_target'], $user_id]);
        header('Location: settings.php'); exit;
    }
    // Dynamic Fields
    elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_field') {
            $pdo->prepare("INSERT INTO activity_fields (user_id, field_name, group_heading, weight, chart_color) VALUES (?, ?, ?, ?, ?)")
                ->execute([$user_id, trim($_POST['field_name']), trim($_POST['group_heading']), floatval($_POST['weight']), $_POST['chart_color']]);
        } elseif ($_POST['action'] === 'edit_field') {
            $pdo->prepare("UPDATE activity_fields SET field_name=?, group_heading=?, weight=?, chart_color=? WHERE id=? AND user_id=?")
                ->execute([trim($_POST['field_name']), trim($_POST['group_heading']), floatval($_POST['weight']), $_POST['chart_color'], $_POST['field_id'], $user_id]);
        } elseif ($_POST['action'] === 'delete_field') {
            // DEMO RESTRICTION: Prevent User 1 from deleting tracking fields
            if ($user_id == 1) { header("Location: settings.php?err=demo"); exit; }

            $pdo->prepare("DELETE FROM activity_fields WHERE id = ? AND user_id = ?")->execute([$_POST['field_id'], $user_id]);
        }
        header('Location: settings.php'); exit;
    }
}

// 4. CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv'); 
    header('Content-Disposition: attachment; filename="CoreHour_Ledger.csv"');
    $output = fopen('php://output', 'w');
    
    $stmt_f = $pdo->prepare("SELECT id, field_name FROM activity_fields WHERE user_id = ? ORDER BY id ASC");
    $stmt_f->execute([$user_id]);
    $active_fields = $stmt_f->fetchAll();
    
    $headers = ['Date', 'Execution Notes', 'Actual Sleep (Calc)'];
    foreach ($active_fields as $f) { $headers[] = $f['field_name'] . ' (Hrs)'; }
    fputcsv($output, $headers);
    
    $stmt_d = $pdo->prepare("SELECT log_date, raw_24h_activities FROM daily_logs WHERE user_id = ? ORDER BY log_date ASC");
    $stmt_d->execute([$user_id]);
    $logs = $stmt_d->fetchAll();
    
    foreach ($logs as $log) {
        $row = [$log['log_date'], $log['raw_24h_activities']];
        $total_active = 0;
        
        foreach ($active_fields as $f) {
            $stmt_v = $pdo->prepare("SELECT hours FROM activity_values WHERE user_id = ? AND log_date = ? AND field_id = ?");
            $stmt_v->execute([$user_id, $log['log_date'], $f['id']]);
            $hrs = floatval($stmt_v->fetchColumn());
            $row[] = $hrs;
            $total_active += $hrs;
        }
        
        $sleep = round(24 - ($total_active + 2), 1);
        if ($sleep < 0) $sleep = 0;
        $row[2] = $sleep; 
        
        fputcsv($output, $row);
    }
    fclose($output); 
    exit;
}

$fields = $pdo->prepare("SELECT * FROM activity_fields WHERE user_id = ? ORDER BY group_heading ASC, id ASC");
$fields->execute([$user_id]);
$fields = $fields->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Settings - CoreHour</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>.table-sm td { vertical-align: middle; }</style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand font-monospace fw-bold text-white text-decoration-none" href="index.php">COREHOUR // SETTINGS</a>
        
        <div class="d-flex align-items-center gap-3">
            <span class="text-white-50 small font-monospace me-2 d-none d-md-block">
                <?= htmlspecialchars($user_data['full_name']) ?>
            </span>
            <a href="index.php" class="btn btn-light btn-sm font-monospace fw-bold">Dashboard</a>
        </div>
    </div>
</nav>

    <div class="container mb-5" style="max-width: 900px; flex: 1;">
        <?php if($msg): ?><div class="alert <?= $msg_class ?> small font-monospace"><?= $msg ?></div><?php endif; ?>

        <div class="card border-0 shadow-sm p-4 mb-4">
            <h5 class="fw-bold mb-3 font-monospace">DYNAMIC TRACKING ENGINE</h5>
            
            <form method="POST" class="mb-4 bg-white p-3 border rounded shadow-sm">
                <h6 class="small font-monospace fw-bold text-muted mb-3">ADD NEW FIELD</h6>
                <input type="hidden" name="action" value="add_field">
                <div class="row g-2 mb-2">
                    <div class="col-md-4"><label class="small fw-bold">Group Heading</label><input type="text" name="group_heading" class="form-control form-control-sm" required></div>
                    <div class="col-md-4"><label class="small fw-bold">Field Name</label><input type="text" name="field_name" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label class="small fw-bold">Multiplier</label><input type="number" step="0.1" name="weight" class="form-control form-control-sm" value="1.0" required></div>
                    <div class="col-md-2"><label class="small fw-bold">Color</label><input type="color" name="chart_color" class="form-control form-control-sm p-1" value="#198754" required></div>
                </div>
                <button type="submit" class="btn btn-dark btn-sm w-100 font-monospace mt-2">CREATE TRACKING FIELD</button>
            </form>

            <div class="table-responsive">
                <table class="table table-sm small align-middle">
                    <thead class="table-light font-monospace"><tr><th>Group Heading</th><th>Field Name</th><th>Weight</th><th>Color</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($fields as $f): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="action" value="edit_field">
                                <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                <td><input type="text" name="group_heading" class="form-control form-control-sm shadow-none" value="<?= htmlspecialchars($f['group_heading']) ?>" required></td>
                                <td><input type="text" name="field_name" class="form-control form-control-sm fw-bold shadow-none" value="<?= htmlspecialchars($f['field_name']) ?>" required></td>
                                <td><input type="number" step="0.1" name="weight" class="form-control form-control-sm shadow-none <?= $f['weight'] < 0 ? 'text-danger' : 'text-success' ?> fw-bold" value="<?= $f['weight'] ?>" required></td>
                                <td><input type="color" name="chart_color" class="form-control form-control-sm p-0 border-0 shadow-none" value="<?= $f['chart_color'] ?>" required style="height:30px; width:40px;"></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-sm btn-outline-dark px-2 py-1"><i class="bi bi-check2"></i></button>
                            </form>
                                        <form method="POST" class="d-inline" onsubmit="return <?= ($user_id == 1) ? 'true' : "confirm('Delete this field?');" ?>">
                                            <input type="hidden" name="action" value="delete_field"><input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <h6 class="fw-bold mb-3 font-monospace">USER PROFILE</h6>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="mb-2">
                            <label class="small fw-bold text-muted">Full Name</label>
                            <input type="text" name="full_name" class="form-control form-control-sm" value="<?= htmlspecialchars($user_data['full_name']) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-muted">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($user_data['email']) ?>" required readonly>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">New Password (Optional)</label>
                            <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Leave blank to keep current">
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm w-100 font-monospace">UPDATE PROFILE</button>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <h6 class="fw-bold mb-3 font-monospace">GLOBAL METRICS & DATA</h6>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="update_globals" value="1">
                        <label class="small fw-bold text-muted">Target Sleep (Hours)</label>
                        <div class="input-group input-group-sm mb-2">
                            <input type="number" step="0.5" name="s_target" class="form-control" value="<?= $settings['sleep_target'] ?>">
                            <button type="submit" class="btn btn-dark">Save</button>
                        </div>
                        <div class="small text-muted" style="font-size:0.75rem;">Falling > 1.5h below this target applies a flat -1.5 score penalty.</div>
                    </form>
                    <hr>
                    <a href="settings.php?export=csv" class="btn btn-outline-secondary btn-sm w-100 font-monospace fw-bold mt-2"><i class="bi bi-download me-2"></i>DOWNLOAD LEDGER.CSV</a>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-4 mb-4 bg-dark text-white">
            <h6 class="fw-bold mb-3 font-monospace text-info">HOW THE CORE SCORE WORKS</h6>
            <ul class="small font-monospace mb-0" style="line-height: 1.6;">
                <li><span class="text-secondary">Base Survival:</span> Every day starts at <strong>4.0 Points</strong>.</li>
                <li><span class="text-success">Positive Momentum:</span> (Hours × Positive Weight) are added.</li>
                <li><span class="text-danger">Distraction Penalty:</span> If a field has negative weight, the FIRST hour is "forgiven". Every hour AFTER that applies the penalty.</li>
                <li><span class="text-warning">Sleep Deficit:</span> If actual sleep is less than (Target - 1.5h), you lose <strong>1.5 Points</strong>.</li>
                <li>The final score is mathematically clamped between <strong>0.0 and 10.0</strong>.</li>
            </ul>
        </div>
    </div>

<?php require_once 'footer.php'; ?>
</body>
</html>