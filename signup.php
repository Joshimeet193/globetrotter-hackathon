<?php

// Start the session
session_start();

// Include database connection
require_once "includes/db-connect.php";


// Variable for displaying messages
$message = "";

// Variable for message type
$message_type = "";


// Check whether signup form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get values from form
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];


    // =========================
    // VALIDATION
    // =========================

    // Check empty fields
    if (empty($name) || empty($email) || empty($password)) {

        $message = "Please fill in all fields.";
        $message_type = "danger";

    }

    // Check valid email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "danger";

    }

    // Check password length
    elseif (strlen($password) < 6) {

        $message = "Password must contain at least 6 characters.";
        $message_type = "danger";

    }

    else {

        // =========================
        // CHECK EXISTING EMAIL
        // =========================

        $sql = "SELECT User_ID
                FROM USERS
                WHERE Email = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $stmt->store_result();


        // If email already exists
        if ($stmt->num_rows > 0) {

            $message = "An account with this email already exists.";
            $message_type = "danger";

        }

        else {

            // =========================
            // HASH PASSWORD
            // =========================

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // =========================
            // INSERT USER
            // =========================

            $sql = "INSERT INTO USERS
                    (Name, Email, Password)
                    VALUES (?, ?, ?)";

            $insert_stmt = $conn->prepare($sql);

            $insert_stmt->bind_param(
                "sss",
                $name,
                $email,
                $hashed_password
            );


            // Execute INSERT query
            if ($insert_stmt->execute()) {

                $message = "Account created successfully! You can now login.";
                $message_type = "success";

            } else {

                $message = "Something went wrong. Please try again.";
                $message_type = "danger";

            }


            // Close insert statement
            $insert_stmt->close();
        }


        // Close select statement
        $stmt->close();
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GlobeTrotter - Sign Up</title>


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
     SIGNUP FORM
     ========================= -->

<div class="container">

    <div class="auth-container">


        <!-- Signup Icon -->

        <div class="icon-circle mx-auto">

            <i class="bi bi-person-plus"></i>

        </div>


        <!-- Heading -->

        <h2>Create Account</h2>

        <p class="text-center text-muted mb-4">
            Start planning your next adventure
        </p>


        <!-- Display Message -->

        <?php if (!empty($message)) { ?>

            <div
                class="alert alert-<?php echo $message_type; ?>"
                role="alert"
            >

                <?php if ($message_type == "success") { ?>

                    <i class="bi bi-check-circle me-2"></i>

                <?php } else { ?>

                    <i class="bi bi-exclamation-circle me-2"></i>

                <?php } ?>


                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php } ?>


        <!-- Signup Form -->

        <form method="POST" action="">


            <!-- Full Name -->

            <div class="form-floating mb-3">

                <input
                    type="text"
                    class="form-control"
                    id="name"
                    name="name"
                    placeholder="Full Name"
                    required
                >

                <label for="name">

                    <i class="bi bi-person me-2"></i>

                    Full Name

                </label>

            </div>


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
                    minlength="6"
                    required
                >

                <label for="password">

                    <i class="bi bi-lock me-2"></i>

                    Password

                </label>

            </div>


            <!-- Password Information -->

            <div class="small text-muted mb-3">

                <i class="bi bi-shield-check me-1"></i>

                Password must be at least 6 characters.

            </div>


            <!-- Signup Button -->

            <button
                type="submit"
                class="btn btn-primary"
            >

                <i class="bi bi-person-plus me-2"></i>

                Create Account

            </button>


        </form>


        <!-- Login Link -->

        <p class="text-center mt-4 mb-0">

            Already have an account?

            <a href="index.php">
                Login
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

