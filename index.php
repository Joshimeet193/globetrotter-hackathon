<?php

// Start the session
session_start();

// Include database connection
require_once "includes/db-connect.php";


// If user is already logged in,
// redirect them to dashboard
if (isset($_SESSION["User_ID"])) {
    header("Location: dashboard.php");
    exit();
}


// Variable to store error message
$message = "";


// Check whether login form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get email and password from form
    $email = trim($_POST["email"]);
    $password = $_POST["password"];


    // Check if fields are empty
    if (empty($email) || empty($password)) {

        $message = "Please enter both email and password.";

    } else {

        // Find the user using email
        $sql = "SELECT User_ID, Name, Password
                FROM USERS
                WHERE Email = ?";

        // Prepare SQL query
        $stmt = $conn->prepare($sql);

        // Bind email to ?
        $stmt->bind_param("s", $email);

        // Execute query
        $stmt->execute();

        // Get result
        $result = $stmt->get_result();


        // Check if user exists
        if ($result->num_rows == 1) {

            // Get user's data
            $user = $result->fetch_assoc();


            // Verify entered password with hashed password
            if (password_verify($password, $user["Password"])) {

                // Store user information in session
                $_SESSION["User_ID"] = $user["User_ID"];
                $_SESSION["Name"] = $user["Name"];
                $_SESSION["user_id"] = $user["User_ID"];   // alias: some pages check lowercase key
                $_SESSION["full_name"] = $user["Name"];    // alias: navbar.php checks this key


                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();

            } else {

                $message = "Invalid email or password.";

            }

        } else {

            $message = "Invalid email or password.";

        }


        // Close statement
        $stmt->close();
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GlobeTrotter - Login</title>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Team's shared CSS (also loads our fonts: Fraunces / Work Sans / Space Mono) -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        /* ---- Page-only styles: boarding-pass split ticket layout ---- */
        .ticket-wrap {
            max-width: 920px;
            margin: 56px auto;
            display: flex;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 3px 0 rgba(27,42,65,0.05), 0 18px 40px rgba(27,42,65,0.14);
            animation: fadeUp 0.55s ease both;
            border: 1px solid var(--line);
        }

        .ticket-stub {
            flex: 0 0 40%;
            background: var(--ink);
            color: #fff;
            padding: 46px 36px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        .ticket-stub::before {
            content: '';
            position: absolute;
            top: 0; bottom: 0; right: -1px;
            width: 0;
            border-right: 3px dashed rgba(255,255,255,0.18);
        }

        .ticket-stub h1 {
            color: #fff;
            font-size: 2.1rem;
            line-height: 1.15;
            margin-bottom: 14px;
        }

        .ticket-stub p.lede {
            color: rgba(255,255,255,0.72);
            font-size: 0.98rem;
            max-width: 30ch;
        }

        .ticket-meta {
            font-family: 'Space Mono', monospace;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.55);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            gap: 26px;
            margin-top: 30px;
        }
        .ticket-meta div span {
            display: block;
            color: var(--gold);
            font-size: 1rem;
            letter-spacing: 0.02em;
            margin-top: 2px;
        }

        .ticket-form {
            flex: 1;
            background: var(--card);
            padding: 46px 40px;
        }

        .ticket-form .icon-circle { margin-bottom: 6px; }

        @media (max-width: 767.98px) {
            .ticket-wrap { flex-direction: column; margin: 20px auto; border-radius: 14px; }
            .ticket-stub { padding: 30px 26px; }
            .ticket-stub::before { border-right: none; border-bottom: 3px dashed rgba(255,255,255,0.18); right: 0; bottom: -1px; top: auto; height: 0; }
            .ticket-form { padding: 30px 26px; }
        }
    </style>

</head>


<body class="bg-cream">


<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            🌍 GlobeTrotter
        </a>
    </div>
</nav>


<div class="container">

    <div class="ticket-wrap">

        <!-- Left stub: brand moment, styled like a boarding-pass counterfoil -->
        <div class="ticket-stub">
            <div>
                <div class="mb-3" style="font-size:1.6rem;">✈️</div>
                <h1>Your next trip<br>starts with one login.</h1>
                <p class="lede">Plan stops, track budgets and build itineraries that actually hold together — sign back in to pick up where you left off.</p>
            </div>

            <div class="ticket-meta">
                <div>Status <span>Boarding</span></div>
                <div>Class <span>Explorer</span></div>
                <div>Gate <span>GT‑01</span></div>
            </div>
        </div>

        <!-- Right: the actual login form -->
        <div class="ticket-form">

            <div class="icon-circle">
                <i class="bi bi-airplane"></i>
            </div>

            <h2 class="mb-1">Welcome Back</h2>
            <p class="text-muted mb-4">Login to continue your journey</p>

            <?php if (!empty($message)) { ?>

                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>

            <?php } ?>

            <form method="POST" action="">

                <div class="form-floating mb-3">
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        placeholder="Email Address"
                        required
                    >
                    <label for="email">
                        <i class="bi bi-envelope me-2"></i>
                        Email Address
                    </label>
                </div>

                <div class="form-floating mb-3">
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Password"
                        required
                    >
                    <label for="password">
                        <i class="bi bi-lock me-2"></i>
                        Password
                    </label>
                </div>

                <div class="text-end mb-3">
                    <a href="#" class="small">
                        Forgot Password?
                    </a>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Login
                </button>

            </form>

            <p class="text-center mt-4 mb-0">
                Don't have an account?
                <a href="signup.php">
                    Sign Up
                </a>
            </p>

        </div>

    </div>

</div>


<footer>
    <p>
        Made with <span>❤️</span> for GlobeTrotter Hackathon
    </p>
</footer>


</body>

</html>
