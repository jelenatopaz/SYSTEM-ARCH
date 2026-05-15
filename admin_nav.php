<?php
// admin_nav.php — shared navbar for all admin pages
if (!isset($active_page)) $active_page = '';
$pending_res = 0;
try { $pending_res = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn(); } catch(Exception $e) {}
?>
<nav>
    <span class="nav-brand">College of Computer Studies Admin</span>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"        <?= $active_page==='home'        ? 'class="active"':'' ?>>Home</a></li>
        <li><a href="admin_search.php"           <?= $active_page==='search'      ? 'class="active"':'' ?>>Search</a></li>
        <li><a href="admin_students.php"         <?= $active_page==='students'    ? 'class="active"':'' ?>>Students</a></li>
        <li><a href="admin_sitin.php"            <?= $active_page==='sitin'       ? 'class="active"':'' ?>>Sit-in</a></li>
        <li><a href="admin_current_sitin.php"    <?= $active_page==='current'     ? 'class="active"':'' ?>>Current Sit-in</a></li>
        <li><a href="admin_sitin_records.php"    <?= $active_page==='records'     ? 'class="active"':'' ?>>Sit-in Records</a></li>
        <li><a href="admin_sitin_reports.php"    <?= $active_page==='reports'     ? 'class="active"':'' ?>>Reports</a></li>
        <li style="position:relative;"><a href="admin_reservation.php" <?= $active_page==='reservation' ? 'class="active"':'' ?>>Reservation<?php if($pending_res>0): ?> <span style="background:var(--gold);color:#1a1030;border-radius:10px;padding:0.05rem 0.45rem;font-size:0.7rem;font-weight:800;margin-left:3px;"><?= $pending_res ?></span><?php endif; ?></a></li>
        <li><a href="admin_leaderboard.php"      <?= $active_page==='leaderboard' ? 'class="active"':'' ?>>Leaderboard</a></li>
        <li><a href="admin_analytics.php"        <?= $active_page==='analytics'   ? 'class="active"':'' ?>>Analytics</a></li>
        <li><a href="admin_feedback.php"         <?= $active_page==='feedback'    ? 'class="active"':'' ?>>Feedback</a></li>
        <li><form method="POST" action="admin_logout.php" style="display:inline;"><button type="submit" class="btn-logout">Log out</button></form></li>
    </ul>
</nav>