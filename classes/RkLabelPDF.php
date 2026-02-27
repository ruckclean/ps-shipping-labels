<?php
/**
 * PDF Label Generator
 * Uses TCPDF for generating shipping labels
 */

require_once _PS_TOOL_DIR_ . 'tcpdf/tcpdf.php';

class RkLabelPDF extends TCPDF
{
    protected $labelWidth;
    protected $labelHeight;
    protected $fontSize;

    public function __construct($width = 100, $height = 60, $fontSize = 12)
    {
        // Convert mm to inches for page size
        $this->labelWidth = $width;
        $this->labelHeight = $height;
        $this->fontSize = $fontSize;

        parent::__construct('P', 'mm', [$width, $height], true, 'UTF-8', false);

        $this->SetCreator('Ruckclean Shipping Labels');
        $this->SetAuthor('Ruckclean');
        $this->SetTitle('Shipping Label');

        // Remove margins for label
        $this->SetMargins(3, 3, 3);
        $this->SetAutoPageBreak(false, 0);
        
        // Disable header and footer
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
    }

    /**
     * Generate a single label
     */
    public function generateLabel($label)
    {
        $this->AddPage();
        $this->renderLabel($label);
    }

    /**
     * Generate multiple labels
     */
    public function generateBatchLabels($labels)
    {
        foreach ($labels as $label) {
            $this->AddPage();
            $this->renderLabel($label);
        }
    }

    /**
     * Render label content
     */
    protected function renderLabel($label)
    {
        $recipient = $label['recipient'];
        $y = 5;

        // Sender (if configured)
        if (isset($label['sender']) && !empty($label['sender']['name'])) {
            $this->SetFont('helvetica', '', $this->fontSize - 3);
            $this->SetXY(3, $y);
            
            $senderText = "Rte: " . $label['sender']['name'];
            if (!empty($label['sender']['address'])) {
                $senderText .= "\n" . $label['sender']['address'];
            }
            if (!empty($label['sender']['postcode']) || !empty($label['sender']['city'])) {
                $senderText .= "\n" . trim($label['sender']['postcode'] . ' ' . $label['sender']['city']);
            }
            
            $this->MultiCell($this->labelWidth - 6, 4, $senderText, 0, 'L', false, 1);
            
            // Separator line
            $y = $this->GetY() + 2;
            $this->Line(3, $y, $this->labelWidth - 3, $y);
            $y += 3;
        }

        // Recipient
        $this->SetFont('helvetica', 'B', $this->fontSize);
        $this->SetXY(3, $y);
        
        // Name
        $name = $recipient['name'];
        if (!empty($recipient['company'])) {
            $name = $recipient['company'] . "\n" . $name;
        }
        $this->MultiCell($this->labelWidth - 6, 5, $name, 0, 'L', false, 1);
        
        // Address
        $this->SetFont('helvetica', '', $this->fontSize);
        $y = $this->GetY() + 1;
        $this->SetXY(3, $y);
        
        $addressLines = [];
        if (!empty($recipient['address1'])) {
            $addressLines[] = $recipient['address1'];
        }
        if (!empty($recipient['address2'])) {
            $addressLines[] = $recipient['address2'];
        }
        
        $this->MultiCell($this->labelWidth - 6, 5, implode("\n", $addressLines), 0, 'L', false, 1);
        
        // City line (postcode + city)
        $y = $this->GetY() + 1;
        $this->SetXY(3, $y);
        $this->SetFont('helvetica', 'B', $this->fontSize + 1);
        
        $cityLine = trim($recipient['postcode'] . ' ' . $recipient['city']);
        if (!empty($recipient['state'])) {
            $cityLine .= ' (' . $recipient['state'] . ')';
        }
        $this->Cell($this->labelWidth - 6, 6, $cityLine, 0, 1, 'L');
        
        // Country (if not Spain)
        if (!empty($recipient['country']) && !in_array(strtolower($recipient['country']), ['spain', 'españa', 'es'])) {
            $y = $this->GetY();
            $this->SetXY(3, $y);
            $this->SetFont('helvetica', 'B', $this->fontSize);
            $this->Cell($this->labelWidth - 6, 5, strtoupper($recipient['country']), 0, 1, 'L');
        }
        
        // Order reference (small, bottom right)
        $this->SetFont('helvetica', '', $this->fontSize - 4);
        $this->SetXY(3, $this->labelHeight - 8);
        $this->Cell($this->labelWidth - 6, 4, 'Ref: ' . $label['order_reference'], 0, 0, 'R');
    }

    /**
     * Output the PDF
     */
    public function output($filename = 'label.pdf', $dest = 'D')
    {
        parent::Output($filename, $dest);
    }
}
