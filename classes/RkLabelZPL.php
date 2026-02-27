<?php
/**
 * ZPL Label Generator
 * For Zebra and compatible thermal printers
 */

class RkLabelZPL
{
    protected $width;   // Label width in mm
    protected $height;  // Label height in mm
    protected $dpi = 203;  // Dots per inch (203 dpi = 8 dots/mm)
    
    protected $output = '';

    public function __construct($width = 100, $height = 60)
    {
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Convert mm to dots
     */
    protected function mmToDots($mm)
    {
        return round($mm * 8);  // 203 dpi ≈ 8 dots/mm
    }

    /**
     * Generate label
     */
    public function generateLabel($label)
    {
        $recipient = $label['recipient'];
        
        $this->output = '';
        
        // Start label
        $this->output .= "^XA\n";  // Start format
        
        // Label dimensions
        $this->output .= "^PW" . $this->mmToDots($this->width) . "\n";  // Print width
        $this->output .= "^LL" . $this->mmToDots($this->height) . "\n"; // Label length
        
        $y = 20;  // Starting Y position in dots
        
        // Sender (if configured)
        if (isset($label['sender']) && !empty($label['sender']['name'])) {
            // Sender font (smaller)
            $this->output .= "^FO20," . $y . "^A0N,20,20^FDRte: " . $this->sanitize($label['sender']['name']) . "^FS\n";
            $y += 25;
            
            if (!empty($label['sender']['address'])) {
                $this->output .= "^FO20," . $y . "^A0N,20,20^FD" . $this->sanitize($label['sender']['address']) . "^FS\n";
                $y += 25;
            }
            
            if (!empty($label['sender']['postcode']) || !empty($label['sender']['city'])) {
                $cityLine = trim($label['sender']['postcode'] . ' ' . $label['sender']['city']);
                $this->output .= "^FO20," . $y . "^A0N,20,20^FD" . $this->sanitize($cityLine) . "^FS\n";
                $y += 25;
            }
            
            // Separator line
            $y += 10;
            $this->output .= "^FO20," . $y . "^GB" . ($this->mmToDots($this->width) - 40) . ",2,2^FS\n";
            $y += 20;
        }
        
        // Recipient
        // Company (if exists)
        if (!empty($recipient['company'])) {
            $this->output .= "^FO20," . $y . "^A0N,35,35^FD" . $this->sanitize($recipient['company']) . "^FS\n";
            $y += 40;
        }
        
        // Name (larger, bold)
        $this->output .= "^FO20," . $y . "^A0N,40,40^FD" . $this->sanitize($recipient['name']) . "^FS\n";
        $y += 50;
        
        // Address
        if (!empty($recipient['address1'])) {
            $this->output .= "^FO20," . $y . "^A0N,28,28^FD" . $this->sanitize($recipient['address1']) . "^FS\n";
            $y += 35;
        }
        if (!empty($recipient['address2'])) {
            $this->output .= "^FO20," . $y . "^A0N,28,28^FD" . $this->sanitize($recipient['address2']) . "^FS\n";
            $y += 35;
        }
        
        // Postcode + City (larger)
        $y += 10;
        $cityLine = trim($recipient['postcode'] . ' ' . $recipient['city']);
        $this->output .= "^FO20," . $y . "^A0N,45,45^FD" . $this->sanitize($cityLine) . "^FS\n";
        $y += 55;
        
        // State/Province
        if (!empty($recipient['state'])) {
            $this->output .= "^FO20," . $y . "^A0N,28,28^FD(" . $this->sanitize($recipient['state']) . ")^FS\n";
            $y += 35;
        }
        
        // Country (if not Spain)
        if (!empty($recipient['country']) && !in_array(strtolower($recipient['country']), ['spain', 'españa', 'es'])) {
            $this->output .= "^FO20," . $y . "^A0N,40,40^FD" . strtoupper($this->sanitize($recipient['country'])) . "^FS\n";
        }
        
        // Order reference (bottom right)
        $refY = $this->mmToDots($this->height) - 40;
        $this->output .= "^FO" . ($this->mmToDots($this->width) - 200) . "," . $refY . "^A0N,18,18^FDRef: " . $label['order_reference'] . "^FS\n";
        
        // End label
        $this->output .= "^XZ\n";  // End format
        
        return $this->output;
    }

    /**
     * Generate batch labels
     */
    public function generateBatchLabels($labels)
    {
        $output = '';
        foreach ($labels as $label) {
            $output .= $this->generateLabel($label);
        }
        return $output;
    }

    /**
     * Sanitize text for ZPL
     */
    protected function sanitize($text)
    {
        // Convert to ASCII
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Escape special ZPL characters
        $text = str_replace('^', '', $text);
        $text = str_replace('~', '', $text);
        
        return $text;
    }

    /**
     * Get raw output
     */
    public function getOutput()
    {
        return $this->output;
    }
}
