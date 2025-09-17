<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Login required.");
}

$user_id = $_SESSION['user_id'];
$week_number = date("W");

// Check if already downloaded
$check = $conn->prepare("SELECT * FROM grocery_downloads WHERE user_id=? AND week_number=?");
$check->bind_param("ii", $user_id, $week_number);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "<div class='alert alert-warning text-center m-3'>
            <strong>Note:</strong> You have already downloaded this week’s grocery list.
          </div>";
} else {
    // ✅ Generate Grocery List (simple example, pull ingredients from recipes)
    header("Content-type: application/pdf");
    header("Content-Disposition: attachment; filename=grocery_week_$week_number.pdf");

    // Minimal PDF generation
    require('fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(40,10,"Grocery List - Week $week_number");

    $ingredients = [];
    $sql = "SELECT r.ingredients 
            FROM meal_plans m 
            JOIN recipes r ON m.recipe_id = r.id 
            WHERE m.user_id=$user_id AND m.week_number=$week_number";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $items = explode(",", $row['ingredients']);
        foreach ($items as $item) {
            $ingredients[] = trim($item);
        }
    }
    $ingredients = array_unique($ingredients);

    $pdf->Ln(20);
    $pdf->SetFont('Arial','',12);
    foreach ($ingredients as $ing) {
        $pdf->Cell(0,10,"- $ing",0,1);
    }
    $pdf->Output();

    // Mark as downloaded
    $insert = $conn->prepare("INSERT INTO grocery_downloads (user_id, week_number) VALUES (?, ?)");
    $insert->bind_param("ii", $user_id, $week_number);
    $insert->execute();
}
?>

