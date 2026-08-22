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

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !filter_var($_SESSION["user_id"], FILTER_VALIDATE_INT)) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

// Variables for messages
$message = "";
$message_type = "";

// Helper: only delete files that this application stores in uploads/.
function delete_profile_photo(string $relative_path): void
{
    $relative_path = str_replace("\\", "/", $relative_path);

    if (strpos($relative_path, "uploads/") !== 0) {
        return;
    }

    $full_path = __DIR__ . "/" . $relative_path;

    if (is_file($full_path)) {
        unlink($full_path);
    }
}

// Fetch current user details
$sql = "SELECT User_ID, Name, Email, Profile_Photo, Language, Created_At
        FROM USERS
        WHERE User_ID = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    $message = "Something went wrong. Please try again.";
    $message_type = "danger";
    $user = null;
} else {
    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        http_response_code(500);
        $message = "Something went wrong. Please try again.";
        $message_type = "danger";
        $user = null;
    } else {
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
        } else {
            $user = null;
        }
    }

    $stmt->close();
}

if ($user === null) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit();
}

// Update profile
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validate_csrf_token()) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "danger";
    } else {
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $language = trim($_POST["language"] ?? "");

        // Validate fields
        if ($name === "" || $email === "") {
            $message = "Name and email are required.";
            $message_type = "danger";
        } elseif (text_length($name) > 50) {
            $message = "Name must not exceed 50 characters.";
            $message_type = "danger";
        } elseif (text_length($email) > 100) {
            $message = "Email address must not exceed 100 characters.";
            $message_type = "danger";
        } elseif (text_length($language) > 30) {
            $message = "Language must not exceed 30 characters.";
            $message_type = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = "danger";
        }

        $old_profile_photo = $user["Profile_Photo"] ?? "";
        $new_profile_photo = $old_profile_photo;
        $new_upload_path = null;

        // Check whether a new photo was selected.
        if (empty($message) && isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
            $upload_error = $_FILES["profile_photo"]["error"];

            if ($upload_error !== UPLOAD_ERR_OK) {
                $message = "Unable to upload profile photo.";
                $message_type = "danger";
            } else {
                $file_tmp = $_FILES["profile_photo"]["tmp_name"];
                $file_size = (int) $_FILES["profile_photo"]["size"];

                if (!is_uploaded_file($file_tmp)) {
                    $message = "Invalid uploaded file.";
                    $message_type = "danger";
                } elseif ($file_size > 2 * 1024 * 1024) {
                    $message = "Profile photo must be smaller than 2 MB.";
                    $message_type = "danger";
                } else {
                    // Validate the actual file contents, not the filename extension.
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->file($file_tmp);

                    $allowed_types = [
                        "image/jpeg" => "jpg",
                        "image/png" => "png",
                        "image/webp" => "webp"
                    ];

                    $image_info = @getimagesize($file_tmp);

                    if (!isset($allowed_types[$mime_type]) || $image_info === false) {
                        $message = "Only valid JPG, PNG and WEBP images are allowed.";
                        $message_type = "danger";
                    } elseif (($image_info[0] ?? 0) > 4000 || ($image_info[1] ?? 0) > 4000) {
                        $message = "Profile photo dimensions must not exceed 4000 × 4000 pixels.";
                        $message_type = "danger";
                    } else {
                        $upload_directory = __DIR__ . "/uploads/";

                        if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
                            $message = "Unable to prepare the upload directory.";
                            $message_type = "danger";
                        } elseif (!is_writable($upload_directory)) {
                            $message = "The upload directory is not writable.";
                            $message_type = "danger";
                        } else {
                            $file_extension = $allowed_types[$mime_type];
                            $new_file_name = "profile_" . bin2hex(random_bytes(16)) . "." . $file_extension;
                            $new_upload_path = "uploads/" . $new_file_name;
                            $full_upload_path = __DIR__ . "/" . $new_upload_path;

                            if (!move_uploaded_file($file_tmp, $full_upload_path)) {
                                $new_upload_path = null;
                                $message = "Unable to upload profile photo.";
                                $message_type = "danger";
                            } else {
                                $new_profile_photo = $new_upload_path;
                            }
                        }
                    }
                }
            }
        }

        // Update the database only after all validation and upload checks pass.
        if (empty($message)) {
            $sql = "UPDATE USERS
                    SET Name = ?, Email = ?, Profile_Photo = ?, Language = ?
                    WHERE User_ID = ?";

            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                $message = "Something went wrong while updating your profile.";
                $message_type = "danger";
            } else {
                $stmt->bind_param("ssssi", $name, $email, $new_profile_photo, $language, $user_id);

                if ($stmt->execute()) {
                    // Remove the previous photo only after the database update succeeds.
                    if ($new_upload_path !== null && $old_profile_photo !== "" && $old_profile_photo !== $new_profile_photo) {
                        delete_profile_photo($old_profile_photo);
                    }

                    $_SESSION["user_id"] = $user_id;
                    $_SESSION["user_name"] = $name;
                    // Compatibility aliases for existing pages in the project.
                    $_SESSION["User_ID"] = $user_id;
                    $_SESSION["Name"] = $name;
                    $_SESSION["full_name"] = $name;

                    $user["Name"] = $name;
                    $user["Email"] = $email;
                    $user["Profile_Photo"] = $new_profile_photo;
                    $user["Language"] = $language;

                    $message = "Profile updated successfully!";
                    $message_type = "success";
                } else {
                    // If DB update fails, remove the newly uploaded file so it is not orphaned.
                    if ($new_upload_path !== null) {
                        delete_profile_photo($new_upload_path);
                    }

                    if ($conn->errno === 1062) {
                        $message = "This email is already being used by another account.";
                    } else {
                        $message = "Something went wrong while updating your profile.";
                    }
                    $message_type = "danger";
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

    <title>GlobeTrotter - My Profile</title>


    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>


<body class="bg-cream">

<?php $active_page = 'profile'; include 'includes/navbar.php'; ?>

<!-- ======================================================
     PROFILE
     ====================================================== -->

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8 col-md-10">


            <div class="card">

                <div class="card-body">


                    <!-- Heading -->

                    <div class="text-center mb-4">

                        <div class="icon-circle mx-auto">

                            <i class="bi bi-person"></i>

                        </div>

                        <h2>My Profile</h2>

                        <p class="text-muted">
                            Manage your GlobeTrotter account
                        </p>

                    </div>


                    <!-- Message -->

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


                    <!-- ==================================================
                         PROFILE PHOTO
                         ================================================== -->

                    <div class="text-center mb-4">

                        <?php if (!empty($user["Profile_Photo"])) { ?>

                            <img
                                src="<?php echo htmlspecialchars($user["Profile_Photo"], ENT_QUOTES, "UTF-8"); ?>"
                                alt="Profile Photo"
                                class="rounded-circle"
                                width="120"
                                height="120"
                            >

                        <?php } else { ?>

                            <div class="icon-circle mx-auto">

                                <i class="bi bi-person"></i>

                            </div>

                        <?php } ?>

                    </div>


                    <!-- ==================================================
                         PROFILE FORM
                         ================================================== -->

                    <form
                        method="POST"
                        action=""
                        enctype="multipart/form-data"
                    >
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">


                        <!-- Name -->

                        <div class="form-floating mb-3">

                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                placeholder="Full Name"
                                maxlength="50"
                                value="<?php echo htmlspecialchars($user["Name"], ENT_QUOTES, "UTF-8"); ?>"
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
                                value="<?php echo htmlspecialchars($user["Email"], ENT_QUOTES, "UTF-8"); ?>"
                                required
                            >

                            <label for="email">

                                <i class="bi bi-envelope me-2"></i>

                                Email Address

                            </label>

                        </div>


                        <!-- Language -->

                        <div class="form-floating mb-3">

                            <input
                                type="text"
                                class="form-control"
                                id="language"
                                name="language"
                                placeholder="Language"
                                maxlength="30"
                                value="<?php echo htmlspecialchars($user["Language"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                            >

                            <label for="language">

                                <i class="bi bi-translate me-2"></i>

                                Preferred Language

                            </label>

                        </div>


                        <!-- Profile Photo -->

                        <div class="mb-4">

                            <label
                                for="profile_photo"
                                class="form-label"
                            >

                                <i class="bi bi-camera me-2"></i>

                                Change Profile Photo

                            </label>

                            <input
                                type="file"
                                class="form-control"
                                id="profile_photo"
                                name="profile_photo"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                            <div class="form-text">

                                Maximum size: 2 MB.
                                JPG, JPEG, PNG or WEBP.

                            </div>

                        </div>


                        <!-- Account Created -->

                        <div class="mb-4">

                            <p class="text-muted">

                                <i class="bi bi-calendar3 me-2"></i>

                                Account created:

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime($user["Created_At"])
                                );
                                ?>

                            </p>

                        </div>


                        <!-- Buttons -->

                        <div class="d-flex gap-2 justify-content-center">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-check-circle me-2"></i>

                                Save Changes

                            </button>


                            <a
                                href="dashboard.php"
                                class="btn btn-secondary"
                            >

                                <i class="bi bi-arrow-left me-2"></i>

                                Back to Dashboard

                            </a>


                            <a
                                href="logout.php"
                                class="btn btn-outline-danger"
                            >

                                <i class="bi bi-box-arrow-right me-2"></i>

                                Logout

                            </a>

                        </div>


                    </form>


                </div>

            </div>


        </div>

    </div>

</div>


<!-- ======================================================
     FOOTER
     ====================================================== -->

<footer>

    <p>
        Made with <span>❤️</span> for GlobeTrotter Hackathon
    </p>

</footer>


<script src="js/script.js"></script>
</body>

</html>
