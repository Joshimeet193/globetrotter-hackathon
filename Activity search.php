<?php
/* =========================================================
   activity-search.php
   Search & discover activities, filter by type/cost/duration,
   add an activity to a trip's stop, and remove ones already
   added.
   ========================================================= */

session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

/* ---------------------------------------------------------
   STEP 1: Hardcoded list of activities (static PHP array).
   category is one of: sightseeing / food / adventure
   cost is in rupees, duration is in hours.
   --------------------------------------------------------- */
$activities = [
    ['name' => 'City Walking Tour',        'category' => 'sightseeing', 'cost' => 500,  'duration' => 3, 'description' => 'A guided walk through the old city covering major landmarks and hidden alleys.'],
    ['name' => 'Museum Visit',             'category' => 'sightseeing', 'cost' => 300,  'duration' => 2, 'description' => 'Explore local history and art at the city\'s most popular museum.'],
    ['name' => 'Sunset Point Trek',        'category' => 'sightseeing', 'cost' => 0,    'duration' => 2, 'description' => 'A short hike to a viewpoint, perfect for photos at golden hour.'],
    ['name' => 'Heritage Palace Tour',     'category' => 'sightseeing', 'cost' => 700,  'duration' => 3, 'description' => 'Guided tour of a historic palace with an audio guide included.'],
    ['name' => 'Street Food Crawl',        'category' => 'food',        'cost' => 800,  'duration' => 3, 'description' => 'Taste the best local street food with a local food guide.'],
    ['name' => 'Cooking Class',            'category' => 'food',        'cost' => 1200, 'duration' => 4, 'description' => 'Learn to cook 3 traditional local dishes hands-on.'],
    ['name' => 'Rooftop Dinner',           'category' => 'food',        'cost' => 1800, 'duration' => 2, 'description' => 'Fine dining experience with a panoramic view of the skyline.'],
    ['name' => 'Wine / Cafe Tasting',      'category' => 'food',        'cost' => 900,  'duration' => 2, 'description' => 'Sample local wines or specialty coffee at a cozy tasting room.'],
    ['name' => 'Scuba Diving',             'category' => 'adventure',   'cost' => 3500, 'duration' => 4, 'description' => 'Beginner-friendly scuba diving session with certified instructors.'],
    ['name' => 'Paragliding',              'category' => 'adventure',   'cost' => 2500, 'duration' => 1, 'description' => 'Tandem paragliding flight over scenic valleys or coastline.'],
    ['name' => 'White Water Rafting',      'category' => 'adventure',   'cost' => 1500, 'duration' => 3, 'description' => 'Grade 2-3 rapids rafting trip with safety gear provided.'],
    ['name' => 'Desert Safari',            'category' => 'adventure',   'cost' => 2200, 'duration' => 5, 'description' => 'Dune bashing, camel ride and a BBQ dinner under the stars.'],
    ['name' => 'Bike Rental & City Ride',  'category' => 'adventure',   'cost' => 400,  'duration' => 3, 'description' => 'Explore the city at your own pace on a rented bicycle.'],
    ['name' => 'Local Market Visit',       'category' => 'sightseeing', 'cost' => 0,    'duration' => 2, 'description' => 'Wander through a bustling local market for souvenirs and spices.'],
    ['name' => 'Boat Cruise',              'category' => 'sightseeing', 'cost' => 1000, 'duration' => 2, 'description' => 'Relaxing boat ride along the river or coastline at sunset.'],
    ['name' => 'Cycling Food Tour',        'category' => 'food',        'cost' => 1100, 'duration' => 4, 'description' => 'Combine cycling and food tasting across multiple local eateries.'],
    ['name' => 'Rock Climbing',            'category' => 'adventure',   'cost' => 1800, 'duration' => 3, 'description' => 'Outdoor rock climbing session with certified guides and gear.'],
    ['name' => 'Photography Walk',         'category' => 'sightseeing', 'cost' => 600,  'duration' => 2, 'description' => 'Guided photo walk to the city\'s most photogenic spots.'],
];

/* ---------------------------------------------------------
   STEP 2: Fetch the user's own trips + stops, so we can show
   a "which stop should this activity go into?" dropdown.
   We JOIN stops with trips to make sure we only show stops
   that belong to the logged-in user.
   --------------------------------------------------------- */
$my_stops = [];
$stmt = $conn->prepare("SELECT stops.stop_id, stops.city_name, trips.trip_name
                         FROM stops
                         INNER JOIN trips ON stops.trip_id = trips.trip_id
                         WHERE trips.user_id = ?
                         ORDER BY stops.stop_id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $my_stops[] = $row;
}
$stmt->close();

/* ---------------------------------------------------------
   STEP 3: Handle "Add to Trip" form submission (POST) -
   inserts a new row into the "activities" table.
   --------------------------------------------------------- */
$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $stop_id       = (int) $_POST['stop_id'];
    $activity_name = trim($_POST['activity_name']);
    $category      = trim($_POST['category']);
    $cost          = (float) $_POST['cost'];
    $duration      = (float) $_POST['duration'];

    if ($stop_id <= 0 || $activity_name === '') {
        $error_message = 'Please choose a stop before adding the activity.';
    } else {
        $insert = $conn->prepare("INSERT INTO activities (stop_id, activity_name, category, cost, duration)
                                   VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("issdd", $stop_id, $activity_name, $category, $cost, $duration);

        if ($insert->execute()) {
            $success_message = $activity_name . ' was added to your trip!';
        } else {
            $error_message = 'Something went wrong. Please try again.';
        }
        $insert->close();
    }
}

/* ---------------------------------------------------------
   STEP 4: Handle "Remove" - deletes a row from "activities".
   We double-check (via JOIN) that the activity really belongs
   to one of this user's own trips, so users can't delete
   someone else's data.
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_activity'])) {
    $activity_id = (int) $_POST['activity_id'];

    $delete = $conn->prepare("DELETE activities FROM activities
                               INNER JOIN stops ON activities.stop_id = stops.stop_id
                               INNER JOIN trips ON stops.trip_id = trips.trip_id
                               WHERE activities.activity_id = ? AND trips.user_id = ?");
    $delete->bind_param("ii", $activity_id, $user_id);
    if ($delete->execute()) {
        $success_message = 'Activity removed from your trip.';
    } else {
        $error_message = 'Could not remove the activity. Please try again.';
    }
    $delete->close();
}

/* ---------------------------------------------------------
   STEP 5: Fetch activities already added to this user's stops,
   so we can list them with a "Remove" button.
   --------------------------------------------------------- */
$added_activities = [];
$stmt = $conn->prepare("SELECT activities.activity_id, activities.activity_name, activities.category,
                                activities.cost, activities.duration, stops.city_name
                         FROM activities
                         INNER JOIN stops ON activities.stop_id = stops.stop_id
                         INNER JOIN trips ON stops.trip_id = trips.trip_id
                         WHERE trips.user_id = ?
                         ORDER BY activities.activity_id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $added_activities[] = $row;
}
$stmt->close();

/* ---------------------------------------------------------
   STEP 6: Handle filters (GET request)
   --------------------------------------------------------- */
$filter_category = $_GET['category'] ?? '';
$filter_cost_max = isset($_GET['cost_max']) && $_GET['cost_max'] !== '' ? (float) $_GET['cost_max'] : null;
$filter_duration  = $_GET['duration'] ?? '';

$filtered_activities = array_filter($activities, function ($act) use ($filter_category, $filter_cost_max, $filter_duration) {
    $matches_category = ($filter_category === '') || ($act['category'] === $filter_category);
    $matches_cost      = ($filter_cost_max === null) || ($act['cost'] <= $filter_cost_max);

    $matches_duration = true;
    if ($filter_duration === 'short') {
        $matches_duration = $act['duration'] <= 2;
    } elseif ($filter_duration === 'medium') {
        $matches_duration = $act['duration'] > 2 && $act['duration'] <= 4;
    } elseif ($filter_duration === 'long') {
        $matches_duration = $act['duration'] > 4;
    }

    return $matches_category && $matches_cost && $matches_duration;
});

// Category icon helper (used in the card + badges)
function category_icon($category) {
    switch ($category) {
        case 'sightseeing': return 'bi-binoculars';
        case 'food':         return 'bi-cup-hot';
        case 'adventure':    return 'bi-mountain';
        default:              return 'bi-star';
    }
}

$active_page = 'activity-search';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Activities - GlobeTrotter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container py-5">

    <h1 class="gt-heading mb-1"><i class="bi bi-compass gt-icon"></i>Search Activities</h1>
    <p class="text-muted mb-4">Fill your itinerary with things to do at each stop.</p>

    <?php if ($success_message): ?>
        <div class="alert alert-success rounded-4"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger rounded-4"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- ===== Filters ===== -->
    <form method="GET" class="row g-3 mb-5 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="category" class="form-select">
                <option value="">All Types</option>
                <option value="sightseeing" <?php echo $filter_category === 'sightseeing' ? 'selected' : ''; ?>>Sightseeing</option>
                <option value="food" <?php echo $filter_category === 'food' ? 'selected' : ''; ?>>Food</option>
                <option value="adventure" <?php echo $filter_category === 'adventure' ? 'selected' : ''; ?>>Adventure</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Max Cost (₹)</label>
            <input type="number" name="cost_max" min="0" step="100" class="form-control"
                   placeholder="e.g. 1000" value="<?php echo htmlspecialchars($_GET['cost_max'] ?? ''); ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Duration</label>
            <select name="duration" class="form-select">
                <option value="">Any Duration</option>
                <option value="short" <?php echo $filter_duration === 'short' ? 'selected' : ''; ?>>Short (≤ 2 hrs)</option>
                <option value="medium" <?php echo $filter_duration === 'medium' ? 'selected' : ''; ?>>Medium (2-4 hrs)</option>
                <option value="long" <?php echo $filter_duration === 'long' ? 'selected' : ''; ?>>Long (4+ hrs)</option>
            </select>
        </div>

        <div class="col-md-1">
            <button type="submit" class="btn btn-gt-primary w-100"><i class="bi bi-funnel"></i></button>
        </div>
    </form>

    <!-- ===== Activity results grid ===== -->
    <div class="row g-4 mb-5">
        <?php if (count($filtered_activities) === 0): ?>
            <div class="col-12">
                <div class="gt-empty-state">
                    <i class="bi bi-emoji-frown" style="font-size: 2.5rem;"></i>
                    <p class="mt-3 mb-0">No activities match your filters. Try adjusting them.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($filtered_activities as $index => $act): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card gt-card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="gt-icon-circle mb-3">
                                <i class="bi <?php echo category_icon($act['category']); ?>"></i>
                            </div>
                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($act['name']); ?></h6>
                            <span class="gt-badge-primary mb-2" style="width: fit-content;">
                                <?php echo ucfirst($act['category']); ?>
                            </span>
                            <p class="small text-muted mb-2">
                                <i class="bi bi-wallet2 gt-icon"></i>
                                <?php echo $act['cost'] > 0 ? '₹' . number_format($act['cost']) : 'Free'; ?>
                                &middot;
                                <i class="bi bi-clock gt-icon"></i><?php echo $act['duration']; ?> hrs
                            </p>

                            <div class="mt-auto d-flex gap-2">
                                <!-- Quick view button -->
                                <button type="button" class="btn btn-gt-outline btn-sm btn-pill flex-fill"
                                        data-bs-toggle="modal" data-bs-target="#quickViewModal<?php echo $index; ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <!-- Add button -->
                                <button type="button" class="btn btn-gt-accent btn-sm btn-pill flex-fill"
                                        data-bs-toggle="modal" data-bs-target="#addActivityModal"
                                        data-name="<?php echo htmlspecialchars($act['name']); ?>"
                                        data-category="<?php echo htmlspecialchars($act['category']); ?>"
                                        data-cost="<?php echo $act['cost']; ?>"
                                        data-duration="<?php echo $act['duration']; ?>">
                                    <i class="bi bi-plus-circle"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick view modal for this activity -->
                <div class="modal fade" id="quickViewModal<?php echo $index; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content" style="border-radius:15px;">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi <?php echo category_icon($act['category']); ?> gt-icon"></i>
                                    <?php echo htmlspecialchars($act['name']); ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p><?php echo htmlspecialchars($act['description']); ?></p>
                                <p class="mb-1"><i class="bi bi-wallet2 gt-icon"></i>
                                    Cost: <?php echo $act['cost'] > 0 ? '₹' . number_format($act['cost']) : 'Free'; ?></p>
                                <p class="mb-0"><i class="bi bi-clock gt-icon"></i> Duration: <?php echo $act['duration']; ?> hrs</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ===== Activities already added to this user's trips ===== -->
    <h3 class="gt-section-title"><i class="bi bi-list-check gt-icon"></i>Added to Your Trips</h3>

    <?php if (count($added_activities) === 0): ?>
        <div class="gt-empty-state">
            <i class="bi bi-clipboard" style="font-size: 2.5rem;"></i>
            <p class="mt-3 mb-0">You haven't added any activities yet. Use "Add" above to get started.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>City</th>
                        <th>Type</th>
                        <th>Cost</th>
                        <th>Duration</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($added_activities as $act): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($act['activity_name']); ?></td>
                            <td><i class="bi bi-geo-alt gt-icon"></i><?php echo htmlspecialchars($act['city_name']); ?></td>
                            <td><span class="gt-badge-primary"><?php echo ucfirst($act['category']); ?></span></td>
                            <td><?php echo $act['cost'] > 0 ? '₹' . number_format($act['cost']) : 'Free'; ?></td>
                            <td><?php echo $act['duration']; ?> hrs</td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Remove this activity?');">
                                    <input type="hidden" name="activity_id" value="<?php echo $act['activity_id']; ?>">
                                    <button type="submit" name="remove_activity" class="btn btn-outline-danger btn-sm btn-pill">
                                        <i class="bi bi-trash"></i> Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- =========================================================
     Shared "Add Activity" modal - reused by every card.
     ========================================================= -->
<div class="modal fade" id="addActivityModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" style="border-radius:15px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle gt-icon"></i>Add <span id="modalActName">Activity</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <?php if (count($my_stops) === 0): ?>
            <p class="text-muted">You don't have any city stops yet. Add a city to a trip first (see Search Cities).</p>
        <?php else: ?>
            <!-- Hidden fields carry the chosen activity's details to the server -->
            <input type="hidden" name="activity_name" id="modalActNameInput">
            <input type="hidden" name="category" id="modalCategoryInput">
            <input type="hidden" name="cost" id="modalCostInput">
            <input type="hidden" name="duration" id="modalDurationInput">

            <div class="form-floating">
                <select name="stop_id" class="form-select" required>
                    <?php foreach ($my_stops as $stop): ?>
                        <option value="<?php echo $stop['stop_id']; ?>">
                            <?php echo htmlspecialchars($stop['trip_name'] . ' - ' . $stop['city_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Choose Trip Stop (City)</label>
            </div>
        <?php endif; ?>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-gt-outline btn-pill" data-bs-dismiss="modal">Cancel</button>
        <?php if (count($my_stops) > 0): ?>
            <button type="submit" name="add_activity" class="btn btn-gt-primary btn-pill">
                <i class="bi bi-check-circle"></i> Add Activity
            </button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fill in the "Add Activity" modal with the clicked card's data
const addActivityModal = document.getElementById('addActivityModal');
if (addActivityModal) {
    addActivityModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;

        const name     = button.getAttribute('data-name');
        const category = button.getAttribute('data-category');
        const cost     = button.getAttribute('data-cost');
        const duration = button.getAttribute('data-duration');

        document.getElementById('modalActName').textContent = name;

        const nameInput     = document.getElementById('modalActNameInput');
        const categoryInput = document.getElementById('modalCategoryInput');
        const costInput     = document.getElementById('modalCostInput');
        const durationInput = document.getElementById('modalDurationInput');

        if (nameInput)     nameInput.value = name;
        if (categoryInput) categoryInput.value = category;
        if (costInput)     costInput.value = cost;
        if (durationInput) durationInput.value = duration;
    });
}
</script>
</body>
</html>
