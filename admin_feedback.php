<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM feedback WHERE id=?")->execute([intval($_POST['delete_id'])]);
    header("Location: admin_feedback.php?deleted=1"); exit;
}

// Fetch feedback joined with student info
$feedbacks = [];
try {
    $feedbacks = $pdo->query("
        SELECT f.id, f.message, f.rating, f.created_at,
               s.id_number, s.first_name, s.last_name
        FROM feedback f
        JOIN students s ON s.id = f.student_id
        ORDER BY f.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

$avg_rating = count($feedbacks) ? round(array_sum(array_column($feedbacks,'rating')) / count($feedbacks), 1) : 0;
$active_page = 'feedback';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--purple:#4a2080;--purple-dark:#2e1260;--purple-light:#6a3ab0;--gold:#f0a500;--gold-light:#ffd060;--gray:#f5f3fa;--text-dark:#1a1030;--text-muted:#7a6a9a}
        body{font-family:'Lato',sans-serif;background:var(--gray);min-height:100vh;display:flex;flex-direction:column}
        <?php include 'admin_nav_css.php'; ?>
        main{flex:1;padding:1.5rem 2rem}
        h1{font-family:'Cinzel',serif;font-size:1.5rem;color:var(--purple-dark);text-align:center;margin-bottom:0.3rem}
        .subtitle{text-align:center;color:var(--text-muted);font-size:0.85rem;margin-bottom:1.5rem}

        /* Stats row */
        .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem}
        .stat-card{background:white;border-radius:12px;padding:1.25rem 1.5rem;box-shadow:0 2px 12px rgba(74,32,128,0.08);display:flex;flex-direction:column;gap:0.25rem;border-top:3px solid var(--purple)}
        .stat-label{font-size:0.75rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted)}
        .stat-value{font-size:2rem;font-weight:700;color:var(--purple-dark);font-family:'Cinzel',serif}
        .stat-stars{color:var(--gold);font-size:1.1rem;letter-spacing:2px}

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
        table{width:100%;border-collapse:collapse;font-size:0.85rem}
        thead th{background:var(--gray);color:var(--text-muted);font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:0.7rem 1rem;text-align:left;border-bottom:2px solid #ede8fa;white-space:nowrap}
        tbody tr{border-bottom:1px solid rgba(74,32,128,0.06);transition:background 0.15s}
        tbody tr:last-child{border-bottom:none}
        tbody tr:hover{background:#faf8ff}
        tbody td{padding:0.75rem 1rem;color:var(--text-dark);vertical-align:middle}
        .id-badge{background:rgba(74,32,128,0.08);color:var(--purple);font-weight:700;font-size:0.78rem;padding:0.18rem 0.5rem;border-radius:6px;font-family:monospace}
        .stars{color:var(--gold);font-size:0.95rem;letter-spacing:1px}
        .msg-text{max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.83rem;color:var(--text-muted)}
        .btn-del{background:#fee;border:1px solid #fcc;color:#c00;border-radius:5px;padding:0.25rem 0.6rem;font-size:0.75rem;font-weight:700;cursor:pointer;font-family:'Lato',sans-serif;transition:background 0.15s}
        .btn-del:hover{background:#fcc}
        .table-footer{display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1.5rem;border-top:1px solid #ede8fa;font-size:0.8rem;color:var(--text-muted);flex-wrap:wrap;gap:0.5rem}
        .pagination{display:flex;gap:0.3rem}
        .page-btn{padding:0.3rem 0.65rem;border:1.5px solid #ddd6f0;border-radius:5px;background:white;font-size:0.8rem;cursor:pointer;font-family:'Lato',sans-serif;color:var(--text-dark);transition:background 0.15s}
        .page-btn:hover,.page-btn.active{background:var(--purple);color:white;border-color:var(--purple)}
        .alert-success{background:#e8f7ee;border:1px solid #82d4a4;color:#1a6a3a;border-radius:8px;padding:0.65rem 1rem;font-size:0.85rem;margin-bottom:1rem}
        footer{background:var(--purple-dark);color:rgba(255,255,255,0.45);text-align:center;padding:1rem;font-size:0.8rem}
    </style>
</head>
<body>
<?php include 'admin_nav.php'; ?>
<main>
    <h1>Feedback Reports</h1>
    <p class="subtitle">Student feedback and ratings for sit-in sessions</p>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert-success">✅ Feedback deleted successfully.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Feedback</div>
            <div class="stat-value"><?= count($feedbacks) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Average Rating</div>
            <div class="stat-value"><?= $avg_rating ?>/5</div>
            <div class="stat-stars"><?= str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">5-Star Reviews</div>
            <div class="stat-value"><?= count(array_filter($feedbacks, fn($f) => $f['rating'] == 5)) ?></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <span>💬 Student Feedback</span>
            <span class="count-badge"><?= count($feedbacks) ?> entries</span>
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
                        <th>ID Number</th>
                        <th>Student Name</th>
                        <th>Rating</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (empty($feedbacks)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">No feedback yet</td></tr>
                    <?php else: foreach ($feedbacks as $f): ?>
                        <tr>
                            <td><span class="id-badge"><?= htmlspecialchars($f['id_number']) ?></span></td>
                            <td><?= htmlspecialchars($f['first_name'].' '.$f['last_name']) ?></td>
                            <td><span class="stars"><?= str_repeat('★',(int)$f['rating']).str_repeat('☆',5-(int)$f['rating']) ?></span></td>
                            <td><div class="msg-text" title="<?= htmlspecialchars($f['message']) ?>"><?= htmlspecialchars($f['message']) ?></div></td>
                            <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('M d, Y h:i A', strtotime($f['created_at'])) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this feedback?')">
                                    <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                                    <button type="submit" class="btn-del">🗑 Delete</button>
                                </form>
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
    const allRows = Array.from(document.querySelectorAll('#tableBody tr')).map(tr=>({el:tr,text:tr.innerText.toLowerCase()}));
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