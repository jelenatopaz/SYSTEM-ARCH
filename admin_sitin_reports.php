<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit; }

// ── CSV Export ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $pdo->query("
        SELECT s.id_number, s.first_name, s.last_name, s.course, s.year_level,
               r.id as record_id, r.purpose, r.lab, r.time_in, r.time_out
        FROM sit_in_records r JOIN students s ON s.id = r.student_id
        ORDER BY r.time_in DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sitin_report_'.date('Ymd').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Record ID','ID Number','First Name','Last Name','Course','Year','Purpose','Lab','Time In','Time Out','Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['record_id'], $r['id_number'], $r['first_name'], $r['last_name'],
            $r['course'], $r['year_level'], $r['purpose'], $r['lab'],
            $r['time_in'], $r['time_out'] ?? '', $r['time_out'] ? 'Done' : 'Active'
        ]);
    }
    fclose($out); exit;
}

$all_records = $pdo->query("
    SELECT s.id_number, s.first_name, s.last_name, s.course, s.year_level,
           r.id as record_id, r.purpose, r.lab, r.time_in, r.time_out
    FROM sit_in_records r JOIN students s ON s.id = r.student_id
    ORDER BY r.time_in DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_records  = count($all_records);
$active_records = count(array_filter($all_records, fn($r) => !$r['time_out']));
$done_records   = $total_records - $active_records;
$active_page    = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in Reports — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--purple:#4a2080;--purple-dark:#2e1260;--purple-light:#6a3ab0;--gold:#f0a500;--gold-light:#ffd060;--gray:#f5f3fa;--text-dark:#1a1030;--text-muted:#7a6a9a}
        body{font-family:'Lato',sans-serif;background:var(--gray);min-height:100vh;display:flex;flex-direction:column}
        <?php include 'admin_nav_css.php'; ?>
        main{flex:1;padding:1.5rem 2rem}
        h1{font-family:'Cinzel',serif;font-size:1.5rem;color:var(--purple-dark);text-align:center;margin-bottom:0.25rem}
        .subtitle{text-align:center;color:var(--text-muted);font-size:0.85rem;margin-bottom:1.5rem}

        .stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .stat-card{flex:1;min-width:150px;background:white;border-radius:12px;padding:1rem 1.25rem;box-shadow:0 2px 12px rgba(74,32,128,0.08);border-left:4px solid var(--purple);display:flex;align-items:center;gap:0.75rem}
        .stat-card:nth-child(2){border-left-color:var(--gold)}
        .stat-card:nth-child(3){border-left-color:#4caf50}
        .stat-icon{font-size:1.75rem}
        .stat-info{}
        .stat-val{font-family:'Cinzel',serif;font-size:1.4rem;font-weight:700;color:var(--purple-dark);line-height:1}
        .stat-card:nth-child(2) .stat-val{color:var(--gold)}
        .stat-card:nth-child(3) .stat-val{color:#2e7d32}
        .stat-lbl{font-size:0.78rem;color:var(--text-muted);margin-top:2px}

        .panel{background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(74,32,128,0.10)}
        .panel-header{background:linear-gradient(135deg,var(--purple-dark) 0%,var(--purple) 60%,var(--purple-light) 100%);color:white;padding:0.8rem 1.5rem;font-family:'Cinzel',serif;font-size:0.82rem;letter-spacing:0.06em;display:flex;align-items:center;justify-content:space-between;position:relative}
        .panel-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
        .count-badge{background:rgba(240,165,0,0.2);border:1px solid rgba(240,165,0,0.4);border-radius:20px;padding:0.15rem 0.75rem;font-size:0.72rem;font-family:'Lato',sans-serif;color:var(--gold-light)}

        .export-bar{display:flex;align-items:center;gap:0.6rem;padding:1rem 1.5rem 0;flex-wrap:wrap}
        .export-bar span{font-size:0.8rem;color:var(--text-muted);font-weight:600}
        .btn-exp{display:inline-flex;align-items:center;gap:0.4rem;padding:0.45rem 1.1rem;border-radius:7px;font-size:0.8rem;font-weight:700;font-family:'Lato',sans-serif;cursor:pointer;border:none;transition:transform 0.15s,box-shadow 0.15s;text-decoration:none}
        .btn-exp:hover{transform:translateY(-1px)}
        .btn-csv{background:#217346;color:white;box-shadow:0 2px 6px rgba(33,115,70,0.3)}
        .btn-xlsx{background:#1d6f42;color:white;box-shadow:0 2px 6px rgba(29,111,66,0.3)}
        .btn-pdf{background:#c0392b;color:white;box-shadow:0 2px 6px rgba(192,57,43,0.3)}
        .btn-docx{background:#2b579a;color:white;box-shadow:0 2px 6px rgba(43,87,154,0.3)}

        .filter-bar{display:flex;align-items:center;gap:1rem;padding:0.75rem 1.5rem;flex-wrap:wrap}
        .filter-bar label{font-size:0.8rem;color:var(--text-muted);font-weight:600}
        .filter-bar select,.filter-bar input{padding:0.35rem 0.7rem;border:1.5px solid #ddd6f0;border-radius:7px;font-family:'Lato',sans-serif;font-size:0.82rem;background:var(--gray);outline:none;color:var(--text-dark)}
        .filter-bar select:focus,.filter-bar input:focus{border-color:var(--purple-light)}
        .search-wrap{display:flex;align-items:center;gap:0.4rem;margin-left:auto}
        .search-wrap input{width:200px}

        .table-wrap{overflow-x:auto;padding:0 1.5rem 1.5rem}
        table{width:100%;border-collapse:collapse;font-size:0.84rem}
        thead th{background:var(--gray);color:var(--text-muted);font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:0.65rem 0.85rem;text-align:left;border-bottom:2px solid #ede8fa;cursor:pointer;user-select:none;white-space:nowrap}
        thead th:hover{color:var(--purple)}
        tbody tr{border-bottom:1px solid rgba(74,32,128,0.06);transition:background 0.15s}
        tbody tr:last-child{border-bottom:none}
        tbody tr:hover{background:rgba(74,32,128,0.03)}
        td{padding:0.65rem 0.85rem;color:var(--text-dark);vertical-align:middle}
        .badge{display:inline-block;font-size:0.7rem;font-weight:700;padding:0.18rem 0.6rem;border-radius:20px}
        .badge-active{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
        .badge-done{background:#ede8fa;color:var(--purple);border:1px solid #c8b8f0}
        .no-records{text-align:center;padding:3rem;color:var(--text-muted)}
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
        <li><a href="admin_sitin_reports.php" class="active">Reports</a></li>
        <li><a href="admin_reservation.php">Reservation</a></li>
        <li><a href="admin_leaderboard.php">Leaderboard</a></li>
        <li><a href="admin_analytics.php">Analytics</a></li>
        <li><a href="admin_feedback.php">Feedback</a></li>
        <li>
            <form method="POST" action="admin_logout.php" style="display:inline;">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </li>
    </ul>
</nav>

<main>
    <h1>📄 Sit-in Reports</h1>
    <p class="subtitle">Generate and export sit-in session reports</p>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-icon">📋</span>
            <div class="stat-info">
                <div class="stat-val"><?= $total_records ?></div>
                <div class="stat-lbl">Total Records</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⏳</span>
            <div class="stat-info">
                <div class="stat-val"><?= $active_records ?></div>
                <div class="stat-lbl">Currently Active</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">✅</span>
            <div class="stat-info">
                <div class="stat-val"><?= $done_records ?></div>
                <div class="stat-lbl">Completed</div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            📄 Sit-in Records
            <span class="count-badge"><?= $total_records ?> records</span>
        </div>

        <!-- Export Buttons -->
        <div class="export-bar">
            <span>Export:</span>
            <a href="admin_sitin_reports.php?export=csv" class="btn-exp btn-csv">📥 CSV</a>
            <button onclick="exportXLSX()" class="btn-exp btn-xlsx">📊 Excel</button>
            <button onclick="exportPDF()" class="btn-exp btn-pdf">📕 PDF</button>
            <button onclick="exportDOCX()" class="btn-exp btn-docx">📘 DOCX</button>
        </div>

        <!-- Filter / Search Bar -->
        <div class="filter-bar">
            <label for="statusFilter">Status:</label>
            <select id="statusFilter" onchange="filterTable()">
                <option value="">All</option>
                <option value="Active">Active</option>
                <option value="Done">Done</option>
            </select>
            <label for="labFilter">Lab:</label>
            <select id="labFilter" onchange="filterTable()">
                <option value="">All Labs</option>
                <?php
                $labs = array_unique(array_column($all_records, 'lab'));
                sort($labs);
                foreach ($labs as $lab) echo '<option>'.htmlspecialchars($lab).'</option>';
                ?>
            </select>
            <div class="search-wrap">
                <label>Search:</label>
                <input type="text" id="searchInput" placeholder="Name, ID, purpose…" oninput="filterTable()">
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table id="recordsTable">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">#</th>
                        <th onclick="sortTable(1)">ID Number</th>
                        <th onclick="sortTable(2)">Name</th>
                        <th onclick="sortTable(3)">Course / Year</th>
                        <th onclick="sortTable(4)">Purpose</th>
                        <th onclick="sortTable(5)">Lab</th>
                        <th onclick="sortTable(6)">Time In</th>
                        <th onclick="sortTable(7)">Time Out</th>
                        <th onclick="sortTable(8)">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($all_records)): ?>
                    <tr><td colspan="9" class="no-records">No sit-in records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($all_records as $i => $r): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($r['id_number']) ?></td>
                        <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
                        <td><?= htmlspecialchars($r['course']) ?> / <?= htmlspecialchars($r['year_level']) ?></td>
                        <td><?= htmlspecialchars($r['purpose']) ?></td>
                        <td><?= htmlspecialchars($r['lab']) ?></td>
                        <td><?= htmlspecialchars($r['time_in']) ?></td>
                        <td><?= $r['time_out'] ? htmlspecialchars($r['time_out']) : '—' ?></td>
                        <td>
                            <?php if ($r['time_out']): ?>
                                <span class="badge badge-done">Done</span>
                            <?php else: ?>
                                <span class="badge badge-active">Active</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<footer>&copy; 2026 College of Computer Studies &mdash; University of Cebu</footer>

<script>
// ── Filter ──
function filterTable() {
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const lab    = document.getElementById('labFilter').value.toLowerCase();
    const search = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#recordsTable tbody tr').forEach(row => {
        const rowText = row.textContent.toLowerCase();
        const rowStatus = row.cells[8]?.textContent.trim().toLowerCase() ?? '';
        const rowLab    = row.cells[5]?.textContent.trim().toLowerCase() ?? '';
        const statusOk  = !status || rowStatus === status;
        const labOk     = !lab    || rowLab === lab;
        const searchOk  = !search || rowText.includes(search);
        row.style.display = (statusOk && labOk && searchOk) ? '' : 'none';
    });
}

// ── Sort ──
let sortDir = {};
function sortTable(col) {
    const tbody = document.querySelector('#recordsTable tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    sortDir[col] = !sortDir[col];
    rows.sort((a, b) => {
        const at = a.cells[col]?.textContent.trim() ?? '';
        const bt = b.cells[col]?.textContent.trim() ?? '';
        return sortDir[col] ? at.localeCompare(bt) : bt.localeCompare(at);
    });
    rows.forEach(r => tbody.appendChild(r));
}

// ── Get visible rows ──
function getVisibleRows() {
    return Array.from(document.querySelectorAll('#recordsTable tbody tr'))
        .filter(r => r.style.display !== 'none');
}

// ── Export XLSX ──
function exportXLSX() {
    const headers = ['#','ID Number','Name','Course/Year','Purpose','Lab','Time In','Time Out','Status'];
    const data = [headers];
    getVisibleRows().forEach((row, i) => {
        data.push([
            i+1,
            row.cells[1].textContent.trim(),
            row.cells[2].textContent.trim(),
            row.cells[3].textContent.trim(),
            row.cells[4].textContent.trim(),
            row.cells[5].textContent.trim(),
            row.cells[6].textContent.trim(),
            row.cells[7].textContent.trim(),
            row.cells[8].textContent.trim()
        ]);
    });
    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [{wch:5},{wch:12},{wch:22},{wch:16},{wch:18},{wch:8},{wch:20},{wch:20},{wch:8}];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sit-in Records');
    XLSX.writeFile(wb, 'sitin_report_<?= date('Ymd') ?>.xlsx');
}

// ── Export PDF ──
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });
    doc.setFont('helvetica','bold');
    doc.setFontSize(14);
    doc.text('CCS Sit-in Report', doc.internal.pageSize.getWidth()/2, 15, {align:'center'});
    doc.setFontSize(9);
    doc.setFont('helvetica','normal');
    doc.text('Generated: <?= date('Y-m-d H:i') ?>', doc.internal.pageSize.getWidth()/2, 22, {align:'center'});
    const headers = [['#','ID Number','Name','Course/Year','Purpose','Lab','Time In','Time Out','Status']];
    const rows = getVisibleRows().map((row, i) => [
        i+1,
        row.cells[1].textContent.trim(),
        row.cells[2].textContent.trim(),
        row.cells[3].textContent.trim(),
        row.cells[4].textContent.trim(),
        row.cells[5].textContent.trim(),
        row.cells[6].textContent.trim(),
        row.cells[7].textContent.trim(),
        row.cells[8].textContent.trim()
    ]);
    doc.autoTable({ head: headers, body: rows, startY: 28, styles:{fontSize:7.5}, headStyles:{fillColor:[74,32,128]} });
    doc.save('sitin_report_<?= date('Ymd') ?>.pdf');
}

// ── Export DOCX (plain HTML → blob) ──
function exportDOCX() {
    let html = `<html><head><meta charset="UTF-8">
    <style>
        body{font-family:Calibri,sans-serif;font-size:11pt}
        h2{color:#4a2080;text-align:center}
        p.sub{color:#666;text-align:center;font-size:9pt}
        table{border-collapse:collapse;width:100%;font-size:9pt}
        th{background:#4a2080;color:white;padding:5px 8px;text-align:left}
        td{padding:4px 8px;border-bottom:1px solid #ddd}
        tr:nth-child(even) td{background:#f5f3fa}
        .done{color:#4a2080} .active{color:#2e7d32;font-weight:bold}
    </style></head><body>
    <h2>CCS Sit-in Report</h2>
    <p class="sub">Generated: <?= date('Y-m-d H:i') ?></p>
    <table><thead><tr>
        <th>#</th><th>ID Number</th><th>Name</th><th>Course/Year</th>
        <th>Purpose</th><th>Lab</th><th>Time In</th><th>Time Out</th><th>Status</th>
    </tr></thead><tbody>`;
    getVisibleRows().forEach((row, i) => {
        const status = row.cells[8].textContent.trim();
        html += `<tr>
            <td>${i+1}</td>
            <td>${row.cells[1].textContent.trim()}</td>
            <td>${row.cells[2].textContent.trim()}</td>
            <td>${row.cells[3].textContent.trim()}</td>
            <td>${row.cells[4].textContent.trim()}</td>
            <td>${row.cells[5].textContent.trim()}</td>
            <td>${row.cells[6].textContent.trim()}</td>
            <td>${row.cells[7].textContent.trim()}</td>
            <td class="${status.toLowerCase()}">${status}</td>
        </tr>`;
    });
    html += '</tbody></table></body></html>';
    const blob = new Blob(['\ufeff', html], {type:'application/msword'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'sitin_report_<?= date('Ymd') ?>.doc';
    a.click();
}
</script>
</body>
</html>