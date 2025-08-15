<?php


// TrieNode class
class TrieNode
{
    public $children = [];
    public $isEndOfWord = false;
}

// Trie class
class Trie
{
    private $root;

    public function __construct()
    {
        $this->root = new TrieNode();
    }

    // Insert a word into the Trie
    public function insert($word)
    {
        $node = $this->root;
        foreach (str_split(strtolower($word)) as $char) {
            if (!isset($node->children[$char])) {
                $node->children[$char] = new TrieNode();
            }
            $node = $node->children[$char];
        }
        $node->isEndOfWord = true;
    }

    // Search for a prefix in the Trie
    public function searchPrefix($prefix)
    {
        $node = $this->root;
        foreach (str_split(strtolower($prefix)) as $char) {
            if (!isset($node->children[$char])) {
                return null;
            }
            $node = $node->children[$char];
        }
        return $node;
    }

    // Collect all words with a given prefix
    public function collectAllWords($node, $prefix, &$resultList)
    {
        if ($node->isEndOfWord) {
            $resultList[] = $prefix;
        }
        foreach ($node->children as $char => $childNode) {
            $this->collectAllWords($childNode, $prefix . $char, $resultList);
        }
    }

    // Get autocomplete suggestions for a prefix
    public function getSuggestions($prefix)
    {
        $resultList = [];
        $node = $this->searchPrefix($prefix);
        if ($node !== null) {
            $this->collectAllWords($node, $prefix, $resultList);
        }
        return $resultList;
    }
}

// Handle AJAX autocomplete request
if (isset($_GET['action']) && $_GET['action'] === 'autocomplete' && isset($_GET['prefix'])) {
    $trie = new Trie();
    $prefix = $_GET['prefix'];

    // Fetch all item names from the database to populate the Trie
    $itemQuery = 'SELECT DISTINCT itemName FROM sale';
    $itemStatement = $conn->prepare($itemQuery);
    if ($itemStatement === false) {
        echo json_encode(['error' => 'Item query preparation failed']);
        exit;
    }
    if (!$itemStatement->execute()) {
        echo json_encode(['error' => 'Item query execution failed']);
        exit;
    }
    while ($row = $itemStatement->fetch(PDO::FETCH_ASSOC)) {
        $trie->insert($row['itemName']);
    }
    $itemStatement->closeCursor();

    // Get suggestions for the prefix
    $suggestions = $trie->getSuggestions($prefix);
    echo json_encode($suggestions);
    exit;
}

$uPrice = 0;
$qty = 0;
$totalPrice = 0;

// Fetch sales data
$saleDetailsSearchSql = 'SELECT * FROM sale';
$saleDetailsSearchStatement = $conn->prepare($saleDetailsSearchSql);

// Check if query preparation failed
if ($saleDetailsSearchStatement === false) {
    echo json_encode(['error' => 'Query preparation failed']);
    exit;
}

if (!$saleDetailsSearchStatement->execute()) {
    echo json_encode(['error' => 'Query execution failed']);
    exit;
}

// Initialize chart data arrays
$quantitiesByItem = [];
$quantitiesByMonth = [];

// Build table output
$output = '<table id="saleReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Item Number</th>
                        <th>Customer ID</th>
                        <th>Customer Name</th>
                        <th>Item Name</th>
                        <th>Sale Date</th>
                        <th>Discount %</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total Price</th>
                    </tr>
                </thead>
                <tbody>';

// Loop for table and chart data
while ($row = $saleDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
    $uPrice = $row['unitPrice'];
    $qty = $row['quantity'];
    $discount = $row['discount'];
    $totalPrice = $uPrice * $qty * ((100 - $discount) / 100);

    // Table row
    $output .= '<tr>' .
        '<td>' . htmlspecialchars($row['saleID']) . '</td>' .
        '<td>' . htmlspecialchars($row['itemNumber']) . '</td>' .
        '<td>' . htmlspecialchars($row['customerID']) . '</td>' .
        '<td>' . htmlspecialchars($row['customerName']) . '</td>' .
        '<td>' . htmlspecialchars($row['itemName']) . '</td>' .
        '<td>' . htmlspecialchars($row['saleDate']) . '</td>' .
        '<td>' . htmlspecialchars($row['discount']) . '</td>' .
        '<td>' . htmlspecialchars($row['quantity']) . '</td>' .
        '<td>' . htmlspecialchars($row['unitPrice']) . '</td>' .
        '<td>' . number_format($totalPrice, 2) . '</td>' .
        '</tr>';

    // Collect Pie chart data - Quantities by Item
    $item = $row['itemName'];
    if (!isset($quantitiesByItem[$item])) {
        $quantitiesByItem[$item] = 0;
    }
    $quantitiesByItem[$item] += (int) $qty;

    // Collect Bar and Line chart data - Quantities by Month
    $month = date('Y-m', strtotime($row['saleDate']));
    if (!isset($quantitiesByMonth[$month])) {
        $quantitiesByMonth[$month] = 0;
    }
    $quantitiesByMonth[$month] += (int) $qty;
}

$saleDetailsSearchStatement->closeCursor();

$output .= '</tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th></th><th></th><th></th><th></th>
                        <th></th><th></th><th></th><th></th><th></th>
                    </tr>
                </tfoot>
            </table>';

// Search input for autocomplete
$output .= '
<div class="mt-3" style="max-width: 600px; margin: auto;">
    <h5>Search Item Name</h5>
    <input type="text" id="itemSearch" class="form-control" placeholder="Type item name...">
    <ul id="suggestions" class="list-group mt-2"></ul>
</div>';

// Chart containers with responsive CSS
$output .= '
<style>
.chart-container {
    width: 100%;
    margin: auto;
}
.chart-container canvas {
    width: 100% !important;
    max-height: 50vh !important;
    min-height: 200px;
}
@media (max-width: 768px) {
    .chart-container canvas {
        max-height: 40vh !important;
    }
}
@media (max-width: 576px) {
    .chart-container canvas {
        max-height: 30vh !important;
    }
}
</style>

<div class="mt-5 chart-container" style="max-width: 600px;">
    <h5>Quantities Sold by Item (Pie Chart)</h5>
    <canvas id="salesPieChart"></canvas>
</div>

<div class="mt-5 chart-container" style="max-width: 700px;">
    <h5>Monthly Quantities Sold (Bar Chart)</h5>
    <canvas id="salesBarChart"></canvas>
</div>

<div class="mt-5 chart-container" style="max-width: 700px;">
    <h5>Monthly Quantities Sold Trend (Line Chart)</h5>
    <canvas id="salesLineChart"></canvas>
</div>

<!-- Include Chart.js and jQuery -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Chart and autocomplete initialization -->
<script>
$(document).ready(function() {
    const quantitiesByItem = ' . json_encode($quantitiesByItem) . ';
    const quantitiesByMonth = ' . json_encode($quantitiesByMonth) . ';

    console.log("quantitiesByItem:", quantitiesByItem);
    console.log("quantitiesByMonth:", quantitiesByMonth);

    // Sort months for bar and line charts
    const sortedMonths = Object.keys(quantitiesByMonth).sort();
    const sortedQuantities = sortedMonths.map(month => quantitiesByMonth[month]);

    // Pie Chart - Quantities Sold by Item
    if (document.getElementById("salesPieChart")) {
        if (Object.keys(quantitiesByItem).length === 0) {
            $("#salesPieChart").parent().append("<p class=\"text-danger\">No sales data available for pie chart</p>");
        } else {
            new Chart(document.getElementById("salesPieChart").getContext("2d"), {
                type: "pie",
                data: {
                    labels: Object.keys(quantitiesByItem),
                    datasets: [{
                        label: "Quantities Sold by Item",
                        data: Object.values(quantitiesByItem),
                        backgroundColor: [
                            "#007bff", "#28a745", "#ffc107", "#dc3545",
                            "#6f42c1", "#17a2b8", "#20c997", "#6610f2"
                        ],
                        borderColor: ["#FFFFFF", "#FFFFFF", "#FFFFFF", "#FFFFFF", "#FFFFFF", "#FFFFFF", "#FFFFFF", "#FFFFFF"],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: "top",
                            labels: {
                                color: "#333333"
                            }
                        },
                        title: {
                            display: true,
                            text: "Quantities Sold by Item",
                            color: "#333333"
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.raw} units`;
                                }
                            }
                        }
                    }
                }
            });
        }
    } else {
        console.error("Canvas element salesPieChart not found");
    }

    // Bar Chart - Quantities Sold by Month
    if (document.getElementById("salesBarChart")) {
        if (sortedMonths.length === 0) {
            $("#salesBarChart").parent().append("<p class=\"text-danger\">No sales data available for bar chart</p>");
        } else {
            new Chart(document.getElementById("salesBarChart").getContext("2d"), {
                type: "bar",
                data: {
                    labels: sortedMonths,
                    datasets: [{
                        label: "Total Quantities Sold",
                        data: sortedQuantities,
                        backgroundColor: "#007bff"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "Quantity Sold",
                                color: "#333333"
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: "Month",
                                color: "#333333"
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: "#333333"
                            }
                        },
                        title: {
                            display: true,
                            text: "Monthly Quantities Sold",
                            color: "#333333"
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.raw} units`;
                                }
                            }
                        }
                    }
                }
            });
        }
    } else {
        console.error("Canvas element salesBarChart not found");
    }

    // Line Chart - Monthly Quantities Sold Trend
    if (document.getElementById("salesLineChart")) {
        if (sortedMonths.length === 0) {
            $("#salesLineChart").parent().append("<p class=\"text-danger\">No sales data available for line chart</p>");
        } else {
            new Chart(document.getElementById("salesLineChart").getContext("2d"), {
                type: "line",
                data: {
                    labels: sortedMonths,
                    datasets: [{
                        label: "Monthly Quantities Sold Trend",
                        data: sortedQuantities,
                        fill: false,
                        borderColor: "#28a745",
                        tension: 0.3,
                        pointBackgroundColor: "#28a745",
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "Quantity Sold",
                                color: "#333333"
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: "Month",
                                color: "#333333"
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: "#333333"
                            }
                        },
                        title: {
                            display: true,
                            text: "Monthly Quantities Sold Trend",
                            color: "#333333"
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.raw} units`;
                                }
                            }
                        }
                    }
                }
            });
        }
    } else {
        console.error("Canvas element salesLineChart not found");
    }

    // Autocomplete functionality
    $("#itemSearch").on("input", function() {
        const prefix = $(this).val();
        if (prefix.length > 0) {
            $.ajax({
                
                type: "GET",
                data: { action: "autocomplete", prefix: prefix },
                success: function(data) {
                    const suggestions = JSON.parse(data);
                    const suggestionsList = $("#suggestions");
                    suggestionsList.empty();
                    if (suggestions.length > 0) {
                        suggestions.forEach(function(item) {
                            suggestionsList.append(`<li class="list-group-item suggestion-item">${item}</li>`);
                        });
                    } else {
                     
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Autocomplete request failed:", error);
                  
                }
            });
        } else {
            $("#suggestions").empty();
        }
    });

    // Handle suggestion click
    $(document).on("click", ".suggestion-item", function() {
        $("#itemSearch").val($(this).text());
        $("#suggestions").empty();
    });

    // Handle tab visibility for chart redraw
    $("a[data-toggle=\"tab\"]").on("shown.bs.tab", function (e) {
        window.dispatchEvent(new Event("resize"));
    });

    // Handle window resize for dynamic chart height
    $(window).on("resize", function() {
        window.dispatchEvent(new Event("resize"));
    });
});
</script>';

// Output the combined table and chart HTML
echo $output;
?>