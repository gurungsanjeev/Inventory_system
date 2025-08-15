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
    $itemQuery = 'SELECT DISTINCT itemName FROM item';
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

$itemDetailsSearchSql = 'SELECT * FROM item';
$itemDetailsSearchStatement = $conn->prepare($itemDetailsSearchSql);
if ($itemDetailsSearchStatement === false) {
    echo json_encode(['error' => 'Query preparation failed']);
    exit;
}
if (!$itemDetailsSearchStatement->execute()) {
    echo json_encode(['error' => 'Query execution failed']);
    exit;
}

$output = '<table id="itemReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Item Number</th>
                        <th>Item Name</th>
                        <th>Discount %</th>
                        <th>Stock</th>
                        <th>Unit Price</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';

// Create table rows from the selected data
while ($row = $itemDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
    $output .= '<tr>' .
        '<td>' . htmlspecialchars($row['productID']) . '</td>' .
        '<td>' . htmlspecialchars($row['itemNumber']) . '</td>' .
        '<td><a href="#" class="itemDetailsHover" data-toggle="popover" id="' . htmlspecialchars($row['productID']) . '">' . htmlspecialchars($row['itemName']) . '</a></td>' .
        '<td>' . htmlspecialchars($row['discount']) . '</td>' .
        '<td>' . htmlspecialchars($row['stock']) . '</td>' .
        '<td>' . htmlspecialchars($row['unitPrice']) . '</td>' .
        '<td>' . htmlspecialchars($row['status']) . '</td>' .
        '<td>' . htmlspecialchars($row['description']) . '</td>' .
        '</tr>';
}

$itemDetailsSearchStatement->closeCursor();

$output .= '</tbody>
                <tfoot>
                    <tr>
                        <th>Product ID</th>
                        <th>Item Number</th>
                        <th>Item Name</th>
                        <th>Discount %</th>
                        <th>Stock</th>
                        <th>Unit Price</th>
                        <th>Status</th>
                        <th>Description</th>
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

// Include jQuery for AJAX and popover functionality
$output .= '
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Autocomplete functionality
$(document).ready(function() {
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
                    } 
                },
                error: function(xhr, status, error) {
                    console.error("Autocomplete request failed:", error);
                   
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

    // Initialize popovers
    $(\'[data-toggle="popover"]\').popover();
});
</script>';

echo $output;
?>