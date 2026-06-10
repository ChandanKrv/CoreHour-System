<?php
require_once 'db.php';
check_auth();
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'quick_complete_goal') {
        $return_date = $_POST['return_date'] ?? date('Y-m-d');
        
        // DEMO RESTRICTION: Block demo user from completing goals
        if ($user_id == 1) {
            header("Location: index.php?date=$return_date&err=demo"); 
            exit;
        }

        $pdo->prepare("UPDATE weekly_goals SET status = 'completed' WHERE id = ? AND user_id = ?")->execute([$_POST['goal_id'], $user_id]);
        header("Location: index.php?date=$return_date"); exit;
    }
}

$stmt_set = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt_set->execute([$user_id]);
$settings = $stmt_set->fetch() ?: ['sleep_target' => 7.5];

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
$graph_range = isset($_GET['range']) && in_array($_GET['range'], ['7', '14', '30']) ? (int)$_GET['range'] : 14;

$stmt_fields = $pdo->prepare("SELECT * FROM activity_fields WHERE user_id = ? ORDER BY id ASC");
$stmt_fields->execute([$user_id]);
$active_fields = $stmt_fields->fetchAll();

$dates = []; $sleep_data = []; $chart_datasets = [];
foreach ($active_fields as $f) {
    $chart_datasets[$f['id']] = ['label' => $f['field_name'], 'data' => [], 'backgroundColor' => $f['chart_color'], 'borderColor' => $f['chart_color'], 'weight' => floatval($f['weight'])];
}

$days_to_subtract = $graph_range - 1;
$start_date = date('Y-m-d', strtotime("-$days_to_subtract days", strtotime($selected_date)));

$stmt_vals = $pdo->prepare("SELECT log_date, field_id, hours FROM activity_values WHERE user_id = ? AND log_date BETWEEN ? AND ? ORDER BY log_date ASC");
$stmt_vals->execute([$user_id, $start_date, $selected_date]);
$raw_data = $stmt_vals->fetchAll();

$data_matrix = [];
foreach ($raw_data as $row) { $data_matrix[$row['log_date']][$row['field_id']] = floatval($row['hours']); }

$total_score_period = 0; $days_logged = 0;
$today_score = 0; $today_donut = []; $today_donut_colors = []; $today_donut_labels = [];
$period_penalty_hrs = 0; $period_positive_hrs = 0; $period_sleep_hrs = 0;

for ($i = $days_to_subtract; $i >= 0; $i--) {
    $loop_date = date('Y-m-d', strtotime("-$i days", strtotime($selected_date)));
    $dates[] = date($graph_range >= 30 ? 'M d' : 'D, M d', strtotime($loop_date));
    
    $daily_active_hours = 0;
    $daily_score = 4.0; 

    foreach ($active_fields as $f) {
        $hrs = isset($data_matrix[$loop_date][$f['id']]) ? $data_matrix[$loop_date][$f['id']] : 0;
        $chart_datasets[$f['id']]['data'][] = $hrs;
        $daily_active_hours += $hrs;
        
        if ($f['weight'] > 0) {
            $daily_score += ($hrs * $f['weight']);
            $period_positive_hrs += $hrs;
        } elseif ($f['weight'] < 0) {
            if ($hrs > 1) { $daily_score += (($hrs - 1) * $f['weight']); }
            $period_penalty_hrs += $hrs;
        }

        if ($loop_date == $selected_date && $hrs > 0) {
            $today_donut[] = $hrs; $today_donut_labels[] = $f['field_name']; $today_donut_colors[] = $f['chart_color'];
        }
    }

    $calc_sleep = round(24 - ($daily_active_hours + 2), 1); 
    if($calc_sleep > 14) $calc_sleep = 8.0; if($calc_sleep < 0) $calc_sleep = 0.0;
    $sleep_data[] = $calc_sleep;
    $period_sleep_hrs += $calc_sleep;

    if ($calc_sleep < ($settings['sleep_target'] - 1.5)) { $daily_score -= 1.5; }

    $daily_score = max(0, min(10, round($daily_score, 1)));
    $total_score_period += $daily_score;
    $days_logged++;

    if ($loop_date == $selected_date) { $today_score = $daily_score; }
}

// Analytics Engine for the selected Time Range
$avg_score = $days_logged > 0 ? round($total_score_period / $days_logged, 1) : 0;
$avg_sleep = $days_logged > 0 ? round($period_sleep_hrs / $days_logged, 1) : 0;
$avg_positive = $days_logged > 0 ? round($period_positive_hrs / $days_logged, 1) : 0;

// Dynamic Feedback based on Average Score
$feedback_title = ""; $feedback_msg = ""; $feedback_color = ""; $feedback_icon = "";

if ($avg_score >= 8.5) {
    $feedback_title = "Elite Execution Phase";
    $feedback_msg = "Your momentum over the last $graph_range days is exceptional. Keep your system running exactly as it is.";
    $feedback_color = "#198754"; // Success Green
    $feedback_icon = "🔥";
} elseif ($avg_score >= 6.5) {
    $feedback_title = "Solid Baseline Maintained";
    $feedback_msg = "You are maintaining a good rhythm. To break into the elite tier, try to add 1 more hour of deep work to your daily average.";
    $feedback_color = "#0d6efd"; // Primary Blue
    $feedback_icon = "📈";
} else {
    if ($avg_sleep < ($settings['sleep_target'] - 1.0)) {
        $feedback_title = "Critical Sleep Deficit Warning";
        $feedback_msg = "Over the last $graph_range days, you averaged only {$avg_sleep}h of sleep. Your primary goal right now is rest to restore your cognitive baseline.";
        $feedback_color = "#dc3545"; // Danger Red
        $feedback_icon = "⚠️";
    } elseif ($period_penalty_hrs > ($graph_range * 1.5)) {
        $feedback_title = "High Distraction Penalty";
        $feedback_msg = "You leaked {$period_penalty_hrs}h to negative habits over this period. Try blocking distraction apps during your core work hours.";
        $feedback_color = "#dc3545"; // Danger Red
        $feedback_icon = "📉";
    } else {
        $feedback_title = "Low Momentum Detected";
        $feedback_msg = "You averaged only {$avg_positive}h of focused project work per day. You need to reprioritize your evening execution blocks.";
        $feedback_color = "#d97706"; // Deep Amber Warning
        $feedback_icon = "⚡";
    }
}

$pending_goals = $pdo->prepare("SELECT * FROM weekly_goals WHERE user_id = ? AND week_start_date = ? AND status != 'completed' ORDER BY id DESC");
$pending_goals->execute([$user_id, $current_week_start]);
$pending_goals = $pending_goals->fetchAll();

$js_datasets = array_values($chart_datasets);
$positive_momentum_datasets = array_values(array_filter($chart_datasets, function($d) { return $d['weight'] > 0; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CoreHour</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        body { background-color: #f4f6f8; }
        .score-box { background: #212529; color: #fff; padding: 12px 20px; border-radius: 8px; text-align: center; }
        .score-value { font-size: 1.8rem; font-weight: bold; line-height: 1; margin-top: 5px; }
        .chart-wrapper { background: #fff; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); height: 350px; display: flex; flex-direction: column; }
        .chart-canvas-container { flex-grow: 1; position: relative; width: 100%; height: 100%; }
        .feedback-container { background: #fff; border-left: 5px solid; border-radius: 8px; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-top: 15px; }
        .feedback-title { font-weight: bold; font-size: 0.9rem; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .feedback-text { font-size: 0.85rem; color: #6c757d; margin: 0; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand font-monospace fw-bold text-white text-decoration-none" href="index.php">COREHOUR</a>
        
        <div class="d-flex align-items-center gap-3">
            <span class="text-white-50 small font-monospace me-2 d-none d-md-block">
                <?= htmlspecialchars($full_name) ?>
            </span>
            <a href="settings.php" class="btn btn-outline-secondary btn-sm border-0 font-monospace">Settings</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm font-monospace border-0">Logout</a>
        </div>
    </div>
</nav>

    <div class="container-fluid px-4 mb-5">
        
        <?php if(isset($_GET['err']) && $_GET['err'] == 'demo'): ?>
            <div class="alert alert-warning small font-monospace fw-bold mb-4 shadow-sm border-warning border-start border-4">
                <i class="bi bi-shield-lock me-2"></i> DEMO MODE ACTIVE: Marking objectives as complete is restricted in the demo environment.
            </div>
        <?php endif; ?>

        <div class="row align-items-start mb-4">
            
            <div class="col-md-6 d-flex flex-column align-items-center align-items-md-start">
                <div class="d-flex gap-3 justify-content-center justify-content-md-start w-100">
                    <div class="score-box shadow-sm flex-fill flex-md-grow-0" style="min-width: 160px;">
                        <div class="text-white-50 font-monospace" style="font-size: 0.75rem; font-weight:bold;">TODAY'S SCORE</div>
                        <div class="score-value text-<?= $today_score >= 8 ? 'success' : ($today_score >= 5 ? 'warning' : 'danger') ?>"><?= $today_score ?></div>
                    </div>
                    <div class="score-box shadow-sm flex-fill flex-md-grow-0" style="background: #fff; color: #212529; border: 1px solid #ddd; min-width: 160px;">
                        <div class="text-muted font-monospace" style="font-size: 0.75rem; font-weight:bold;"><?= $graph_range ?>-DAY AVG</div>
                        <div class="score-value"><?= $avg_score ?></div>
                    </div>
                </div>
                
                <div class="feedback-container w-100" style="border-left-color: <?= $feedback_color ?>;">
                    <div class="feedback-title font-monospace" style="color: <?= $feedback_color ?>;">
                        <?= $feedback_icon ?> <?= $feedback_title ?>
                    </div>
                    <p class="feedback-text"><?= $feedback_msg ?></p>
                </div>
            </div>
            
            <div class="col-md-6 mt-3 mt-md-0">
                <form method="GET" class="d-flex justify-content-center justify-content-md-end align-items-center gap-2 flex-wrap">
                    <div class="d-flex align-items-center bg-white p-1 rounded border shadow-sm me-1">
                        <span class="small text-muted ms-2 me-2 font-monospace fw-bold">DATE:</span>
                        <input type="date" name="date" class="form-control form-control-sm border-0 shadow-none fw-bold" value="<?= $selected_date ?>" onchange="this.form.submit()">
                    </div>
                    <div class="d-flex align-items-center bg-white p-1 rounded border shadow-sm me-2">
                        <span class="small text-muted ms-2 me-2 font-monospace fw-bold">VIEW:</span>
                        <select name="range" class="form-select form-select-sm border-0 shadow-none fw-bold" onchange="this.form.submit()">
                            <option value="7" <?= $graph_range == 7 ? 'selected' : '' ?>>7 Days</option>
                            <option value="14" <?= $graph_range == 14 ? 'selected' : '' ?>>14 Days</option>
                            <option value="30" <?= $graph_range == 30 ? 'selected' : '' ?>>30 Days</option>
                        </select>
                    </div>
                    <a href="log_data.php?date=<?= $selected_date ?>" class="btn btn-dark btn-sm font-monospace shadow-sm" style="padding: 0.45rem 1.2rem;">+ LOG DATA</a>
                </form>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="chart-wrapper">
                    <h6 class="small text-muted font-monospace fw-bold mb-3 text-center">HUSTLE MOMENTUM (POSITIVE DRIVERS)</h6>
                    <div class="chart-canvas-container"><canvas id="momentumChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-wrapper">
                    <h6 class="small text-muted font-monospace fw-bold mb-3 text-center">ACTIVE DISTRIBUTION (<?= date('M d', strtotime($selected_date)) ?>)</h6>
                    <div class="chart-canvas-container"><canvas id="distributionChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-wrapper">
                    <h6 class="small text-muted font-monospace fw-bold mb-3 text-center">LINE TREND COMPARISON</h6>
                    <div class="chart-canvas-container"><canvas id="lineChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-wrapper">
                    <h6 class="small text-muted font-monospace fw-bold mb-3 text-center">FULL 24-HOUR COMPOSITION</h6>
                    <div class="chart-canvas-container"><canvas id="stackedChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm p-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h6 class="fw-bold font-monospace m-0">INCOMPLETE WEEKLY OBJECTIVES</h6>
                        <a href="log_data.php?date=<?= $selected_date ?>" class="btn btn-outline-dark btn-sm font-monospace">Manage All Objectives</a>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if(empty($pending_goals)): ?>
                            <div class="small text-muted mt-2">Awesome! No pending objectives for this week.</div>
                        <?php else: ?>
                            <?php foreach ($pending_goals as $goal): ?>
                                <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-bottom">
                                    <div>
                                        <span class="badge bg-secondary me-2" style="font-size:0.65rem;"><?= htmlspecialchars($goal['project_category']) ?></span>
                                        <span class="small fw-bold text-dark"><?= htmlspecialchars($goal['task_name']) ?></span>
                                        <?php if(!empty($goal['task_details'])): ?>
                                            <div class="text-muted small mt-1 font-monospace" style="font-size: 0.75rem;"><?= htmlspecialchars($goal['task_details']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="quick_complete_goal">
                                        <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                                        <input type="hidden" name="return_date" value="<?= $selected_date ?>">
                                        <button type="submit" class="btn btn-sm btn-success font-monospace shadow-sm">Complete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        Chart.register(ChartDataLabels);
        const chartDates = <?= json_encode($dates) ?>;
        const allDatasets = <?= json_encode($js_datasets) ?>;
        const momentumDatasets = <?= json_encode($positive_momentum_datasets) ?>;
        const sleepData = <?= json_encode($sleep_data) ?>;
        const donutData = <?= json_encode($today_donut) ?>;
        const donutLabels = <?= json_encode($today_donut_labels) ?>;
        const donutColors = <?= json_encode($today_donut_colors) ?>;

        Chart.defaults.plugins.tooltip.callbacks.label = function(context) { return context.dataset.label + ': ' + context.parsed.y + 'h'; };

        new Chart(document.getElementById('momentumChart'), {
            type: 'bar',
            data: { labels: chartDates, datasets: momentumDatasets },
            options: { responsive: true, maintainAspectRatio: false, plugins: { datalabels: { color: '#fff', font: { weight: 'bold', size: 10 }, formatter: v => v > 0 ? v + 'h' : '' } }, scales: { x: { stacked: true }, y: { stacked: true } } }
        });

        const totalHrs = donutData.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
        if (totalHrs > 0) {
            new Chart(document.getElementById('distributionChart'), {
                type: 'pie',
                data: { labels: donutLabels, datasets: [{ data: donutData, backgroundColor: donutColors }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed + 'h'; } } }, legend: { position: 'right' }, datalabels: { color: '#fff', font: { weight: 'bold', size: 14 }, formatter: (v, ctx) => v > 0 ? (v * 100 / totalHrs).toFixed(0) + "%" : '' } } }
            });
        }

        allDatasets.forEach(d => { d.tension = 0.3; d.fill = false; d.borderWidth = 2; });
        new Chart(document.getElementById('lineChart'), {
            type: 'line',
            data: { labels: chartDates, datasets: allDatasets },
            options: { responsive: true, maintainAspectRatio: false, plugins: { datalabels: { display: false } }, scales: { y: { suggestedMin: 0 } } }
        });

        const stackedDatasets = JSON.parse(JSON.stringify(allDatasets)); 
        stackedDatasets.unshift({ label: 'Sleep Target/Calc', data: sleepData, backgroundColor: '#e2e8f0' });
        
        new Chart(document.getElementById('stackedChart'), {
            type: 'bar',
            data: { labels: chartDates, datasets: stackedDatasets },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: {size: 10} } }, datalabels: { display: false } }, scales: { x: { stacked: true }, y: { stacked: true, suggestedMax: 24 } } }
        });
    </script>
    <?php require_once 'footer.php'; ?>
</body>
</html>