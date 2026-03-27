<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$students = [];
$searched = false;
$query    = trim($_POST['query'] ?? $_GET['query'] ?? '');

if (!empty($query)) {
    $searched = true;
    $like = "%$query%";
    $stmt = $pdo->prepare("
        SELECT * FROM students
        WHERE id_number   LIKE ?
           OR first_name  LIKE ?
           OR last_name   LIKE ?
           OR course      LIKE ?
        ORDER BY last_name ASC
    ");
    $stmt->execute([$like, $like, $like, $like]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Students — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple:      #4a2080;
            --purple-dark: #2e1260;
            --purple-light:#6a3ab0;
            --gold:        #f0a500;
            --gold-light:  #ffd060;
            --white:       #ffffff;
            --gray:        #f5f3fa;
            --text-dark:   #1a1030;
            --text-muted:  #7a6a9a;
        }

        body {
            font-family: 'Lato', sans-serif;
            background: var(--gray);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── NAVBAR ── */
        nav {
            background: var(--purple-dark);
            padding: 0 1.5rem;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .nav-brand {
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            color: var(--gold);
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.1rem;
            list-style: none;
        }

        .nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
            border-radius: 5px;
            transition: background 0.2s, color 0.2s;
            white-space: nowrap;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(240,165,0,0.15);
            color: var(--gold-light);
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--text-dark);
            font-weight: 700;
            border: none;
            padding: 0.35rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            font-family: 'Lato', sans-serif;
            transition: transform 0.15s, box-shadow 0.15s;
            margin-left: 0.4rem;
            box-shadow: 0 2px 8px rgba(240,165,0,0.3);
        }

        .btn-logout:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(240,165,0,0.4);
        }

        /* ── MAIN ── */
        main {
            flex: 1;
            padding: 2rem;
        }

        /* ── SEARCH CARD ── */
        .search-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(74,32,128,0.10);
            max-width: 700px;
            margin: 0 auto 2rem;
            animation: slideUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .search-card-header {
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple) 60%, var(--purple-light) 100%);
            color: white;
            padding: 0.85rem 1.5rem;
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            letter-spacing: 0.06em;
            position: relative;
        }

        .search-card-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .search-card-body {
            padding: 1.75rem;
        }

        .search-row {
            display: flex;
            gap: 0.75rem;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1.5px solid #ddd6f0;
            border-radius: 10px;
            font-family: 'Lato', sans-serif;
            font-size: 0.95rem;
            color: var(--text-dark);
            background: var(--gray);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input:focus {
            border-color: var(--purple-light);
            background: white;
            box-shadow: 0 0 0 3px rgba(106,58,176,0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 10px;
            font-family: 'Lato', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 3px 12px rgba(74,32,128,0.3);
            white-space: nowrap;
        }

        .btn-search:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(74,32,128,0.4);
        }

        .search-hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 0.6rem;
        }

        /* ── RESULTS PANEL ── */
        .results-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(74,32,128,0.10);
            max-width: 1000px;
            margin: 0 auto;
            animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
            animation-delay: 0.05s;
        }

        .results-header {
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple) 60%, var(--purple-light) 100%);
            color: white;
            padding: 0.85rem 1.5rem;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .results-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .results-count {
            background: rgba(240,165,0,0.25);
            border: 1px solid rgba(240,165,0,0.4);
            border-radius: 20px;
            padding: 0.15rem 0.75rem;
            font-size: 0.75rem;
            font-family: 'Lato', sans-serif;
            color: var(--gold-light);
        }

        /* ── TABLE ── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        thead th {
            background: var(--gray);
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 0.75rem 1.25rem;
            text-align: left;
            border-bottom: 2px solid #ede8fa;
        }

        tbody tr {
            border-bottom: 1px solid rgba(74,32,128,0.06);
            transition: background 0.15s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #faf8ff; }

        tbody td {
            padding: 0.85rem 1.25rem;
            color: var(--text-dark);
            vertical-align: middle;
        }

        /* ID badge */
        .id-badge {
            background: rgba(74,32,128,0.08);
            color: var(--purple);
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-family: monospace;
        }

        /* Course badge */
        .course-badge {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
        }

        /* Session badge */
        .session-badge {
            background: rgba(240,165,0,0.15);
            color: #7a5800;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            border: 1px solid rgba(240,165,0,0.3);
        }

        /* Action buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            font-family: 'Lato', sans-serif;
            border: none;
            cursor: pointer;
            transition: transform 0.12s, box-shadow 0.12s;
            text-decoration: none;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            box-shadow: 0 2px 8px rgba(74,32,128,0.25);
        }

        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74,32,128,0.35);
        }

        .btn-sitin {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--text-dark);
            box-shadow: 0 2px 8px rgba(240,165,0,0.25);
        }

        .btn-sitin:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(240,165,0,0.35);
        }

        .actions-cell {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        /* Empty / no results */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }

        .empty-state .empty-icon {
            font-size: 3rem;
            margin-bottom: 0.75rem;
        }

        .empty-state p { font-size: 0.9rem; }

        /* ── STUDENT DETAIL MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30,10,60,0.45);
            z-index: 200;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(74,32,128,0.25);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            animation: popIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.92); }
            to   { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--purple-dark), var(--purple));
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .modal-title {
            font-family: 'Cinzel', serif;
            color: var(--gold);
            font-size: 0.95rem;
        }

        .modal-close {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            width: 28px; height: 28px;
            border-radius: 50%;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .modal-close:hover { background: rgba(255,255,255,0.25); }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-avatar {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .modal-avatar img {
            width: 90px; height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(74,32,128,0.15);
            box-shadow: 0 4px 16px rgba(74,32,128,0.15);
        }

        .modal-avatar-placeholder {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            font-family: 'Cinzel', serif;
            border: 3px solid rgba(240,165,0,0.3);
        }

        .modal-divider {
            width: 40px; height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 0 auto 1.25rem;
        }

        .modal-info-row {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(74,32,128,0.07);
            font-size: 0.875rem;
        }

        .modal-info-row:last-child { border-bottom: none; }

        .modal-info-label {
            font-weight: 700;
            color: var(--text-muted);
            font-size: 0.78rem;
            min-width: 80px;
        }

        .modal-info-value { color: var(--text-dark); }

        /* Sit-in modal */
        .sitin-modal-body { padding: 1.5rem 1.75rem; }

        .sitin-form-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.9rem;
        }

        .sitin-label-inline {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            min-width: 150px;
            text-align: right;
        }

        .sitin-input, .sitin-select {
            flex: 1;
            padding: 0.6rem 0.9rem;
            border: 1.5px solid #ddd6f0;
            border-radius: 8px;
            font-family: 'Lato', sans-serif;
            font-size: 0.875rem;
            color: var(--text-dark);
            background: var(--gray);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .sitin-select:focus {
            border-color: var(--purple-light);
            background: white;
            box-shadow: 0 0 0 3px rgba(106,58,176,0.1);
        }

        .sitin-input:disabled {
            background: #f0edf8;
            color: var(--text-dark);
            cursor: default;
            border-color: #e0d8f0;
        }

        .sitin-btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray);
        }

        .btn-close-form {
            padding: 0.55rem 1.5rem;
            border-radius: 8px;
            font-family: 'Lato', sans-serif;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            background: #6c757d;
            color: white;
            border: none;
            transition: background 0.2s;
        }

        .btn-close-form:hover { background: #5a6268; }

        .btn-sitin-confirm {
            padding: 0.55rem 1.75rem;
            border-radius: 8px;
            font-family: 'Lato', sans-serif;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            border: none;
            box-shadow: 0 3px 12px rgba(74,32,128,0.3);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-sitin-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(74,32,128,0.4);
        }

        footer {
            background: var(--purple-dark);
            color: rgba(255,255,255,0.45);
            text-align: center;
            padding: 1rem;
            font-size: 0.8rem;
        }

        @media (max-width: 600px) {
            main { padding: 1rem; }
            .search-row { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- ==================== NAVBAR ==================== -->
<nav>
    <span class="nav-brand">College of Computer Studies Admin</span>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php">Home</a></li>
        <li><a href="admin_search.php" class="active">Search</a></li>
        <li><a href="admin_students.php">Students</a></li>
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

<!-- ==================== MAIN ==================== -->
<main>

    <!-- Search Box -->
    <div class="search-card">
        <div class="search-card-header">🔍 Search Student</div>
        <div class="search-card-body">
            <form method="POST" action="admin_search.php">
                <div class="search-row">
                    <input
                        type="text"
                        name="query"
                        class="search-input"
                        placeholder="Search..."
                        value="<?= htmlspecialchars($query) ?>"
                        autofocus
                    >
                    <button type="submit" class="btn-search">🔍 Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if ($searched): ?>
    <div class="results-card">
        <div class="results-header">
            <span>Search Results for "<?= htmlspecialchars($query) ?>"</span>
            <span class="results-count"><?= count($students) ?> found</span>
        </div>

        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-icon">🔎</div>
                <p>No students found matching <strong>"<?= htmlspecialchars($query) ?>"</strong>.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Email</th>
                            <th>Sessions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><span class="id-badge"><?= htmlspecialchars($s['id_number']) ?></span></td>
                            <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td><span class="course-badge"><?= htmlspecialchars($s['course']) ?></span></td>
                            <td><?= htmlspecialchars($s['year_level']) ?></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td><span class="session-badge"><?= htmlspecialchars($s['sessions'] ?? 30) ?></span></td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action btn-view"
                                        onclick="openViewModal(<?= htmlspecialchars(json_encode($s)) ?>)">
                                        👁 View
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<!-- ==================== VIEW MODAL ==================== -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">👤 Student Details</span>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="modal-avatar">
                <div class="modal-avatar-placeholder" id="modalAvatar">S</div>
            </div>
            <div class="modal-divider"></div>
            <div class="modal-info-row">
                <span class="modal-info-label">ID Number</span>
                <span class="modal-info-value" id="mIdNumber">—</span>
            </div>
            <div class="modal-info-row">
                <span class="modal-info-label">Full Name</span>
                <span class="modal-info-value" id="mFullName">—</span>
            </div>
            <div class="modal-info-row">
                <span class="modal-info-label">Course</span>
                <span class="modal-info-value" id="mCourse">—</span>
            </div>
            <div class="modal-info-row">
                <span class="modal-info-label">Year Level</span>
                <span class="modal-info-value" id="mYear">—</span>
            </div>
            <div class="modal-info-row">
                <span class="modal-info-label">Email</span>
                <span class="modal-info-value" id="mEmail">—</span>
            </div>
            <div class="modal-info-row">
                <span class="modal-info-label">Address</span>
                <span class="modal-info-value" id="mAddress">—</span>
            </div>
            <div class="modal-info-row">
                <span class="modal-info-label">Sessions</span>
                <span class="modal-info-value" id="mSessions">—</span>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FOOTER ==================== -->
<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

<script>
    // Close modal when clicking overlay background
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    function openViewModal(s) {
        document.getElementById('mIdNumber').textContent  = s.id_number  || '—';
        document.getElementById('mFullName').textContent  = s.first_name + ' ' + s.last_name;
        document.getElementById('mCourse').textContent    = s.course     || '—';
        document.getElementById('mYear').textContent      = s.year_level || '—';
        document.getElementById('mEmail').textContent     = s.email      || '—';
        document.getElementById('mAddress').textContent   = s.address    || '—';
        document.getElementById('mSessions').textContent  = s.sessions   || 30;
        document.getElementById('modalAvatar').textContent = (s.first_name || 'S').charAt(0).toUpperCase();
        document.getElementById('viewModal').classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
        }
    });
</script>

</body>
</html>