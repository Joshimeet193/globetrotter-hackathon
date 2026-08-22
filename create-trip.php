<?php
/* =========================================================
   create-trip.php
   Form to create a new trip, linked to the logged-in user.
   ========================================================= */
session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trip_name   = trim($_POST['trip_name']);
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];
    $description = trim($_POST['description']);
    $budget      = ($_POST['budget'] !== '') ? (float) $_POST['budget'] : 0;
    $cover_photo = '';

    if ($trip_name === '' || $start_date === '' || $end_date === '') {
        $error_message = 'Please fill in trip name, start date and end date.';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error_message = 'End date cannot be before the start date.';
    } elseif ($budget < 0) {
        $error_message = 'Budget cannot be negative.';
    } else {
        if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/trip-covers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_ext  = pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION);
            $file_name = 'trip_' . $user_id . '_' . time() . '.' . $file_ext;
            $target    = $upload_dir . $file_name;
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array(strtolower($file_ext), $allowed_ext)) {
                if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $target)) {
                    $cover_photo = $target;
                }
            }
        }

        $sql = "INSERT INTO TRIP (User_ID, Trip_Name, Start_Date, End_Date, Description, Cover_Photo, Budget)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssd", $user_id, $trip_name, $start_date, $end_date, $description, $cover_photo, $budget);

        if ($stmt->execute()) {
            $new_trip_id = $stmt->insert_id;
            $stmt->close();
            header('Location: city-search.php?trip_id=' . $new_trip_id);
            exit;
        } else {
            $error_message = 'Something went wrong while saving your trip. Please try again.';
            $stmt->close();
        }
    }
}
$active_page = 'create-trip';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Plan New Trip - GlobeTrotter</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<style>
.trip-form-card { position: relative; overflow: hidden; }
.trip-form-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
  background: repeating-linear-gradient(90deg, var(--gold) 0 10px, transparent 10px 18px);
}
.trip-days-hint {
  font-family: 'Space Mono', monospace; font-size: 0.8rem; color: var(--teal);
}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-7">
<h1 class="section-title mb-1"><i class="bi bi-airplane"></i> Plan a New Trip</h1>
<p class="text-muted mb-4">Give your trip a name and set the dates to get started.</p>

<?php if ($error_message): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card trip-form-card">
<div class="card-body">
<form method="POST" enctype="multipart/form-data">
<div class="form-floating mb-3">
<input type="text" name="trip_name" id="trip_name" class="form-control"
       placeholder="Trip Name" required
       value="<?php echo htmlspecialchars($_POST['trip_name'] ?? ''); ?>">
<label for="trip_name"><i class="bi bi-signpost"></i> Trip Name</label>
</div>

<div class="row">
<div class="col-md-6">
<div class="form-floating mb-3">
<input type="date" name="start_date" id="start_date" class="form-control" required
       value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
<label for="start_date"><i class="bi bi-calendar3"></i> Start Date</label>
</div>
</div>
<div class="col-md-6">
<div class="form-floating mb-3">
<input type="date" name="end_date" id="end_date" class="form-control" required
       value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
<label for="end_date"><i class="bi bi-calendar3"></i> End Date</label>
</div>
</div>
</div>
<p class="trip-days-hint mb-3" id="tripDaysHint"></p>

<div class="form-floating mb-3">
<input type="number" name="budget" id="budget" class="form-control" min="0" step="1"
       placeholder="Budget" value="<?php echo htmlspecialchars($_POST['budget'] ?? ''); ?>">
<label for="budget"><i class="bi bi-wallet2"></i> Budget (₹, optional)</label>
</div>

<div class="form-floating mb-3">
<textarea name="description" id="description" class="form-control" placeholder="Description"
          style="height: 110px;"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
<label for="description"><i class="bi bi-card-text"></i> Description (optional)</label>
</div>

<div class="mb-4">
<label for="cover_photo" class="form-label"><i class="bi bi-image"></i> Cover Photo (optional)</label>
<input type="file" name="cover_photo" id="cover_photo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
<div class="form-text">JPG, PNG or WEBP. Leave empty if you don't have one yet.</div>
</div>

<div class="d-flex justify-content-between">
<a href="dashboard.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Cancel</a>
<button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Trip</button>
</div>
</form>
</div>
</div>
</div>
</div>
</div>

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
<script>
// live "trip length" hint - pure UX sugar, no logic change
(function () {
  const start = document.getElementById('start_date');
  const end = document.getElementById('end_date');
  const hint = document.getElementById('tripDaysHint');
  function update() {
    if (start.value && end.value) {
      const days = Math.round((new Date(end.value) - new Date(start.value)) / 86400000);
      hint.textContent = days >= 0 ? '✈ ' + days + ' day trip' : '';
    } else {
      hint.textContent = '';
    }
  }
  start.addEventListener('change', update);
  end.addEventListener('change', update);
  update();
})();
</script>
</body>
</html>
