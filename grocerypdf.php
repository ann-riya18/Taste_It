<?php
// GroceryPDF.php
// Custom TCPDF class for generating the styled, bulleted grocery list.

class GroceryPDF extends TCPDF {
    protected $PlanTitle;
    protected $WeekDescription;
    
    // TCPDF primary color (R, G, B) equivalent to #B0C364
    const PRIMARY_RGB = [176, 195, 100]; 

    public function __construct($orientation='P', $unit='mm', $format='A4', $planTitle, $weekDescription) {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);
        
        $this->PlanTitle = $planTitle;
        $this->WeekDescription = $weekDescription;
        
        $this->SetAuthor('TasteIt Planner');
        $this->SetTitle($planTitle . ' Grocery List');
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
        $this->SetMargins(15, 30, 15);
        $this->SetHeaderMargin(10);
        $this->SetFooterMargin(10);
        $this->SetAutoPageBreak(TRUE, 25);
        $this->SetFont('helvetica', '', 10);
    }
    
    public function Header() {
        $this->SetFillColor(self::PRIMARY_RGB[0], self::PRIMARY_RGB[1], self::PRIMARY_RGB[2]);
        $this->Rect(0, 0, $this->getPageWidth(), 20, 'F');
        
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, $this->PlanTitle . ' - Weekly Grocery List', 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, $this->WeekDescription, 0, 1, 'C', 0, '', 0, false, 'T', 'M');

        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    public function SectionTitle($title) {
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(40, 40, 40);
        $this->SetFillColor(230, 240, 200);
        $this->Cell(0, 7, $title, 0, 1, 'L', true);
        $this->Ln(3);
    }

    // *** UPDATED METHOD WITH BULLET POINTS (•) ***
    public function PrintGroceryList($items) {
        $this->SectionTitle('🛒 Required Ingredients');
        $this->SetFont('helvetica', '', 11);
        $this->SetTextColor(0, 0, 0);
        
        $total_items = count($items);
        $cols = 3; 
        $items_per_col = ceil($total_items / $cols);
        $col_width = (210 - 30) / $cols; 
        $indent = 15;
        $bullet_width = 5; // Space for the bullet point
        $item_width = $col_width - $bullet_width;

        $start_y = $this->GetY();
        
        for ($c = 0; $c < $cols; $c++) {
            $this->SetY($start_y);
            $this->SetX($indent + ($c * $col_width));

            $start_index = $c * $items_per_col;
            $end_index = min(($c + 1) * $items_per_col, $total_items);

            for ($i = $start_index; $i < $end_index; $i++) {
                $item = $items[$i];
                $current_x = $this->GetX();

                // Print the bullet point (Unicode U+2022)
                $this->Cell($bullet_width, 7, '•', 0, 0, 'L'); 
                
                // Print the cleansed ingredient name
                $this->MultiCell($item_width, 7, $item, 0, 'L', false, 1, $current_x + $bullet_width, $this->GetY(), true, 0, false, true, 7, 'M');
                
                // Reset X position for the next bullet point in the column
                $this->SetX($indent + ($c * $col_width)); 
            }
        }
        $this->Ln(5);
    }
}