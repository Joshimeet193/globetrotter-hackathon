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

// Clear all session data (covers user_id, User_ID, Name, user_name,
// full_name, csrf_token — every key used across the app).
$_SESSION = [];

// Also expire the session cookie itself in the browser.
// session_destroy() alone only clears server-side data; without this,
// the browser keeps sending the old (now-invalid) session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session on the server.
session_destroy();

// Redirect to the login/home page.
header("Location: index.php");
exit();
