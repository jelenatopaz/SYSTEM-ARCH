<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

$success = ''; $error = '';

// Approve
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['approve'])) {
    $res_id = intval($_POST['res_id'] ?? 0);
    $res = $pdo->prepare("SELECT * FROM reservations WHERE id=? AND status='pending'");
    $res->execute([$res_id]);
    $reservation = $res->fetch(PDO::FETCH_ASSOC);
    if ($reservation) {
        $stu = $pdo->prepare("SELECT * FROM students WHERE id=?");
        $stu->execute([$reservation['student_id']]);
        $student = $stu->fetch(PDO::FETCH_ASSOC);
        if ($student && $student['sessions'] > 0) {
            $datetime_in = $reservation['date'].' '.$reservation['time_in'];
            $pdo->prepare("INSERT INTO sit_in_records (student_id,purpose,lab,time_in) VALUES (?,?,?,?)")
                ->execute([$reservation['student_id'],$reservation['purpose'],$reservation['lab'],$datetime_in]);
            $pdo->prepare("UPDATE students SET sessions=sessions-1 WHERE id=?")->execute([$reservation['student_id']]);
            // Award points for reservation
            $pdo->prepare("UPDATE students SET points=COALESCE(points,0)+5 WHERE id=?")->execute([$reservation['student_id']]);
            $pdo->prepare("UPDATE reservations SET status='approved' WHERE id=?")->execute([$res_id]);
            $success = 'Reservation approved! Student awarded +5 points.';
        } else { $error = 'Student has no remaining sessions.'; }
    } else { $error = 'Reservation not found or already processed.'; }
}

// Reject
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reject'])) {
    $res_id = intval($_POST['res_id'] ?? 0);
    $pdo->prepare("UPDATE reservations SET status='rejected' WHERE id=? AND status='pending'")->execute([$res_id]);
    $success = 'Reservation rejected.';
}

// Delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM reservations WHERE id=?")->execute([intval($_POST['delete_id'])]);
    $success = 'Reservation record deleted.';
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where = $filter === 'pending' ? "WHERE r.status='pending'" : ($filter === 'approved' ? "WHERE r.status='approved'" : ($filter === 'rejected' ? "WHERE r.status='rejected'" : ''));

$reservations = $pdo->query("
    SELECT r.id, r.purpose, r.lab, r.date, r.time_in, r.status, r.created_at,
           s.id_number, s.first_name, s.last_name, s.sessions, s.points
    FROM reservations r JOIN students s ON s.id = r.student_id
    $where
    ORDER BY CASE r.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END, r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT status, COUNT(*) as cnt FROM reservations GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pending_count   = $counts['pending']  ?? 0;
$approved_count  = $counts['approved'] ?? 0;
$rejected_count  = $counts['rejected'] ?? 0;
$active_page = 'reservation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--purple:#4a2080;--purple-dark:#2e1260;--purple-light:#6a3ab0;--gold:#f0a500;--gold-light:#ffd060;--gray:#f5f3fa;--text-dark:#1a1030;--text-muted:#7a6a9a}
        body{font-family:'Lato',sans-serif;background:var(--gray);min-height:100vh;display:flex;flex-direction:column}
        <?php include 'admin_nav_css.php'; ?>
        main{flex:1;padding:1.5rem 2rem}
        h1{font-family:'Cinzel',serif;font-size:1.5rem;color:var(--purple-dark);text-align:center;margin-bottom:0.3rem}
        .subtitle{text-align:center;color:var(--text-muted);font-size:0.85rem;margin-bottom:1.25rem}

        /* Stats */
        .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.25rem}
        .stat-card{background:white;border-radius:12px;padding:1rem 1.5rem;box-shadow:0 2px 12px rgba(74,32,128,0.08);display:flex;align-items:center;gap:1rem;border-left:4px solid}
        .stat-card.pending{border-color:#f9a825}
        .stat-card.approved{border-color:#43a047}
        .stat-card.rejected{border-color:#e53935}
        .stat-icon{font-size:1.75rem}
        .stat-label{font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted)}
        .stat-value{font-size:1.75rem;font-weight:700;color:var(--purple-dark);font-family:'Cinzel',serif}

        /* Filter tabs */
        .filter-tabs{display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap}
        .filter-tab{padding:0.4rem 1.1rem;border-radius:20px;font-size:0.8rem;font-weight:700;font-family:'Lato',sans-serif;border:1.5px solid transparent;cursor:pointer;text-decoration:none;transition:all 0.15s;color:var(--text-muted);background:white;border-color:#ddd6f0}
        .filter-tab:hover{border-color:var(--purple-light);color:var(--purple)}
        .filter-tab.active{background:var(--purple);color:white;border-color:var(--purple)}

        .panel{background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(74,32,128,0.10)}
        .panel-header{background:linear-gradient(135deg,var(--purple-dark) 0%,var(--purple) 60%,var(--purple-light) 100%);color:white;padding:0.8rem 1.5rem;font-family:'Cinzel',serif;font-size:0.82rem;letter-spacing:0.06em;display:flex;align-items:center;justify-content:space-between;position:relative}
        .panel-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
        .count-badge{background:rgba(240,165,0,0.2);border:1px solid rgba(240,165,0,0.4);border-radius:20px;padding:0.15rem 0.75rem;font-size:0.72rem;font-family:'Lato',sans-serif;color:var(--gold-light)}
        .table-controls{display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1.5rem;flex-wrap:wrap;gap:0.75rem}
        .entries-row{display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;color:var(--text-muted)}
        .entries-row select{padding:0.3rem 0.5rem;border:1.5px solid #ddd6f0;border-radius:6px;font-family:'Lato',sans-serif;font-size:0.82rem;background:var(--gray);outline:none}
        .search-control{display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;color:var(--text-muted)}
        .search-control input{padding:0.35rem 0.8rem;border:1.5px solid #ddd6f0;border-radius:6px;font-family:'Lato',sans-serif;font-size:0.82rem;background:var(--gray);outline:none;width:180px}
        .search-control input:focus{border-color:var(--purple-light)}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:0.84rem}
        thead th{background:var(--gray);color:var(--text-muted);font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:0.7rem 1rem;text-align:left;border-bottom:2px solid #ede8fa;white-space:nowrap}
        tbody tr{border-bottom:1px solid rgba(74,32,128,0.06);transition:background 0.15s}
        tbody tr:last-child{border-bottom:none}
        tbody tr:hover{background:#faf8ff}
        tbody td{padding:0.7rem 1rem;color:var(--text-dark);vertical-align:middle}
        .id-badge{background:rgba(74,32,128,0.08);color:var(--purple);font-weight:700;font-size:0.78rem;padding:0.18rem 0.5rem;border-radius:6px;font-family:monospace}
        .badge-pending{background:#fff8e1;border:1px solid #ffe082;color:#f57f17;border-radius:20px;padding:0.18rem 0.65rem;font-size:0.72rem;font-weight:700}
        .badge-approved{background:#e8f5e9;border:1px solid #a5d6a7;color:#2e7d32;border-radius:20px;padding:0.18rem 0.65rem;font-size:0.72rem;font-weight:700}
        .badge-rejected{background:#fce4ec;border:1px solid #f48fb1;color:#880e4f;border-radius:20px;padding:0.18rem 0.65rem;font-size:0.72rem;font-weight:700}
        .btn-approve{background:linear-gradient(135deg,#2e7d32,#43a047);color:white;border:none;padding:0.3rem 0.75rem;border-radius:6px;font-size:0.75rem;font-weight:700;font-family:'Lato',sans-serif;cursor:pointer;transition:transform 0.15s;box-shadow:0 2px 5px rgba(46,125,50,0.3)}
        .btn-approve:hover{transform:translateY(-1px)}
        .btn-reject{background:linear-gradient(135deg,#b71c1c,#e53935);color:white;border:none;padding:0.3rem 0.75rem;border-radius:6px;font-size:0.75rem;font-weight:700;font-family:'Lato',sans-serif;cursor:pointer;transition:transform 0.15s;box-shadow:0 2px 5px rgba(183,28,28,0.3);margin-left:0.35rem}
        .btn-reject:hover{transform:translateY(-1px)}
        .btn-del{background:#fee;border:1px solid #fcc;color:#c00;border-radius:5px;padding:0.22rem 0.55rem;font-size:0.72rem;font-weight:700;cursor:pointer;font-family:'Lato',sans-serif;margin-left:0.35rem}
        .action-done{color:var(--text-muted);font-size:0.78rem;font-style:italic}
        .table-footer{display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1.5rem;border-top:1px solid #ede8fa;font-size:0.8rem;color:var(--text-muted);flex-wrap:wrap;gap:0.5rem}
        .pagination{display:flex;gap:0.3rem}
        .page-btn{padding:0.3rem 0.65rem;border:1.5px solid #ddd6f0;border-radius:5px;background:white;font-size:0.8rem;cursor:pointer;font-family:'Lato',sans-serif;color:var(--text-dark);transition:background 0.15s}
        .page-btn:hover,.page-btn.active{background:var(--purple);color:white;border-color:var(--purple)}
        .alert{border-radius:8px;padding:0.65rem 1rem;font-size:0.85rem;margin-bottom:1rem}
        .alert-success{background:#e8f7ee;border:1px solid #82d4a4;color:#1a6a3a}
        .alert-error{background:#fdecea;border:1px solid #f5a8a3;color:#8b1a1a}
        footer{background:var(--purple-dark);color:rgba(255,255,255,0.45);text-align:center;padding:1rem;font-size:0.8rem}
    </style>
</head>
<body>
<?php include 'admin_nav.php'; ?>
<main>
    <h1>Student Reservations</h1>
    <p class="subtitle">Review and manage student lab reservation requests. Approving awards +5 points.</p>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="stats-row">
        <div class="stat-card pending">
            <div class="stat-icon">⏳</div>
            <div><div class="stat-label">Pending</div><div class="stat-value"><?= $pending_count ?></div></div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon">✅</div>
            <div><div class="stat-label">Approved</div><div class="stat-value"><?= $approved_count ?></div></div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-icon">❌</div>
            <div><div class="stat-label">Rejected</div><div class="stat-value"><?= $rejected_count ?></div></div>
        </div>
    </div>

    <div class="filter-tabs">
        <a href="?filter=all"      class="filter-tab <?= $filter==='all'      ?'active':'' ?>">All (<?= $pending_count+$approved_count+$rejected_count ?>)</a>
        <a href="?filter=pending"  class="filter-tab <?= $filter==='pending'  ?'active':'' ?>">⏳ Pending (<?= $pending_count ?>)</a>
        <a href="?filter=approved" class="filter-tab <?= $filter==='approved' ?'active':'' ?>">✅ Approved (<?= $approved_count ?>)</a>
        <a href="?filter=rejected" class="filter-tab <?= $filter==='rejected' ?'active':'' ?>">❌ Rejected (<?= $rejected_count ?>)</a>
    </div>

    <div class="panel">
        <div class="panel-header">
            <span>📋 Reservation Requests</span>
            <span class="count-badge"><?= count($reservations) ?> shown</span>
        </div>
        <div class="table-controls">
            <div class="entries-row">
                <select id="entriesSelect" onchange="renderTable()">
                    <option value="10">10</option><option value="25">25</option><option value="50">50</option>
                </select> entries per page
            </div>
            <div class="search-control">
                Search: <input type="text" id="tableSearch" oninput="renderTable()" placeholder="">
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID Number</th>
                        <th>Student Name</th>
                        <th>Purpose</th>
                        <th>Lab</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Sessions Left</th>
                        <th>Points</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="12" style="text-align:center;padding:2rem;color:var(--text-muted);">No reservations found</td></tr>
                    <?php else: foreach ($reservations as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><span class="id-badge"><?= htmlspecialchars($r['id_number']) ?></span></td>
                        <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
                        <td><?= htmlspecialchars($r['purpose']) ?></td>
                        <td><?= htmlspecialchars($r['lab']) ?></td>
                        <td><?= htmlspecialchars($r['date']) ?></td>
                        <td><?= date('h:i A', strtotime($r['time_in'])) ?></td>
                        <td><?= (int)$r['sessions'] ?></td>
                        <td>⭐ <?= (int)$r['points'] ?></td>
                        <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></td>
                        <td>
                            <?php if ($r['status']==='pending'): ?>
                                <span class="badge-pending">⏳ Pending</span>
                            <?php elseif ($r['status']==='approved'): ?>
                                <span class="badge-approved">✅ Approved</span>
                            <?php else: ?>
                                <span class="badge-rejected">❌ Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status']==='pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="approve" class="btn-approve" onclick="return confirm('Approve? Student gets +5 points.')">✓ Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="reject" class="btn-reject" onclick="return confirm('Reject this reservation?')">✗ Reject</button>
                                </form>
                            <?php else: ?>
                                <span class="action-done">processed</span>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn-del" onclick="return confirm('Delete record?')">🗑</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span id="showingInfo"></span>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
</main>
<footer>&copy; 2026 College of Computer Studies &mdash; University of Cebu</footer>
<script>
    const allRows=Array.from(document.querySelectorAll('#tableBody tr')).map(tr=>({el:tr,text:tr.innerText.toLowerCase()}));
    let filtered=[...allRows],currentPage=1,perPage=10;
    document.getElementById('tableSearch').addEventListener('input',function(){filtered=allRows.filter(r=>r.text.includes(this.value.toLowerCase()));currentPage=1;render()});
    document.getElementById('entriesSelect').addEventListener('change',function(){perPage=parseInt(this.value);currentPage=1;render()});
    function render(){
        const start=(currentPage-1)*perPage,end=start+perPage;
        allRows.forEach(r=>r.el.style.display='none');
        filtered.slice(start,end).forEach(r=>r.el.style.display='');
        const total=filtered.length;
        document.getElementById('showingInfo').textContent=total===0?'No results':`Showing ${start+1} to ${Math.min(end,total)} of ${total} entries`;
        const pg=document.getElementById('pagination');pg.innerHTML='';
        const totalPages=Math.max(1,Math.ceil(total/perPage));
        const btn=(l,p,a,d)=>{const b=document.createElement('button');b.className='page-btn'+(a?' active':'');b.textContent=l;b.disabled=d;if(!d)b.onclick=()=>{currentPage=p;render()};pg.appendChild(b)};
        btn('«',1,false,currentPage===1);for(let i=1;i<=totalPages;i++)btn(i,i,i===currentPage,false);btn('»',totalPages,false,currentPage===totalPages);
    }
    function renderTable(){render()}
    render();
</script>
</body>
</html>