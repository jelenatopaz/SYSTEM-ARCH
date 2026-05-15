<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit; }

// ── Stats ──
$total_students  = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_sitin     = $pdo->query("SELECT COUNT(*) FROM sit_in_records")->fetchColumn();
$active_sitin    = $pdo->query("SELECT COUNT(*) FROM sit_in_records WHERE time_out IS NULL")->fetchColumn();
$total_points    = $pdo->query("SELECT SUM(points) FROM students")->fetchColumn() ?? 0;
$total_reservations = 0;
try { $total_reservations = $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(); } catch(Exception $e){}

// Purpose breakdown
$purpose_rows = $pdo->query("SELECT purpose, COUNT(*) as cnt FROM sit_in_records GROUP BY purpose ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);

// Lab breakdown
$lab_rows = $pdo->query("SELECT lab, COUNT(*) as cnt FROM sit_in_records GROUP BY lab ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);

// Daily sit-ins (last 14 days)
$daily_rows = $pdo->query("
    SELECT DATE(time_in) as day, COUNT(*) as cnt
    FROM sit_in_records
    WHERE time_in >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(time_in)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Top students by sessions
$top_students = $pdo->query("
    SELECT s.first_name, s.last_name, s.id_number, s.course,
           COUNT(r.id) as total, s.points
    FROM students s
    LEFT JOIN sit_in_records r ON r.student_id = s.id
    GROUP BY s.id
    ORDER BY total DESC, s.points DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Course breakdown
$course_rows = $pdo->query("SELECT course, COUNT(*) as cnt FROM students GROUP BY course ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);

$active_page = 'analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--purple:#4a2080;--purple-dark:#2e1260;--purple-light:#6a3ab0;--gold:#f0a500;--gold-light:#ffd060;--gray:#f5f3fa;--text-dark:#1a1030;--text-muted:#7a6a9a}
        body{font-family:'Lato',sans-serif;background:var(--gray);min-height:100vh;display:flex;flex-direction:column}
        <?php include 'admin_nav_css.php'; ?>
        main{flex:1;padding:1.5rem 2rem}
        h1{font-family:'Cinzel',serif;font-size:1.5rem;color:var(--purple-dark);text-align:center;margin-bottom:0.3rem}
        .subtitle{text-align:center;color:var(--text-muted);font-size:0.85rem;margin-bottom:1.75rem}

        /* KPI cards */
        .kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.75rem}
        .kpi{background:white;border-radius:14px;padding:1.1rem 1.25rem;box-shadow:0 2px 12px rgba(74,32,128,0.09);border-top:4px solid var(--purple);text-align:center;transition:transform 0.2s}
        .kpi:hover{transform:translateY(-2px)}
        .kpi:nth-child(2){border-top-color:var(--gold)}
        .kpi:nth-child(3){border-top-color:#4caf50}
        .kpi:nth-child(4){border-top-color:#2196f3}
        .kpi:nth-child(5){border-top-color:#e91e63}
        .kpi-icon{font-size:1.75rem;margin-bottom:0.35rem}
        .kpi-val{font-family:'Cinzel',serif;font-size:1.65rem;font-weight:700;color:var(--purple-dark)}
        .kpi:nth-child(2) .kpi-val{color:var(--gold)}
        .kpi:nth-child(3) .kpi-val{color:#2e7d32}
        .kpi:nth-child(4) .kpi-val{color:#1565c0}
        .kpi:nth-child(5) .kpi-val{color:#880e4f}
        .kpi-lbl{font-size:0.78rem;color:var(--text-muted);margin-top:3px}

        /* Chart grid */
        .charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem}
        .chart-card{background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(74,32,128,0.09)}
        .chart-header{background:linear-gradient(135deg,var(--purple-dark),var(--purple),var(--purple-light));color:white;padding:0.75rem 1.25rem;font-family:'Cinzel',serif;font-size:0.82rem;letter-spacing:0.06em;position:relative}
        .chart-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
        .chart-body{padding:1.25rem;height:260px;display:flex;align-items:center;justify-content:center}
        .chart-body canvas{max-height:220px}

        /* Top students table */
        .panel{background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(74,32,128,0.09)}
        .panel-header{background:linear-gradient(135deg,var(--purple-dark),var(--purple),var(--purple-light));color:white;padding:0.75rem 1.5rem;font-family:'Cinzel',serif;font-size:0.82rem;letter-spacing:0.06em;position:relative}
        .panel-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
        table{width:100%;border-collapse:collapse;font-size:0.85rem}
        thead th{background:var(--gray);color:var(--text-muted);font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:0.65rem 1.25rem;text-align:left;border-bottom:2px solid #ede8fa}
        tbody td{padding:0.65rem 1.25rem;color:var(--text-dark);border-bottom:1px solid rgba(74,32,128,0.06)}
        tbody tr:last-child td{border-bottom:none}
        tbody tr:hover td{background:rgba(74,32,128,0.03)}
        .rank-badge{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;font-family:'Cinzel',serif;font-weight:700;font-size:0.78rem}
        .rank-1{background:var(--gold);color:var(--text-dark)}
        .rank-2{background:#b0bec5;color:white}
        .rank-3{background:#cd7f32;color:white}
        .rank-other{background:var(--gray);color:var(--text-muted)}

        @media(max-width:900px){.charts-grid{grid-template-columns:1fr}}
        footer{background:var(--purple-dark);color:rgba(255,255,255,0.45);text-align:center;padding:1rem;font-size:0.8rem}
    </style>
</head>
<body>

<nav>
    <span class="nav-brand">CCS Admin</span>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php">Home</a></li>
        <li><a href="admin_search.php">Search</a></li>
        <li><a href="admin_students.php">Students</a></li>
        <li><a href="admin_sitin.php">Sit-in</a></li>
        <li><a href="admin_current_sitin.php">Current Sit-in</a></li>
        <li><a href="admin_sitin_records.php">Sit-in Records</a></li>
        <li><a href="admin_sitin_reports.php">Reports</a></li>
        <li><a href="admin_reservation.php">Reservation</a></li>
        <li><a href="admin_leaderboard.php">Leaderboard</a></li>
        <li><a href="admin_analytics.php" class="active">Analytics</a></li>
        <li><a href="admin_feedback.php">Feedback</a></li>
        <li>
            <form method="POST" action="admin_logout.php" style="display:inline;">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </li>
    </ul>
</nav>

<main>
    <h1>📊 Analytics Dashboard</h1>
    <p class="subtitle">System-wide statistics and usage insights</p>

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-icon">👥</div>
            <div class="kpi-val"><?= (int)$total_students ?></div>
            <div class="kpi-lbl">Registered Students</div>
        </div>
        <div class="kpi">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-val"><?= (int)$active_sitin ?></div>
            <div class="kpi-lbl">Currently Sitting In</div>
        </div>
        <div class="kpi">
            <div class="kpi-icon">📋</div>
            <div class="kpi-val"><?= (int)$total_sitin ?></div>
            <div class="kpi-lbl">Total Sit-in Sessions</div>
        </div>
        <div class="kpi">
            <div class="kpi-icon">📅</div>
            <div class="kpi-val"><?= (int)$total_reservations ?></div>
            <div class="kpi-lbl">Total Reservations</div>
        </div>
        <div class="kpi">
            <div class="kpi-icon">⭐</div>
            <div class="kpi-val"><?= (int)$total_points ?></div>
            <div class="kpi-lbl">Points Awarded</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <!-- Daily sit-ins line chart -->
        <div class="chart-card">
            <div class="chart-header">📈 Daily Sit-ins (Last 14 Days)</div>
            <div class="chart-body">
                <?php if(empty($daily_rows)): ?>
                    <p style="color:var(--text-muted);font-size:0.85rem">No data yet.</p>
                <?php else: ?>
                    <canvas id="dailyChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Purpose pie chart -->
        <div class="chart-card">
            <div class="chart-header">🎯 Sit-ins by Purpose</div>
            <div class="chart-body">
                <?php if(empty($purpose_rows)): ?>
                    <p style="color:var(--text-muted);font-size:0.85rem">No data yet.</p>
                <?php else: ?>
                    <canvas id="purposeChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lab bar chart -->
        <div class="chart-card">
            <div class="chart-header">🖥️ Sit-ins by Lab</div>
            <div class="chart-body">
                <?php if(empty($lab_rows)): ?>
                    <p style="color:var(--text-muted);font-size:0.85rem">No data yet.</p>
                <?php else: ?>
                    <canvas id="labChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Course distribution -->
        <div class="chart-card">
            <div class="chart-header">🎓 Students by Course</div>
            <div class="chart-body">
                <?php if(empty($course_rows)): ?>
                    <p style="color:var(--text-muted);font-size:0.85rem">No data yet.</p>
                <?php else: ?>
                    <canvas id="courseChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Students -->
    <div class="panel">
        <div class="panel-header">🏅 Top 5 Most Active Students</div>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Name</th>
                    <th>ID Number</th>
                    <th>Course</th>
                    <th>Total Sessions</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($top_students)): ?>
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted)">No student data.</td></tr>
            <?php else: ?>
                <?php foreach($top_students as $i => $s): ?>
                <tr>
                    <td><span class="rank-badge rank-<?= $i < 3 ? $i+1 : 'other' ?>"><?= $i+1 ?></span></td>
                    <td><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
                    <td><?= htmlspecialchars($s['id_number']) ?></td>
                    <td><?= htmlspecialchars($s['course']) ?></td>
                    <td><strong><?= (int)$s['total'] ?></strong></td>
                    <td>⭐ <?= (int)$s['points'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<footer>&copy; 2026 College of Computer Studies &mdash; University of Cebu</footer>

<script>
const COLORS = ['#4a2080','#f0a500','#6a3ab0','#ffd060','#2e1260','#e57373','#64b5f6','#81c784','#ffb74d','#ba68c8'];
const commonOpts = { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ labels:{ font:{family:'Lato',size:10}, boxWidth:12, padding:8 } } } };

<?php if(!empty($daily_rows)): ?>
new Chart(document.getElementById('dailyChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($daily_rows,'day')) ?>,
        datasets: [{ label:'Sit-ins', data: <?= json_encode(array_column($daily_rows,'cnt')) ?>,
            borderColor:'#4a2080', backgroundColor:'rgba(74,32,128,0.1)', tension:0.4, fill:true,
            pointBackgroundColor:'#f0a500', pointRadius:4 }]
    },
    options: { ...commonOpts, scales:{ y:{ beginAtZero:true, ticks:{precision:0} } } }
});
<?php endif; ?>

<?php if(!empty($purpose_rows)): ?>
new Chart(document.getElementById('purposeChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($purpose_rows,'purpose')) ?>,
        datasets: [{ data: <?= json_encode(array_column($purpose_rows,'cnt')) ?>,
            backgroundColor: COLORS.slice(0, <?= count($purpose_rows) ?>),
            borderColor:'#fff', borderWidth:2 }]
    },
    options: commonOpts
});
<?php endif; ?>

<?php if(!empty($lab_rows)): ?>
new Chart(document.getElementById('labChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($lab_rows,'lab')) ?>,
        datasets: [{ label:'Sessions', data: <?= json_encode(array_column($lab_rows,'cnt')) ?>,
            backgroundColor: COLORS.slice(0, <?= count($lab_rows) ?>),
            borderRadius:6 }]
    },
    options: { ...commonOpts, plugins:{...commonOpts.plugins, legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{precision:0}}} }
});
<?php endif; ?>

<?php if(!empty($course_rows)): ?>
new Chart(document.getElementById('courseChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($course_rows,'course')) ?>,
        datasets: [{ data: <?= json_encode(array_column($course_rows,'cnt')) ?>,
            backgroundColor: COLORS.slice(0, <?= count($course_rows) ?>),
            borderColor:'#fff', borderWidth:2 }]
    },
    options: commonOpts
});
<?php endif; ?>
</script>
</body>
</html>