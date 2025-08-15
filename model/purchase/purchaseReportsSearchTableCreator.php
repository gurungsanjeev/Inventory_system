<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

$uPrice = 0;
$qty = 0;
$totalPrice = 0;

$purchaseDetailsSearchSql = 'SELECT * FROM purchase';
$purchaseDetailsSearchStatement = $conn->prepare($purchaseDetailsSearchSql);
$purchaseDetailsSearchStatement->execute();

// Arrays for chart data
$purchasesByItem = [];
$purchasesByMonth = [];

$output = '<table id="purchaseReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
				<thead>
					<tr>
						<th>Purchase ID</th>
						<th>Item Number</th>
						<th>Purchase Date</th>
						<th>Item Name</th>
						<th>Vendor Name</th>
						<th>Vendor ID</th>
						<th>Quantity</th>
						<th>Unit Price</th>
						<th>Total Price</th>
					</tr>
				</thead>
				<tbody>';

// Build table rows and collect data for charts
while ($row = $purchaseDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
	$uPrice = $row['unitPrice'];
	$qty = $row['quantity'];
	$totalPrice = $uPrice * $qty;

	// Table row
	$output .= '<tr>' .
		'<td>' . $row['purchaseID'] . '</td>' .
		'<td>' . $row['itemNumber'] . '</td>' .
		'<td>' . $row['purchaseDate'] . '</td>' .
		'<td>' . $row['itemName'] . '</td>' .
		'<td>' . $row['vendorName'] . '</td>' .
		'<td>' . $row['vendorID'] . '</td>' .
		'<td>' . $qty . '</td>' .
		'<td>' . $uPrice . '</td>' .
		'<td>' . $totalPrice . '</td>' .
		'</tr>';

	// Chart data by item (using quantity)
	if (!isset($purchasesByItem[$row['itemName']])) {
		$purchasesByItem[$row['itemName']] = 0;
	}
	$purchasesByItem[$row['itemName']] += $qty;

	// Chart data by month (using quantity, YYYY-MM)
	$month = date('Y-m', strtotime($row['purchaseDate']));
	if (!isset($purchasesByMonth[$month])) {
		$purchasesByMonth[$month] = 0;
	}
	$purchasesByMonth[$month] += $qty;
}

$purchaseDetailsSearchStatement->closeCursor();

$output .= '</tbody>
				<tfoot>
					<tr>
						<th>Total</th><th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
					</tr>
				</tfoot>
			</table>';

// Output the table first
echo $output;
?>

<!-- Chart containers -->
<div class="mt-5" style="max-width: 400px; margin: auto;">
	<h5>Purchase Item in Quantity (Pie Chart)</h5>
	<canvas id="purchasePieChart"></canvas>
</div>

<div class="mt-5" style="max-width: 700px; margin: auto;">
	<h5>Monthly Purchase Quantity (Bar Chart)</h5>
	<canvas id="purchaseBarChart"></canvas>
</div>

<div class="mt-5" style="max-width: 700px; margin: auto;">
	<h5>Monthly Purchase Quantity Trend (Line Chart)</h5>
	<canvas id="purchaseLineChart"></canvas>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
	// Pass PHP data to JS
	const purchasesByItem = <?php echo json_encode($purchasesByItem); ?>;
	const purchasesByMonth = <?php echo json_encode($purchasesByMonth); ?>;

	// Pie Chart - Quantity by Item
	const pieCtx = document.getElementById('purchasePieChart').getContext('2d');
	new Chart(pieCtx, {
		type: 'pie',
		data: {
			labels: Object.keys(purchasesByItem),
			datasets: [{
				label: 'Quantity by Item',
				data: Object.values(purchasesByItem),
				backgroundColor: [
					'#007bff', '#28a745', '#ffc107', '#dc3545',
					'#6f42c1', '#17a2b8', '#20c997', '#6610f2'
				],
				borderWidth: 1
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: true
		}
	});

	// Bar Chart - Quantity by Month
	const barCtx = document.getElementById('purchaseBarChart').getContext('2d');
	new Chart(barCtx, {
		type: 'bar',
		data: {
			labels: Object.keys(purchasesByMonth),
			datasets: [{
				label: 'Total Quantity',
				data: Object.values(purchasesByMonth),
				backgroundColor: '#007bff'
			}]
		},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true,
					title: {
						display: true,
						text: 'Quantity'
					}
				}
			}
		}
	});

	// Line Chart - Monthly Quantity Trend
	const lineCtx = document.getElementById('purchaseLineChart').getContext('2d');
	new Chart(lineCtx, {
		type: 'line',
		data: {
			labels: Object.keys(purchasesByMonth),
			datasets: [{
				label: 'Monthly Quantity Trend',
				data: Object.values(purchasesByMonth),
				fill: false,
				borderColor: '#28a745',
				tension: 0.3,
				pointBackgroundColor: '#28a745',
				pointRadius: 5
			}]
		},
		options: {
			responsive: true,
			scales: {
				y: {
					beginAtZero: true,
					title: {
						display: true,
						text: 'Quantity'
					}
				}
			}
		}
	});
</script>