<?php
session_start();
require_once "db.php";
 
if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}
 
$error = "";
 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id       = trim($_POST["id_number"] ?? "");
    $password = trim($_POST["password"]  ?? "");
 
    if (empty($id) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {

        // ── CHECK ADMIN FIRST ──
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name']     = $admin['full_name'] ?? 'CCS Admin';
            header("Location: admin_dashboard.php");
            exit;
        }

        // ── CHECK STUDENTS NEXT ──
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id_number = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
 
        if ($student && password_verify($password, $student['password'])) {
            $_SESSION['student_id']  = $student['id'];
            $_SESSION['id_number']   = $student['id_number'];
            $_SESSION['full_name']   = $student['first_name'] . ' ' . $student['last_name'];
            $_SESSION['first_name']  = $student['first_name'];
            $_SESSION['last_name']   = $student['last_name'];
            $_SESSION['course']      = $student['course'];
            $_SESSION['year_level']  = $student['year_level'];
            $_SESSION['email']       = $student['email'];
            $_SESSION['address']     = $student['address'];
            $_SESSION['sessions']    = $student['sessions'] ?? 30;
            $_SESSION['profile_pic'] = $student['profile_pic'] ?? '';
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid ID/username or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Sit-in Monitoring System — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple: #4a2080;
            --purple-dark: #2e1260;
            --purple-light: #6a3ab0;
            --gold: #f0a500;
            --gold-light: #ffd060;
            --white: #ffffff;
            --gray: #f5f3fa;
            --text-dark: #1a1030;
            --text-muted: #7a6a9a;
        }

        body {
            font-family: 'Lato', sans-serif;
            background: var(--gray);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        nav {
            background: var(--purple-dark);
            padding: 0 2.5rem;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }

        .nav-brand {
            font-family: 'Cinzel', serif;
            font-size: 1rem;
            color: var(--gold);
            letter-spacing: 0.04em;
        }

        .nav-links {
            display: flex;
            gap: 0.25rem;
            list-style: none;
        }

        .nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.875rem;
            padding: 0.4rem 0.9rem;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(240,165,0,0.15);
            color: var(--gold-light);
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            position: relative;
            overflow: hidden;
        }

        main::before, main::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        main::before {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(74,32,128,0.08) 0%, transparent 70%);
            top: -100px; left: -100px;
        }
        main::after {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(240,165,0,0.07) 0%, transparent 70%);
            bottom: -80px; right: -80px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(74,32,128,0.12), 0 2px 8px rgba(0,0,0,0.06);
            width: 100%;
            max-width: 860px;
            display: flex;
            overflow: hidden;
            animation: slideUp 0.55s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-left {
            background: linear-gradient(145deg, var(--purple-dark) 0%, var(--purple) 60%, var(--purple-light) 100%);
            flex: 0 0 320px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .card-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 30px,
                rgba(255,255,255,0.02) 30px,
                rgba(255,255,255,0.02) 31px
            );
        }

        .logo-wrapper {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            border: 3px solid rgba(240,165,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.75rem;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 40px rgba(240,165,0,0.15);
            overflow: hidden;
        }

        .logo-wrapper img {
            width: 155px;
            height: 155px;
            object-fit: contain;
            border-radius: 50%;
        }

        .school-name {
            font-family: 'Cinzel', serif;
            font-size: 1rem;
            color: var(--gold-light);
            text-align: center;
            line-height: 1.5;
            position: relative;
            z-index: 1;
            margin-bottom: 0.5rem;
        }

        .gold-divider {
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 1.25rem auto;
            position: relative;
            z-index: 1;
        }

        .tagline {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.45);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .card-right {
            flex: 1;
            padding: 3rem 2.75rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-title {
            font-family: 'Cinzel', serif;
            font-size: 1.6rem;
            color: var(--purple-dark);
            margin-bottom: 0.4rem;
        }

        .form-subtitle {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 2.25rem;
        }

        .alert {
            background: #fef3cd;
            border-left: 4px solid var(--gold);
            color: #7a5800;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .alert.success {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }

        .field {
            margin-bottom: 1.35rem;
        }

        label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.45rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #ddd6f0;
            border-radius: 8px;
            font-family: 'Lato', sans-serif;
            font-size: 0.95rem;
            color: var(--text-dark);
            background: var(--gray);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        input:focus {
            border-color: var(--purple-light);
            background: white;
            box-shadow: 0 0 0 3px rgba(106,58,176,0.1);
        }

        .row-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            font-size: 0.85rem;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        input[type="checkbox"] {
            accent-color: var(--purple);
            width: 15px; height: 15px;
        }

        .forgot-link {
            color: var(--purple-light);
            text-decoration: none;
            font-size: 0.85rem;
        }

        .forgot-link:hover { text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-light) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Cinzel', serif;
            font-size: 1rem;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 4px 15px rgba(74,32,128,0.35);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74,32,128,0.45);
        }

        .btn-login:active { transform: translateY(0); }

        .register-prompt {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .register-prompt a {
            color: var(--gold);
            font-weight: 700;
            text-decoration: none;
        }

        .register-prompt a:hover { text-decoration: underline; }

        /* Login hint box */
        .login-hint {
            background: var(--gray);
            border: 1px dashed rgba(74,32,128,0.2);
            border-radius: 8px;
            padding: 0.65rem 1rem;
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .login-hint strong { color: var(--purple); }

        footer {
            background: var(--purple-dark);
            color: rgba(255,255,255,0.5);
            text-align: center;
            padding: 1rem;
            font-size: 0.8rem;
        }

        @media (max-width: 640px) {
            .card { flex-direction: column; }
            .card-left { flex: none; padding: 2rem 1.5rem; }
            .card-right { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<nav>
    <span class="nav-brand">College of Computer Studies Sit-in Monitoring System</span>
    <ul class="nav-links">
        <li><a href="#">Home</a></li>
        <li><a href="#">Community</a></li>
        <li><a href="#">About</a></li>
        <li><a href="login.php" class="active">Login</a></li>
        <li><a href="register.php">Register</a></li>
    </ul>
</nav>

<main>
    <div class="card">

        <div class="card-left">
            <div class="logo-wrapper">
                <img src="ccs.png" alt="CCS Logo">
            </div>
            <p class="school-name">College of<br>Computer Studies</p>
            <div class="gold-divider"></div>
            <p class="tagline">UNIVERSITY OF CEBU · MAIN CAMPUS · UC SUCCESS</p>
        </div>

        <div class="card-right">
            <h1 class="form-title">Welcome Back</h1>
            <p class="form-subtitle">Sign in to access the Sit-in Monitoring System</p>

            <!-- Hint box -->
            <div class="login-hint">
                👤 <strong>Students:</strong> use your <strong>ID Number</strong> &nbsp;|&nbsp;
                ⚙ <strong>Admin:</strong> use your <strong>username</strong>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert <?= str_starts_with($error, '✅') ? 'success' : '' ?>">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">

                <div class="field">
                    <label for="id_number">ID Number / Username</label>
                    <input
                        type="text"
                        id="id_number"
                        name="id_number"
                        placeholder="Student ID or admin username"
                        value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                    >
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                    >
                </div>

                <div class="row-options">
                    <label class="remember">
                        <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                        Remember me
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">Login</button>

            </form>

            <p class="register-prompt">
                Don't have an account? <a href="register.php">Register</a>
            </p>

        </div>
    </div>
</main>

<footer>
    &copy; 2026 College of Computer Studies
</footer>

</body>
</html>