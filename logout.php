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

// Clear all session data.
$_SESSION = [];

// Expire the session cookie as well.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        (bool) $params["secure"],
        (bool) $params["httponly"]
    );
}

session_destroy();

header("Location: index.php");
exit();
