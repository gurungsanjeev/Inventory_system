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

    // Fetch all customer full names from the database to populate the Trie
    $customerQuery = 'SELECT DISTINCT fullName FROM customer';
    $customerStatement = $conn->prepare($customerQuery);
    if ($customerStatement === false) {
        echo json_encode(['error' => 'Customer query preparation failed']);
        exit;
    }
    if (!$customerStatement->execute()) {
        echo json_encode(['error' => 'Customer query execution failed']);
        exit;
    }
    while ($row = $customerStatement->fetch(PDO::FETCH_ASSOC)) {
        $trie->insert($row['fullName']);
    }
    $customerStatement->closeCursor();

    // Get suggestions for the prefix
    $suggestions = $trie->getSuggestions($prefix);
    echo json_encode($suggestions);
    exit;
}

$customerDetailsSearchSql = 'SELECT * FROM customer';
$customerDetailsSearchStatement = $conn->prepare($customerDetailsSearchSql);
if ($customerDetailsSearchStatement === false) {
    echo json_encode(['error' => 'Query preparation failed']);
    exit;
}
if (!$customerDetailsSearchStatement->execute()) {
    echo json_encode(['error' => 'Query execution failed']);
    exit;
}

$output = '<table id="customerDetailsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Phone 2</th>
                        <th>Address</th>
                        <th>Address 2</th>
                        <th>City</th>
                        <th>District</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';

// Create table rows from the selected data
while ($row = $customerDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
    $output .= '<tr>' .
        '<td>' . htmlspecialchars($row['customerID']) . '</td>' .
        '<td>' . htmlspecialchars($row['fullName']) . '</td>' .
        '<td>' . htmlspecialchars($row['email']) . '</td>' .
        '<td>' . htmlspecialchars($row['mobile']) . '</td>' .
        '<td>' . htmlspecialchars($row['phone2']) . '</td>' .
        '<td>' . htmlspecialchars($row['address']) . '</td>' .
        '<td>' . htmlspecialchars($row['address2']) . '</td>' .
        '<td>' . htmlspecialchars($row['city']) . '</td>' .
        '<td>' . htmlspecialchars($row['district']) . '</td>' .
        '<td>' . htmlspecialchars($row['status']) . '</td>' .
        '</tr>';
}

$customerDetailsSearchStatement->closeCursor();

$output .= '</tbody>
                <tfoot>
                    <tr>
                        <th>Customer ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Phone 2</th>
                        <th>Address</th>
                        <th>Address 2</th>
                        <th>City</th>
                        <th>District</th>
                        <th>Status</th>
                    </tr>
                </tfoot>
            </table>';

// Search input for autocomplete
$output .= '
<div class="mt-3" style="max-width: 600px; margin: auto;">
    <h5>Search Customer Name</h5>
    <input type="text" id="customerSearch" class="form-control" placeholder="Type customer name...">
    <ul id="suggestions" class="list-group mt-2"></ul>
</div>';

// Include jQuery for AJAX functionality
$output .= '
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Autocomplete functionality
$(document).ready(function() {
    $("#customerSearch").on("input", function() {
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
                        suggestions.forEach(function(customer) {
                            suggestionsList.append(`<li class="list-group-item suggestion-item">${customer}</li>`);
                        });
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
        $("#customerSearch").val($(this).text());
        $("#suggestions").empty();
    });
});
</script>';

echo $output;
?>