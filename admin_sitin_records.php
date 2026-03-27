<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$all_records = $pdo->query("
    SELECT s.id_number, s.first_name, s.last_name, s.course, s.year_level, s.sessions,
           r.id as record_id, r.purpose, r.lab, r.time_in, r.time_out
    FROM sit_in_records r
    JOIN students s ON s.id = r.student_id
    ORDER BY r.time_in DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Sit-in Records — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --purple:#4a2080; --purple-dark:#2e1260; --purple-light:#6a3ab0;
            --gold:#f0a500; --gold-light:#ffd060; --gray:#f5f3fa;
            --text-dark:#1a1030; --text-muted:#7a6a9a;
        }
        body { font-family:'Lato',sans-serif; background:var(--gray); min-height:100vh; display:flex; flex-direction:column; }

        nav { background:var(--purple-dark); padding:0 1.5rem; height:52px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 12px rgba(0,0,0,0.3); position:sticky; top:0; z-index:50; }
        .nav-brand { font-family:'Cinzel',serif; font-size:0.9rem; color:var(--gold); letter-spacing:0.04em; white-space:nowrap; }
        .nav-links { display:flex; align-items:center; gap:0.1rem; list-style:none; }
        .nav-links a, .nav-btn { color:rgba(255,255,255,0.85); text-decoration:none; font-size:0.8rem; padding:0.35rem 0.65rem; border-radius:5px; transition:background 0.2s,color 0.2s; white-space:nowrap; background:none; border:none; font-family:'Lato',sans-serif; cursor:pointer; }
        .nav-links a:hover, .nav-btn:hover, .nav-links a.active { background:rgba(240,165,0,0.15); color:var(--gold-light); }
        .btn-logout { background:linear-gradient(135deg,var(--gold),var(--gold-light)); color:var(--text-dark); font-weight:700; border:none; padding:0.35rem 1rem; border-radius:6px; font-size:0.8rem; cursor:pointer; font-family:'Lato',sans-serif; margin-left:0.4rem; box-shadow:0 2px 8px rgba(240,165,0,0.3); }

        main { flex:1; padding:2rem; }

        h1 { font-family:'Cinzel',serif; font-size:1.6rem; color:var(--purple-dark); text-align:center; margin-bottom:1.5rem; }

        .panel { background:white; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(74,32,128,0.10); }
        .panel-header { background:linear-gradient(135deg,var(--purple-dark) 0%,var(--purple) 60%,var(--purple-light) 100%); color:white; padding:0.8rem 1.5rem; font-family:'Cinzel',serif; font-size:0.85rem; letter-spacing:0.06em; display:flex; align-items:center; justify-content:space-between; position:relative; }
        .panel-header::after { content:''; position:absolute; bottom:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,var(--gold),transparent); }
        .count-badge { background:rgba(240,165,0,0.2); border:1px solid rgba(240,165,0,0.4); border-radius:20px; padding:0.15rem 0.75rem; font-size:0.75rem; font-family:'Lato',sans-serif; color:var(--gold-light); }

        .table-controls { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; flex-wrap:wrap; gap:0.75rem; }
        .entries-row { display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-muted); }
        .entries-row select { padding:0.3rem 0.5rem; border:1.5px solid #ddd6f0; border-radius:6px; font-family:'Lato',sans-serif; font-size:0.85rem; background:var(--gray); outline:none; }
        .search-control { display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-muted); }
        .search-control input { padding:0.4rem 0.8rem; border:1.5px solid #ddd6f0; border-radius:6px; font-family:'Lato',sans-serif; font-size:0.85rem; background:var(--gray); outline:none; }
        .search-control input:focus { border-color:var(--purple-light); }

        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        thead th { background:var(--gray); color:var(--text-muted); font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; padding:0.75rem 1.25rem; text-align:left; border-bottom:2px solid #ede8fa; cursor:pointer; user-select:none; }
        thead th:hover { color:var(--purple); }
        tbody tr { border-bottom:1px solid rgba(74,32,128,0.06); transition:background 0.15s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#faf8ff; }
        tbody td { padding:0.85rem 1.25rem; color:var(--text-dark); vertical-align:middle; }

        .id-badge { background:rgba(74,32,128,0.08); color:var(--purple); font-weight:700; font-size:0.8rem; padding:0.2rem 0.6rem; border-radius:6px; font-family:monospace; }
        .purpose-badge { background:rgba(240,165,0,0.12); color:#7a5800; font-size:0.75rem; font-weight:700; padding:0.2rem 0.6rem; border-radius:6px; border:1px solid rgba(240,165,0,0.25); }
        .status-active { color:#4caf50; font-weight:700; font-size:0.8rem; }
        .status-done { color:#9e9e9e; font-weight:700; font-size:0.8rem; }

        .table-footer { display:flex; align-items:center; justify-content:space-between; padding:0.85rem 1.5rem; border-top:1px solid #ede8fa; font-size:0.82rem; color:var(--text-muted); flex-wrap:wrap; gap:0.5rem; }
        .pagination { display:flex; gap:0.3rem; }
        .page-btn { padding:0.3rem 0.65rem; border:1.5px solid #ddd6f0; border-radius:5px; background:white; font-size:0.82rem; cursor:pointer; font-family:'Lato',sans-serif; color:var(--text-dark); transition:background 0.15s; }
        .page-btn:hover, .page-btn.active { background:var(--purple); color:white; border-color:var(--purple); }

        footer { background:var(--purple-dark); color:rgba(255,255,255,0.45); text-align:center; padding:1rem; font-size:0.8rem; }
    </style>
</head>
<body>

<nav>
    <span class="nav-brand">College of Computer Studies Admin</span>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php">Home</a></li>
        <li><a href="admin_search.php">Search</a></li>
        <li><a href="admin_students.php">Students</a></li>
        <li><a href="admin_sitin.php">Sit in</a></li>
        <li><a href="admin_current_sitin.php">Current Sit in</a></li>
        <li><a href="admin_sitin_records.php" class="active">View Sit-in Records</a></li>
        <li><a href="admin_sitin_reports.php">Sit-in Reports</a></li>
        <li><a href="admin_feedback.php">Feedback Reports</a></li>
        <li><a href="admin_reservation.php">Reservation</a></li>
        <li>
            <form method="POST" action="admin_logout.php" style="display:inline;">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </li>
    </ul>
</nav>

<main>
    <h1>View Sit-in Records</h1>

    <div class="panel">
        <div class="panel-header">
            <span>📋 All Sit-in Records</span>
            <span class="count-badge" id="totalCount"><?= count($all_records) ?> total</span>
        </div>

        <div class="table-controls">
            <div class="entries-row">
                <select id="entriesSelect" onchange="renderTable()">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                entries per page
            </div>
            <div class="search-control">
                Search: <input type="text" id="tableSearch" oninput="renderTable()" placeholder="">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">Sit ID Number ⇅</th>
                        <th onclick="sortTable(1)">ID Number ⇅</th>
                        <th onclick="sortTable(2)">Name ⇅</th>
                        <th onclick="sortTable(3)">Purpose ⇅</th>
                        <th onclick="sortTable(4)">Sit Lab ⇅</th>
                        <th onclick="sortTable(5)">Session ⇅</th>
                        <th onclick="sortTable(6)">Time In ⇅</th>
                        <th onclick="sortTable(7)">Time Out ⇅</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        <div class="table-footer">
            <span id="showingInfo">Showing 0 to 0 of 0 entries</span>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
</main>

<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

<script>
const rawData = <?= json_encode(array_values($all_records)) ?>;
let filtered = [...rawData];
let currentPage = 1;
let sortCol = -1;
let sortAsc = true;

function formatDateTime(dt) {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit', hour12:true });
}

function renderTable() {
    const q = document.getElementById('tableSearch').value.toLowerCase();
    const perPage = parseInt(document.getElementById('entriesSelect').value);

    filtered = rawData.filter(r => {
        const name = (r.first_name + ' ' + r.last_name).toLowerCase();
        const status = r.time_out ? 'done' : 'active';
        return r.id_number.toLowerCase().includes(q)
            || name.includes(q)
            || (r.purpose||'').toLowerCase().includes(q)
            || (r.lab||'').toLowerCase().includes(q)
            || String(r.record_id).includes(q)
            || status.includes(q);
    });

    if (sortCol >= 0) {
        filtered.sort((a, b) => {
            const vals = [
                [a.record_id, b.record_id],
                [a.id_number, b.id_number],
                [a.first_name+' '+a.last_name, b.first_name+' '+b.last_name],
                [a.purpose||'', b.purpose||''],
                [a.lab||'', b.lab||''],
                [a.sessions??0, b.sessions??0],
                [a.time_in||'', b.time_in||''],
                [a.time_out||'', b.time_out||''],
            ];
            const [av, bv] = vals[sortCol] || ['',''];
            return sortAsc ? String(av).localeCompare(String(bv), undefined, {numeric:true})
                           : String(bv).localeCompare(String(av), undefined, {numeric:true});
        });
    }

    const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * perPage;
    const pageData = filtered.slice(start, start + perPage);

    const tbody = document.getElementById('tableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted);">No data available</td></tr>`;
    } else {
        tbody.innerHTML = pageData.map(r => {
            const isActive = !r.time_out;
            const statusHtml = isActive
                ? `<span class="status-active">● Active</span>`
                : `<span class="status-done">✓ Done</span>`;
            return `
            <tr>
                <td><span class="id-badge">${r.record_id}</span></td>
                <td><span class="id-badge">${r.id_number}</span></td>
                <td>${r.first_name} ${r.last_name}</td>
                <td><span class="purpose-badge">${r.purpose||'—'}</span></td>
                <td>${r.lab||'—'}</td>
                <td>${r.sessions??0}</td>
                <td style="font-size:0.8rem;color:var(--text-muted);">${formatDateTime(r.time_in)}</td>
                <td style="font-size:0.8rem;color:var(--text-muted);">${formatDateTime(r.time_out)}</td>
                <td>${statusHtml}</td>
            </tr>`;
        }).join('');
    }

    document.getElementById('showingInfo').textContent =
        filtered.length === 0 ? 'Showing 0 to 0 of 0 entries'
        : `Showing ${start + 1} to ${Math.min(start + perPage, filtered.length)} of ${filtered.length} entries`;

    const pg = document.getElementById('pagination');
    pg.innerHTML = '';
    const addBtn = (label, page, active=false, disabled=false) => {
        const b = document.createElement('button');
        b.className = 'page-btn' + (active ? ' active' : '');
        b.textContent = label;
        b.disabled = disabled;
        b.onclick = () => { currentPage = page; renderTable(); };
        pg.appendChild(b);
    };
    addBtn('«', 1, false, currentPage===1);
    for (let i = 1; i <= totalPages; i++) addBtn(i, i, i===currentPage);
    addBtn('»', totalPages, false, currentPage===totalPages);
}

function sortTable(col) {
    if (sortCol === col) sortAsc = !sortAsc;
    else { sortCol = col; sortAsc = true; }
    renderTable();
}

renderTable();
</script>

</body>
</html>