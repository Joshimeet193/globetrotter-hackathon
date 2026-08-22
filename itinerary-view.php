<?php
// itinerary-view.php
// Shows the full itinerary of a trip — stops in order, with
// scheduled activities per stop, plus a running total cost.

session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['User_ID'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['User_ID'];

if (!isset($_GET['trip_id'])) {
    header("Location: my-trips.php");
    exit();
}

$trip_id = intval($_GET['trip_id']);

$tripQuery = $conn->prepare("SELECT * FROM TRIP WHERE Trip_ID = ? AND User_ID = ?");
$tripQuery->bind_param("ii", $trip_id, $user_id);
$tripQuery->execute();
$tripResult = $tripQuery->get_result();

if ($tripResult->num_rows === 0) {
    header("Location: my-trips.php?error=trip_not_found");
    exit();
}

$trip = $tripResult->fetch_assoc();

$stopsQuery = $conn->prepare("
    SELECT ts.*, c.City_Name, c.Image AS City_Image, co.Country_Name
    FROM TRIP_STOP ts
    INNER JOIN CITY c ON ts.City_ID = c.City_ID
    INNER JOIN COUNTRY co ON c.Country_ID = co.Country_ID
    WHERE ts.Trip_ID = ?
    ORDER BY ts.Stop_Order ASC
");
$stopsQuery->bind_param("i", $trip_id);
$stopsQuery->execute();
$stopsResult = $stopsQuery->get_result();

$stops = [];
while ($row = $stopsResult->fetch_assoc()) {
    $stops[] = $row;
}

$trip_total_cost = 0;
foreach ($stops as $index => $stop) {
    $itinQuery = $conn->prepare("
        SELECT i.*, a.Activity_Name, a.Activity_Type, a.Duration
        FROM ITINERARY i
        INNER JOIN ACTIVITY a ON i.Activity_ID = a.Activity_ID
        WHERE i.Stop_ID = ?
        ORDER BY i.Activity_Date ASC, i.Start_Time ASC
    ");
    $itinQuery->bind_param("i", $stop['Stop_ID']);
    $itinQuery->execute();
    $itinResult = $itinQuery->get_result();

    $items = [];
    while ($item = $itinResult->fetch_assoc()) {
        $items[] = $item;
        $trip_total_cost += (float) $item['Activity_Cost'];
    }

    $stops[$index]['itinerary_items'] = $items;
}

$trip_days = (strtotime($trip['End_Date']) - strtotime($trip['Start_Date'])) / 86400 + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Itinerary - <?php echo htmlspecialchars($trip['Trip_Name']); ?> | GlobeTrotter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<style>
.trip-summary-strip {
  display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 26px;
}
.trip-summary-strip > div {
  background: var(--card); border: 1px solid var(--line); border-radius: 12px;
  padding: 10px 18px; font-family: 'Space Mono', monospace; font-size: 0.85rem; color: var(--ink);
}
.stop-order-stamp {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 50%;
  background: var(--stamp); color: #fff;
  font-family: 'Space Mono', monospace; font-size: 0.75rem; font-weight: 700;
  margin-right: 6px;
}
</style>
</head>
<body>

<?php $active_page = 'my-trips'; include 'includes/navbar.php'; ?>

<div class="container py-section">

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
<div>
<h2><i class="bi bi-map text-primary-custom"></i> <?php echo htmlspecialchars($trip['Trip_Name']); ?></h2>
<p class="text-muted mb-0">
<i class="bi bi-calendar3"></i>
<?php echo date("d M Y", strtotime($trip['Start_Date'])); ?>
&nbsp;→&nbsp;
<?php echo date("d M Y", strtotime($trip['End_Date'])); ?>
</p>
</div>
<div>
<a href="itinerary-builder.php?trip_id=<?php echo $trip_id; ?>" class="btn btn-outline-primary">
<i class="bi bi-pencil-square"></i> Edit Itinerary
</a>
<a href="budget.php?trip_id=<?php echo $trip_id; ?>" class="btn btn-primary">
<i class="bi bi-wallet2"></i> View Budget
</a>
</div>
</div>

<div class="trip-summary-strip">
<div><i class="bi bi-calendar-range"></i> <?php echo (int) $trip_days; ?> day trip</div>
<div><i class="bi bi-geo-alt"></i> <?php echo count($stops); ?> stop<?php echo count($stops) === 1 ? '' : 's'; ?></div>
<div><i class="bi bi-cash-coin"></i> ₹<?php echo number_format($trip_total_cost, 2); ?> planned</div>
</div>

<?php if ($trip['Description']): ?>
<p class="mb-4"><?php echo htmlspecialchars($trip['Description']); ?></p>
<?php endif; ?>

<h4 class="section-title"><i class="bi bi-signpost-2"></i> Trip Timeline</h4>

<?php if (count($stops) === 0): ?>
<div class="budget-alert">
<i class="bi bi-info-circle"></i> No stops added yet.
<a href="itinerary-builder.php?trip_id=<?php echo $trip_id; ?>">Add your first stop</a>.
</div>
<?php else: ?>
<?php foreach ($stops as $stop): ?>
<div class="timeline-day">
<h5>
<span class="stop-order-stamp"><?php echo $stop['Stop_Order']; ?></span>
<?php echo htmlspecialchars($stop['City_Name']); ?>,
<?php echo htmlspecialchars($stop['Country_Name']); ?>
</h5>
<p class="text-muted mb-2">
<i class="bi bi-calendar-event"></i>
<?php echo date("d M", strtotime($stop['Arrival_Date'])); ?>
-
<?php echo date("d M", strtotime($stop['Departure_Date'])); ?>
</p>

<?php if (count($stop['itinerary_items']) === 0): ?>
<p class="text-muted"><i class="bi bi-dash-circle"></i> No activities scheduled for this stop yet.</p>
<?php else: ?>
<div class="row">
<?php foreach ($stop['itinerary_items'] as $item): ?>
<div class="col-md-4 mb-3">
<div class="card h-100">
<div class="card-body">
<span class="badge-custom">
<?php echo htmlspecialchars($item['Activity_Type']); ?>
</span>
<h6 class="card-title mt-2">
<i class="bi bi-stars"></i>
<?php echo htmlspecialchars($item['Activity_Name']); ?>
</h6>
<p class="card-text mb-1">
<i class="bi bi-calendar-date"></i>
<?php echo date("d M", strtotime($item['Activity_Date'])); ?>
<?php if ($item['Start_Time']): ?>
&nbsp;•&nbsp;<i class="bi bi-clock"></i> <?php echo date("h:i A", strtotime($item['Start_Time'])); ?>
<?php endif; ?>
</p>
<p class="card-text mb-1">
<i class="bi bi-hourglass-split"></i> <?php echo htmlspecialchars($item['Duration']); ?> hrs
</p>
<p class="card-text">
<i class="bi bi-cash-coin"></i> ₹<?php echo number_format($item['Activity_Cost'], 2); ?>
</p>
<?php if ($item['Notes']): ?>
<p class="card-text text-muted" style="font-size:0.8rem;">
<i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($item['Notes']); ?>
</p>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>

<footer>
<p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>
