<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Handle delete
if (isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    header("Location: admin_students.php?deleted=1");
    exit;
}

// Handle reset all sessions
if (isset($_POST['reset_sessions'])) {
    $pdo->query("UPDATE students SET sessions = 30");
    header("Location: admin_students.php?reset=1");
    exit;
}

$students = $pdo->query("SELECT * FROM students ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$edit_student = null;

// Load student for edit
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit_student = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle edit save
$edit_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student_id'])) {
    $sid        = (int)$_POST['edit_student_id'];
    $last_name  = trim($_POST['last_name']  ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $course     = trim($_POST['course']     ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $email      = trim($_POST['email']      ?? '');
    $address    = trim($_POST['address']    ?? '');
    $sessions   = (int)($_POST['sessions']  ?? 30);

    $pdo->prepare("UPDATE students SET last_name=?,first_name=?,course=?,year_level=?,email=?,address=?,sessions=? WHERE id=?")
        ->execute([$last_name,$first_name,$course,$year_level,$email,$address,$sessions,$sid]);
    header("Location: admin_students.php?updated=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — CCS Admin</title>
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
        .nav-links a:hover,.nav-links a.active { background:rgba(240,165,0,0.15); color:var(--gold-light); }
        .btn-logout { background:linear-gradient(135deg,var(--gold),var(--gold-light)); color:var(--text-dark); font-weight:700; border:none; padding:0.35rem 1rem; border-radius:6px; font-size:0.8rem; cursor:pointer; font-family:'Lato',sans-serif; margin-left:0.4rem; }

        main { flex:1; padding:2rem; }
        h1 { font-family:'Cinzel',serif; font-size:1.6rem; color:var(--purple-dark); text-align:center; margin-bottom:1.5rem; }

        .alert { padding:0.7rem 1.25rem; border-radius:8px; font-size:0.85rem; margin-bottom:1rem; }
        .alert-success { background:#e8f5e9; border-left:4px solid #4caf50; color:#2e7d32; }

        .top-actions { display:flex; gap:0.75rem; margin-bottom:1.25rem; }

        .btn-add { background:linear-gradient(135deg,#1976d2,#42a5f5); color:white; border:none; padding:0.55rem 1.25rem; border-radius:8px; font-family:'Lato',sans-serif; font-size:0.875rem; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(25,118,210,0.3); transition:transform 0.15s; }
        .btn-add:hover { transform:translateY(-1px); }

        .btn-reset-all { background:linear-gradient(135deg,#f44336,#ef9a9a); color:white; border:none; padding:0.55rem 1.25rem; border-radius:8px; font-family:'Lato',sans-serif; font-size:0.875rem; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(244,67,54,0.3); transition:transform 0.15s; }
        .btn-reset-all:hover { transform:translateY(-1px); }

        .panel { background:white; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(74,32,128,0.10); }

        .table-controls { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; flex-wrap:wrap; gap:0.75rem; }
        .entries-row { display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-muted); }
        .entries-row select { padding:0.3rem 0.5rem; border:1.5px solid #ddd6f0; border-radius:6px; font-family:'Lato',sans-serif; font-size:0.85rem; background:var(--gray); outline:none; }
        .search-control { display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-muted); }
        .search-control input { padding:0.4rem 0.8rem; border:1.5px solid #ddd6f0; border-radius:6px; font-family:'Lato',sans-serif; font-size:0.85rem; background:var(--gray); outline:none; }

        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        thead th { background:var(--gray); color:var(--text-muted); font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; padding:0.75rem 1.25rem; text-align:left; border-bottom:2px solid #ede8fa; cursor:pointer; }
        thead th:hover { color:var(--purple); }
        tbody tr { border-bottom:1px solid rgba(74,32,128,0.06); transition:background 0.15s; }
        tbody tr:hover { background:#faf8ff; }
        tbody td { padding:0.75rem 1.25rem; color:var(--text-dark); vertical-align:middle; }

        .btn-edit { background:#1976d2; color:white; border:none; padding:0.3rem 0.85rem; border-radius:5px; font-size:0.78rem; font-weight:700; font-family:'Lato',sans-serif; cursor:pointer; transition:background 0.2s; }
        .btn-edit:hover { background:#1565c0; }
        .btn-delete { background:#e53935; color:white; border:none; padding:0.3rem 0.85rem; border-radius:5px; font-size:0.78rem; font-weight:700; font-family:'Lato',sans-serif; cursor:pointer; transition:background 0.2s; }
        .btn-delete:hover { background:#c62828; }

        .table-footer { display:flex; align-items:center; justify-content:space-between; padding:0.85rem 1.5rem; border-top:1px solid #ede8fa; font-size:0.82rem; color:var(--text-muted); flex-wrap:wrap; gap:0.5rem; }
        .pagination { display:flex; gap:0.3rem; }
        .page-btn { padding:0.3rem 0.65rem; border:1.5px solid #ddd6f0; border-radius:5px; background:white; font-size:0.82rem; cursor:pointer; font-family:'Lato',sans-serif; color:var(--text-dark); }
        .page-btn:hover,.page-btn.active { background:var(--purple); color:white; border-color:var(--purple); }

        /* Edit Modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(30,10,60,0.45); z-index:200; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
        .modal-overlay.open { display:flex; }
        .modal { background:white; border-radius:16px; box-shadow:0 20px 60px rgba(74,32,128,0.25); width:100%; max-width:500px; overflow:hidden; animation:popIn 0.3s cubic-bezier(0.22,1,0.36,1) both; }
        @keyframes popIn { from{opacity:0;transform:scale(0.92)}to{opacity:1;transform:scale(1)} }
        .modal-header { background:linear-gradient(135deg,var(--purple-dark),var(--purple)); padding:1rem 1.5rem; display:flex; align-items:center; justify-content:space-between; position:relative; }
        .modal-header::after { content:''; position:absolute; bottom:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,var(--gold),transparent); }
        .modal-title { font-family:'Cinzel',serif; color:var(--gold); font-size:0.95rem; }
        .modal-close { background:rgba(255,255,255,0.15); border:none; color:white; width:28px; height:28px; border-radius:50%; font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .modal-close:hover { background:rgba(255,255,255,0.3); }
        .modal-body { padding:1.5rem; }
        .mform-group { margin-bottom:0.9rem; }
        .mform-group label { display:block; font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.35rem; }
        .mform-group input, .mform-group select { width:100%; padding:0.6rem 0.9rem; border:1.5px solid #ddd6f0; border-radius:8px; font-family:'Lato',sans-serif; font-size:0.875rem; color:var(--text-dark); background:var(--gray); outline:none; }
        .mform-group input:focus, .mform-group select:focus { border-color:var(--purple-light); background:white; }
        .mform-group input:disabled { background:#ede8fa; color:var(--text-muted); cursor:not-allowed; }
        .mrow2 { display:flex; gap:1rem; }
        .mrow2 .mform-group { flex:1; }
        .modal-btn-row { display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--gray); }
        .btn-cancel { padding:0.55rem 1.5rem; border-radius:8px; font-family:'Lato',sans-serif; font-size:0.875rem; font-weight:700; cursor:pointer; background:#6c757d; color:white; border:none; }
        .btn-save { padding:0.55rem 1.75rem; border-radius:8px; font-family:'Lato',sans-serif; font-size:0.875rem; font-weight:700; cursor:pointer; background:linear-gradient(135deg,var(--purple),var(--purple-light)); color:white; border:none; }

        footer { background:var(--purple-dark); color:rgba(255,255,255,0.45); text-align:center; padding:1rem; font-size:0.8rem; }
    </style>
</head>
<body>

<nav>
    <span class="nav-brand">College of Computer Studies Admin</span>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php">Home</a></li>
        <li><a href="admin_search.php">Search</a></li>
        <li><a href="admin_students.php" class="active">Students</a></li>
        <li><a href="admin_sitin.php">Sit-in</a></li>
        <li><a href="admin_current_sitin.php">View Sit-in Records</a></li>
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
    <h1>Students Information</h1>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">✅ Student deleted successfully.</div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="alert alert-success">✅ Student updated successfully.</div>
    <?php elseif (isset($_GET['reset'])): ?>
        <div class="alert alert-success">✅ All sessions reset to 30.</div>
    <?php endif; ?>

    <div class="top-actions">
        <button class="btn-add" onclick="window.location='register.php'">Add Students</button>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Reset ALL student sessions to 30?')">
            <button type="submit" name="reset_sessions" class="btn-reset-all">Reset All Session</button>
        </form>
    </div>

    <div class="panel">
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
                Search: <input type="text" id="tableSearch" oninput="renderTable()">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">ID Number ⇅</th>
                        <th onclick="sortTable(1)">Name ⇅</th>
                        <th onclick="sortTable(2)">Year Level ⇅</th>
                        <th onclick="sortTable(3)">Course ⇅</th>
                        <th onclick="sortTable(4)">Remaining Session ⇅</th>
                        <th>Actions</th>
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

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">✏️ Edit Student</span>
            <button class="modal-close" onclick="closeEdit()">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="admin_students.php">
                <input type="hidden" name="edit_student_id" id="editId">
                <div class="mform-group">
                    <label>ID Number</label>
                    <input type="text" id="editIdNumber" disabled>
                </div>
                <div class="mrow2">
                    <div class="mform-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="editLastName">
                    </div>
                    <div class="mform-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="editFirstName">
                    </div>
                </div>
                <div class="mrow2">
                    <div class="mform-group">
                        <label>Course</label>
                        <select name="course" id="editCourse">
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSIS">BSIS</option>
                            <option value="ACT">ACT</option>
                        </select>
                    </div>
                    <div class="mform-group">
                        <label>Year Level</label>
                        <select name="year_level" id="editYear">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>
                <div class="mform-group">
                    <label>Email</label>
                    <input type="email" name="email" id="editEmail">
                </div>
                <div class="mform-group">
                    <label>Address</label>
                    <input type="text" name="address" id="editAddress">
                </div>
                <div class="mform-group">
                    <label>Sessions</label>
                    <input type="number" name="sessions" id="editSessions" min="0" max="30">
                </div>
                <div class="modal-btn-row">
                    <button type="button" class="btn-cancel" onclick="closeEdit()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

<script>
const rawData = <?= json_encode(array_values($students)) ?>;
let filtered = [...rawData];
let currentPage = 1;
let sortCol = -1;
let sortAsc = true;

function renderTable() {
    const q = document.getElementById('tableSearch').value.toLowerCase();
    const perPage = parseInt(document.getElementById('entriesSelect').value);

    filtered = rawData.filter(r => {
        const name = (r.first_name + ' ' + r.last_name).toLowerCase();
        return r.id_number.toLowerCase().includes(q)
            || name.includes(q)
            || (r.course||'').toLowerCase().includes(q)
            || String(r.year_level).includes(q);
    });

    if (sortCol >= 0) {
        filtered.sort((a, b) => {
            const vals = [
                [a.id_number, b.id_number],
                [a.first_name+' '+a.last_name, b.first_name+' '+b.last_name],
                [a.year_level, b.year_level],
                [a.course||'', b.course||''],
                [a.sessions??30, b.sessions??30],
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
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">No data available</td></tr>`;
    } else {
        tbody.innerHTML = pageData.map(r => `
            <tr>
                <td>${r.id_number}</td>
                <td>${r.first_name} ${r.last_name}</td>
                <td>${r.year_level}</td>
                <td>${r.course}</td>
                <td>${r.sessions ?? 30}</td>
                <td style="display:flex;gap:0.4rem;">
                    <button class="btn-edit" onclick='openEdit(${JSON.stringify(r)})'>Edit</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this student?')">
                        <input type="hidden" name="delete_id" value="${r.id}">
                        <button type="submit" class="btn-delete">Delete</button>
                    </form>
                </td>
            </tr>
        `).join('');
    }

    document.getElementById('showingInfo').textContent =
        filtered.length === 0 ? 'Showing 0 to 0 of 0 entries'
        : `Showing ${start+1} to ${Math.min(start+perPage, filtered.length)} of ${filtered.length} entries`;

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

function openEdit(s) {
    document.getElementById('editId').value        = s.id;
    document.getElementById('editIdNumber').value  = s.id_number;
    document.getElementById('editLastName').value  = s.last_name;
    document.getElementById('editFirstName').value = s.first_name;
    document.getElementById('editCourse').value    = s.course;
    document.getElementById('editYear').value      = s.year_level;
    document.getElementById('editEmail').value     = s.email;
    document.getElementById('editAddress').value   = s.address;
    document.getElementById('editSessions').value  = s.sessions ?? 30;
    document.getElementById('editModal').classList.add('open');
}

function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});

renderTable();
</script>

</body>
</html>