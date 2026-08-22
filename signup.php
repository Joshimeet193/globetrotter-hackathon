<?php

// Secure session configuration
$is_https = (
    (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
    (isset($_SERVER["SERVER_PORT"]) && (int) $_SERVER["SERVER_PORT"] === 443)
);

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => $is_https,
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

// Create a CSRF token for state-changing forms.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function validate_csrf_token(): bool
{
    return isset($_POST["csrf_token"], $_SESSION["csrf_token"])
        && is_string($_POST["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"]);
}

function text_length(string $value): int
{
    return function_exists("mb_strlen") ? mb_strlen($value, "UTF-8") : strlen($value);
}

// Include database connection
require_once "includes/db-connect.php";


// Variable for displaying messages
$message = "";

// Variable for message type
$message_type = "";


// Check whether signup form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Reject forged submissions before doing any account work.
    if (!validate_csrf_token()) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "danger";
    } else {
        // Get values from form safely.
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        // =========================
        // VALIDATION
        // =========================

        if ($name === "" || $email === "" || $password === "") {
            $message = "Please fill in all fields.";
            $message_type = "danger";
        } elseif (text_length($name) > 50) {
            $message = "Name must not exceed 50 characters.";
            $message_type = "danger";
        } elseif (text_length($email) > 100) {
            $message = "Email address must not exceed 100 characters.";
            $message_type = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = "danger";
        } elseif (strlen($password) < 8) {
            $message = "Password must contain at least 8 characters.";
            $message_type = "danger";
        } else {
            // =========================
            // CHECK EXISTING EMAIL
            // =========================

            $sql = "SELECT User_ID FROM USERS WHERE Email = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                $message = "Something went wrong. Please try again.";
                $message_type = "danger";
            } else {
                $stmt->bind_param("s", $email);

                if (!$stmt->execute()) {
                    $message = "Something went wrong. Please try again.";
                    $message_type = "danger";
                } else {
                    $stmt->store_result();

                    if ($stmt->num_rows > 0) {
                        $message = "An account with this email already exists.";
                        $message_type = "danger";
                    } else {
                        // =========================
                        // HASH PASSWORD
                        // =========================

                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        if ($hashed_password === false) {
                            $message = "Something went wrong. Please try again.";
                            $message_type = "danger";
                        } else {
                            // =========================
                            // INSERT USER
                            // =========================

                            $sql = "INSERT INTO USERS (Name, Email, Password) VALUES (?, ?, ?)";
                            $insert_stmt = $conn->prepare($sql);

                            if ($insert_stmt === false) {
                                $message = "Something went wrong. Please try again.";
                                $message_type = "danger";
                            } else {
                                $insert_stmt->bind_param("sss", $name, $email, $hashed_password);

                                if ($insert_stmt->execute()) {
                                    $message = "Account created successfully! You can now login.";
                                    $message_type = "success";
                                } else {
                                    // The UNIQUE constraint is the final protection against a race condition.
                                    if ($conn->errno === 1062) {
                                        $message = "An account with this email already exists.";
                                    } else {
                                        $message = "Something went wrong. Please try again.";
                                    }
                                    $message_type = "danger";
                                }

                                $insert_stmt->close();
                            }
                        }
                    }
                }

                $stmt->close();
            }
        }
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
     SIGNUP — mirrored boarding-pass ticket, so this reads as
     the other half of the same document as the login page
     ========================= -->

<div class="container">

    <div class="ticket-wrap ticket-wrap--reverse">

        <!-- Left stub: brand moment, matches login's but with its own copy -->
        <div class="ticket-stub">
            <div>
                <div class="mb-3" style="font-size:1.6rem;">🧳</div>

                <h1>
                    One account.<br>
                    Every trip ahead.
                </h1>

                <p class="lede">
                    Save your stops, track your budget, and pick up planning from any device — starting with today's sign-up.
                </p>
            </div>

            <div class="ticket-meta">
                <div>
                    Passport
                    <span>New</span>
                </div>

                <div>
                    Validity
                    <span>Lifetime</span>
                </div>

                <div>
                    Class
                    <span>Explorer</span>
                </div>
            </div>
        </div>


        <!-- Right: the actual signup form -->
        <div class="ticket-form">

            <div class="icon-circle">
                <i class="bi bi-person-plus"></i>
            </div>

            <h2 class="mb-1">Create Account</h2>

            <p class="text-muted mb-4">
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


                    <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>

                </div>

            <?php } ?>


            <!-- Signup Form -->

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">


                <!-- Full Name -->

                <div class="form-floating mb-3">

                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        placeholder="Full Name"
                        maxlength="50"
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
                        maxlength="100"
                        required
                    >

                    <label for="email">

                        <i class="bi bi-envelope me-2"></i>

                        Email Address

                    </label>

                </div>


                <!-- Password -->

                <div class="form-floating mb-3 password-field">

                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Password"
                        minlength="8"
                        required
                    >

                    <label for="password">

                        <i class="bi bi-lock me-2"></i>

                        Password

                    </label>

                    <button
                        type="button"
                        class="password-toggle-btn"
                        data-toggle-for="password"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <i class="bi bi-eye"></i>
                    </button>

                </div>


                <!-- Password Information -->

                <div class="small text-muted mb-3">

                    <i class="bi bi-shield-check me-1"></i>

                    Password must be at least 8 characters.

                </div>


                <!-- Signup Button -->

                <button
                    type="submit"
                    class="btn btn-primary w-100"
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

</div>


<!-- =========================
     FOOTER
     ========================= -->

<footer>

    <p>
        Made with <span>❤️</span> for GlobeTrotter Hackathon
    </p>

</footer>


<script>
    document.querySelectorAll(".password-toggle-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var input = document.getElementById(btn.getAttribute("data-toggle-for"));
            var icon = btn.querySelector("i");
            var showing = input.type === "text";

            input.type = showing ? "password" : "text";
            icon.classList.toggle("bi-eye", showing);
            icon.classList.toggle("bi-eye-slash", !showing);
            btn.setAttribute("aria-pressed", String(!showing));
            btn.setAttribute("aria-label", showing ? "Show password" : "Hide password");
        });
    });
</script>


</body>

</html>
