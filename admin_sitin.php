<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// Fetch ALL students for the dropdown
$allStudents = $pdo->query("
    SELECT id, id_number, first_name, last_name, course, year_level, sessions
    FROM students
    ORDER BY id_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

// IDs of students who already have an active sit-in
$activeSitinIdNumbers = $pdo->query("
    SELECT s.id_number
    FROM sit_in_records r
    JOIN students s ON r.student_id = s.id
    WHERE r.time_out IS NULL
")->fetchAll(PDO::FETCH_COLUMN);

// Currently sitting-in (read-only display)
$currentSitins = $pdo->query("
    SELECT s.id_number, s.first_name, s.last_name, s.course, s.year_level,
           r.purpose, r.lab, r.time_in
    FROM sit_in_records r
    JOIN students s ON r.student_id = s.id
    WHERE r.time_out IS NULL
    ORDER BY r.time_in DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in — CCS Admin</title>
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

        /* Sit-in nav trigger — styled as a gold button */
        .nav-links .btn-sitin-trigger {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--text-dark);
            font-weight: 700;
            padding: 0.35rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            font-family: 'Lato', sans-serif;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 2px 8px rgba(240,165,0,0.3);
            white-space: nowrap;
        }

        .nav-links .btn-sitin-trigger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(240,165,0,0.4);
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
        main { flex: 1; padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; }

        /* ── ALERTS ── */
        .alert {
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            max-width: 700px;
            margin: 0 auto;
            width: 100%;
        }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; color: #2e7d32; }
        .alert-error   { background: #fdecea; border-left: 4px solid #e53935; color: #b71c1c; }



        /* ── CURRENTLY SITTING IN TABLE ── */
        .panel {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(74,32,128,0.10);
            animation: slideUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .panel-header {
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple) 60%, var(--purple-light) 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .panel-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .count-badge {
            background: rgba(240,165,0,0.2);
            border: 1px solid rgba(240,165,0,0.4);
            border-radius: 20px;
            padding: 0.15rem 0.75rem;
            font-size: 0.75rem;
            font-family: 'Lato', sans-serif;
            color: var(--gold-light);
        }

        .table-wrap { overflow-x: auto; }

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

        .id-badge {
            background: rgba(74,32,128,0.08);
            color: var(--purple);
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-family: monospace;
        }

        .course-badge {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
        }

        .purpose-badge {
            background: rgba(240,165,0,0.12);
            color: #7a5800;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            border: 1px solid rgba(240,165,0,0.25);
        }

        .empty-state {
            text-align: center;
            padding: 2.5rem;
            color: var(--text-muted);
        }

        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

        /* ── SIT-IN MODAL ── */

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
            padding: 1.1rem 1.5rem;
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

        .modal-body { padding: 1.5rem 1.75rem; }

        /* inline warning inside modal */
        .modal-inline-warning {
            display: none;
            align-items: center;
            gap: 0.65rem;
            padding: 0.7rem 1rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .modal-inline-warning.active-warn {
            background: #fff5f5;
            border: 1.5px solid #f87171;
            color: #b91c1c;
        }
        .modal-inline-warning.nosess-warn {
            background: #fffbeb;
            border: 1.5px solid #f59e0b;
            color: #92400e;
        }
        .modal-inline-warning.show { display: flex; }

        .form-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.9rem;
        }

        .form-label-inline {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            min-width: 150px;
            text-align: right;
        }

        .form-control {
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

        .form-control:focus {
            border-color: var(--purple-light);
            background: white;
            box-shadow: 0 0 0 3px rgba(106,58,176,0.1);
        }

        .form-control:disabled {
            background: #f0edf8;
            color: var(--text-dark);
            cursor: default;
            border-color: #e0d8f0;
        }

        /* dropdown arrow override */
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%234a2080' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.2rem;
            cursor: pointer;
        }

        /* ID dropdown gets a purple border to signal it's the primary pick */
        #sitinIdDropdown {
            border-color: var(--purple-light);
            background-color: white;
            font-weight: 700;
            font-family: monospace;
            font-size: 0.92rem;
        }
        #sitinIdDropdown:focus {
            box-shadow: 0 0 0 3px rgba(74,32,128,0.18);
        }

        .modal-btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray);
        }

        .btn-close-modal {
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

        .btn-close-modal:hover { background: #5a6268; }

        .btn-confirm-sitin {
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
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
        }

        .btn-confirm-sitin:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(74,32,128,0.4);
        }

        .btn-confirm-sitin:disabled {
            opacity: 0.4;
            cursor: not-allowed;
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
            .form-label-inline { min-width: 110px; font-size: 0.78rem; }
        }
    </style>
</head>
<body>

<!-- ==================== NAVBAR ==================== -->
<nav>
    <span class="nav-brand">College of Computer Studies Admin</span>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php">Home</a></li>
        <li><a href="admin_search.php">Search</a></li>
        <li><a href="admin_students.php">Students</a></li>
        <li>
            <!-- Sit-in: click → modal opens immediately (no page redirect) -->
            <button class="btn-sitin-trigger" onclick="openSitinModal()">&#x2795; Sit-in</button>
        </li>
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

    <?php if ($success === 'sitin'): ?>
        <div class="alert alert-success">&#x2705; Student has been successfully registered for sit-in.</div>
    <?php endif; ?>
    <?php if ($error === 'already_active'): ?>
        <div class="alert alert-error">&#x1F6AB; This student is already in an active sit-in session. Please time them out first before registering a new session.</div>
    <?php elseif ($error === 'already_sitin'): ?>
        <div class="alert alert-error">&#x1F6AB; This student is already currently sitting in.</div>
    <?php elseif ($error === 'no_sessions'): ?>
        <div class="alert alert-error">&#x26A0;&#xFE0F; This student has no remaining sessions.</div>
    <?php elseif ($error === 'missing_fields'): ?>
        <div class="alert alert-error">&#x26A0;&#xFE0F; Please fill in all required fields.</div>
    <?php endif; ?>

    <!-- Currently Sitting In — read-only, no action column -->
    <div class="panel">
        <div class="panel-header">
             Currently Sitting In
            <span class="count-badge"><?= count($currentSitins) ?> active</span>
        </div>
        <div class="table-wrap">
            <?php if (empty($currentSitins)): ?>
                <div class="empty-state">
                    <div class="empty-icon">&#x1FA91;</div>
                    <p>No students are currently sitting in.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Purpose</th>
                            <th>Lab</th>
                            <th>Time In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentSitins as $row): ?>
                            <tr>
                                <td><span class="id-badge"><?= htmlspecialchars($row['id_number']) ?></span></td>
                                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                <td><span class="course-badge"><?= htmlspecialchars($row['course']) ?></span></td>
                                <td><?= htmlspecialchars($row['year_level']) ?></td>
                                <td><span class="purpose-badge"><?= htmlspecialchars($row['purpose']) ?></span></td>
                                <td><?= htmlspecialchars($row['lab']) ?></td>
                                <td><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ==================== SIT-IN MODAL ==================== -->
<div class="modal-overlay" id="sitinModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Sit In Form</span>
            <button class="modal-close" onclick="closeModal()">&#x2715;</button>
        </div>
        <div class="modal-body">

            <!-- warnings (shown via JS) -->
            <div class="modal-inline-warning active-warn" id="warnActive">
                &#x1F534; This student already has an active sit-in session. Time them out first.
            </div>
            <div class="modal-inline-warning nosess-warn" id="warnNoSess">
                &#x26A0;&#xFE0F; This student has no remaining sessions.
            </div>

            <form method="POST" action="admin_sitin_register.php" id="sitinForm">
                <input type="hidden" name="student_id" id="sitinStudentId">

                <!-- ID Number dropdown — primary selection -->
                <div class="form-row">
                    <label class="form-label-inline" for="sitinIdDropdown">ID Number:</label>
                    <select id="sitinIdDropdown" class="form-control" onchange="onStudentPick(this)">
                        <option value="">— Select Student ID —</option>
                        <?php foreach ($allStudents as $s): ?>
                            <option
                                value="<?= $s['id'] ?>"
                                data-idnum="<?= htmlspecialchars($s['id_number']) ?>"
                                data-name="<?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>"
                                data-course="<?= htmlspecialchars($s['course']) ?>"
                                data-year="<?= htmlspecialchars($s['year_level']) ?>"
                                data-sessions="<?= intval($s['sessions'] ?? 30) ?>"
                                data-active="<?= in_array($s['id_number'], $activeSitinIdNumbers) ? '1' : '0' ?>"
                            ><?= htmlspecialchars($s['id_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Auto-filled read-only fields -->
                <div class="form-row">
                    <label class="form-label-inline">Student Name:</label>
                    <input type="text" class="form-control" id="sitinName" disabled placeholder="—">
                </div>

                <div class="form-row">
                    <label class="form-label-inline">Purpose:</label>
                    <select name="purpose" class="form-control" required>
                        <option value="">-- Select --</option>
                        <option value="C#">C#</option>
                        <option value="Java">Java</option>
                        <option value="C">C</option>
                        <option value="C Programming">C Programming</option>
                        <option value="ASP.Net">ASP.Net</option>
                        <option value="PHP">PHP</option>
                        <option value="Python">Python</option>
                        <option value="Research">Research</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label-inline">Lab:</label>
                    <select name="lab" class="form-control" required>
                        <option value="">-- Select --</option>
                        <option value="517">517</option>
                        <option value="518">518</option>
                        <option value="519">519</option>
                        <option value="524">524</option>
                        <option value="526">526</option>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label-inline">Remaining Session:</label>
                    <input type="text" class="form-control" id="sitinSessions" disabled placeholder="—">
                </div>

                <div class="modal-btn-row">
                    <button type="button" class="btn-close-modal" onclick="closeModal()">Close</button>
                    <button type="submit" class="btn-confirm-sitin" id="sitinSubmitBtn" disabled>Sit In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== FOOTER ==================== -->
<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

<script>
    function openSitinModal() {
        // Reset every time
        document.getElementById('sitinIdDropdown').value = '';
        document.getElementById('sitinStudentId').value  = '';
        document.getElementById('sitinName').value        = '';
        document.getElementById('sitinSessions').value    = '';
        document.getElementById('sitinSubmitBtn').disabled = true;
        document.getElementById('warnActive').classList.remove('show');
        document.getElementById('warnNoSess').classList.remove('show');
        // Reset purpose + lab too
        document.querySelectorAll('#sitinForm select[name="purpose"], #sitinForm select[name="lab"]')
                .forEach(s => s.value = '');
        document.getElementById('sitinModal').classList.add('open');
        setTimeout(() => document.getElementById('sitinIdDropdown').focus(), 100);
    }

    function closeModal() {
        document.getElementById('sitinModal').classList.remove('open');
    }

    function onStudentPick(sel) {
        const opt = sel.options[sel.selectedIndex];

        // Hide both warnings first
        document.getElementById('warnActive').classList.remove('show');
        document.getElementById('warnNoSess').classList.remove('show');

        if (!opt.value) {
            document.getElementById('sitinStudentId').value = '';
            document.getElementById('sitinName').value       = '';
            document.getElementById('sitinSessions').value   = '';
            document.getElementById('sitinSubmitBtn').disabled = true;
            return;
        }

        // Auto-fill
        document.getElementById('sitinStudentId').value = opt.value;
        document.getElementById('sitinName').value       = opt.dataset.name;
        document.getElementById('sitinSessions').value   = opt.dataset.sessions;

        const isActive   = opt.dataset.active === '1';
        const noSessions = parseInt(opt.dataset.sessions) <= 0;

        if (isActive) {
            document.getElementById('warnActive').classList.add('show');
            document.getElementById('sitinSubmitBtn').disabled = true;
        } else if (noSessions) {
            document.getElementById('warnNoSess').classList.add('show');
            document.getElementById('sitinSubmitBtn').disabled = true;
        } else {
            document.getElementById('sitinSubmitBtn').disabled = false;
        }
    }

    // Click backdrop to close
    document.getElementById('sitinModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
</script>

</body>
</html>