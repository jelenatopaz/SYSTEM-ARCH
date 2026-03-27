<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$error   = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $last_name   = trim($_POST["last_name"]   ?? "");
    $first_name  = trim($_POST["first_name"]  ?? "");
    $middle_name = trim($_POST["middle_name"] ?? "");
    $course      = trim($_POST["course"]      ?? "");
    $year_level  = trim($_POST["year_level"]  ?? "");
    $email       = trim($_POST["email"]       ?? "");
    $address     = trim($_POST["address"]     ?? "");
    $new_pass    = trim($_POST["new_password"]    ?? "");
    $repeat_pass = trim($_POST["repeat_password"] ?? "");

    if (empty($last_name) || empty($first_name) || empty($course) ||
        empty($year_level) || empty($email) || empty($address)) {
        $error = "Please fill in all required fields marked with *.";

    } elseif (!empty($new_pass) && $new_pass !== $repeat_pass) {
        $error = "Passwords do not match. Please try again.";

    } elseif (!empty($new_pass) && strlen($new_pass) < 6) {
        $error = "New password must be at least 6 characters.";

    } else {

        // ── Handle profile picture upload ──────────────────────────────
        $profile_pic_path = null; // null = no change

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES['profile_pic'];
            $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxBytes = 2 * 1024 * 1024; // 2 MB

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowed)) {
                $error = "Only JPG, PNG, GIF, or WEBP images are allowed.";
            } elseif ($file['size'] > $maxBytes) {
                $error = "Image must be under 2 MB.";
            } else {
                $uploadDir = "uploads/profile_pics/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = "student_" . $_SESSION['student_id'] . "_" . time() . "." . strtolower($ext);
                $dest     = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $profile_pic_path = $dest;
                } else {
                    $error = "Failed to save image. Please try again.";
                }
            }
        }

        // Only proceed if no upload error
        if (empty($error)) {
            // Build SQL dynamically based on what's being updated
            $params = [$last_name, $first_name, $middle_name, $course,
                       $year_level, $email, $address];

            $sql = "UPDATE students SET last_name=?, first_name=?, middle_name=?, course=?,
                        year_level=?, email=?, address=?";

            if ($profile_pic_path !== null) {
                $sql    .= ", profile_pic=?";
                $params[] = $profile_pic_path;
            }

            if (!empty($new_pass)) {
                $sql    .= ", password=?";
                $params[] = password_hash($new_pass, PASSWORD_DEFAULT);
            }

            $sql    .= " WHERE id=?";
            $params[] = $_SESSION['student_id'];

            $pdo->prepare($sql)->execute($params);

            // Update session vars
            $_SESSION['full_name']   = $first_name . ' ' . $last_name;
            $_SESSION['first_name']  = $first_name;
            $_SESSION['last_name']   = $last_name;
            $_SESSION['course']      = $course;
            $_SESSION['year_level']  = $year_level;
            $_SESSION['email']       = $email;
            $_SESSION['address']     = $address;
            if ($profile_pic_path !== null) {
                $_SESSION['profile_pic'] = $profile_pic_path;
            }

            $success = "Profile updated successfully!";
        }
    }
}

// Fetch latest student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$currentPic = !empty($student['profile_pic']) ? htmlspecialchars($student['profile_pic']) : 'raiden.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — CCS Sit-in Monitoring</title>
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
        .nav-brand { font-family: 'Cinzel', serif; font-size: 0.95rem; color: var(--gold); letter-spacing: 0.04em; }
        .nav-links { display: flex; align-items: center; gap: 0.15rem; list-style: none; }
        .nav-links a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.875rem; padding: 0.4rem 0.85rem; border-radius: 5px; transition: background 0.2s, color 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(240,165,0,0.15); color: var(--gold-light); }
        .dropdown { position: relative; }
        .dropdown-toggle { display: flex; align-items: center; gap: 5px; color: rgba(255,255,255,0.85); font-size: 0.875rem; padding: 0.4rem 0.85rem; border-radius: 5px; cursor: pointer; background: none; border: none; font-family: 'Lato', sans-serif; transition: background 0.2s, color 0.2s; }
        .dropdown-toggle:hover { background: rgba(240,165,0,0.15); color: var(--gold-light); }
        .dropdown-toggle::after { content: '▾'; font-size: 0.7rem; opacity: 0.8; }
        .dropdown-menu { display: none; position: absolute; top: calc(100% + 6px); left: 0; background: white; border-radius: 8px; box-shadow: 0 6px 24px rgba(74,32,128,0.18); min-width: 190px; z-index: 100; overflow: hidden; border: 1px solid rgba(74,32,128,0.1); }
        .dropdown:hover .dropdown-menu { display: block; }
        .dropdown-menu a { display: block; padding: 0.65rem 1rem; font-size: 0.85rem; color: var(--text-dark); text-decoration: none; transition: background 0.15s; }
        .dropdown-menu a:hover { background: var(--gray); color: var(--purple); }
        .btn-logout { background: linear-gradient(135deg, var(--gold), var(--gold-light)); color: var(--text-dark); font-weight: 700; border: none; padding: 0.4rem 1.1rem; border-radius: 6px; font-size: 0.875rem; cursor: pointer; font-family: 'Lato', sans-serif; transition: transform 0.15s, box-shadow 0.15s; margin-left: 0.4rem; box-shadow: 0 2px 8px rgba(240,165,0,0.3); }
        .btn-logout:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(240,165,0,0.4); }

        /* ── MAIN ── */
        main { flex: 1; display: flex; align-items: flex-start; justify-content: center; padding: 2.5rem 1.5rem; }

        /* ── CARD ── */
        .card { background: white; border-radius: 16px; box-shadow: 0 8px 40px rgba(74,32,128,0.12), 0 2px 8px rgba(0,0,0,0.06); width: 100%; max-width: 560px; overflow: hidden; animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) both; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }

        /* ── CARD HEADER with avatar upload ── */
        .card-header {
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple) 60%, var(--purple-light) 100%);
            padding: 1.75rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            position: relative;
        }
        .card-header::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--gold), transparent); }

        /* Clickable avatar wrapper */
        .avatar-wrap {
            position: relative;
            width: 72px;
            height: 72px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .avatar-wrap img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            border: 2.5px solid rgba(240,165,0,0.6);
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
            display: block;
            transition: filter 0.2s;
        }
        .avatar-wrap:hover img { filter: brightness(0.65); }
        .avatar-overlay {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
        }
        .avatar-wrap:hover .avatar-overlay { opacity: 1; }
        .avatar-overlay span { font-size: 1.2rem; }
        .avatar-overlay small { color: white; font-size: 0.6rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 2px; }

        /* Hidden file input */
        #profilePicInput { display: none; }

        .card-header-text h2 { font-family: 'Cinzel', serif; color: var(--gold); font-size: 1.2rem; margin-bottom: 0.2rem; }
        .card-header-text p  { color: rgba(255,255,255,0.55); font-size: 0.72rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .pic-hint { color: rgba(255,255,255,0.45); font-size: 0.72rem; margin-top: 0.35rem; }

        /* ── FORM BODY ── */
        .card-body { padding: 1.75rem; }
        .btn-back { display: inline-block; background: rgba(240,165,0,0.12); color: var(--gold); text-decoration: none; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.82rem; font-weight: 700; margin-bottom: 1.5rem; border: 1px solid rgba(240,165,0,0.25); transition: background 0.2s; }
        .btn-back:hover { background: rgba(240,165,0,0.22); }

        .alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.25rem; }
        .alert-error   { background: #fdecea; border-left: 4px solid #e53935; color: #b71c1c; }
        .alert-success { background: #e8f5e9; border-left: 4px solid #4caf50; color: #2e7d32; }

        .section-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--purple); border-bottom: 1.5px solid #ede8fa; padding-bottom: 6px; margin-bottom: 1rem; margin-top: 0.25rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.4rem; }
        .form-group label .req { color: #e53935; margin-left: 2px; }
        .form-group input, .form-group select { width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid #ddd6f0; border-radius: 8px; font-family: 'Lato', sans-serif; font-size: 0.9rem; color: var(--text-dark); background: var(--gray); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--purple-light); background: white; box-shadow: 0 0 0 3px rgba(106,58,176,0.1); }
        .form-group input:disabled, .form-group input[readonly] { background: #ede8fa; color: var(--text-muted); cursor: not-allowed; border-color: #ddd6f0; }
        .id-lock-hint { font-size: 0.73rem; color: var(--text-muted); margin-top: 0.3rem; display: flex; align-items: center; gap: 0.3rem; }
        .row-2 { display: flex; gap: 1rem; }
        .row-2 .form-group { flex: 1; }
        .section-gap { margin-bottom: 1.25rem; }
        .btn-save { width: 100%; padding: 0.85rem; background: linear-gradient(135deg, var(--purple) 0%, var(--purple-light) 100%); color: white; border: none; border-radius: 10px; font-family: 'Cinzel', serif; font-size: 1rem; letter-spacing: 0.05em; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; box-shadow: 0 4px 15px rgba(74,32,128,0.35); margin-top: 0.5rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(74,32,128,0.45); }
        .btn-save:active { transform: translateY(0); }
        .pass-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem; }

        /* ── PICTURE CHANGE BADGE ── */
        .pic-changed-badge {
            display: none;
            align-items: center;
            gap: 0.4rem;
            background: rgba(240,165,0,0.2);
            border: 1px solid rgba(240,165,0,0.5);
            color: var(--gold-light);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            margin-top: 0.4rem;
        }
        .pic-changed-badge.show { display: inline-flex; }

        footer { background: var(--purple-dark); color: rgba(255,255,255,0.45); text-align: center; padding: 1rem; font-size: 0.8rem; }

        @media (max-width: 560px) {
            .row-2 { flex-direction: column; gap: 0; }
            nav { padding: 0 1rem; }
            .card-body { padding: 1.25rem; }
        }
    </style>
</head>
<body>

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
        <li><a href="edit_profile.php" class="active">Edit Profile</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="reservation.php">Reservation</a></li>
        <li>
            <form method="POST" action="logout.php" style="display:inline;">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </li>
    </ul>
</nav>

<main>
    <div class="card">

        <!-- Purple header with clickable avatar -->
        <div class="card-header">
            <div class="avatar-wrap" onclick="document.getElementById('profilePicInput').click()" title="Click to change photo">
                <img src="<?= $currentPic ?>" alt="Profile" id="avatarPreview">
                <div class="avatar-overlay">
                    <span>📷</span>
                    <small>Change</small>
                </div>
            </div>
            <div class="card-header-text">
                <h2>Edit Profile</h2>
                <p>University of Cebu &middot; Main Campus &middot; UC Success</p>
                <span class="pic-changed-badge" id="picBadge">&#x1F4F7; New photo selected</span>
            </div>
        </div>

        <div class="card-body">

            <a href="dashboard.php" class="btn-back">&#8592; Back to Dashboard</a>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">&#x2705; <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Single form handles BOTH pic upload and profile fields -->
            <form method="POST" action="edit_profile.php" enctype="multipart/form-data">

                <!-- Hidden file input triggered by avatar click -->
                <input type="file" id="profilePicInput" name="profile_pic"
                       accept="image/jpeg,image/png,image/gif,image/webp">

                <!-- ── PERSONAL INFORMATION ── -->
                <p class="section-title">Personal Information</p>

                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text" value="<?= htmlspecialchars($student['id_number'] ?? '') ?>" disabled>
                    <p class="id-lock-hint">&#x1F512; ID Number cannot be changed.</p>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" placeholder="e.g. Dela Cruz"
                            value="<?= htmlspecialchars($_POST['last_name'] ?? $student['last_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" placeholder="e.g. Juan"
                            value="<?= htmlspecialchars($_POST['first_name'] ?? $student['first_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group section-gap">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" placeholder="e.g. Santos (optional)"
                        value="<?= htmlspecialchars($_POST['middle_name'] ?? $student['middle_name'] ?? '') ?>">
                </div>

                <!-- ── ACADEMIC INFORMATION ── -->
                <p class="section-title">Academic Information</p>

                <div class="row-2">
                    <div class="form-group">
                        <label>Course <span class="req">*</span></label>
                        <select name="course">
                            <option value="">-- Select --</option>
                            <?php foreach (['BSIT','BSCS','BSIS','ACT'] as $c): ?>
                                <option value="<?= $c ?>"
                                    <?= (($_POST['course'] ?? $student['course'] ?? '') === $c) ? 'selected' : '' ?>>
                                    <?= $c ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year Level <span class="req">*</span></label>
                        <select name="year_level">
                            <option value="">-- Select --</option>
                            <?php foreach (['1'=>'1st Year','2'=>'2nd Year','3'=>'3rd Year','4'=>'4th Year'] as $v=>$l): ?>
                                <option value="<?= $v ?>"
                                    <?= (($_POST['year_level'] ?? $student['year_level'] ?? '') == $v) ? 'selected' : '' ?>>
                                    <?= $l ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" placeholder="yourname@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? $student['email'] ?? '') ?>">
                </div>

                <div class="form-group section-gap">
                    <label>Address <span class="req">*</span></label>
                    <input type="text" name="address" placeholder="Your address"
                        value="<?= htmlspecialchars($_POST['address'] ?? $student['address'] ?? '') ?>">
                </div>

                <!-- ── CHANGE PASSWORD ── -->
                <p class="section-title">Change Password</p>

                <div class="row-2">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="Leave blank to keep">
                        <p class="pass-hint">Min. 6 characters</p>
                    </div>
                    <div class="form-group">
                        <label>Repeat Password</label>
                        <input type="password" name="repeat_password" placeholder="Confirm new password">
                    </div>
                </div>

                <button type="submit" class="btn-save">Save Changes</button>

            </form>
        </div>
    </div>
</main>

<footer>
    &copy; 2026 College of Computer Studies &mdash; University of Cebu
</footer>

<script>
    const input   = document.getElementById('profilePicInput');
    const preview = document.getElementById('avatarPreview');
    const badge   = document.getElementById('picBadge');

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Client-side type check
        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Only JPG, PNG, GIF, or WEBP images are allowed.');
            this.value = '';
            return;
        }

        // Client-side size check (2 MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Image must be under 2 MB.');
            this.value = '';
            return;
        }

        // Live preview
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            badge.classList.add('show');
        };
        reader.readAsDataURL(file);
    });
</script>

</body>
</html>