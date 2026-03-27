<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch history records for this student
$stmt = $pdo->prepare("
    SELECT st.id_number, st.first_name, st.last_name,
           r.purpose, r.lab, r.time_in, r.time_out
    FROM sit_in_records r
    JOIN students st ON st.id = r.student_id
    WHERE r.student_id = ?
    ORDER BY r.time_in DESC
");
$stmt->execute([$student_id]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History — CCS Sit-in Monitoring</title>
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
        }

        .page-title {
            font-family: 'Cinzel', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--purple-dark);
            text-align: center;
            margin-bottom: 1.75rem;
            letter-spacing: 0.04em;
            position: relative;
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

        /* ══════════ CARD WRAPPER ══════════ */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(74,32,128,0.10), 0 1px 4px rgba(0,0,0,0.04);
            overflow: hidden;
            animation: slideUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ══════════ TABLE CONTROLS ══════════ */
        .table-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem 0.75rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .entries-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-dark);
        }

        .entries-select {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            color: var(--text-dark);
            background: white;
            cursor: pointer;
        }

        .search-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-dark);
        }

        .search-input {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 0.3rem 0.75rem;
            font-size: 0.875rem;
            font-family: 'Lato', sans-serif;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 200px;
        }

        .search-input:focus {
            border-color: var(--purple-light);
            box-shadow: 0 0 0 3px rgba(74,32,128,0.12);
        }

        /* ══════════ TABLE ══════════ */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple) 60%, var(--purple-light) 100%);
        }

        thead th {
            color: white;
            font-family: 'Lato', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 0.85rem 1rem;
            text-align: left;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        thead th::after {
            content: ' ⇅';
            font-size: 0.7rem;
            opacity: 0.6;
        }

        thead th.sort-asc::after  { content: ' ↑'; opacity: 1; }
        thead th.sort-desc::after { content: ' ↓'; opacity: 1; }

        /* Gold underline on thead */
        thead tr::after {
            display: none;
        }

        tbody tr {
            border-bottom: 1px solid rgba(74,32,128,0.07);
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: rgba(74,32,128,0.04);
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody td {
            padding: 0.8rem 1rem;
            font-size: 0.875rem;
            color: var(--text-dark);
        }

        .no-data {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        /* ══════════ PAGINATION ══════════ */
        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1.5rem;
            border-top: 1px solid rgba(74,32,128,0.07);
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .showing-label {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .page-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: white;
            color: var(--text-dark);
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
            font-family: 'Lato', sans-serif;
        }

        .page-btn:hover:not(:disabled) {
            border-color: var(--purple-light);
            color: var(--purple);
        }

        .page-btn.active {
            background: var(--purple);
            border-color: var(--purple);
            color: white;
            font-weight: 700;
        }

        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
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
        <li><a href="history.php" class="active">History</a></li>
        <li><a href="reservation.php">Reservation</a></li>
        <li>
            <form method="POST" action="logout.php" style="display:inline;">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </li>
    </ul>
</nav>

<!-- ==================== MAIN ==================== -->
<main>

    <h1 class="page-title">History Information</h1>

    <div class="card">

        <div class="table-controls">
            <div class="entries-label">
                <select class="entries-select" id="entriesPerPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                entries per page
            </div>
            <div class="search-label">
                Search:
                <input type="text" class="search-input" id="searchInput" placeholder="">
            </div>
        </div>

        <div class="table-wrap">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">ID Number</th>
                        <th onclick="sortTable(1)">Name</th>
                        <th onclick="sortTable(2)">Sit Purpose</th>
                        <th onclick="sortTable(3)">Laboratory</th>
                        <th onclick="sortTable(4)">Login</th>
                        <th onclick="sortTable(5)">Logout</th>
                        <th onclick="sortTable(6)">Date</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (empty($records)): ?>
                        <tr><td colspan="7" class="no-data">No data available</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($row['purpose'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['lab'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '—') ?></td>
                            <td><?= htmlspecialchars($row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '—') ?></td>
                            <td><?= htmlspecialchars($row['time_in'] ? date('Y-m-d', strtotime($row['time_in'])) : '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="showing-label" id="showingLabel">Showing 1 to 1 of 1 entry</div>
            <div class="pagination" id="pagination"></div>
        </div>

    </div>
</main>

<!-- ==================== FOOTER ==================== -->
<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

<script>
    // ── Raw data from PHP ──
    const allRows = Array.from(document.querySelectorAll('#tableBody tr')).map(tr => ({
        el: tr,
        text: tr.innerText.toLowerCase()
    }));

    let filteredRows = [...allRows];
    let currentPage = 1;
    let perPage = 10;
    let sortCol = -1;
    let sortDir = 1;

    // ── Search ──
    document.getElementById('searchInput').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        filteredRows = allRows.filter(r => r.text.includes(q));
        currentPage = 1;
        render();
    });

    // ── Entries per page ──
    document.getElementById('entriesPerPage').addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        render();
    });

    // ── Sort ──
    function sortTable(col) {
        const ths = document.querySelectorAll('thead th');
        ths.forEach((th, i) => {
            th.classList.remove('sort-asc', 'sort-desc');
        });

        if (sortCol === col) sortDir *= -1;
        else { sortCol = col; sortDir = 1; }

        ths[col].classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');

        filteredRows.sort((a, b) => {
            const aVal = a.el.cells[col]?.innerText.trim() ?? '';
            const bVal = b.el.cells[col]?.innerText.trim() ?? '';
            return aVal.localeCompare(bVal, undefined, { numeric: true }) * sortDir;
        });

        currentPage = 1;
        render();
    }

    // ── Render ──
    function render() {
        const tbody = document.getElementById('tableBody');

        // Hide all, show page slice
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        const pageRows = filteredRows.slice(start, end);

        allRows.forEach(r => r.el.style.display = 'none');

        if (filteredRows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="no-data">No data available</td></tr>';
        } else {
            // Remove any "no data" row
            const noData = tbody.querySelector('.no-data');
            if (noData) noData.parentElement.remove();
            pageRows.forEach(r => r.el.style.display = '');
        }

        // Showing label
        const total = filteredRows.length;
        const from = total === 0 ? 0 : start + 1;
        const to = Math.min(end, total);
        document.getElementById('showingLabel').textContent =
            `Showing ${from} to ${to} of ${total} ${total === 1 ? 'entry' : 'entries'}`;

        // Pagination
        renderPagination(Math.ceil(total / perPage));
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('pagination');
        container.innerHTML = '';

        const btn = (label, page, disabled, active) => {
            const b = document.createElement('button');
            b.className = 'page-btn' + (active ? ' active' : '');
            b.textContent = label;
            b.disabled = disabled;
            if (!disabled) b.onclick = () => { currentPage = page; render(); };
            return b;
        };

        container.appendChild(btn('«', 1, currentPage === 1, false));
        container.appendChild(btn('‹', currentPage - 1, currentPage === 1, false));

        // Page numbers (show at most 5)
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);

        for (let p = start; p <= end; p++) {
            container.appendChild(btn(p, p, false, p === currentPage));
        }

        container.appendChild(btn('›', currentPage + 1, currentPage === totalPages || totalPages === 0, false));
        container.appendChild(btn('»', totalPages || 1, currentPage === totalPages || totalPages === 0, false));
    }

    // Init
    render();
</script>

</body>
</html>