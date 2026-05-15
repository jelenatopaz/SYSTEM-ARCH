<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch sit-in history (last 5) ──
$sitin_history = [];
if (!empty($_SESSION['student_id'])) {
    try {
        $stmt_h = $pdo->prepare("
            SELECT purpose, lab, time_in, time_out
            FROM sit_in_records
            WHERE student_id = ?
            ORDER BY time_in DESC
            LIMIT 5
        ");
        $stmt_h->execute([$_SESSION['student_id']]);
        $sitin_history = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $sitin_history = []; }
}

// ── Fetch student points ──
$student_points = 0;
$reward_logs = [];
if (!empty($_SESSION['student_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT points FROM students WHERE id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        $student_points = (int)($stmt->fetchColumn() ?? 0);
    } catch(Exception $e) { $student_points = 0; }

    // Try to get reward log history
    try {
        $stmt2 = $pdo->prepare("SELECT description, points, created_at FROM reward_logs WHERE student_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt2->execute([$_SESSION['student_id']]);
        $reward_logs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $reward_logs = []; }
}

// Determine tier
function getRewardTier($pts) {
    if ($pts >= 100) return ['name'=>'Platinum', 'icon'=>'💎', 'range'=>'100+ pts', 'key'=>'platinum'];
    if ($pts >= 50)  return ['name'=>'Gold',     'icon'=>'🥇', 'range'=>'50–99 pts', 'key'=>'gold'];
    if ($pts >= 20)  return ['name'=>'Silver',   'icon'=>'🥈', 'range'=>'20–49 pts', 'key'=>'silver'];
    return ['name'=>'Bronze', 'icon'=>'🥉', 'range'=>'0–19 pts', 'key'=>'bronze'];
}
$tiers = [
    ['name'=>'Platinum','icon'=>'💎','range'=>'100+ pts','key'=>'platinum'],
    ['name'=>'Gold',    'icon'=>'🥇','range'=>'50–99 pts','key'=>'gold'],
    ['name'=>'Silver',  'icon'=>'🥈','range'=>'20–49 pts','key'=>'silver'],
    ['name'=>'Bronze',  'icon'=>'🥉','range'=>'0–19 pts', 'key'=>'bronze'],
];
$current_tier = getRewardTier($student_points);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CCS Sit-in Monitoring</title>
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

        /* ══════════════════════════════
           NAVBAR
        ══════════════════════════════ */
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

        /* ══════════════════════════════
           MAIN LAYOUT
        ══════════════════════════════ */
        main {
            flex: 1;
            padding: 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 270px 1fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        /* ══════════════════════════════
           REWARDS PANEL
        ══════════════════════════════ */
        .points-hero {
            text-align: center;
            padding: 1rem 0 1.25rem;
        }

        .points-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            border: 4px solid var(--gold);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            box-shadow: 0 6px 20px rgba(74,32,128,0.3), 0 0 0 6px rgba(240,165,0,0.12);
        }

        .points-number {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gold);
            line-height: 1;
        }

        .points-label {
            font-size: 0.62rem;
            color: rgba(255,255,255,0.75);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .points-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .rewards-divider {
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 0.75rem auto 1.25rem;
        }

        .rewards-tier {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.9rem;
            border-radius: 10px;
            background: var(--gray);
            margin-bottom: 0.5rem;
            border-left: 4px solid transparent;
            transition: transform 0.15s;
        }

        .rewards-tier.active-tier {
            background: linear-gradient(90deg, rgba(74,32,128,0.08), rgba(240,165,0,0.06));
            border-left-color: var(--gold);
        }

        .tier-icon { font-size: 1.4rem; }

        .tier-info { flex: 1; }

        .tier-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .tier-range {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .tier-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 20px;
            background: var(--gold);
            color: var(--text-dark);
        }

        .rewards-history-title {
            font-family: 'Cinzel', serif;
            font-size: 0.78rem;
            color: var(--purple);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 1.1rem 0 0.65rem;
        }

        .reward-log-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(74,32,128,0.07);
            font-size: 0.82rem;
        }

        .reward-log-item:last-child { border-bottom: none; }

        .reward-log-desc { color: var(--text-dark); }

        .reward-log-pts {
            font-weight: 700;
            color: #2e7d32;
            font-size: 0.85rem;
        }

        .reward-log-pts.negative { color: #c62828; }

        .no-rewards {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.82rem;
            padding: 1rem 0;
        }

        /* ══════════════════════════════
           SIT-IN HISTORY PANEL
        ══════════════════════════════ */
        .history-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(74,32,128,0.07);
        }

        .history-item:last-child { border-bottom: none; }

        .history-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .history-info { flex: 1; min-width: 0; }

        .history-purpose {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .history-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .history-status {
            flex-shrink: 0;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 20px;
            align-self: center;
        }

        .status-done {
            background: #ede8fa;
            color: var(--purple);
            border: 1px solid #c8b8f0;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .history-viewall {
            display: block;
            text-align: center;
            margin-top: 1rem;
            padding: 0.5rem;
            background: var(--gray);
            border-radius: 8px;
            color: var(--purple);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            transition: background 0.2s, color 0.2s;
        }

        .history-viewall:hover {
            background: rgba(74,32,128,0.1);
            color: var(--purple-dark);
        }

        .no-history {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.82rem;
            padding: 1.5rem 0;
        }

        /* ══════════════════════════════
           SHARED PANEL CARD
        ══════════════════════════════ */
        .panel {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(74,32,128,0.10), 0 1px 4px rgba(0,0,0,0.04);
            animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        .panel:nth-child(2) { animation-delay: 0.07s; }
        .panel:nth-child(3) { animation-delay: 0.14s; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .panel-header {
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple) 60%, var(--purple-light) 100%);
            color: white;
            padding: 0.8rem 1.25rem;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        /* Gold bottom accent on header */
        .panel-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .panel-body {
            padding: 1.5rem;
        }

        /* ══════════════════════════════
           STUDENT INFO PANEL
        ══════════════════════════════ */
        .student-avatar {
            display: flex;
            justify-content: center;
            margin-bottom: 0.75rem;
        }

        .student-avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(74,32,128,0.15);
            box-shadow: 0 4px 16px rgba(74,32,128,0.15);
        }

        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.75rem;
            color: white;
            font-family: 'Cinzel', serif;
            box-shadow: 0 4px 16px rgba(74,32,128,0.25);
            border: 3px solid rgba(240,165,0,0.35);
        }

        .avatar-divider {
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 0 auto 1.25rem;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid rgba(74,32,128,0.07);
            font-size: 0.875rem;
            color: var(--text-dark);
        }

        .info-row:last-child { border-bottom: none; }

        .info-icon {
            font-size: 0.9rem;
            min-width: 20px;
            color: var(--purple);
            margin-top: 1px;
        }

        .info-label {
            font-weight: 700;
            color: var(--text-muted);
            margin-right: 4px;
            font-size: 0.8rem;
        }

        .session-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.15rem 0.65rem;
            border-radius: 20px;
        }

        /* ══════════════════════════════
           ANNOUNCEMENTS PANEL
        ══════════════════════════════ */
        .announcement-item {
            padding: 1rem 0;
            border-bottom: 1px solid rgba(74,32,128,0.08);
        }

        .announcement-item:first-child { padding-top: 0; }
        .announcement-item:last-child { border-bottom: none; padding-bottom: 0; }

        .announcement-meta {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.45rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .announcement-meta::before {
            content: '';
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--gold);
            flex-shrink: 0;
        }

        .announcement-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--purple-dark);
            margin-bottom: 0.35rem;
        }

        .announcement-content {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .no-announcements {
            text-align: center;
            padding: 2.5rem 0;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .no-announcements span {
            display: block;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        /* ══════════════════════════════
           RULES PANEL
        ══════════════════════════════ */
        .rules-body {
            padding: 1.5rem;
            max-height: 430px;
            overflow-y: auto;
            font-size: 0.85rem;
            color: var(--text-dark);
            line-height: 1.65;
        }

        .rules-body::-webkit-scrollbar { width: 5px; }
        .rules-body::-webkit-scrollbar-track { background: var(--gray); border-radius: 4px; }
        .rules-body::-webkit-scrollbar-thumb {
            background: rgba(74,32,128,0.25);
            border-radius: 4px;
        }

        .rules-institution {
            text-align: center;
            margin-bottom: 1.1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(74,32,128,0.1);
        }

        .rules-institution h3 {
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            color: var(--purple-dark);
            margin-bottom: 0.2rem;
        }

        .rules-institution h4 {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .rules-section-title {
            font-family: 'Cinzel', serif;
            font-size: 0.78rem;
            color: var(--purple);
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        .rules-intro {
            color: var(--text-muted);
            font-style: italic;
            margin-bottom: 0.9rem;
            font-size: 0.82rem;
        }

        .rule-item {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.7rem;
            align-items: flex-start;
        }

        .rule-number {
            min-width: 22px;
            height: 22px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .rule-text {
            font-size: 0.84rem;
            color: var(--text-dark);
            line-height: 1.55;
        }

        /* ══════════════════════════════
           FOOTER
        ══════════════════════════════ */
        footer {
            background: var(--purple-dark);
            color: rgba(255,255,255,0.45);
            text-align: center;
            padding: 1rem;
            font-size: 0.8rem;
        }

        /* ══════════════════════════════
           RESPONSIVE
        ══════════════════════════════ */
        @media (max-width: 1400px) {
            .dashboard-grid { grid-template-columns: 250px 220px 1fr 1fr !important; }
            #rewards-panel { grid-column: 1 / -1; }
        }

        @media (max-width: 1200px) {
            .dashboard-grid { grid-template-columns: 250px 220px 1fr !important; }
            #rewards-panel { grid-column: auto; }
        }

        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr !important; }
            nav { padding: 0 1rem; }
            main { padding: 1rem; }
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
        <li><a href="dashboard.php" class="active">Home</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="reservation.php">Reservation</a></li>
        <li><a href="#rewards-panel" onclick="document.getElementById('rewards-panel').scrollIntoView({behavior:'smooth'});return false;">🏆 Rewards</a></li>
        <li>
            <form method="POST" action="logout.php" style="display:inline;">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </li>
    </ul>
</nav>

<!-- ==================== MAIN ==================== -->
<main>
    <div class="dashboard-grid" style="grid-template-columns: 250px 220px 1fr 1fr 240px;">

        <!-- ── COLUMN 1: Student Info ── -->
        <div class="panel">
            <div class="panel-header">
                👤 Student Information
            </div>
            <div class="panel-body">

                <div class="student-avatar">
                    <?php
                        $pic = !empty($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'raiden.jpg';
                    ?>
                    <img src="<?= htmlspecialchars($pic) ?>" alt="Profile">
                </div>
                <div class="avatar-divider"></div>

                <div class="info-row">
                    <span class="info-icon">👤</span>
                    <div><span class="info-label">Name:</span> <?= htmlspecialchars($_SESSION['full_name'] ?? 'N/A') ?></div>
                </div>
                <div class="info-row">
                    <span class="info-icon">🎓</span>
                    <div><span class="info-label">Course:</span> <?= htmlspecialchars($_SESSION['course'] ?? 'N/A') ?></div>
                </div>
                <div class="info-row">
                    <span class="info-icon">📅</span>
                    <div><span class="info-label">Year:</span> <?= htmlspecialchars($_SESSION['year_level'] ?? 'N/A') ?></div>
                </div>
                <div class="info-row">
                    <span class="info-icon">✉️</span>
                    <div><span class="info-label">Email:</span> <?= htmlspecialchars($_SESSION['email'] ?? 'N/A') ?></div>
                </div>
                <div class="info-row">
                    <span class="info-icon">🏠</span>
                    <div><span class="info-label">Address:</span> <?= htmlspecialchars($_SESSION['address'] ?? 'N/A') ?></div>
                </div>
                <div class="info-row">
                    <span class="info-icon">⏱️</span>
                    <div>
                        <span class="info-label">Session:</span>
                        <span class="session-badge"><?= htmlspecialchars($_SESSION['sessions'] ?? 30) ?></span>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── COLUMN 2: Sit-in History ── -->
        <div class="panel">
            <div class="panel-header">
                🕘 Sit-in History
            </div>
            <div class="panel-body">

                <?php if (empty($sitin_history)): ?>
                    <div class="no-history">
                        <div style="font-size:1.5rem;margin-bottom:0.4rem;">📋</div>
                        No sit-in sessions yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($sitin_history as $h): ?>
                    <div class="history-item">
                        <div class="history-icon">💻</div>
                        <div class="history-info">
                            <div class="history-purpose"><?= htmlspecialchars($h['purpose']) ?></div>
                            <div class="history-meta">
                                🖥️ <?= htmlspecialchars($h['lab']) ?><br>
                                📅 <?= date('M d, Y', strtotime($h['time_in'])) ?><br>
                                ⏱️ <?= date('h:i A', strtotime($h['time_in'])) ?>
                                <?php if ($h['time_out']): ?>
                                    → <?= date('h:i A', strtotime($h['time_out'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($h['time_out']): ?>
                            <span class="history-status status-done">Done</span>
                        <?php else: ?>
                            <span class="history-status status-active">Active</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <a href="history.php" class="history-viewall">View All History →</a>
                <?php endif; ?>

            </div>
        </div>

        <!-- ── COLUMN 3: Announcements ── -->
        <div class="panel">
            <div class="panel-header">
                📢 Announcement
            </div>
            <div class="panel-body">

                <?php if (empty($announcements)): ?>
                    <div class="no-announcements">
                        <span>📭</span>
                        No announcements at this time.
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="announcement-item">
                            <p class="announcement-meta">
                                CCS Admin &nbsp;|&nbsp; <?= date('Y-M-d', strtotime($ann['created_at'])) ?>
                            </p>
                            <?php if (!empty($ann['title'])): ?>
                                <p class="announcement-title"><?= htmlspecialchars($ann['title']) ?></p>
                            <?php endif; ?>
                            <p class="announcement-content">
                                <?= nl2br(htmlspecialchars($ann['content'])) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

        <!-- ── COLUMN 3: Rules ── -->
        <div class="panel">
            <div class="panel-header">
                Rules and Regulation
            </div>
            <div class="rules-body">

                <div class="rules-institution">
                    <h3>University of Cebu</h3>
                    <h4>College of Computer Studies</h4>
                </div>

                <p class="rules-section-title">Laboratory Rules and Regulations</p>

                <p class="rules-intro">To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</p>

                <?php
                $rules = [
                    "Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.",
                    "Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.",
                    "Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.",
                    "Getting access to other websites not related to the course is not allowed (e.g., Facebook, Twitter, Instagram, and other social networking sites).",
                    "Deleting computer files without permission from the laboratory attendant is strictly prohibited.",
                    "Observe computer time limits. Logged-in students shall be given priority.",
                    "Food and drinks are not allowed inside the laboratory. This is to ensure that the equipment are in good condition.",
                    "Anyone who causes damage to the equipment will be held responsible and required to repair or replace the damaged part.",
                    "Lab coats are required when using the laboratory. Students without lab coats will not be allowed to use the facilities.",
                    "Students must clean up after themselves. Log out of all accounts and shut down computers properly before leaving.",
                ];
                foreach ($rules as $i => $rule): ?>
                    <div class="rule-item">
                        <span class="rule-number"><?= $i + 1 ?></span>
                        <span class="rule-text"><?= htmlspecialchars($rule) ?></span>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- ── COLUMN 4: Rewards & Points ── -->
        <div class="panel" id="rewards-panel">
            <div class="panel-header">
                🏆 Rewards &amp; Points
            </div>
            <div class="panel-body">

                <div class="points-hero">
                    <div class="points-circle">
                        <span class="points-number"><?= (int)$student_points ?></span>
                        <span class="points-label">Points</span>
                    </div>
                    <p class="points-subtitle">Your current reward points</p>
                </div>

                <div class="rewards-divider"></div>

                <?php foreach ($tiers as $tier): ?>
                <div class="rewards-tier <?= ($current_tier['key'] === $tier['key']) ? 'active-tier' : '' ?>">
                    <span class="tier-icon"><?= $tier['icon'] ?></span>
                    <div class="tier-info">
                        <div class="tier-name"><?= $tier['name'] ?></div>
                        <div class="tier-range"><?= $tier['range'] ?></div>
                    </div>
                    <?php if ($current_tier['key'] === $tier['key']): ?>
                    <span class="tier-badge">You</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <p class="rewards-history-title">Points History</p>

                <?php if (empty($reward_logs)): ?>
                    <div class="no-rewards">
                        <div style="font-size:1.5rem;margin-bottom:0.3rem;">🎯</div>
                        No point history yet.<br>
                        <small>Earn points through sit-ins &amp; reservations!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($reward_logs as $log): ?>
                    <div class="reward-log-item">
                        <span class="reward-log-desc"><?= htmlspecialchars($log['description']) ?></span>
                        <span class="reward-log-pts <?= ($log['points'] < 0) ? 'negative' : '' ?>">
                            <?= ($log['points'] >= 0 ? '+' : '') . (int)$log['points'] ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

    </div>
</main>

<!-- ==================== FOOTER ==================== -->
<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

</body>
</html>