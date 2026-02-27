<?php
/**
 * ESC/POS Label Generator
 * For thermal printers that support ESC/POS commands
 */

class RkLabelESCPOS
{
    // ESC/POS Commands
    const ESC = "\x1B";
    const GS = "\x1D";
    const LF = "\x0A";
    const CR = "\x0D";
    
    // Initialize printer
    const INIT = "\x1B\x40";
    
    // Text formatting
    const BOLD_ON = "\x1B\x45\x01";
    const BOLD_OFF = "\x1B\x45\x00";
    const DOUBLE_WIDTH = "\x1D\x21\x10";
    const DOUBLE_HEIGHT = "\x1D\x21\x01";
    const NORMAL_SIZE = "\x1D\x21\x00";
    const ALIGN_LEFT = "\x1B\x61\x00";
    const ALIGN_CENTER = "\x1B\x61\x01";
    const ALIGN_RIGHT = "\x1B\x61\x02";
    
    // Cut paper
    const CUT = "\x1D\x56\x00";
    const PARTIAL_CUT = "\x1D\x56\x01";
    
    // Feed
    const FEED_LINES = "\x1B\x64";

    protected $output = '';

    /**
     * Generate label data
     */
    public function generateLabel($label)
    {
        $this->output = '';
        $recipient = $label['recipient'];
        
        // Initialize
        $this->output .= self::INIT;
        
        // Sender (if configured)
        if (isset($label['sender']) && !empty($label['sender']['name'])) {
            $this->output .= self::ALIGN_LEFT;
            $this->output .= self::NORMAL_SIZE;
            $this->output .= "Rte: " . $this->sanitize($label['sender']['name']) . self::LF;
            
            if (!empty($label['sender']['address'])) {
                $this->output .= $this->sanitize($label['sender']['address']) . self::LF;
            }
            if (!empty($label['sender']['postcode']) || !empty($label['sender']['city'])) {
                $this->output .= $this->sanitize(trim($label['sender']['postcode'] . ' ' . $label['sender']['city'])) . self::LF;
            }
            
            // Separator
            $this->output .= "--------------------------------" . self::LF;
            $this->output .= self::LF;
        }
        
        // Recipient name (bold, larger)
        $this->output .= self::ALIGN_LEFT;
        $this->output .= self::BOLD_ON;
        $this->output .= self::DOUBLE_HEIGHT;
        
        if (!empty($recipient['company'])) {
            $this->output .= $this->sanitize($recipient['company']) . self::LF;
        }
        $this->output .= $this->sanitize($recipient['name']) . self::LF;
        
        // Address (normal)
        $this->output .= self::BOLD_OFF;
        $this->output .= self::NORMAL_SIZE;
        
        if (!empty($recipient['address1'])) {
            $this->output .= $this->sanitize($recipient['address1']) . self::LF;
        }
        if (!empty($recipient['address2'])) {
            $this->output .= $this->sanitize($recipient['address2']) . self::LF;
        }
        
        // Postcode + City (bold)
        $this->output .= self::LF;
        $this->output .= self::BOLD_ON;
        $this->output .= self::DOUBLE_WIDTH;
        
        $cityLine = trim($recipient['postcode'] . ' ' . $recipient['city']);
        $this->output .= $this->sanitize($cityLine) . self::LF;
        
        // State/Province
        $this->output .= self::NORMAL_SIZE;
        if (!empty($recipient['state'])) {
            $this->output .= "(" . $this->sanitize($recipient['state']) . ")" . self::LF;
        }
        
        // Country (if not Spain)
        if (!empty($recipient['country']) && !in_array(strtolower($recipient['country']), ['spain', 'españa', 'es'])) {
            $this->output .= self::DOUBLE_HEIGHT;
            $this->output .= strtoupper($this->sanitize($recipient['country'])) . self::LF;
        }
        
        // Order reference
        $this->output .= self::BOLD_OFF;
        $this->output .= self::NORMAL_SIZE;
        $this->output .= self::LF;
        $this->output .= self::ALIGN_RIGHT;
        $this->output .= "Ref: " . $label['order_reference'] . self::LF;
        
        // Feed and cut
        $this->output .= self::FEED_LINES . "\x05"; // Feed 5 lines
        $this->output .= self::PARTIAL_CUT;
        
        return $this->output;
    }

    /**
     * Sanitize text for ESC/POS
     * Convert special characters
     */
    protected function sanitize($text)
    {
        // Convert to ASCII-compatible
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Remove any remaining non-printable characters
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        
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
