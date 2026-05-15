<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

// ── CSV Export ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $pdo->query("
        SELECT s.id_number, s.first_name, s.last_name, s.course, s.year_level,
               r.id as record_id, r.purpose, r.lab, r.time_in, r.time_out
        FROM sit_in_records r JOIN students s ON s.id = r.student_id
        ORDER BY r.time_in DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sitin_records_'.date('Ymd').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Record ID','ID Number','First Name','Last Name','Course','Year','Purpose','Lab','Time In','Time Out','Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['record_id'], $r['id_number'], $r['first_name'], $r['last_name'],
            $r['course'], $r['year_level'], $r['purpose'], $r['lab'],
            $r['time_in'], $r['time_out'] ?? '', $r['time_out'] ? 'Done' : 'Active'
        ]);
    }
    fclose($out); exit;
}

$all_records = $pdo->query("
    SELECT s.id_number, s.first_name, s.last_name, s.course, s.year_level, s.sessions,
           r.id as record_id, r.purpose, r.lab, r.time_in, r.time_out
    FROM sit_in_records r JOIN students s ON s.id = r.student_id
    ORDER BY r.time_in DESC
")->fetchAll(PDO::FETCH_ASSOC);
$active_page = 'records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Sit-in Records — CCS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <!-- jsPDF + AutoTable for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <!-- SheetJS for Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--purple:#4a2080;--purple-dark:#2e1260;--purple-light:#6a3ab0;--gold:#f0a500;--gold-light:#ffd060;--gray:#f5f3fa;--text-dark:#1a1030;--text-muted:#7a6a9a}
        body{font-family:'Lato',sans-serif;background:var(--gray);min-height:100vh;display:flex;flex-direction:column}
        <?php include 'admin_nav_css.php'; ?>
        main{flex:1;padding:1.5rem 2rem}
        h1{font-family:'Cinzel',serif;font-size:1.5rem;color:var(--purple-dark);text-align:center;margin-bottom:1.25rem}
        .panel{background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(74,32,128,0.10)}
        .panel-header{background:linear-gradient(135deg,var(--purple-dark) 0%,var(--purple) 60%,var(--purple-light) 100%);color:white;padding:0.8rem 1.5rem;font-family:'Cinzel',serif;font-size:0.82rem;letter-spacing:0.06em;display:flex;align-items:center;justify-content:space-between;position:relative}
        .panel-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
        .count-badge{background:rgba(240,165,0,0.2);border:1px solid rgba(240,165,0,0.4);border-radius:20px;padding:0.15rem 0.75rem;font-size:0.72rem;font-family:'Lato',sans-serif;color:var(--gold-light)}

        /* Export buttons */
        .export-bar{display:flex;align-items:center;gap:0.6rem;padding:1rem 1.5rem 0;flex-wrap:wrap}
        .export-bar span{font-size:0.8rem;color:var(--text-muted);font-weight:600;margin-right:0.25rem}
        .btn-exp{display:inline-flex;align-items:center;gap:0.35rem;padding:0.4rem 1rem;border-radius:7px;font-size:0.78rem;font-weight:700;font-family:'Lato',sans-serif;cursor:pointer;border:none;transition:transform 0.15s,box-shadow 0.15s;text-decoration:none}
        .btn-exp:hover{transform:translateY(-1px)}
        .btn-csv{background:#217346;color:white;box-shadow:0 2px 6px rgba(33,115,70,0.3)}
        .btn-xlsx{background:#1d6f42;color:white;box-shadow:0 2px 6px rgba(29,111,66,0.3)}
        .btn-pdf{background:#c0392b;color:white;box-shadow:0 2px 6px rgba(192,57,43,0.3)}

        .table-controls{display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1.5rem;flex-wrap:wrap;gap:0.75rem}
        .entries-row{display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;color:var(--text-muted)}
        .entries-row select{padding:0.3rem 0.5rem;border:1.5px solid #ddd6f0;border-radius:6px;font-family:'Lato',sans-serif;font-size:0.82rem;background:var(--gray);outline:none}
        .search-control{display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;color:var(--text-muted)}
        .search-control input{padding:0.35rem 0.8rem;border:1.5px solid #ddd6f0;border-radius:6px;font-family:'Lato',sans-serif;font-size:0.82rem;background:var(--gray);outline:none;width:180px}
        .search-control input:focus{border-color:var(--purple-light)}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:0.85rem}
        thead th{background:var(--gray);color:var(--text-muted);font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:0.7rem 1rem;text-align:left;border-bottom:2px solid #ede8fa;cursor:pointer;user-select:none;white-space:nowrap}
        thead th:hover{color:var(--purple)}
        tbody tr{border-bottom:1px solid rgba(74,32,128,0.06);transition:background 0.15s}
        tbody tr:last-child{border-bottom:none}
        tbody tr:hover{background:#faf8ff}
        tbody td{padding:0.75rem 1rem;color:var(--text-dark);vertical-align:middle}
        .id-badge{background:rgba(74,32,128,0.08);color:var(--purple);font-weight:700;font-size:0.78rem;padding:0.18rem 0.5rem;border-radius:6px;font-family:monospace}
        .purpose-badge{background:rgba(240,165,0,0.12);color:#7a5800;font-size:0.72rem;font-weight:700;padding:0.18rem 0.5rem;border-radius:6px;border:1px solid rgba(240,165,0,0.25)}
        .status-active{color:#4caf50;font-weight:700;font-size:0.78rem}
        .status-done{color:#9e9e9e;font-weight:700;font-size:0.78rem}
        .table-footer{display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1.5rem;border-top:1px solid #ede8fa;font-size:0.8rem;color:var(--text-muted);flex-wrap:wrap;gap:0.5rem}
        .pagination{display:flex;gap:0.3rem}
        .page-btn{padding:0.3rem 0.65rem;border:1.5px solid #ddd6f0;border-radius:5px;background:white;font-size:0.8rem;cursor:pointer;font-family:'Lato',sans-serif;color:var(--text-dark);transition:background 0.15s}
        .page-btn:hover,.page-btn.active{background:var(--purple);color:white;border-color:var(--purple)}
        footer{background:var(--purple-dark);color:rgba(255,255,255,0.45);text-align:center;padding:1rem;font-size:0.8rem}
    </style>
</head>
<body>
<?php include 'admin_nav.php'; ?>
<main>
    <h1>View Sit-in Records</h1>
    <div class="panel">
        <div class="panel-header">
            <span>📋 All Sit-in Records</span>
            <span class="count-badge" id="totalCount"><?= count($all_records) ?> total</span>
        </div>

        <!-- Export Buttons -->
        <div class="export-bar">
            <span>Export:</span>
            <a href="?export=csv" class="btn-exp btn-csv">📄 CSV</a>
            <button onclick="exportXLSX()" class="btn-exp btn-xlsx">📊 Excel</button>
            <button onclick="exportPDF()" class="btn-exp btn-pdf">📕 PDF</button>
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
            <table id="mainTable">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">Sit ID ⇅</th>
                        <th onclick="sortTable(1)">ID Number ⇅</th>
                        <th onclick="sortTable(2)">Name ⇅</th>
                        <th onclick="sortTable(3)">Purpose ⇅</th>
                        <th onclick="sortTable(4)">Lab ⇅</th>
                        <th onclick="sortTable(5)">Sessions ⇅</th>
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
<footer>&copy; 2026 College of Computer Studies &mdash; University of Cebu</footer>

<script>
const rawData = <?= json_encode(array_values($all_records)) ?>;
let filtered = [...rawData], currentPage = 1, sortCol = -1, sortAsc = true;

function formatDateTime(dt) {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit',hour12:true});
}

function renderTable() {
    const q = document.getElementById('tableSearch').value.toLowerCase();
    const perPage = parseInt(document.getElementById('entriesSelect').value);
    filtered = rawData.filter(r => {
        const name = (r.first_name+' '+r.last_name).toLowerCase();
        return r.id_number.toLowerCase().includes(q)||name.includes(q)||(r.purpose||'').toLowerCase().includes(q)||(r.lab||'').toLowerCase().includes(q)||String(r.record_id).includes(q);
    });
    if (sortCol>=0) filtered.sort((a,b)=>{
        const vals=[[a.record_id,b.record_id],[a.id_number,b.id_number],[a.first_name+' '+a.last_name,b.first_name+' '+b.last_name],[a.purpose||'',b.purpose||''],[a.lab||'',b.lab||''],[a.sessions??0,b.sessions??0],[a.time_in||'',b.time_in||''],[a.time_out||'',b.time_out||'']];
        const[av,bv]=vals[sortCol]||['',''];
        return sortAsc?String(av).localeCompare(String(bv),undefined,{numeric:true}):String(bv).localeCompare(String(av),undefined,{numeric:true});
    });
    const totalPages=Math.max(1,Math.ceil(filtered.length/perPage));
    if(currentPage>totalPages)currentPage=totalPages;
    const start=(currentPage-1)*perPage, pageData=filtered.slice(start,start+perPage);
    const tbody=document.getElementById('tableBody');
    tbody.innerHTML=filtered.length===0?`<tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted);">No data available</td></tr>`:pageData.map(r=>`
        <tr>
            <td><span class="id-badge">${r.record_id}</span></td>
            <td><span class="id-badge">${r.id_number}</span></td>
            <td>${r.first_name} ${r.last_name}</td>
            <td><span class="purpose-badge">${r.purpose||'—'}</span></td>
            <td>${r.lab||'—'}</td>
            <td>${r.sessions??0}</td>
            <td style="font-size:0.78rem;color:var(--text-muted);">${formatDateTime(r.time_in)}</td>
            <td style="font-size:0.78rem;color:var(--text-muted);">${formatDateTime(r.time_out)}</td>
            <td>${!r.time_out?'<span class="status-active">● Active</span>':'<span class="status-done">✓ Done</span>'}</td>
        </tr>`).join('');
    document.getElementById('showingInfo').textContent=filtered.length===0?'Showing 0 to 0 of 0 entries':`Showing ${start+1} to ${Math.min(start+perPage,filtered.length)} of ${filtered.length} entries`;
    const pg=document.getElementById('pagination');pg.innerHTML='';
    const addBtn=(label,page,active=false,disabled=false)=>{const b=document.createElement('button');b.className='page-btn'+(active?' active':'');b.textContent=label;b.disabled=disabled;b.onclick=()=>{currentPage=page;renderTable()};pg.appendChild(b)};
    addBtn('«',1,false,currentPage===1);
    for(let i=1;i<=totalPages;i++)addBtn(i,i,i===currentPage);
    addBtn('»',totalPages,false,currentPage===totalPages);
}
function sortTable(col){if(sortCol===col)sortAsc=!sortAsc;else{sortCol=col;sortAsc=true}renderTable()}

// ── XLSX Export ──
function exportXLSX() {
    const ws_data = [['Record ID','ID Number','Name','Course','Year','Purpose','Lab','Time In','Time Out','Status']];
    rawData.forEach(r => ws_data.push([r.record_id,r.id_number,r.first_name+' '+r.last_name,r.course,r.year_level,r.purpose,r.lab,r.time_in,r.time_out||'',r.time_out?'Done':'Active']));
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sit-in Records');
    XLSX.writeFile(wb, 'sitin_records_'+new Date().toISOString().slice(0,10)+'.xlsx');
}

// ── PDF Export ──
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });
    doc.setFontSize(14);
    doc.text('CCS Sit-in Records', 14, 15);
    doc.setFontSize(9);
    doc.text('Generated: '+new Date().toLocaleString(), 14, 22);
    doc.autoTable({
        startY: 28,
        head: [['Rec ID','ID Number','Name','Purpose','Lab','Sessions','Time In','Time Out','Status']],
        body: rawData.map(r=>[r.record_id,r.id_number,r.first_name+' '+r.last_name,r.purpose||'',r.lab||'',r.sessions??0,r.time_in||'',r.time_out||'',r.time_out?'Done':'Active']),
        styles:{fontSize:7,cellPadding:2},
        headStyles:{fillColor:[46,18,96],textColor:255,fontStyle:'bold'},
        alternateRowStyles:{fillColor:[245,243,250]}
    });
    doc.save('sitin_records_'+new Date().toISOString().slice(0,10)+'.pdf');
}

renderTable();
</script>
</body>
</html>