<?php
// =====================================================
// activity-search.php
// Activities search + filter page. Activities static array
// ma hardcoded chhe. "Add to Trip" click karta activities
// table ma row insert thay chhe (ek stop sathe linked).
// Quick View button e activity ni details modal ma batave chhe.
// =====================================================

session_start();
include 'includes/db-connect.php';

// Login check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// -----------------------------------------------------
// STEP A: Static activities data (hardcoded array)
// Har activity ma: name, category, cost, duration(hours), description
// -----------------------------------------------------
$all_activities = [
    ["name" => "Beach Parasailing",       "category" => "adventure",   "cost" => 1500, "duration" => 1,  "description" => "Fly above the water attached to a parachute towed by a boat. Great views and a big adrenaline rush."],
    ["name" => "Snorkeling Tour",         "category" => "adventure",   "cost" => 1200, "duration" => 2,  "description" => "Explore colorful coral reefs and marine life just below the surface with a guided group."],
    ["name" => "Scuba Diving",            "category" => "adventure",   "cost" => 3500, "duration" => 3,  "description" => "Certified instructor takes you deep underwater to explore reefs and sometimes shipwrecks."],
    ["name" => "Trekking Trail",          "category" => "adventure",   "cost" => 800,  "duration" => 4,  "description" => "A guided hike through scenic hills and forest trails, suitable for beginners and pros alike."],
    ["name" => "Paragliding",             "category" => "adventure",   "cost" => 2500, "duration" => 1,  "description" => "Soar over valleys and mountains with a trained tandem pilot. No experience required."],
    ["name" => "City Heritage Walk",      "category" => "sightseeing", "cost" => 300,  "duration" => 2,  "description" => "A guided walking tour through the old city covering historic monuments and local stories."],
    ["name" => "Museum Visit",            "category" => "sightseeing", "cost" => 200,  "duration" => 2,  "description" => "Explore art, history, and culture at one of the city's most famous museums."],
    ["name" => "Sunset Point Tour",       "category" => "sightseeing", "cost" => 0,    "duration" => 1,  "description" => "Visit the city's best viewpoint to watch a breathtaking sunset over the landscape."],
    ["name" => "Palace / Fort Tour",      "category" => "sightseeing", "cost" => 500,  "duration" => 3,  "description" => "Guided tour of a historic palace or fort, including architecture and royal history."],
    ["name" => "Boat Cruise",             "category" => "sightseeing", "cost" => 1000, "duration" => 2,  "description" => "Relaxing boat ride along the coastline or river with scenic views and light refreshments."],
    ["name" => "Street Food Walk",        "category" => "food",        "cost" => 600,  "duration" => 2,  "description" => "Taste the best local street food with a guide who knows all the hidden gems."],
    ["name" => "Wine / Vineyard Tasting", "category" => "food",        "cost" => 1800, "duration" => 3,  "description" => "Sample local wines at a vineyard, including a tour of the cellar and production process."],
    ["name" => "Cooking Class",           "category" => "food",        "cost" => 1200, "duration" => 3,  "description" => "Hands-on class where you learn to cook authentic local dishes from a professional chef."],
    ["name" => "Fine Dining Experience",  "category" => "food",        "cost" => 2500, "duration" => 2,  "description" => "A multi-course meal at a top-rated local restaurant, often with a scenic view."],
    ["name" => "Rooftop Cafe Hopping",    "category" => "food",        "cost" => 900,  "duration" => 2,  "description" => "Visit a few of the city's best rooftop cafes for coffee, snacks, and skyline views."],
    ["name" => "Desert Safari",           "category" => "adventure",   "cost" => 2800, "duration" => 4,  "description" => "Dune bashing, camel rides, and a traditional dinner under the stars in the desert."],
    ["name" => "Local Market Tour",       "category" => "sightseeing", "cost" => 100,  "duration" => 1,  "description" => "Wander through vibrant local markets to shop for souvenirs, spices, and handicrafts."],
    ["name" => "Kayaking",                "category" => "adventure",   "cost" => 1000, "duration" => 2,  "description" => "Paddle through calm backwaters or coastline at your own pace, no experience needed."],
    ["name" => "Night Market Food Tour",  "category" => "food",        "cost" => 700,  "duration" => 2,  "description" => "Explore a bustling night market and sample a variety of local snacks and dishes."],
    ["name" => "Photography Walk",        "category" => "sightseeing", "cost" => 400,  "duration" => 2,  "description" => "A guided walk to the city's most photogenic spots, great for travel photography lovers."],
];

// -----------------------------------------------------
// STEP B: Handle "Add to Trip" form (POST) - insert into activities table
// -----------------------------------------------------
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $stop_id        = $_POST['stop_id'];
    $activity_name  = $_POST['activity_name'];
    $category       = $_POST['category'];
    $cost           = $_POST['cost'];
    $duration       = $_POST['duration'];

    if (empty($stop_id)) {
        $error_message = "Please select a trip stop first!";
    } else {
        $sql_insert = "INSERT INTO activities (stop_id, activity_name, category, cost, duration) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_insert);
        // "i" = integer(stop_id), "s"=string(name), "s"=string(category), "d"=decimal(cost), "i"=integer(duration)
        $stmt->bind_param("issdi", $stop_id, $activity_name, $category, $cost, $duration);

        if ($stmt->execute()) {
            $success_message = htmlspecialchars($activity_name) . " added to your trip!";
        } else {
            $error_message = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

// -----------------------------------------------------
// STEP C: User na stops lavo (dropdown mate) - joined with trips
// jethi dropdown ma "Trip Name - City Name" dekhaay
// -----------------------------------------------------
$sql_stops = "SELECT stops.stop_id, stops.city_name, trips.trip_name 
              FROM stops 
              JOIN trips ON stops.trip_id = trips.trip_id 
              WHERE trips.user_id = ? 
              ORDER BY stops.stop_id DESC";
$stmt = $conn->prepare($sql_stops);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------------------------------------
// STEP D: Filters (GET request)
// -----------------------------------------------------
$filter_category = $_GET['category'] ?? '';
$min_cost         = isset($_GET['min_cost']) && $_GET['min_cost'] !== '' ? (float)$_GET['min_cost'] : null;
$max_cost         = isset($_GET['max_cost']) && $_GET['max_cost'] !== '' ? (float)$_GET['max_cost'] : null;
$filter_duration  = $_GET['duration'] ?? '';

$filtered_activities = array_filter($all_activities, function ($activity) use ($filter_category, $min_cost, $max_cost, $filter_duration) {
    $matches_category = empty($filter_category) || $activity['category'] === $filter_category;
    $matches_min       = is_null($min_cost) || $activity['cost'] >= $min_cost;
    $matches_max       = is_null($max_cost) || $activity['cost'] <= $max_cost;
    $matches_duration  = empty($filter_duration) || $activity['duration'] == $filter_duration;
    return $matches_category && $matches_min && $matches_max && $matches_duration;
});

// Category mujab kayo Bootstrap icon vaparvo (standard bootstrap-icons, custom color nathi)
$category_icon = [
    "sightseeing" => "bi-binoculars",
    "food"        => "bi-cup-hot",
    "adventure"   => "bi-lightning-charge",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Search - GlobeTrotter</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">🌍 GlobeTrotter</a>
        <div class="d-flex">
            <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-house"></i> Dashboard</a>
            <a href="city-search.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> City Search</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <h2 class="section-title"><i class="bi bi-compass"></i> Search Activities</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- ===== FILTER FORM (GET method) ===== -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <option value="sightseeing" <?php echo $filter_category === 'sightseeing' ? 'selected' : ''; ?>>Sightseeing</option>
                <option value="food" <?php echo $filter_category === 'food' ? 'selected' : ''; ?>>Food</option>
                <option value="adventure" <?php echo $filter_category === 'adventure' ? 'selected' : ''; ?>>Adventure</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Min Cost (₹)</label>
            <input type="number" name="min_cost" class="form-control" value="<?php echo htmlspecialchars($min_cost ?? ''); ?>" placeholder="0">
        </div>
        <div class="col-md-2">
            <label class="form-label">Max Cost (₹)</label>
            <input type="number" name="max_cost" class="form-control" value="<?php echo htmlspecialchars($max_cost ?? ''); ?>" placeholder="5000">
        </div>
        <div class="col-md-3">
            <label class="form-label">Duration (hours)</label>
            <select name="duration" class="form-select">
                <option value="">Any Duration</option>
                <option value="1" <?php echo $filter_duration == '1' ? 'selected' : ''; ?>>1 hour</option>
                <option value="2" <?php echo $filter_duration == '2' ? 'selected' : ''; ?>>2 hours</option>
                <option value="3" <?php echo $filter_duration == '3' ? 'selected' : ''; ?>>3 hours</option>
                <option value="4" <?php echo $filter_duration == '4' ? 'selected' : ''; ?>>4 hours</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Apply</button>
        </div>
    </form>

    <!-- ===== ACTIVITY RESULTS ===== -->
    <div class="row g-4">
        <?php if (count($filtered_activities) === 0): ?>
            <div class="col-12">
                <div class="alert alert-warning">No activities match your filters. Try adjusting them.</div>
            </div>
        <?php else: ?>
            <?php foreach ($filtered_activities as $index => $activity): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <span class="badge bg-secondary mb-2">
                                <i class="bi <?php echo $category_icon[$activity['category']]; ?>"></i>
                                <?php echo ucfirst($activity['category']); ?>
                            </span>
                            <h5 class="card-title"><?php echo htmlspecialchars($activity['name']); ?></h5>
                            <p class="card-text mb-1"><i class="bi bi-wallet2"></i> ₹<?php echo number_format($activity['cost']); ?></p>
                            <p class="card-text mb-3"><i class="bi bi-clock"></i> <?php echo $activity['duration']; ?> hr(s)</p>

                            <div class="d-flex gap-2">
                                <!-- Quick View button - opens modal with full description -->
                                <button type="button" class="btn btn-outline-primary btn-sm flex-fill"
                                        data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $index; ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>

                                <!-- Add button - opens modal to select which trip-stop to add it to -->
                                <?php if (count($user_stops) > 0): ?>
                                    <button type="button" class="btn btn-primary btn-sm flex-fill"
                                            data-bs-toggle="modal" data-bs-target="#addModal<?php echo $index; ?>">
                                        <i class="bi bi-plus-circle"></i> Add
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== QUICK VIEW MODAL ===== -->
                <div class="modal fade" id="viewModal<?php echo $index; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><?php echo htmlspecialchars($activity['name']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                <hr>
                                <p class="mb-1"><i class="bi <?php echo $category_icon[$activity['category']]; ?>"></i> <strong>Category:</strong> <?php echo ucfirst($activity['category']); ?></p>
                                <p class="mb-1"><i class="bi bi-wallet2"></i> <strong>Cost:</strong> ₹<?php echo number_format($activity['cost']); ?></p>
                                <p class="mb-0"><i class="bi bi-clock"></i> <strong>Duration:</strong> <?php echo $activity['duration']; ?> hour(s)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== ADD TO TRIP MODAL ===== -->
                <div class="modal fade" id="addModal<?php echo $index; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add "<?php echo htmlspecialchars($activity['name']); ?>" to Trip</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Hidden fields carry the activity's static data along with the form -->
                                    <input type="hidden" name="activity_name" value="<?php echo htmlspecialchars($activity['name']); ?>">
                                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($activity['category']); ?>">
                                    <input type="hidden" name="cost" value="<?php echo $activity['cost']; ?>">
                                    <input type="hidden" name="duration" value="<?php echo $activity['duration']; ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Select Trip Stop</label>
                                        <select name="stop_id" class="form-select" required>
                                            <option value="">-- Choose a stop --</option>
                                            <?php foreach ($user_stops as $stop): ?>
                                                <option value="<?php echo $stop['stop_id']; ?>">
                                                    <?php echo htmlspecialchars($stop['trip_name']); ?> - <?php echo htmlspecialchars($stop['city_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small><i class="bi bi-info-circle"></i> Don't see your city? Add it first from City Search.</small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_activity" class="btn btn-primary">Add Activity</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>
</body>
</html>
