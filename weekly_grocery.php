<?php
// weekly_grocery.php
session_start();
// IMPORTANT: Ensure tcpdf.php path is correct for your setup
require_once('tcpdf/tcpdf.php'); 
include 'db.php'; 

// =========================================================
// 1. Date Helpers and Session Key Determination
// (Replicated from 'what_to_cook.php' to handle the 'week' offset)
// =========================================================
$current_timestamp = time();

function get_start_monday($offset = 0) {
    global $current_timestamp;
    $base_monday_timestamp = strtotime('monday this week', $current_timestamp);
    
    if ($offset === 0) {
        return date('Y-m-d', $base_monday_timestamp);
    }
    return date('Y-m-d', strtotime('+1 week', $base_monday_timestamp));
}

// Determine plan based on URL parameter 'plan' (e.g., ?plan=healthy or ?plan=cook)
$plan_type = $_GET['plan'] ?? '';
$is_healthy_plan = (strtolower($plan_type) === 'healthy');

// Determine the week offset (0 or 1)
$selected_week_offset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$start_monday_date = get_start_monday($selected_week_offset);

// Construct the session key based on the plan type
if ($is_healthy_plan) {
    // Session key structure from 'health_meal.php' (assuming it uses this date format)
    $session_key = "meal_plan_health_{$start_monday_date}";
    $plan_title = "Healthy Meal Plan";
    $filename = "Healthy_Weekly_Grocery.pdf";
} else {
    // Session key structure from 'what_to_cook.php'
    $session_key = "meal_plan_week_{$start_monday_date}";
    $plan_title = "What to Cook Plan";
    $filename = "What_To_Cook_Weekly_Grocery.pdf";
}

// Load the meal plan from the session
$meal_plan = $_SESSION[$session_key] ?? [];
$week_description = date('M j, Y', strtotime($start_monday_date)) . ' - ' . 
                    date('M j, Y', strtotime($start_monday_date . ' +6 days'));

if (empty($meal_plan)) {
    // Display an error or redirect if the session plan is missing
    die("Error: The {$plan_title} for the selected week could not be found in your session. Please generate the plan first.");
}

// =========================================================
// 2. Ingredient Aggregation Logic (Unchanged)
// =========================================================

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

    if (empty($recipe_ids)) {
        return ["No recipes found in the plan to generate a list."];
    }
    
    $unique_ids = array_unique($recipe_ids);
    // Prepared statement is safer, but for dynamic IN list, this is a common approach if IDs are confirmed to be integers.
    $id_list = implode(',', $unique_ids);

    $sql = "SELECT title, ingredients FROM recipes WHERE id IN ($id_list)";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ingredient_lines = explode("\n", $row['ingredients']);
            foreach ($ingredient_lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    if (!in_array($line, $required_ingredients)) {
                        $required_ingredients[] = $line;
                    }
                }
            }
        }
    }
    
    sort($required_ingredients);
    return $required_ingredients;
}

$grocery_list = aggregate_ingredients($meal_plan, $conn);

// =========================================================
// 3. TCPDF Generation Class
// =========================================================

class GroceryPDF extends TCPDF {
    protected $PlanTitle;
    protected $WeekDescription;

    public function __construct($orientation='P', $unit='mm', $format='A4', $planTitle, $weekDescription) {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);
        $this->PlanTitle = $planTitle;
        $this->WeekDescription = $weekDescription;
        $this->SetAuthor('TasteIt Planner');
        $this->SetTitle($planTitle . ' Grocery List');
        $this->SetSubject('Weekly Meal Plan and Grocery List');
        $this->SetKeywords('grocery, meal plan, weekly');
        
        // Remove default header/footer
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
        
        // Set margins
        $this->SetMargins(15, 30, 15);
        $this->SetHeaderMargin(10);
        $this->SetFooterMargin(10);
        
        // Set auto page breaks
        $this->SetAutoPageBreak(TRUE, 25);
        
        // Set font
        $this->SetFont('helvetica', '', 10);
    }
    
    // Page header
    public function Header() {
        // TCPDF primary color (R, G, B) equivalent to #B0C364
        $primary_rgb = [176, 195, 100]; 

        // Set background color for header strip
        $this->SetFillColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $this->Rect(0, 0, $this->getPageWidth(), 20, 'F');
        
        // Title
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, $this->PlanTitle . ' - Weekly Planner', 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        
        // Subtitle
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, $this->WeekDescription, 0, 1, 'C', 0, '', 0, false, 'T', 'M');

        // Line break
        $this->Ln(5);
    }

    // Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    // Custom Section Title
    public function SectionTitle($title) {
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(40, 40, 40);
        $this->SetFillColor(230, 240, 200); // Light background
        $this->Cell(0, 7, $title, 0, 1, 'L', true);
        $this->Ln(3);
    }

    // Print the aggregated grocery list
    public function PrintGroceryList($items) {
        $this->SectionTitle('🛒 Weekly Grocery List');
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        // Print items with a checkbox placeholder (using a wingding-like symbol for box)
        foreach ($items as $item) {
            // Using a simple square bracket text for checkbox, or use a Unicode character:
            $this->Cell(8, 7, '[ ]', 0, 0, 'L'); 
            $this->MultiCell(0, 7, htmlspecialchars($item), 0, 'L', false, 1, '', '', true, 0, false, true, 7, 'T');
        }
        $this->Ln(5);
    }

    // Print the weekly meal plan schedule
    public function PrintMealPlan($meal_plan) {
        $this->SectionTitle('🗓️ Weekly Meal Schedule');
        
        // Set up the table headers
        $this->SetFont('helvetica', 'B', 9);
        $this->SetFillColor(240, 240, 240);
        $widths = [20, 43, 43, 43, 41]; // Day, B, L, S, D columns
        $header = ['Day', 'Breakfast', 'Lunch', 'Snacks', 'Dinner'];
        
        $this->SetX(15);
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($widths[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        // Populate the table rows
        $this->SetFont('helvetica', '', 8);
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $courses = ['Breakfast', 'Lunch', 'Snacks', 'Dinner'];
        
        $day_index = 0;
        foreach ($meal_plan as $day_date => $meals) {
            $day_name = $days[$day_index % 7];
            $day_index++;

            $current_y = $this->GetY();
            
            // Collect titles and estimate row height (using a rough max line count)
            $row_data = [];
            $max_lines = 1;
            foreach ($courses as $course) {
                $title = $meals[$course]['title'] ?? 'N/A';
                $row_data[] = $title;
                // Rough estimate: how many lines will the longest title take in its cell width?
                $max_lines = max($max_lines, ceil($this->GetStringWidth($title) / $widths[array_search($course, $courses) + 1] * 2));
            }
            
            $cell_height = $max_lines * 4; // Base height of 4mm per line

            // Print Day Cell (fixed height)
            $this->MultiCell($widths[0], $cell_height, $day_name . "\n" . date('M j', strtotime($day_date)), 1, 'C', false, 0, $this->GetX(), $current_y, true, 0, false, true, 0, 'T');
            
            // Print Meal Cells
            $this->SetX($this->GetX() + $widths[0]);
            for ($i = 0; $i < count($row_data); $i++) {
                $this->MultiCell($widths[$i+1], $cell_height, $row_data[$i], 'R', 'L', false, 0, $this->GetX(), $current_y, true, 0, false, true, 0, 'T');
                if ($i < count($row_data) - 1) {
                    $this->SetX($this->GetX() + $widths[$i+1]);
                }
            }
            // Draw bottom border to complete the row
            $this->SetY($current_y + $cell_height);
            $this->SetX(15);
            $this->Cell(array_sum($widths), 0, '', 'T', 1);
        }
        $this->Ln(5);
    }
}

// -----------------------------------------------------------
// 4. Execution
// -----------------------------------------------------------

// Instantiate TCPDF
$pdf = new GroceryPDF('P', 'mm', 'A4', $plan_title, $week_description);

// Add a page
$pdf->AddPage();

// Print Meal Plan
$pdf->PrintMealPlan($meal_plan);

// Print Grocery List
$pdf->PrintGroceryList($grocery_list);

// Close and output PDF document
$pdf->Output($filename, 'I'); // 'I' sends the file inline to the browser

exit;
?>