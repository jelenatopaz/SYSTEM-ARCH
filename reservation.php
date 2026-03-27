<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$success = '';
$error   = '';

// ── Handle Purpose+Lab submit (first step) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_purpose'])) {
    $_SESSION['res_purpose'] = trim($_POST['purpose'] ?? '');
    $_SESSION['res_lab']     = trim($_POST['lab'] ?? '');
}

// ── Handle Reserve submit ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $purpose    = trim($_POST['purpose'] ?? '');
    $lab        = trim($_POST['lab'] ?? '');
    $time_in    = trim($_POST['time_in'] ?? '');
    $date       = trim($_POST['date'] ?? '');
    $student_id = $_SESSION['student_id'] ?? null;
    $sessions   = $_SESSION['sessions'] ?? 0;

    if (!$purpose || !$lab || !$time_in || !$date) {
        $error = 'Please fill in all required fields.';
    } elseif ($sessions <= 0) {
        $error = 'You have no remaining sessions.';
    } else {
        try {
            // Combine date + time into a datetime string
            $datetime_in = $date . ' ' . $time_in . ':00';

            $stmt = $pdo->prepare("
                INSERT INTO sit_in_records (student_id, purpose, lab, time_in)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $purpose, $lab, $datetime_in]);

            // Decrement sessions
            $pdo->prepare("UPDATE students SET sessions = sessions - 1 WHERE id = ?")
                ->execute([$student_id]);
            $_SESSION['sessions'] = $sessions - 1;

            $success = 'Reservation submitted successfully!';
            unset($_SESSION['res_purpose'], $_SESSION['res_lab']);
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$sessions = $_SESSION['sessions'] ?? 30;
$idno     = $_SESSION['id_number'] ?? '';
$fullname = $_SESSION['full_name'] ?? '';
$purpose  = $_SESSION['res_purpose'] ?? '';
$lab      = $_SESSION['res_lab']     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation — CCS Sit-in Monitoring</title>
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

        /* ══════════ NAVBAR ══════════ */
        nav {
            background: var(--purple-dark);
            padding: 0 2.5rem;
            height: 56px;
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
            font-size: 0.95rem;
            color: var(--gold);
            letter-spacing: 0.04em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.15rem;
            list-style: none;
        }

        .nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.875rem;
            padding: 0.4rem 0.85rem;
            border-radius: 5px;
            transition: background 0.2s, color 0.2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(240,165,0,0.15);
            color: var(--gold-light);
        }

        .dropdown { position: relative; }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            color: rgba(255,255,255,0.85);
            font-size: 0.875rem;
            padding: 0.4rem 0.85rem;
            border-radius: 5px;
            cursor: pointer;
            background: none;
            border: none;
            font-family: 'Lato', sans-serif;
            transition: background 0.2s, color 0.2s;
        }

        .dropdown-toggle:hover {
            background: rgba(240,165,0,0.15);
            color: var(--gold-light);
        }

        .dropdown-toggle::after {
            content: '▾';
            font-size: 0.7rem;
            opacity: 0.8;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(74,32,128,0.18);
            min-width: 190px;
            z-index: 100;
            overflow: hidden;
            border: 1px solid rgba(74,32,128,0.1);
        }

        .dropdown:hover .dropdown-menu { display: block; }

        .dropdown-menu a {
            display: block;
            padding: 0.65rem 1rem;
            font-size: 0.85rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: background 0.15s;
        }

        .dropdown-menu a:hover {
            background: var(--gray);
            color: var(--purple);
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--text-dark);
            font-weight: 700;
            border: none;
            padding: 0.4rem 1.1rem;
            border-radius: 6px;
            font-size: 0.875rem;
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

        /* ══════════ MAIN ══════════ */
        main {
            flex: 1;
            padding: 2rem 2.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .page-title {
            font-family: 'Cinzel', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--purple-dark);
            text-align: center;
            margin-bottom: 1.75rem;
            letter-spacing: 0.04em;
        }

        .page-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            border-radius: 2px;
            margin: 0.5rem auto 0;
        }

        /* ══════════ CARD ══════════ */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(74,32,128,0.10), 0 1px 4px rgba(0,0,0,0.04);
            padding: 2.5rem 3rem;
            width: 100%;
            max-width: 780px;
            animation: slideUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ══════════ FORM ROWS ══════════ */
        .form-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            align-items: center;
            margin-bottom: 1.1rem;
            gap: 1rem;
        }

        .form-label {
            font-size: 0.9rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 7px;
            padding: 0.55rem 0.9rem;
            font-size: 0.875rem;
            font-family: 'Lato', sans-serif;
            color: var(--text-dark);
            background: white;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: var(--purple-light);
            box-shadow: 0 0 0 3px rgba(74,32,128,0.10);
        }

        .form-control[readonly],
        .form-control[disabled] {
            background: #f0edf8;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        /* ══════════ DIVIDER ══════════ */
        .section-divider {
            border: none;
            border-top: 1px solid rgba(74,32,128,0.1);
            margin: 1.4rem 0;
        }

        /* ══════════ BUTTONS ══════════ */
        .btn-submit {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            border: none;
            padding: 0.5rem 1.4rem;
            border-radius: 7px;
            font-size: 0.875rem;
            font-weight: 700;
            font-family: 'Lato', sans-serif;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 2px 8px rgba(74,32,128,0.25);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(74,32,128,0.35);
        }

        .btn-reserve {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            border: none;
            padding: 0.55rem 1.6rem;
            border-radius: 7px;
            font-size: 0.9rem;
            font-weight: 700;
            font-family: 'Lato', sans-serif;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 2px 8px rgba(74,32,128,0.25);
            margin-top: 0.5rem;
        }

        .btn-reserve:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(74,32,128,0.35);
        }

        /* ══════════ ALERTS ══════════ */
        .alert {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .alert-success {
            background: #e8f7ee;
            border: 1px solid #82d4a4;
            color: #1a6a3a;
        }

        .alert-error {
            background: #fdecea;
            border: 1px solid #f5a8a3;
            color: #8b1a1a;
        }

        /* ══════════ SESSION BADGE ══════════ */
        .session-badge-value {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            border-radius: 6px;
            padding: 0.3rem 0.9rem;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.04em;
        }

        /* ══════════ FOOTER ══════════ */
        footer {
            background: var(--purple-dark);
            color: rgba(255,255,255,0.45);
            text-align: center;
            padding: 1rem;
            font-size: 0.8rem;
        }

        @media (max-width: 600px) {
            nav { padding: 0 1rem; }
            main { padding: 1rem; }
            .card { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .nav-brand { font-size: 0.75rem; }
        }
    </style>
</head>
<body>

<!-- ==================== NAVBAR ==================== -->
<nav>
    <span class="nav-brand">College of Computer Studies Sit-in Monitoring System</span>
    <ul class="nav-links">
        <li class="dropdown">
            <button class="dropdown-toggle">Notification</button>
            <div class="dropdown-menu">
                <a href="#">View All Notifications</a>
                <a href="#">Mark All as Read</a>
            </div>
        </li>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="reservation.php" class="active">Reservation</a></li>
        <li>
            <form method="POST" action="logout.php" style="display:inline;">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </li>
    </ul>
</nav>

<!-- ==================== MAIN ==================== -->
<main>
    <h1 class="page-title">Reservation</h1>

    <?php if ($success): ?>
        <div class="alert alert-success" style="width:100%;max-width:780px;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="width:100%;max-width:780px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">

        <!-- ── Step 1: ID, Name, Purpose, Lab ── -->
        <form method="POST">
            <input type="hidden" name="submit_purpose" value="1">

            <div class="form-row">
                <label class="form-label">ID Number:</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($idno) ?>" readonly>
            </div>

            <div class="form-row">
                <label class="form-label">Student Name:</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($fullname) ?>" readonly>
            </div>

            <div class="form-row">
                <label class="form-label" for="purpose">Purpose:</label>
                <input type="text" class="form-control" id="purpose" name="purpose"
                       placeholder="e.g. C Programming"
                       value="<?= htmlspecialchars($purpose) ?>" required>
            </div>

            <div class="form-row">
                <label class="form-label" for="lab">Lab:</label>
                <input type="text" class="form-control" id="lab" name="lab"
                       placeholder="e.g. 524"
                       value="<?= htmlspecialchars($lab) ?>" required>
            </div>

            <div class="form-row">
                <div></div>
                <div>
                    <button type="submit" class="btn-submit">Submit</button>
                </div>
            </div>
        </form>

        <hr class="section-divider">

        <!-- ── Step 2: Time, Date, Sessions, Reserve ── -->
        <form method="POST">
            <input type="hidden" name="reserve" value="1">
            <input type="hidden" name="purpose" value="<?= htmlspecialchars($purpose) ?>">
            <input type="hidden" name="lab" value="<?= htmlspecialchars($lab) ?>">

            <div class="form-row">
                <label class="form-label" for="time_in">Time In:</label>
                <input type="time" class="form-control" id="time_in" name="time_in" required>
            </div>

            <div class="form-row">
                <label class="form-label" for="date">Date:</label>
                <input type="date" class="form-control" id="date" name="date" required>
            </div>

            <div class="form-row">
                <label class="form-label">Remaining Session:</label>
                <div>
                    <span class="session-badge-value"><?= (int)$sessions ?></span>
                </div>
            </div>

            <div>
                <button type="submit" class="btn-reserve">Reserve</button>
            </div>
        </form>

    </div>
</main>

<!-- ==================== FOOTER ==================== -->
<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

</body>
</html>