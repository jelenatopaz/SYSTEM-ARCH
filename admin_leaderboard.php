<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

// Leaderboard: rank by total sit-in sessions completed (time_out not null)
$leaders = $pdo->query("
    SELECT s.id_number, s.first_name, s.last_name, s.course, s.year_level,
           s.points,
           COUNT(r.id) as total_sessions,
           SUM(CASE WHEN r.time_out IS NOT NULL THEN 1 ELSE 0 END) as completed_sessions,
           MAX(r.time_in) as last_sitin
    FROM students s
    LEFT JOIN sit_in_records r ON r.student_id = s.id
    GROUP BY s.id
    ORDER BY s.points DESC, completed_sessions DESC, total_sessions DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$active_page = 'leaderboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;900&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--purple:#4a2080;--purple-dark:#2e1260;--purple-light:#6a3ab0;--gold:#f0a500;--gold-light:#ffd060;--gray:#f5f3fa;--text-dark:#1a1030;--text-muted:#7a6a9a}
        body{font-family:'Lato',sans-serif;background:var(--gray);min-height:100vh;display:flex;flex-direction:column}
        <?php include 'admin_nav_css.php'; ?>
        main{flex:1;padding:1.5rem 2rem}
        h1{font-family:'Cinzel',serif;font-size:1.5rem;color:var(--purple-dark);text-align:center;margin-bottom:0.3rem}
        .subtitle{text-align:center;color:var(--text-muted);font-size:0.85rem;margin-bottom:1.75rem}

        /* Top 3 podium */
        .podium{display:flex;justify-content:center;align-items:flex-end;gap:1.25rem;margin-bottom:2rem}
        .podium-card{background:white;border-radius:14px;box-shadow:0 4px 20px rgba(74,32,128,0.12);padding:1.25rem 1rem;text-align:center;min-width:150px;position:relative;transition:transform 0.2s}
        .podium-card:hover{transform:translateY(-3px)}
        .podium-card.rank-1{border-top:4px solid var(--gold);padding-top:1.75rem;min-width:180px}
        .podium-card.rank-2{border-top:4px solid #b0bec5}
        .podium-card.rank-3{border-top:4px solid #cd7f32}
        .crown{position:absolute;top:-14px;left:50%;transform:translateX(-50%);font-size:1.6rem}
        .podium-rank{font-family:'Cinzel',serif;font-size:1.8rem;font-weight:900}
        .rank-1 .podium-rank{color:var(--gold)}
        .rank-2 .podium-rank{color:#90a4ae}
        .rank-3 .podium-rank{color:#a0522d}
        .podium-name{font-weight:700;font-size:0.9rem;color:var(--text-dark);margin-top:0.3rem}
        .podium-id{font-size:0.75rem;color:var(--text-muted);font-family:monospace}
        .podium-pts{font-size:1.1rem;font-weight:700;color:var(--purple);margin-top:0.5rem}
        .podium-pts span{font-size:0.75rem;color:var(--text-muted);font-weight:400}
        .podium-sessions{font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem}

        /* Full table */
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
        .rank-badge{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-weight:900;font-size:0.8rem;font-family:'Cinzel',serif}
        .rank-1-badge{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:#1a1030}
        .rank-2-badge{background:#cfd8dc;color:#37474f}
        .rank-3-badge{background:#d7a97a;color:#4e2c00}
        .rank-other{background:rgba(74,32,128,0.08);color:var(--purple)}
        .id-badge{background:rgba(74,32,128,0.08);color:var(--purple);font-weight:700;font-size:0.78rem;padding:0.18rem 0.5rem;border-radius:6px;font-family:monospace}
        .pts-value{font-weight:700;color:var(--purple);font-size:0.95rem}
        .progress-wrap{background:#ede8fa;border-radius:10px;height:8px;width:100px;overflow:hidden}
        .progress-bar{height:100%;border-radius:10px;background:linear-gradient(90deg,var(--purple),var(--purple-light))}
        .add-pts-btn{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:var(--text-dark);border:none;border-radius:6px;padding:0.25rem 0.65rem;font-size:0.75rem;font-weight:700;font-family:'Lato',sans-serif;cursor:pointer;transition:transform 0.15s}
        .add-pts-btn:hover{transform:translateY(-1px)}
        .table-footer{display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1.5rem;border-top:1px solid #ede8fa;font-size:0.8rem;color:var(--text-muted);flex-wrap:wrap;gap:0.5rem}
        .pagination{display:flex;gap:0.3rem}
        .page-btn{padding:0.3rem 0.65rem;border:1.5px solid #ddd6f0;border-radius:5px;background:white;font-size:0.8rem;cursor:pointer;font-family:'Lato',sans-serif;color:var(--text-dark);transition:background 0.15s}
        .page-btn:hover,.page-btn.active{background:var(--purple);color:white;border-color:var(--purple)}

        /* Modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;align-items:center;justify-content:center}
        .modal-overlay.open{display:flex}
        .modal{background:white;border-radius:16px;padding:2rem;width:340px;box-shadow:0 8px 40px rgba(0,0,0,0.2)}
        .modal h3{font-family:'Cinzel',serif;color:var(--purple-dark);margin-bottom:1rem;font-size:1rem}
        .modal input{width:100%;border:1.5px solid #ddd6f0;border-radius:7px;padding:0.55rem 0.9rem;font-size:0.9rem;font-family:'Lato',sans-serif;outline:none;margin-bottom:1rem}
        .modal input:focus{border-color:var(--purple-light)}
        .modal-actions{display:flex;gap:0.75rem;justify-content:flex-end}
        .btn-cancel{background:var(--gray);border:none;border-radius:6px;padding:0.45rem 1rem;font-size:0.85rem;font-family:'Lato',sans-serif;cursor:pointer;color:var(--text-muted)}
        .btn-save{background:linear-gradient(135deg,var(--purple),var(--purple-light));color:white;border:none;border-radius:6px;padding:0.45rem 1rem;font-size:0.85rem;font-weight:700;font-family:'Lato',sans-serif;cursor:pointer}
        .alert-success{background:#e8f7ee;border:1px solid #82d4a4;color:#1a6a3a;border-radius:8px;padding:0.65rem 1rem;font-size:0.85rem;margin-bottom:1rem}
        footer{background:var(--purple-dark);color:rgba(255,255,255,0.45);text-align:center;padding:1rem;font-size:0.8rem}
    </style>
</head>
<body>
<?php include 'admin_nav.php'; ?>
<main>
    <h1>🏆 Leaderboard</h1>
    <p class="subtitle">Top students ranked by points and completed sit-in sessions</p>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert-success">✅ Points updated successfully.</div>
    <?php endif; ?>

    <!-- Top 3 Podium -->
    <?php if (count($leaders) >= 1): ?>
    <div class="podium">
        <?php
        // Reorder for podium: 2nd, 1st, 3rd
        $podium_order = [];
        if (isset($leaders[1])) $podium_order[] = [$leaders[1], 2];
        if (isset($leaders[0])) $podium_order[] = [$leaders[0], 1];
        if (isset($leaders[2])) $podium_order[] = [$leaders[2], 3];
        foreach ($podium_order as [$p, $rank]):
        ?>
        <div class="podium-card rank-<?= $rank ?>">
            <?php if ($rank === 1): ?><div class="crown">👑</div><?php endif; ?>
            <div class="podium-rank">#<?= $rank ?></div>
            <div class="podium-name"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
            <div class="podium-id"><?= htmlspecialchars($p['id_number']) ?></div>
            <div class="podium-pts"><?= (int)$p['points'] ?> <span>pts</span></div>
            <div class="podium-sessions"><?= (int)$p['completed_sessions'] ?> sessions completed</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Full Rankings Table -->
    <div class="panel">
        <div class="panel-header">
            <span>📊 Full Rankings</span>
            <span class="count-badge"><?= count($leaders) ?> students</span>
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
                        <th>Rank</th>
                        <th>ID Number</th>
                        <th>Name</th>
                        <th>Course / Year</th>
                        <th>Points</th>
                        <th>Progress</th>
                        <th>Sessions Done</th>
                        <th>Last Sit-in</th>
                        <th>Add Points</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (empty($leaders)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted);">No data yet</td></tr>
                    <?php else: foreach ($leaders as $i => $l):
                        $rank = $i + 1;
                        $maxPts = max(array_column($leaders,'points') ?: [1]);
                        $pct = $maxPts > 0 ? round(($l['points']/$maxPts)*100) : 0;
                        $badgeClass = $rank===1?'rank-1-badge':($rank===2?'rank-2-badge':($rank===3?'rank-3-badge':'rank-other'));
                    ?>
                    <tr>
                        <td><span class="rank-badge <?= $badgeClass ?>"><?= $rank ?></span></td>
                        <td><span class="id-badge"><?= htmlspecialchars($l['id_number']) ?></span></td>
                        <td><?= htmlspecialchars($l['first_name'].' '.$l['last_name']) ?></td>
                        <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($l['course']) ?> — <?= htmlspecialchars($l['year_level']) ?></td>
                        <td><span class="pts-value"><?= (int)$l['points'] ?></span> pts</td>
                        <td><div class="progress-wrap"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div></td>
                        <td><?= (int)$l['completed_sessions'] ?></td>
                        <td style="font-size:0.78rem;color:var(--text-muted);"><?= $l['last_sitin'] ? date('M d, Y', strtotime($l['last_sitin'])) : '—' ?></td>
                        <td>
                            <button class="add-pts-btn" onclick='openModal(<?= $l["id_number"] ? json_encode(["id_number"=>$l["id_number"],"name"=>$l["first_name"]." ".$l["last_name"],"pts"=>$l["points"]]) : '{}' ?>)'>+ Points</button>
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

<!-- Add Points Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <h3>➕ Add / Adjust Points</h3>
        <p id="modalStudentName" style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.75rem;"></p>
        <form method="POST" action="admin_update_points.php">
            <input type="hidden" name="id_number" id="modalIdNumber">
            <input type="number" name="points" id="modalPoints" placeholder="Enter points to add (e.g. 10)" min="-999" max="999" required>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Points</button>
            </div>
        </form>
    </div>
</div>

<footer>&copy; 2026 College of Computer Studies &mdash; University of Cebu</footer>
<script>
    function openModal(data) {
        document.getElementById('modalStudentName').textContent = data.name + ' ('+data.id_number+') — Current: '+data.pts+' pts';
        document.getElementById('modalIdNumber').value = data.id_number;
        document.getElementById('modalPoints').value = '';
        document.getElementById('modalOverlay').classList.add('open');
    }
    function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }
    document.getElementById('modalOverlay').addEventListener('click', function(e){ if(e.target===this) closeModal(); });

    // Auto-open a quick standalone add-points form if ?add_points=1
    <?php if(isset($_GET['add_points'])): ?>
    window.addEventListener('DOMContentLoaded', function() {
        // Show a prompt-style quick modal for adding points by ID
        const overlay = document.getElementById('modalOverlay');
        document.querySelector('.modal h3').textContent = '⭐ Add / Adjust Points';
        document.getElementById('modalStudentName').textContent = 'Enter the student ID number below.';
        // Add ID input if not there
        if (!document.getElementById('manualIdInput')) {
            const idInp = document.createElement('input');
            idInp.id = 'manualIdInput'; idInp.placeholder = 'Student ID Number';
            idInp.style.cssText = 'width:100%;border:1.5px solid #ddd6f0;border-radius:7px;padding:0.55rem 0.9rem;font-size:0.9rem;font-family:Lato,sans-serif;outline:none;margin-bottom:1rem';
            idInp.oninput = function(){ document.getElementById('modalIdNumber').value = this.value; };
            document.querySelector('.modal form').insertBefore(idInp, document.getElementById('modalPoints'));
        }
        overlay.classList.add('open');
    });
    <?php endif; ?>

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