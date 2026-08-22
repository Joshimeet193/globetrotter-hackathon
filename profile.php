<?php

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION["User_ID"])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once "includes/db-connect.php";


// Get logged-in user's ID
$user_id = $_SESSION["User_ID"];


// Variables for messages
$message = "";
$message_type = "";


// ======================================================
// FETCH CURRENT USER DETAILS
// ======================================================

$sql = "SELECT User_ID, Name, Email, Profile_Photo, Language, Created_At
        FROM USERS
        WHERE User_ID = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();


// Check if user exists
if ($result->num_rows == 1) {

    $user = $result->fetch_assoc();

} else {

    // User not found
    session_destroy();

    header("Location: index.php");
    exit();
}

$stmt->close();


// ======================================================
// UPDATE PROFILE
// ======================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form values
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $language = trim($_POST["language"]);


    // Check required fields
    if (empty($name) || empty($email)) {

        $message = "Name and email are required.";
        $message_type = "danger";

    }

    // Check email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "danger";

    }

    else {

        // ==================================================
        // CHECK IF EMAIL IS ALREADY USED BY ANOTHER USER
        // ==================================================

        $sql = "SELECT User_ID
                FROM USERS
                WHERE Email = ?
                AND User_ID != ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("si", $email, $user_id);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows > 0) {

            $message = "This email is already being used by another account.";
            $message_type = "danger";

            $stmt->close();

        } else {

            $stmt->close();


            // ==================================================
            // UPDATE PROFILE PHOTO
            // ==================================================

            $profile_photo = $user["Profile_Photo"];


            // Check whether a new photo was selected
            if (
                isset($_FILES["profile_photo"]) &&
                $_FILES["profile_photo"]["error"] == 0
            ) {

                $file_name = $_FILES["profile_photo"]["name"];
                $file_tmp = $_FILES["profile_photo"]["tmp_name"];
                $file_size = $_FILES["profile_photo"]["size"];

                // Get file extension
                $file_extension = strtolower(
                    pathinfo($file_name, PATHINFO_EXTENSION)
                );


                // Allowed image types
                $allowed_extensions = array(
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                );


                // Check file type
                if (!in_array($file_extension, $allowed_extensions)) {

                    $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                    $message_type = "danger";

                }

                // Check file size - maximum 2 MB
                elseif ($file_size > 2 * 1024 * 1024) {

                    $message = "Profile photo must be smaller than 2 MB.";
                    $message_type = "danger";

                }

                else {

                    // Create uploads directory if it doesn't exist
                    $upload_directory = "uploads/";

                    if (!is_dir($upload_directory)) {
                        mkdir($upload_directory, 0777, true);
                    }


                    // Create unique file name
                    $new_file_name = "profile_" . $user_id . "_" . time() . "." . $file_extension;

                    $upload_path = $upload_directory . $new_file_name;


                    // Move uploaded image
                    if (move_uploaded_file($file_tmp, $upload_path)) {

                        $profile_photo = $upload_path;

                    } else {

                        $message = "Unable to upload profile photo.";
                        $message_type = "danger";

                    }
                }
            }


            // ==================================================
            // UPDATE DATABASE
            // ==================================================

            if (empty($message)) {

                $sql = "UPDATE USERS
                        SET Name = ?,
                            Email = ?,
                            Profile_Photo = ?,
                            Language = ?
                        WHERE User_ID = ?";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "ssssi",
                    $name,
                    $email,
                    $profile_photo,
                    $language,
                    $user_id
                );


                if ($stmt->execute()) {

                    // Update session name
                    $_SESSION["Name"] = $name;
                    $_SESSION["full_name"] = $name;

                    $message = "Profile updated successfully!";
                    $message_type = "success";


                    // Update displayed values
                    $user["Name"] = $name;
                    $user["Email"] = $email;
                    $user["Profile_Photo"] = $profile_photo;
                    $user["Language"] = $language;

                } else {

                    $message = "Something went wrong while updating your profile.";
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


<!-- ======================================================
     NAVBAR
     ====================================================== -->

<nav class="navbar navbar-expand-lg">

    <div class="container">

        <a class="navbar-brand" href="dashboard.php">
            🌍 GlobeTrotter
        </a>


        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarMenu"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <div class="collapse navbar-collapse" id="navbarMenu">

            <ul class="navbar-nav ms-auto">

                <li class="nav-item">

                    <a class="nav-link" href="dashboard.php">

                        <i class="bi bi-speedometer2 me-1"></i>
                        Dashboard

                    </a>

                </li>


                <li class="nav-item">

                    <a class="nav-link" href="profile.php">

                        <i class="bi bi-person-circle me-1"></i>
                        Profile

                    </a>

                </li>


                <li class="nav-item">

                    <a class="nav-link" href="logout.php">

                        <i class="bi bi-box-arrow-right me-1"></i>
                        Logout

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>


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


                            <?php echo htmlspecialchars($message); ?>

                        </div>

                    <?php } ?>


                    <!-- ==================================================
                         PROFILE PHOTO
                         ================================================== -->

                    <div class="text-center mb-4">

                        <?php if (!empty($user["Profile_Photo"])) { ?>

                            <img
                                src="<?php echo htmlspecialchars($user["Profile_Photo"]); ?>"
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


                        <!-- Name -->

                        <div class="form-floating mb-3">

                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                placeholder="Full Name"
                                value="<?php echo htmlspecialchars($user["Name"]); ?>"
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
                                value="<?php echo htmlspecialchars($user["Email"]); ?>"
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
                                value="<?php echo htmlspecialchars($user["Language"] ?? ""); ?>"
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


</body>

</html>
