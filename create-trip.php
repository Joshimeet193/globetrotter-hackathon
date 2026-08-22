<?php
/* =========================================================
   create-trip.php
   Form to create a new trip, linked to the logged-in user.
   ========================================================= */

session_start();
include 'includes/db-connect.php';


// =========================================================
// CHECK LOGIN
// =========================================================

if (!isset($_SESSION['User_ID'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['User_ID'];

$error_message = '';


// =========================================================
// HANDLE FORM SUBMISSION
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form values safely
    $trip_name   = trim($_POST['trip_name'] ?? '');
    $start_date  = trim($_POST['start_date'] ?? '');
    $end_date    = trim($_POST['end_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $budget_input = trim($_POST['budget'] ?? '');

    $budget = 0;


    // =====================================================
    // BASIC VALIDATION
    // =====================================================

    if ($trip_name === '' || $start_date === '' || $end_date === '') {

        $error_message =
            'Please fill in trip name, start date and end date.';

    }

    // TRIP.Trip_Name = VARCHAR(100)
    elseif (strlen($trip_name) > 100) {

        $error_message =
            'Trip name cannot be longer than 100 characters.';

    }

    // =====================================================
    // DATE VALIDATION
    // =====================================================

    elseif (!isValidDate($start_date) || !isValidDate($end_date)) {

        $error_message =
            'Please enter valid start and end dates.';

    }

    elseif ($end_date < $start_date) {

        $error_message =
            'End date cannot be before the start date.';

    }

    // =====================================================
    // BUDGET VALIDATION
    // =====================================================

    elseif ($budget_input !== '' &&
            (!is_numeric($budget_input) || (float)$budget_input < 0)) {

        $error_message =
            'Budget must be a valid positive number or zero.';

    }

    else {

        if ($budget_input !== '') {
            $budget = (float) $budget_input;
        }


        // =================================================
        // DATABASE DECIMAL(10,2) LIMIT
        // Maximum value is 99999999.99
        // =================================================

        if ($budget > 99999999.99) {

            $error_message =
                'Budget is too large. Please enter a smaller amount.';

        }
    }


    // =====================================================
    // IF VALIDATION PASSED
    // =====================================================

    if ($error_message === '') {

        $cover_photo = '';


        // =================================================
        // HANDLE COVER PHOTO
        // =================================================

        if (
            isset($_FILES['cover_photo']) &&
            $_FILES['cover_photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            // Check upload error
            if ($_FILES['cover_photo']['error'] !== UPLOAD_ERR_OK) {

                $error_message =
                    'There was a problem uploading the cover photo.';

            } else {

                $tmp_name = $_FILES['cover_photo']['tmp_name'];
                $original_name = $_FILES['cover_photo']['name'];

                $file_ext = strtolower(
                    pathinfo(
                        $original_name,
                        PATHINFO_EXTENSION
                    )
                );


                // Allowed extensions
                $allowed_ext = [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ];


                // Check extension
                if (!in_array($file_ext, $allowed_ext, true)) {

                    $error_message =
                        'Only JPG, JPEG, PNG and WEBP images are allowed.';

                } else {

                    // =====================================
                    // CHECK ACTUAL MIME TYPE
                    // =====================================

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);

                    $mime_type = $finfo
                        ? finfo_file($finfo, $tmp_name)
                        : '';

                    if ($finfo) {
                        finfo_close($finfo);
                    }


                    $allowed_mime_types = [
                        'jpg'  => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png'  => 'image/png',
                        'webp' => 'image/webp'
                    ];


                    if (
                        !isset($allowed_mime_types[$file_ext]) ||
                        $mime_type !== $allowed_mime_types[$file_ext]
                    ) {

                        $error_message =
                            'The uploaded file is not a valid image.';

                    }
                }


                // =========================================
                // UPLOAD IMAGE
                // =========================================

                if ($error_message === '') {

                    $upload_dir = 'uploads/trip-covers/';


                    if (!is_dir($upload_dir)) {

                        if (!mkdir($upload_dir, 0755, true)) {

                            $error_message =
                                'Unable to create the upload directory.';
                        }
                    }


                    if ($error_message === '') {

                        // Generate unique filename
                        $file_name =
                            'trip_' .
                            $user_id .
                            '_' .
                            bin2hex(random_bytes(8)) .
                            '.' .
                            $file_ext;

                        $target =
                            $upload_dir . $file_name;


                        if (
                            move_uploaded_file(
                                $tmp_name,
                                $target
                            )
                        ) {

                            $cover_photo = $target;

                        } else {

                            $error_message =
                                'Unable to save the uploaded cover photo.';
                        }
                    }
                }
            }
        }


        // =================================================
        // INSERT TRIP
        // =================================================

        if ($error_message === '') {

            /*
             * Is_Public is NOT included here intentionally.
             *
             * Your schema has:
             *
             * Is_Public BOOLEAN DEFAULT FALSE
             *
             * Therefore MySQL automatically stores FALSE.
             */

            $sql = "INSERT INTO TRIP
                    (
                        User_ID,
                        Trip_Name,
                        Start_Date,
                        End_Date,
                        Description,
                        Cover_Photo,
                        Budget
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)";


            $stmt = $conn->prepare($sql);


            if ($stmt === false) {

                $error_message =
                    'Unable to prepare the trip query. Please try again.';

            } else {

                $stmt->bind_param(
                    "isssssd",
                    $user_id,
                    $trip_name,
                    $start_date,
                    $end_date,
                    $description,
                    $cover_photo,
                    $budget
                );


                // Execute INSERT
                if ($stmt->execute()) {

                    $new_trip_id = $stmt->insert_id;

                    $stmt->close();


                    // Redirect to city selection for this trip
                    header(
                        'Location: city-search.php?trip_id=' .
                        $new_trip_id
                    );

                    exit();

                } else {

                    $error_message =
                        'Something went wrong while saving your trip. Please try again.';

                    $stmt->close();


                    // If DB insert failed after successful upload,
                    // remove the uploaded file to avoid orphan files.
                    if (
                        $cover_photo !== '' &&
                        file_exists($cover_photo)
                    ) {
                        unlink($cover_photo);
                    }
                }
            }
        }
    }
}


// =========================================================
// ACTIVE NAVBAR PAGE
// =========================================================

$active_page = 'create-trip';


// =========================================================
// DATE VALIDATION FUNCTION
// =========================================================

function isValidDate($date)
{
    $date_object = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    if (!$date_object) {
        return false;
    }

    return $date_object->format('Y-m-d') === $date;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Plan New Trip - GlobeTrotter</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="css/style.css"
>


<style>

.trip-form-card {
    position: relative;
    overflow: hidden;
}

.trip-form-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: repeating-linear-gradient(
        90deg,
        var(--gold) 0 10px,
        transparent 10px 18px
    );
}

.trip-days-hint {
    font-family: 'Space Mono', monospace;
    font-size: 0.8rem;
    color: var(--teal);
}

</style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-7">


<h1 class="section-title mb-1">

<i class="bi bi-airplane"></i>

Plan a New Trip

</h1>


<p class="text-muted mb-4">

Give your trip a name and set the dates to get started.

</p>


<?php if ($error_message): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-circle"></i>

<?php echo htmlspecialchars($error_message); ?>

</div>

<?php endif; ?>


<div class="card trip-form-card">

<div class="card-body">


<form
    method="POST"
    enctype="multipart/form-data"
>


<div class="form-floating mb-3">

<input
    type="text"
    name="trip_name"
    id="trip_name"
    class="form-control"
    placeholder="Trip Name"
    required
    maxlength="100"
    value="<?php echo htmlspecialchars($_POST['trip_name'] ?? ''); ?>"
>

<label for="trip_name">

<i class="bi bi-signpost"></i>

Trip Name

</label>

</div>


<div class="row">


<div class="col-md-6">

<div class="form-floating mb-3">

<input
    type="date"
    name="start_date"
    id="start_date"
    class="form-control"
    required
    value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
>

<label for="start_date">

<i class="bi bi-calendar3"></i>

Start Date

</label>

</div>

</div>


<div class="col-md-6">

<div class="form-floating mb-3">

<input
    type="date"
    name="end_date"
    id="end_date"
    class="form-control"
    required
    value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>"
>

<label for="end_date">

<i class="bi bi-calendar3"></i>

End Date

</label>

</div>

</div>


</div>


<p
    class="trip-days-hint mb-3"
    id="tripDaysHint"
></p>


<div class="form-floating mb-3">

<input
    type="number"
    name="budget"
    id="budget"
    class="form-control"
    min="0"
    step="1"
    placeholder="Budget"
    value="<?php echo htmlspecialchars($_POST['budget'] ?? ''); ?>"
>

<label for="budget">

<i class="bi bi-wallet2"></i>

Budget (₹, optional)

</label>

</div>


<div class="form-floating mb-3">

<textarea
    name="description"
    id="description"
    class="form-control"
    placeholder="Description"
    style="height: 110px;"
><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

<label for="description">

<i class="bi bi-card-text"></i>

Description (optional)

</label>

</div>


<div class="mb-4">

<label
    for="cover_photo"
    class="form-label"
>

<i class="bi bi-image"></i>

Cover Photo (optional)

</label>


<input
    type="file"
    name="cover_photo"
    id="cover_photo"
    class="form-control"
    accept=".jpg,.jpeg,.png,.webp"
>


<div class="form-text">

JPG, PNG or WEBP. Leave empty if you don't have one yet.

</div>

</div>


<div class="d-flex justify-content-between">


<a
    href="dashboard.php"
    class="btn btn-outline-primary"
>

<i class="bi bi-arrow-left"></i>

Cancel

</a>


<button
    type="submit"
    class="btn btn-primary"
>

<i class="bi bi-check-circle"></i>

Save Trip

</button>


</div>


</form>


</div>

</div>

</div>

</div>

</div>


<footer>

<p>

Made with <span>❤️</span> for GlobeTrotter Hackathon

</p>

</footer>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>

<script src="js/script.js"></script>


<script>

// live "trip length" hint - pure UX sugar, no logic change

(function () {

  const start =
      document.getElementById('start_date');

  const end =
      document.getElementById('end_date');

  const hint =
      document.getElementById('tripDaysHint');


  function update() {

    if (start.value && end.value) {

      const days =
          Math.round(
              (
                  new Date(end.value) -
                  new Date(start.value)
              ) / 86400000
          );


      hint.textContent =
          days >= 0
              ? '✈ ' + days + ' day trip'
              : '';

    } else {

      hint.textContent = '';

    }

  }


  start.addEventListener(
      'change',
      update
  );


  end.addEventListener(
      'change',
      update
  );


  update();

})();

</script>


</body>

</html>
