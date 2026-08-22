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


    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Team's shared CSS -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>


<body class="bg-cream">


<!-- =========================
     NAVBAR
     ========================= -->

<nav class="navbar navbar-expand-lg">

    <div class="container">

        <a class="navbar-brand" href="index.php">
            🌍 GlobeTrotter
        </a>

    </div>

</nav>


<!-- =========================
     LOGIN FORM
     ========================= -->

<div class="container">

    <div class="auth-container">


        <!-- Login Icon -->

        <div class="icon-circle mx-auto">

            <i class="bi bi-airplane"></i>

        </div>


        <!-- Heading -->

        <h2>Welcome Back!</h2>

        <p class="text-center text-muted mb-4">
            Login to continue your journey
        </p>


        <!-- Display Error Message -->

        <?php if (!empty($message)) { ?>

            <div class="alert alert-danger" role="alert">

                <i class="bi bi-exclamation-circle me-2"></i>

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php } ?>


        <!-- Login Form -->

        <form method="POST" action="">


            <!-- Email -->

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


            <!-- Password -->

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


            <!-- Forgot Password -->

            <div class="text-end mb-3">

                <a href="#" class="small">
                    Forgot Password?
                </a>

            </div>


            <!-- Login Button -->

            <button
                type="submit"
                class="btn btn-primary"
            >

                <i class="bi bi-box-arrow-in-right me-2"></i>

                Login

            </button>


        </form>


        <!-- Signup Link -->

        <p class="text-center mt-4 mb-0">

            Don't have an account?

            <a href="signup.php">
                Sign Up
            </a>

        </p>


    </div>

</div>


<!-- =========================
     FOOTER
     ========================= -->

<footer>

    <p>
        Made with <span>❤️</span> for GlobeTrotter Hackathon
    </p>

</footer>


</body>

</html>
