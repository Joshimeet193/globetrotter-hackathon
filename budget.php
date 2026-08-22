<?php
// budget.php
// Purpose: Show estimated budget breakdown for a trip (transport, stay, activities, meals)
session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['trip_id'])) {
    die("No trip selected.");
}
$trip_id = intval($_GET['trip_id']);

// Confirm trip belongs to this user
$tripQuery = $conn->prepare("SELECT * FROM trips WHERE trip_id = ? AND user_id = ?");
$tripQuery->bind_param("ii", $trip_id, $user_id);
$tripQuery->execute();
$tripResult = $tripQuery->get_result();

if ($tripResult->num_rows === 0) {
    die("Trip not found or you don't have access to it.");
}
$trip = $tripResult->fetch_assoc();

// ---- Handle "Set Budget Limit" form submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['total_budget_limit'])) {
    $budget_limit = floatval($_POST['total_budget_limit']);

    // Check if a budget row already exists for this trip
    $check = $conn->prepare("SELECT budget_id FROM budget WHERE trip_id = ?");
    $check->bind_param("i", $trip_id);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {
        $update = $conn->prepare("UPDATE budget SET budget_limit = ? WHERE trip_id = ?");
        $update->bind_param("di", $budget_limit, $trip_id);
        $update->execute();
    } else {
        $insert = $conn->prepare("INSERT INTO budget (trip_id, budget_limit, transport_cost, stay_cost, activity_cost, meal_cost) VALUES (?, ?, 0, 0, 0, 0)");
        $insert->bind_param("id", $trip_id, $budget_limit);
        $insert->execute();
    }
    header("Location: budget.php?trip_id=" . $trip_id);
    exit();
}

// ---- Calculate activity cost automatically from activities table ----
$activityCostQuery = $conn->prepare("
    SELECT COALESCE(SUM(a.cost), 0) AS total_activity_cost
    FROM activities a
    INNER JOIN stops s ON a.stop_id = s.stop_id
    WHERE s.trip_id = ?
");
$activityCostQuery->bind_param("i", $trip_id);
$activityCostQuery->execute();
$activityCostResult = $activityCostQuery->get_result()->fetch_assoc();
$activity_cost = $activityCostResult['total_activity_cost'];

// ---- Fetch budget row (transport/stay/meal costs + limit set by user) ----
$budgetQuery = $conn->prepare("SELECT * FROM budget WHERE trip_id = ?");
$budgetQuery->bind_param("i", $trip_id);
$budgetQuery->execute();
$budgetResult = $budgetQuery->get_result();

if ($budgetResult->num_rows > 0) {
    $budget = $budgetResult->fetch_assoc();
} else {
    // Default values if no budget row exists yet
    $budget = [
        'budget_limit' => 0,
        'transport_cost' => 0,
        'stay_cost' => 0,
        'meal_cost' => 0
    ];
}

$transport_cost = $budget['transport_cost'];
$stay_cost = $budget['stay_cost'];
$meal_cost = $budget['meal_cost'];
$budget_limit = $budget['budget_limit'];

$total_cost = $transport_cost + $stay_cost + $activity_cost + $meal_cost;

// Percentage used (avoid divide by zero)
$percent_used = $budget_limit > 0 ? min(100, round(($total_cost / $budget_limit) * 100)) : 0;
$is_over_budget = $budget_limit > 0 && $total_cost > $budget_limit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Budget - <?php echo htmlspecialchars($trip['trip_name']); ?> | GlobeTrotter</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">🌍 GlobeTrotter</a>
        <div class="ms-auto">
            <a href="dashboard.php" class="nav-link d-inline"><i class="bi bi-house"></i> Dashboard</a>
            <a href="my-trips.php" class="nav-link d-inline"><i class="bi bi-suitcase"></i> My Trips</a>
            <a href="logout.php" class="nav-link d-inline"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container py-section">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-wallet2 text-primary-custom"></i> Budget - <?php echo htmlspecialchars($trip['trip_name']); ?></h2>
        </div>
        <a href="itinerary-view.php?trip_id=<?php echo $trip_id; ?>" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Back to Itinerary
        </a>
    </div>

    <!-- Set / Update Budget Limit -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-pencil"></i> Set Your Budget Limit</h5>
            <form method="POST" class="row g-2 align-items-center">
                <div class="col-auto flex-grow-1">
                    <div class="form-floating">
                        <input type="number" step="0.01" name="total_budget_limit" class="form-control"
                               id="budgetLimit" placeholder="Enter total budget"
                               value="<?php echo $budget_limit > 0 ? $budget_limit : ''; ?>" required>
                        <label for="budgetLimit">Total Budget Limit (₹)</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($is_over_budget): ?>
        <div class="budget-alert mb-4">
            <i class="bi bi-exclamation-triangle-fill"></i>
            You are over budget! Estimated cost exceeds your set limit.
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Total Overview -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-pie-chart"></i> Overview</h5>

                    <p class="mb-1">Estimated Total: <strong>₹<?php echo number_format($total_cost, 2); ?></strong></p>
                    <?php if ($budget_limit > 0): ?>
                        <p class="text-muted mb-2">Budget Limit: ₹<?php echo number_format($budget_limit, 2); ?></p>

                        <div class="progress">
                            <div class="progress-bar <?php echo $is_over_budget ? 'bg-over-budget' : ''; ?>"
                                 role="progressbar" style="width: <?php echo $percent_used; ?>%"
                                 aria-valuenow="<?php echo $percent_used; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $percent_used; ?>%
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted"><i class="bi bi-info-circle"></i> Set a budget limit above to track your spending.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-list-ul"></i> Category Breakdown</h5>

                    <p><i class="bi bi-airplane"></i> Transport: <strong>₹<?php echo number_format($transport_cost, 2); ?></strong></p>
                    <p><i class="bi bi-building"></i> Stay: <strong>₹<?php echo number_format($stay_cost, 2); ?></strong></p>
                    <p><i class="bi bi-stars"></i> Activities: <strong>₹<?php echo number_format($activity_cost, 2); ?></strong></p>
                    <p><i class="bi bi-cup-hot"></i> Meals: <strong>₹<?php echo number_format($meal_cost, 2); ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Manually update Transport / Stay / Meal cost -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-sliders"></i> Update Other Costs</h5>
            <p class="text-muted" style="font-size:0.85rem;">
                Note: Activity cost is calculated automatically from your itinerary activities.
            </p>
            <form method="POST" action="update-budget-costs.php" class="row g-3">
                <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="number" step="0.01" name="transport_cost" class="form-control"
                               value="<?php echo $transport_cost; ?>" id="transportCost">
                        <label for="transportCost"><i class="bi bi-airplane"></i> Transport Cost</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="number" step="0.01" name="stay_cost" class="form-control"
                               value="<?php echo $stay_cost; ?>" id="stayCost">
                        <label for="stayCost"><i class="bi bi-building"></i> Stay Cost</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="number" step="0.01" name="meal_cost" class="form-control"
                               value="<?php echo $meal_cost; ?>" id="mealCost">
                        <label for="mealCost"><i class="bi bi-cup-hot"></i> Meal Cost</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-secondary"><i class="bi bi-save"></i> Update Costs</button>
                </div>
            </form>
        </div>
    </div>

</div>

<footer>
    <p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
