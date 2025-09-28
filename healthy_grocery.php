<?php
// healthy_grocery.php
session_start();
// IMPORTANT: Ensure paths are correct
require_once('tcpdf/tcpdf.php'); 
require_once('GroceryPDF.php'); 
include 'db.php'; 

// =========================================================
// 1. Setup & Session Key (Dedicated to HEALTHY)
// =========================================================
$current_timestamp = time();

function get_start_monday($offset = 0) {
    global $current_timestamp;
    $base_monday_timestamp = strtotime('monday this week', $current_timestamp);
    if ($offset === 0) return date('Y-m-d', $base_monday_timestamp);
    return date('Y-m-d', strtotime('+1 week', $base_monday_timestamp));
}

$selected_week_offset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$start_monday_date = get_start_monday($selected_week_offset);

$session_key = "meal_plan_health_{$start_monday_date}";
$plan_title = "Healthy Meal Plan";
$filename = "Healthy_Weekly_Grocery.pdf";

$meal_plan = $_SESSION[$session_key] ?? [];
$week_description = date('M j, Y', strtotime($start_monday_date)) . ' - ' . 
                    date('M j, Y', strtotime($start_monday_date . ' +6 days'));

if (empty($meal_plan)) {
    die("Error: The {$plan_title} for the selected week could not be found. Please generate the plan first.");
}

// =========================================================
// 2. Ingredient Cleansing Logic (With improved deduplication)
// =========================================================

function cleanse_ingredient_line($line) {
    // 1. Convert to lowercase and trim
    $line = trim(strtolower($line));
    
    // 2. Define patterns for removal
    $patterns = [
        '/\b\d+(\s*\/\s*\d+)?(\-\s*\d+)?\b/', // Numbers, dashes, fractions
        '/\b(cups?|teaspoons?|tbsps?|ounces?|oz|grams?|g|pounds?|lbs?|ml|liters?|pinches?|dashes?|sprigs?|clo(ves?)|can(s?)|pkg|slices?|jars?|bottles?)\b/', // Units
        '/\b(crushed|chopped|melted|diced|finely|fresh|dried|small|large|medium|sweet|ground|whole|peeled|grated|sliced|cubed|minced|toasted|warm|cold|softened|cooked|uncooked|plain|shredded|packed|loosely|optional|boneless|skinless|reduced|low|zest|kosher|sea|flaky|table|black)\b/', // Prep words
        '/(^of\s|\swith\s|\sfor\s|\sand\s|\sfrom\s|\s|\(|\)|\-|\/|\\|:|\.)/', // Common fillers
        '/\bwater\b/', 
    ];
    
    // 3. Apply removals
    $line = preg_replace($patterns, ' ', $line);
    
    // 4. Remove extra spaces, trim
    $line = preg_replace('/\s+/', ' ', $line);
    $line = trim($line);

    // 5. Aggressive final filtering for common items (forces standardization for deduplication)
    $final_filter_list = ['salt', 'pepper', 'oil', 'sugar', 'butter', 'flour', 'milk', 'egg', 'eggs', 'vinegar', 'broth', 'stock'];
    
    if (in_array($line, $final_filter_list) || str_starts_with($line, 'olive oil')) {
        if (str_contains($line, 'salt')) return 'Salt';
        if (str_contains($line, 'pepper')) return 'Pepper';
        if (str_contains($line, 'oil')) return 'Oil (Cooking)';
        if (str_contains($line, 'sugar')) return 'Sugar';
    }
    
    return !empty($line) ? ucfirst($line) : '';
}


function aggregate_ingredients($meal_plan, $conn) {
    $required_ingredients = [];
    $recipe_ids = [];

    foreach ($meal_plan as $day_meals) {
        foreach ($day_meals as $course_data) {
            if (!empty($course_data['id']) && (int)$course_data['id'] > 0) {
                $recipe_ids[] = (int)$course_data['id'];
            }
        }
    }

    if (empty($recipe_ids)) return ["No recipes found to generate a list."];
    
    $unique_ids = array_unique($recipe_ids);
    $id_list = implode(',', $unique_ids);

    $sql = "SELECT ingredients FROM recipes WHERE id IN ($id_list)";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ingredient_lines = explode("\n", $row['ingredients']);
            foreach ($ingredient_lines as $line) {
                $cleansed_name = cleanse_ingredient_line($line);
                
                if (!empty($cleansed_name) && !in_array($cleansed_name, $required_ingredients)) {
                    $required_ingredients[] = $cleansed_name;
                }
            }
        }
    }
    
    sort($required_ingredients);
    return $required_ingredients;
}

$grocery_list = aggregate_ingredients($meal_plan, $conn);

// =========================================================
// 3. PDF Generation Execution
// =========================================================

$pdf = new GroceryPDF('P', 'mm', 'A4', $plan_title, $week_description);
$pdf->AddPage();
$pdf->PrintGroceryList($grocery_list);
$pdf->Output($filename, 'I'); 

exit;