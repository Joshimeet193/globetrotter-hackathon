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


// If user is already logged in,
// redirect them to dashboard
if (isset($_SESSION["user_id"]) || isset($_SESSION["User_ID"])) {
    header("Location: dashboard.php");
    exit();
}


// Variable to store error message
$message = "";


// Check whether login form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Reject forged login submissions.
    if (!validate_csrf_token()) {
        $message = "Invalid request. Please refresh the page and try again.";
    }

    // Get email and password from form
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    if (empty($message) && (empty($email) || empty($password))) {

        $message = "Please enter both email and password.";

    } elseif (empty($message)) {

        // Find the user using email
        $sql = "SELECT User_ID, Name, Password
                FROM USERS
                WHERE Email = ?";

        // Prepare SQL query
        $stmt = $conn->prepare($sql);

        // Check whether query preparation was successful
        if ($stmt === false) {

            $message = "Something went wrong. Please try again.";

        } else {

            // Bind email to ?
            $stmt->bind_param("s", $email);

            // Execute query
            if ($stmt->execute()) {

                // Get result
                $result = $stmt->get_result();


                // Check if user exists
                if ($result->num_rows == 1) {

                    // Get user's data
                    $user = $result->fetch_assoc();


                    // Verify entered password with hashed password
                    if (password_verify($password, $user["Password"])) {

                        // Regenerate session ID after successful login
                        session_regenerate_id(true);


                        // Store user information in session
                        $_SESSION["user_id"] = (int) $user["User_ID"];
                        $_SESSION["user_name"] = $user["Name"];

                        // Compatibility aliases for existing pages in the project.
                        $_SESSION["User_ID"] = (int) $user["User_ID"];
                        $_SESSION["Name"] = $user["Name"];
                        $_SESSION["full_name"] = $user["Name"];


                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit();

                    } else {

                        $message = "Invalid email or password.";

                    }

                } else {

                    $message = "Invalid email or password.";

                }

            } else {

                $message = "Something went wrong. Please try again.";

            }


            // Close statement
            $stmt->close();
        }
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

                <h1>
                    Your next trip<br>
                    starts with one login.
                </h1>

                <p class="lede">
                    Plan stops, track budgets and build itineraries that actually hold together — sign back in to pick up where you left off.
                </p>
            </div>

            <div class="ticket-meta">
                <div>
                    Status
                    <span>Boarding</span>
                </div>

                <div>
                    Class
                    <span>Explorer</span>
                </div>

                <div>
                    Gate
                    <span>GT-01</span>
                </div>
            </div>
        </div>


        <!-- Right: the actual login form -->
        <div class="ticket-form">

            <div class="icon-circle">
                <i class="bi bi-airplane"></i>
            </div>

            <h2 class="mb-1">Welcome Back</h2>

            <p class="text-muted mb-4">
                Login to continue your journey
            </p>


            <?php if (!empty($message)) { ?>

                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
                </div>

            <?php } ?>


            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">

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


                <div class="form-floating mb-3 password-field">

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


                <button
                    type="submit"
                    class="btn btn-primary w-100"
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
