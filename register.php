<?php
// FILE: register.php

$error   = "";
$success = "";

// This runs only when the Register button is clicked
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Step 1: Get all the values the user typed in
    $id_number   = trim($_POST["id_number"]);
    $last_name   = trim($_POST["last_name"]);
    $first_name  = trim($_POST["first_name"]);
    $middle_name = trim($_POST["middle_name"]);
    $course      = trim($_POST["course"]);
    $year_level  = trim($_POST["year_level"]);
    $email       = trim($_POST["email"]);
    $address       = trim($_POST["address"]);
    $password    = trim($_POST["password"]);
    $repeat_pass = trim($_POST["repeat_pass"]);

    // Step 2: Check if required fields are empty
    if (empty($id_number) || empty($last_name) || empty($first_name) ||
        empty($course)    || empty($year_level) || empty($email)     ||
        empty($address)   || empty($password)  || empty($repeat_pass)) {
        $error = "Please fill in all required fields marked with *.";

    // Step 3: Check if the two passwords match
    } elseif ($password !== $repeat_pass) {
        $error = "Passwords do not match. Please try again.";

    } else {
        // No database yet — this is where we'll save to DB later
        $success = "Account created successfully! You can now login.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Sit-in Monitoring System - Register</title>
    <style>

        /* Remove default browser spacing */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Page background and font */
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f3fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── NAVBAR ── */
        .navbar {
            background-color: #2e1260;
            padding: 12px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-title {
            color: #f0a500;
            font-size: 15px;
            font-weight: bold;
        }

        .navbar-links {
            list-style: none;
            display: flex;
            gap: 5px;
        }

        .navbar-links a {
            color: white;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 14px;
        }

        .navbar-links a:hover,
        .navbar-links a.active {
            background-color: rgba(240, 165, 0, 0.2);
            color: #f0a500;
        }

        /* ── PAGE CENTER ── */
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        /* ── WHITE CARD ── */
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(74, 32, 128, 0.12);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
        }

        /* ── PURPLE HEADER STRIP ── */
        .card-header {
            background: linear-gradient(135deg, #2e1260, #4a2080);
            padding: 25px 30px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .card-header img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 50%;
            border: 2px solid rgba(240, 165, 0, 0.5);
            background: rgba(255,255,255,0.05);
        }

        .card-header-text h2 {
            color: #f0a500;
            font-size: 18px;
            margin-bottom: 2px;
        }

        .card-header-text p {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            letter-spacing: 0.05em;
        }

        /* ── FORM BODY ── */
        .card-body {
            padding: 28px 30px;
        }

        /* ── ALERT MESSAGES ── */
        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .alert-error {
            background-color: #fdecea;
            border-left: 4px solid #e53935;
            color: #b71c1c;
        }

        .alert-success {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            color: #2e7d32;
        }

        /* ── SECTION TITLE (groups the fields) ── */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6a3ab0;
            border-bottom: 1.5px solid #ede8fa;
            padding-bottom: 6px;
            margin-bottom: 14px;
        }

        /* ── FORM GROUP (label + input) ── */
        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }

        /* Red star for required fields */
        .form-group label .req {
            color: #e53935;
            margin-left: 2px;
        }

        /* Input and select styling */
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #ddd6f0;
            border-radius: 6px;
            font-size: 13px;
            color: #333;
            background-color: #f5f3fa;
            outline: none;
            transition: border-color 0.2s;
        }

        /* Highlight when user clicks the field */
        .form-group input:focus,
        .form-group select:focus {
            border-color: #4a2080;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(74, 32, 128, 0.08);
        }

        /* ── TWO COLUMNS SIDE BY SIDE ── */
        .row-2 {
            display: flex;
            gap: 14px;
        }

        .row-2 .form-group {
            flex: 1;
        }

        /* ── SPACING BETWEEN SECTIONS ── */
        .section-gap {
            margin-bottom: 20px;
        }

        /* ── REGISTER BUTTON ── */
        .btn-register {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #4a2080, #6a3ab0);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 6px;
        }

        .btn-register:hover {
            background: linear-gradient(135deg, #3a1870, #5a2a9a);
            transform: translateY(-1px);
        }

        /* ── LOGIN LINK ── */
        .login-prompt {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #777;
        }

        .login-prompt a {
            color: #f0a500;
            font-weight: bold;
            text-decoration: none;
        }

        .login-prompt a:hover {
            text-decoration: underline;
        }

        /* ── BACK BUTTON ── */
        .btn-back {
            display: inline-block;
            background-color: rgba(240,165,0,0.15);
            color: #f0a500;
            text-decoration: none;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .btn-back:hover {
            background-color: rgba(240,165,0,0.25);
        }

        /* ── FOOTER ── */
        footer {
            background-color: #2e1260;
            color: rgba(255,255,255,0.5);
            text-align: center;
            padding: 14px;
            font-size: 13px;
        }

    </style>
</head>
<body>

    <!-- ==================== NAVBAR ==================== -->
    <nav class="navbar">
        <span class="navbar-title">College of Computer Studies Sit-in Monitoring System</span>
        <ul class="navbar-links">
            <li><a href="#">Home</a></li>
            <li><a href="#">Community</a></li>
            <li><a href="#">About</a></li>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php" class="active">Register</a></li>
        </ul>
    </nav>

    <!-- ==================== MAIN ==================== -->
    <main>
        <div class="card">

            <!-- PURPLE HEADER with logo -->
            <div class="card-header">
                <img src="ccs.png" alt="CCS Logo">
                <div class="card-header-text">
                    <h2>Sign Up</h2>
                    <p>UNIVERSITY OF CEBU · MAIN CAMPUS · UC SUCCESS</p>
                </div>
            </div>

            <!-- FORM BODY -->
            <div class="card-body">

                <!-- Back button -->
                <a href="login.php" class="btn-back">&#8592; Back to Login</a>

                <!-- Show error message if there is one -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Show success message if registration worked -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <!-- REGISTRATION FORM -->
                <form method="POST" action="register.php">

                    <!-- ── SECTION 1: Personal Info ── -->
                    <p class="section-title">Personal Information</p>

                    <!-- ID Number (full width) -->
                    <div class="form-group">
                        <label>ID Number <span class="req">*</span></label>
                        <input type="text" name="id_number"
                            placeholder="e.g. 2021-00123"
                            value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
                    </div>

                    <!-- Last Name and First Name side by side -->
                    <div class="row-2">
                        <div class="form-group">
                            <label>Last Name <span class="req">*</span></label>
                            <input type="text" name="last_name"
                                placeholder="e.g. Dela Cruz"
                                value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>First Name <span class="req">*</span></label>
                            <input type="text" name="first_name"
                                placeholder="e.g. Juan"
                                value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Middle Name (optional) -->
                    <div class="form-group section-gap">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name"
                            placeholder="e.g. Santos (optional)"
                            value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                    </div>

                    <!-- ── SECTION 2: Academic Info ── -->
                    <p class="section-title">Academic Information</p>

                    <!-- Course and Year Level side by side -->
                    <div class="row-2">
                        <div class="form-group">
                            <label>Course <span class="req">*</span></label>
                            <select name="course">
                                <option value="">-- Select --</option>
                                <option value="BSIT" <?= ($_POST['course'] ?? '') === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                                <option value="BSCS" <?= ($_POST['course'] ?? '') === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
                                <option value="BSIS" <?= ($_POST['course'] ?? '') === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
                                <option value="ACT"  <?= ($_POST['course'] ?? '') === 'ACT'  ? 'selected' : '' ?>>ACT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year Level <span class="req">*</span></label>
                            <select name="year_level">
                                <option value="">-- Select --</option>
                                <option value="1" <?= ($_POST['year_level'] ?? '') === '1' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= ($_POST['year_level'] ?? '') === '2' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= ($_POST['year_level'] ?? '') === '3' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= ($_POST['year_level'] ?? '') === '4' ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>
                    </div>

                    <!-- Email and address  side by side -->
                   
                        <div class="form-group">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email"
                                placeholder="yourname@email.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Address<span class="req">*</span></label>
                            <input type="text" name="address"
                                placeholder="Your address"
                                value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                        </div>
                

                    <!-- ── SECTION 3: Password ── -->
                    <p class="section-title">Set Password</p>

                    <!-- Password and Confirm Password side by side -->
                    <div class="row-2">
                        <div class="form-group">
                            <label>Password <span class="req">*</span></label>
                            <input type="password" name="password"
                                placeholder="Create a password">
                        </div>
                        <div class="form-group">
                            <label>Repeat Password <span class="req">*</span></label>
                            <input type="password" name="repeat_pass"
                                placeholder="Confirm password">
                        </div>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <button type="submit" class="btn-register">Register</button>

                </form>

                <!-- Already have account? -->
                <p class="login-prompt">
                    Already have an account? <a href="login.php">Login here</a>
                </p>

            </div>
        </div>
    </main>

    <!-- ==================== FOOTER ==================== -->
    <footer>
        &copy; 2026 College of Computer Studies
    </footer>

</body>
</html>
