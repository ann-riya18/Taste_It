<?php
// weekly_grocery.php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

// ---- Week selection (next week available from Sunday) ----
$dayOfWeek = date('w'); // 0 = Sunday, 6 = Saturday
$currentWeek = ceil(date('j') / 7);
if ($dayOfWeek == 0) { // if today is Sunday, move to next week
    $selected_week = $currentWeek + 1;
    if ($selected_week > 4) {
        $selected_week = 1; // reset to week 1 of next month
        $month++;
        if ($month > 12) {
            $month = 1;
            $year++;
        }
    }
} else {
    $selected_week = $currentWeek;
}

$planner_type = isset($_GET['type']) ? $_GET['type'] : 'what_to_cook';

// Create session key based on planner type
$session_key = "meal_plan_{$planner_type}_{$year}_{$month}_week{$selected_week}";

// Check if meal plan exists for the specified planner type
if (!isset($_SESSION[$session_key])) {
    $alt_planner_type = ($planner_type === 'what_to_cook') ? 'health' : 'what_to_cook';
    $alt_session_key = "meal_plan_{$alt_planner_type}_{$year}_{$month}_week{$selected_week}";
    
    if (isset($_SESSION[$alt_session_key])) {
        $session_key = $alt_session_key;
        $planner_type = $alt_planner_type;
    } else {
        header('Location: what_to_cook.php?week=' . $selected_week . '&month=' . $month . '&year=' . $year);
        exit;
    }
}

$meal_plan = $_SESSION[$session_key];
$ingredients_list = [];

// Function to clean ingredient names
function cleanIngredient($ingredient) {
    $ingredient = strtolower(trim($ingredient));

    // Remove anything inside brackets
    $ingredient = preg_replace('/\([^)]*\)/', '', $ingredient);

    // Split by commas and dashes (to avoid "chickpea - 1 overnight")
    $parts = preg_split('/[,-]/', $ingredient);
    $cleaned = [];

    foreach ($parts as $part) {
        if (empty(trim($part))) continue;

        $part = trim($part);

        // Remove unicode fractions
        $part = preg_replace('/[¼½¾⅓⅔⅛⅜⅝⅞]/u', '', $part);

        // Remove quantities and units
        $part = preg_replace('/\b\d+(?:\.\d+)?\s*(kg|g|gram|grams|ml|l|litre|litres|oz|ounce|ounces|lb|lbs|pound|pounds|cup|cups|tsp|tbsp|teaspoon|tablespoon|clove|cloves|piece|pieces|slice|slices)\b/i', '', $part);
        $part = preg_replace('/^\d+\s*(?:[\/\-]\s*\d+)?\s*/', '', $part);

        // Remove preparation and vague terms
        $part = preg_replace('/\b(small|medium|large|chopped|sliced|diced|minced|soaked|fried|boiled|roasted|toasted|peeled|crushed|shredded|fresh|ground|unsalted|salted|powdered|grated|ripe|to\s*taste|overnight|few|some|pinch|dash)\b/i', '', $part);

        // Collapse spaces
        $part = preg_replace('/\s+/', ' ', $part);

        // Capitalize
        $part = ucfirst(trim($part));

        // Skip invalids
        if (!empty($part) && strtolower($part) !== 'water') {
            $cleaned[] = $part;
        }
    }

    return $cleaned;
}

// Extract ingredients from recipes
foreach ($meal_plan as $day => $meals) {
    foreach ($meals as $course => $recipe) {
        if ($recipe['id'] > 0) {
            $ingredients = explode("\n", trim($recipe['ingredients']));
            foreach ($ingredients as $ingredient) {
                $ingredient = trim($ingredient);
                if (!empty($ingredient)) {
                    $cleaned_items = cleanIngredient($ingredient);
                    foreach ($cleaned_items as $ci) {
                        $ingredients_list[] = $ci;
                    }
                }
            }
        }
    }
}

// Remove duplicates and sort alphabetically
$ingredients_list = array_unique($ingredients_list);
sort($ingredients_list);

// Generate PDF
require_once('tcpdf/tcpdf.php');
$pdf = new TCPDF();
$pdf->AddPage();

// Title
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 15, 'GROCERY LIST', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, date('F', mktime(0, 0, 0, $month, 1)) . ' - Week ' . $selected_week, 0, 1, 'C');
$pdf->Cell(0, 10, 'Planner: ' . ucfirst($planner_type), 0, 1, 'C');
$pdf->Ln(10);

// Ingredients heading
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Ingredients:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

// Split into two columns
$half = ceil(count($ingredients_list) / 2);
$col1 = array_slice($ingredients_list, 0, $half);
$col2 = array_slice($ingredients_list, $half);
$rows = max(count($col1), count($col2));
$lineHeight = 8;

// Print in two neat columns
for ($i = 0; $i < $rows; $i++) {
    $col1Text = isset($col1[$i]) ? '• ' . $col1[$i] : '';
    $col2Text = isset($col2[$i]) ? '• ' . $col2[$i] : '';
    $pdf->Cell(90, $lineHeight, $col1Text, 0, 0, 'L');
    $pdf->Cell(90, $lineHeight, $col2Text, 0, 1, 'L');
}

// PDF headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="grocery_list.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output PDF
$pdf->Output('grocery_list.pdf', 'D');
exit;
?>
