<?php
// budget.php
// Budget overview for a trip: total limit, expenses, category
// breakdown, progress bar, plus estimated activity cost tie-in.

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

$allowed_expense_types = ['Transport', 'Stay', 'Activity', 'Meals', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['budget_limit'])) {
    $budget_limit = floatval($_POST['budget_limit']);
    if ($budget_limit >= 0) {
        $update = $conn->prepare("UPDATE TRIP SET Budget = ? WHERE Trip_ID = ? AND User_ID = ?");
        $update->bind_param("dii", $budget_limit, $trip_id, $user_id);
        $update->execute();
    }
    header("Location: budget.php?trip_id=" . $trip_id);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $expense_type = trim($_POST['expense_type']);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'];

    if (!in_array($expense_type, $allowed_expense_types, true)) {
        $expense_type = 'Other';
    }

    if ($amount > 0 && $expense_date !== '') {
        $insert = $conn->prepare("INSERT INTO EXPENSE (Trip_ID, Expense_Type, Description, Amount, Expense_Date) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("issds", $trip_id, $expense_type, $description, $amount, $expense_date);
        $insert->execute();
    }
    header("Location: budget.php?trip_id=" . $trip_id);
    exit();
}

if (isset($_GET['delete_expense'])) {
    $expense_id = intval($_GET['delete_expense']);
    $del = $conn->prepare("DELETE FROM EXPENSE WHERE Expense_ID = ? AND Trip_ID = ?");
    $del->bind_param("ii", $expense_id, $trip_id);
    $del->execute();
    header("Location: budget.php?trip_id=" . $trip_id);
    exit();
}

$expQuery = $conn->prepare("SELECT * FROM EXPENSE WHERE Trip_ID = ? ORDER BY Expense_Date DESC");
$expQuery->bind_param("i", $trip_id);
$expQuery->execute();
$expResult = $expQuery->get_result();

$expenses = [];
while ($row = $expResult->fetch_assoc()) {
    $expenses[] = $row;
}

$categoryTotals = [];
$total_spent = 0;
foreach ($expenses as $exp) {
    $type = $exp['Expense_Type'];
    if (!isset($categoryTotals[$type])) {
        $categoryTotals[$type] = 0;
    }
    $categoryTotals[$type] += $exp['Amount'];
    $total_spent += $exp['Amount'];
}

$activityEstQuery = $conn->prepare("
    SELECT COALESCE(SUM(i.Activity_Cost), 0) AS total_activity_est
    FROM ITINERARY i
    INNER JOIN TRIP_STOP ts ON i.Stop_ID = ts.Stop_ID
    WHERE ts.Trip_ID = ?
");
$activityEstQuery->bind_param("i", $trip_id);
$activityEstQuery->execute();
$activityEstResult = $activityEstQuery->get_result()->fetch_assoc();
$estimated_activity_cost = $activityEstResult['total_activity_est'];

$budget_limit = $trip['Budget'] ? floatval($trip['Budget']) : 0;
$percent_used = $budget_limit > 0 ? min(100, round(($total_spent / $budget_limit) * 100)) : 0;
$is_over_budget = $budget_limit > 0 && $total_spent > $budget_limit;
$budget_left = $budget_limit > 0 ? max(0, $budget_limit - $total_spent) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Budget - <?php echo htmlspecialchars($trip['Trip_Name']); ?> | GlobeTrotter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<style>
.budget-stat { font-family: 'Space Mono', monospace; }
.category-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 0; border-bottom: 1px dashed var(--line);
}
.category-row:last-child { border-bottom: none; }
</style>
</head>
<body>

<?php $active_page = 'my-trips'; include 'includes/navbar.php'; ?>

<div class="container py-section">

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
<h2><i class="bi bi-wallet2 text-primary-custom"></i> Budget - <?php echo htmlspecialchars($trip['Trip_Name']); ?></h2>
<a href="itinerary-view.php?trip_id=<?php echo $trip_id; ?>" class="btn btn-outline-primary">
<i class="bi bi-arrow-left"></i> Back to Itinerary
</a>
</div>

<div class="card mb-4">
<div class="card-body">
<h5 class="card-title"><i class="bi bi-pencil"></i> Set Your Total Budget</h5>
<form method="POST" class="row g-2 align-items-center">
<div class="col-auto flex-grow-1">
<div class="form-floating">
<input type="number" step="0.01" min="0" name="budget_limit" class="form-control"
id="budgetLimit" placeholder="Enter total budget"
value="<?php echo $budget_limit > 0 ? $budget_limit : ''; ?>" required>
<label for="budgetLimit">Total Budget (₹)</label>
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
You are over budget! Total expenses exceed your set limit.
</div>
<?php endif; ?>

<div class="row">
<div class="col-md-6 mb-4">
<div class="card h-100">
<div class="card-body">
<h5 class="card-title"><i class="bi bi-pie-chart"></i> Overview</h5>
<p class="mb-1 budget-stat">Total Spent: <strong>₹<?php echo number_format($total_spent, 2); ?></strong></p>
<?php if ($budget_limit > 0): ?>
<p class="text-muted mb-2 budget-stat">Budget Limit: ₹<?php echo number_format($budget_limit, 2); ?> &nbsp;·&nbsp; Left: ₹<?php echo number_format($budget_left, 2); ?></p>
<div class="progress">
<div class="progress-bar <?php echo $is_over_budget ? 'bg-over-budget' : ''; ?>"
role="progressbar" style="width: <?php echo $percent_used; ?>%"
aria-valuenow="<?php echo $percent_used; ?>" aria-valuemin="0" aria-valuemax="100">
<?php echo $percent_used; ?>%
</div>
</div>
<?php else: ?>
<p class="text-muted"><i class="bi bi-info-circle"></i> Set a total budget above to track your spending.</p>
<?php endif; ?>
<hr>
<p class="mb-0 text-muted budget-stat" style="font-size:0.9rem;">
<i class="bi bi-stars"></i> Estimated cost of planned activities (from itinerary):
<strong>₹<?php echo number_format($estimated_activity_cost, 2); ?></strong>
</p>
</div>
</div>
</div>

<div class="col-md-6 mb-4">
<div class="card h-100">
<div class="card-body">
<h5 class="card-title"><i class="bi bi-list-ul"></i> Category Breakdown</h5>
<?php if (count($categoryTotals) === 0): ?>
<p class="text-muted">No expenses logged yet.</p>
<?php else: ?>
<?php foreach ($categoryTotals as $type => $amount): ?>
<div class="category-row">
<span><i class="bi bi-tag"></i> <?php echo htmlspecialchars($type); ?></span>
<strong class="budget-stat">₹<?php echo number_format($amount, 2); ?></strong>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</div>
</div>

<div class="card mb-4">
<div class="card-body">
<h5 class="card-title"><i class="bi bi-plus-circle"></i> Add an Expense</h5>
<form method="POST" class="row g-3">
<input type="hidden" name="add_expense" value="1">
<div class="col-md-3">
<div class="form-floating">
<select name="expense_type" class="form-select" id="expenseType" required>
<option value="Transport">Transport</option>
<option value="Stay">Stay</option>
<option value="Activity">Activity</option>
<option value="Meals">Meals</option>
<option value="Other">Other</option>
</select>
<label for="expenseType"><i class="bi bi-tag"></i> Category</label>
</div>
</div>
<div class="col-md-4">
<div class="form-floating">
<input type="text" name="description" class="form-control" id="expenseDesc" placeholder="Description">
<label for="expenseDesc"><i class="bi bi-card-text"></i> Description</label>
</div>
</div>
<div class="col-md-2">
<div class="form-floating">
<input type="number" step="0.01" min="0.01" name="amount" class="form-control" id="expenseAmount" placeholder="Amount" required>
<label for="expenseAmount"><i class="bi bi-cash-coin"></i> Amount</label>
</div>
</div>
<div class="col-md-3">
<div class="form-floating">
<input type="date" name="expense_date" class="form-control" id="expenseDate" required>
<label for="expenseDate"><i class="bi bi-calendar3"></i> Date</label>
</div>
</div>
<div class="col-12">
<button type="submit" class="btn btn-secondary"><i class="bi bi-save"></i> Add Expense</button>
</div>
</form>
</div>
</div>

<div class="card">
<div class="card-body">
<h5 class="card-title"><i class="bi bi-receipt"></i> All Expenses</h5>
<?php if (count($expenses) === 0): ?>
<p class="text-muted">No expenses logged yet.</p>
<?php else: ?>
<div class="table-responsive">
<table class="table align-middle">
<thead>
<tr>
<th>Date</th>
<th>Category</th>
<th>Description</th>
<th>Amount</th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach ($expenses as $exp): ?>
<tr>
<td><?php echo date("d M Y", strtotime($exp['Expense_Date'])); ?></td>
<td><span class="badge-custom"><?php echo htmlspecialchars($exp['Expense_Type']); ?></span></td>
<td><?php echo htmlspecialchars($exp['Description']); ?></td>
<td>₹<?php echo number_format($exp['Amount'], 2); ?></td>
<td>
<a href="budget.php?trip_id=<?php echo $trip_id; ?>&delete_expense=<?php echo $exp['Expense_ID']; ?>"
class="btn btn-sm btn-outline-primary"
onclick="return confirm('Delete this expense?');">
<i class="bi bi-trash"></i>
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>

</div>

<footer>
<p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>
