<?php
require_once 'db.php';
check_auth();
$user_id = $_SESSION['user_id'];
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Daily Execution Logging
    if (isset($_POST['action']) && $_POST['action'] === 'log_day') {
        $date = $_POST['log_date'];
        $notes = $_POST['raw_activities'] ?? '';
        $pdo->prepare("INSERT INTO daily_logs (user_id, log_date, raw_24h_activities) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE raw_24h_activities=?")->execute([$user_id, $date, $notes, $notes]);

        if (isset($_POST['hours']) && is_array($_POST['hours'])) {
            foreach ($_POST['hours'] as $field_id => $hrs) {
                $h = floatval($hrs);
                $pdo->prepare("INSERT INTO activity_values (user_id, log_date, field_id, hours) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE hours=?")->execute([$user_id, $date, $field_id, $h, $h]);
            }
        }
        header("Location: index.php?date=$date"); exit;
    }
    // 2. Weekly Goals Management
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_goal') {
        $pdo->prepare("INSERT INTO weekly_goals (user_id, week_start_date, task_name, task_details, project_category) VALUES (?, ?, ?, ?, ?)")->execute([$user_id, $current_week_start, $_POST['task_name'], $_POST['task_details'], $_POST['project_category']]);
        header("Location: log_data.php?date=$selected_date"); exit;
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_goal_status') {
        // DEMO RESTRICTION: Prevent User 1 from marking complete
        if ($user_id == 1 && $_POST['status'] === 'completed') {
            header("Location: log_data.php?date=$selected_date&err=demo"); exit;
        }
        $pdo->prepare("UPDATE weekly_goals SET status = ? WHERE id = ? AND user_id = ?")->execute([$_POST['status'], $_POST['goal_id'], $user_id]);
        header("Location: log_data.php?date=$selected_date"); exit;
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_goal') {
        // DEMO RESTRICTION: Prevent User 1 from deleting
        if ($user_id == 1) { header("Location: log_data.php?date=$selected_date&err=demo"); exit; }
        
        $pdo->prepare("DELETE FROM weekly_goals WHERE id = ? AND user_id = ?")->execute([$_POST['goal_id'], $user_id]);
        header("Location: log_data.php?date=$selected_date"); exit;
    }
    // 3. Project Categories Management
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_category') {
        $name = trim($_POST['category_name']);
        if ($name) {
            try { $pdo->prepare("INSERT INTO project_categories (user_id, category_name) VALUES (?, ?)")->execute([$user_id, $name]); } catch(PDOException $e) {}
        }
        header("Location: log_data.php?date=$selected_date"); exit;
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'edit_category') {
        $name = trim($_POST['category_name']);
        $cat_id = $_POST['category_id'];
        $old_name = $_POST['old_category_name'];
        if ($name) {
            $pdo->prepare("UPDATE project_categories SET category_name = ? WHERE id = ? AND user_id = ?")->execute([$name, $cat_id, $user_id]);
            $pdo->prepare("UPDATE weekly_goals SET project_category = ? WHERE project_category = ? AND user_id = ?")->execute([$name, $old_name, $user_id]);
        }
        header("Location: log_data.php?date=$selected_date"); exit;
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
        // DEMO RESTRICTION: Prevent User 1 from deleting
        if ($user_id == 1) { header("Location: log_data.php?date=$selected_date&err=demo"); exit; }

        $pdo->prepare("DELETE FROM project_categories WHERE id = ? AND user_id = ?")->execute([$_POST['category_id'], $user_id]);
        header("Location: log_data.php?date=$selected_date"); exit;
    }
}

// Fetch Dynamic Fields
$stmt = $pdo->prepare("SELECT * FROM activity_fields WHERE user_id = ? ORDER BY group_heading, id");
$stmt->execute([$user_id]);
$fields = $stmt->fetchAll();
$grouped_fields = []; foreach ($fields as $f) { $grouped_fields[$f['group_heading']][] = $f; }

// Fetch Today's Logged Hours
$stmt_vals = $pdo->prepare("SELECT field_id, hours FROM activity_values WHERE user_id = ? AND log_date = ?");
$stmt_vals->execute([$user_id, $selected_date]);
$logged_values = []; while ($row = $stmt_vals->fetch()) { $logged_values[$row['field_id']] = $row['hours']; }

$notes = $pdo->prepare("SELECT raw_24h_activities FROM daily_logs WHERE user_id = ? AND log_date = ?");
$notes->execute([$user_id, $selected_date]); $notes = $notes->fetchColumn() ?: '';

// Fetch Objectives & Categories
$weekly_goals = $pdo->prepare("SELECT * FROM weekly_goals WHERE user_id = ? AND week_start_date = ? ORDER BY status ASC, id DESC");
$weekly_goals->execute([$user_id, $current_week_start]); $weekly_goals = $weekly_goals->fetchAll();

$categories = $pdo->prepare("SELECT * FROM project_categories WHERE user_id = ? ORDER BY category_name ASC");
$categories->execute([$user_id]); $categories = $categories->fetchAll();
if(empty($categories)) $categories = [['id' => 0, 'category_name'=>'General']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Data Entry - CoreHour</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-monospace fw-bold text-white text-decoration-none" href="index.php">COREHOUR // LOGGING</a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white-50 small font-monospace me-2 d-none d-md-block"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                <a href="settings.php" class="btn btn-outline-secondary btn-sm border-0 font-monospace">Settings</a>
                <a href="index.php?date=<?= $selected_date ?>" class="btn btn-light btn-sm font-monospace fw-bold">Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow-1 w-100">
        <div class="container-fluid px-4 mb-5">
            
            <?php if(isset($_GET['err']) && $_GET['err'] == 'demo'): ?>
                <div class="alert alert-warning small font-monospace fw-bold mb-4 shadow-sm border-warning border-start border-4">
                    <i class="bi bi-shield-lock me-2"></i> DEMO MODE ACTIVE: Deleting items or marking objectives as complete is restricted in the demo environment.
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                            <h5 class="fw-bold m-0 font-monospace">EXECUTION LEDGER</h5>
                            <form method="GET"><input type="date" name="date" class="form-control form-control-sm bg-light border-0 fw-bold shadow-none" value="<?= $selected_date ?>" onchange="this.form.submit()"></form>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="log_day"><input type="hidden" name="log_date" value="<?= $selected_date ?>">
                            
                            <?php if(empty($grouped_fields)): ?>
                                <div class="alert alert-warning small font-monospace">No tracking fields found. Go to Settings to build your engine.</div>
                            <?php else: ?>
                                <?php foreach ($grouped_fields as $heading => $group): ?>
                                    <div style="font-size: 0.8rem; font-weight: bold; letter-spacing: 1px; color: #6c757d; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top:15px;"><?= htmlspecialchars($heading) ?></div>
                                    <div class="row g-3 mb-2">
                                        <?php foreach ($group as $field): ?>
                                            <div class="col-sm-6">
                                                <label class="small text-muted fw-bold"><?= htmlspecialchars($field['field_name']) ?></label>
                                                <div class="input-group input-group-sm shadow-none">
                                                    <input type="number" step="0.25" class="form-control border-dark shadow-none" name="hours[<?= $field['id'] ?>]" placeholder="0.00" value="<?= isset($logged_values[$field['id']]) ? floatval($logged_values[$field['id']]) : '' ?>">
                                                    <span class="input-group-text border-dark bg-light">Hrs</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div style="font-size: 0.8rem; font-weight: bold; letter-spacing: 1px; color: #6c757d; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top:25px;">24-HOUR NOTES</div>
                            <div class="mb-4"><textarea class="form-control font-monospace small" name="raw_activities" rows="4" placeholder="Brief notes on today's execution..."><?= htmlspecialchars($notes) ?></textarea></div>
                            <button type="submit" class="btn btn-dark w-100 font-monospace py-2 shadow-sm">COMMIT TO LEDGER</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-3 font-monospace">ALL WEEKLY OBJECTIVES</h5>
                        
                        <form method="POST" class="mb-4 bg-white p-3 rounded border shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="small fw-bold text-muted font-monospace m-0">ADD NEW OBJECTIVE</h6>
                                <button type="button" class="btn btn-sm btn-outline-secondary font-monospace py-0 px-2" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                    <i class="bi bi-tags me-1"></i>Manage Categories
                                </button>
                            </div>
                            
                            <input type="hidden" name="action" value="add_goal">
                            <input type="text" class="form-control form-control-sm mb-2 shadow-none" name="task_name" placeholder="Target Task Name" required>
                            <textarea class="form-control form-control-sm mb-2 shadow-none" name="task_details" rows="2" placeholder="Task description/steps..."></textarea>
                            <select class="form-select form-select-sm mb-3 shadow-none fw-bold" name="project_category">
                                <?php foreach($categories as $cat): ?><option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option><?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-dark w-100 btn-sm font-monospace">SAVE OBJECTIVE</button>
                        </form>
                        
                        <div class="list-group list-group-flush border-top pt-2">
                            <?php foreach ($weekly_goals as $goal): ?>
                                <div class="list-group-item px-3 py-3 border rounded shadow-sm mb-3 bg-white">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge bg-secondary me-1" style="font-size:0.65rem;"><?= htmlspecialchars($goal['project_category']) ?></span> 
                                            <span class="small fw-bold <?= $goal['status']=='completed'?'text-decoration-line-through text-muted':'' ?>"><?= htmlspecialchars($goal['task_name']) ?></span>
                                        </div>
                                        <form method="POST" onsubmit="return <?= ($user_id == 1) ? 'true' : "confirm('Delete this objective entirely?');" ?>">
                                            <input type="hidden" name="action" value="delete_goal">
                                            <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                                            <button type="submit" class="btn btn-sm text-danger p-0 px-1 border-0 fs-5">×</button>
                                        </form>
                                    </div>
                                    <?php if(!empty($goal['task_details'])): ?>
                                        <div class="small text-muted mb-3 font-monospace" style="font-size:0.75rem; line-height: 1.4;"><?= nl2br(htmlspecialchars($goal['task_details'])) ?></div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="action" value="update_goal_status">
                                        <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="small text-muted fw-bold font-monospace" style="font-size:0.7rem;">STATUS:</span>
                                            <select class="form-select form-select-sm shadow-none w-auto font-monospace" name="status" style="font-size: 0.75rem; background-color: #f8f9fa;" onchange="this.form.submit()">
                                                <option value="planned" <?= $goal['status'] == 'planned' ? 'selected' : '' ?>>Planned</option>
                                                <option value="in-progress" <?= $goal['status'] == 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="completed" <?= $goal['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title font-monospace small fw-bold">PROJECT CATEGORIES</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    
                    <form method="POST" class="mb-4 d-flex gap-2">
                        <input type="hidden" name="action" value="add_category">
                        <input type="text" name="category_name" class="form-control form-control-sm shadow-none border-dark" placeholder="New Category Name..." required>
                        <button type="submit" class="btn btn-dark btn-sm font-monospace px-3">ADD</button>
                    </form>
                    
                    <div class="list-group border shadow-sm">
                        <?php foreach($categories as $cat): ?>
                            <?php if($cat['id'] > 0): ?>
                            <div class="list-group-item p-2 d-flex justify-content-between align-items-center bg-white">
                                <form method="POST" class="d-flex gap-2 w-100 me-2">
                                    <input type="hidden" name="action" value="edit_category">
                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                    <input type="hidden" name="old_category_name" value="<?= htmlspecialchars($cat['category_name']) ?>">
                                    <input type="text" name="category_name" class="form-control form-control-sm shadow-none bg-light border-0 fw-bold" value="<?= htmlspecialchars($cat['category_name']) ?>" required>
                                    <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" title="Save Change"><i class="bi bi-check2"></i></button>
                                </form>
                                <form method="POST" onsubmit="return <?= ($user_id == 1) ? 'true' : "confirm('Delete this category? It will NOT delete objectives using this category.');" ?>">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php require_once 'footer.php'; ?>
</body>
</html>