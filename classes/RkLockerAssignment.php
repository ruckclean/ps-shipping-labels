<?php
/**
 * Locker Assignment Model
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class RkLockerAssignment extends ObjectModel
{
    public $id_assignment;
    public $id_locker;
    public $id_order;
    public $status = 'assigned';
    public $pin_code;
    public $pin_valid_from;
    public $pin_valid_until;
    public $date_assigned;
    public $date_ready;
    public $date_collected;
    public $date_upd;

    public static $definition = [
        'table' => 'rk_locker_assignment',
        'primary' => 'id_assignment',
        'fields' => [
            'id_locker' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 20],
            'pin_code' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32],
            'pin_valid_from' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'pin_valid_until' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_assigned' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_ready' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_collected' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Get assignment by order ID
     */
    public static function getByOrderId($idOrder)
    {
        $sql = "SELECT id_assignment 
                FROM " . _DB_PREFIX_ . "rk_locker_assignment 
                WHERE id_order = " . (int) $idOrder;
        
        $id = Db::getInstance()->getValue($sql);
        
        if ($id) {
            return new RkLockerAssignment($id);
        }
        
        return null;
    }

    /**
     * Get active assignment for a locker
     */
    public static function getActiveByLockerId($idLocker)
    {
        $sql = "SELECT id_assignment 
                FROM " . _DB_PREFIX_ . "rk_locker_assignment 
                WHERE id_locker = " . (int) $idLocker 
              . " AND status IN ('assigned', 'ready')";
        
        $id = Db::getInstance()->getValue($sql);
        
        if ($id) {
            return new RkLockerAssignment($id);
        }
        
        return null;
    }

    /**
     * Assign a locker to an order (round-robin)
     */
    public static function assignToOrder($idOrder)
    {
        // Check if already assigned
        $existing = self::getByOrderId($idOrder);
        if ($existing) {
            return $existing;
        }

        // Get next available locker
        $locker = RkLocker::getNextAvailable();
        if (!$locker) {
            return null; // No lockers available
        }

        // Create assignment
        $assignment = new RkLockerAssignment();
        $assignment->id_locker = $locker->id;
        $assignment->id_order = (int) $idOrder;
        $assignment->status = 'assigned';
        $assignment->date_assigned = date('Y-m-d H:i:s');
        $assignment->date_upd = date('Y-m-d H:i:s');
        
        if ($assignment->save()) {
            // Log the assignment
            RkLockerLog::add($locker->id, $idOrder, $assignment->id, 'assigned', [
                'locker_name' => $locker->name,
            ]);
            
            return $assignment;
        }
        
        return null;
    }

    /**
     * Mark locker as ready for pickup (keychain deposited)
     */
    public function markReady($pinCode = null, $validHours = 72)
    {
        $this->status = 'ready';
        $this->pin_code = $pinCode;
        $this->pin_valid_from = date('Y-m-d H:i:s');
        $this->pin_valid_until = date('Y-m-d H:i:s', strtotime("+{$validHours} hours"));
        $this->date_ready = date('Y-m-d H:i:s');
        $this->date_upd = date('Y-m-d H:i:s');
        
        if ($this->save()) {
            RkLockerLog::add($this->id_locker, $this->id_order, $this->id, 'ready', [
                'pin_code' => $pinCode ? '****' : null,
                'valid_until' => $this->pin_valid_until,
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Mark as collected (keychain picked up - frees the locker)
     */
    public function markCollected()
    {
        $this->status = 'collected';
        $this->date_collected = date('Y-m-d H:i:s');
        $this->date_upd = date('Y-m-d H:i:s');
        
        if ($this->save()) {
            RkLockerLog::add($this->id_locker, $this->id_order, $this->id, 'collected', []);
            return true;
        }
        
        return false;
    }

    /**
     * Cancel assignment (frees the locker)
     */
    public function cancel($reason = '')
    {
        $this->status = 'cancelled';
        $this->date_upd = date('Y-m-d H:i:s');
        
        if ($this->save()) {
            RkLockerLog::add($this->id_locker, $this->id_order, $this->id, 'cancelled', [
                'reason' => $reason,
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Get all assignments with details
     */
    public static function getAllWithDetails($status = null, $limit = 50)
    {
        $sql = "SELECT a.*, 
                       l.name as locker_name,
                       l.location as locker_location,
                       o.reference as order_reference,
                       o.total_paid,
                       CONCAT(c.firstname, ' ', c.lastname) as customer_name,
                       c.email as customer_email
                FROM " . _DB_PREFIX_ . "rk_locker_assignment a
                JOIN " . _DB_PREFIX_ . "rk_locker l ON a.id_locker = l.id_locker
                JOIN " . _DB_PREFIX_ . "orders o ON a.id_order = o.id_order
                JOIN " . _DB_PREFIX_ . "customer c ON o.id_customer = c.id_customer";
        
        if ($status) {
            $sql .= " WHERE a.status = '" . pSQL($status) . "'";
        }
        
        $sql .= " ORDER BY a.date_assigned DESC LIMIT " . (int) $limit;
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get assignment statistics
     */
    public static function getStats()
    {
        $stats = [];
        
        // Count by status
        $sql = "SELECT status, COUNT(*) as count 
                FROM " . _DB_PREFIX_ . "rk_locker_assignment 
                GROUP BY status";
        $results = Db::getInstance()->executeS($sql);
        
        $stats['by_status'] = [];
        foreach ($results as $row) {
            $stats['by_status'][$row['status']] = (int) $row['count'];
        }

        // Total
        $stats['total'] = array_sum($stats['by_status']);
        
        // Active (assigned + ready)
        $stats['active'] = ($stats['by_status']['assigned'] ?? 0) + ($stats['by_status']['ready'] ?? 0);
        
        // Collected today
        $sql = "SELECT COUNT(*) FROM " . _DB_PREFIX_ . "rk_locker_assignment 
                WHERE status = 'collected' AND DATE(date_collected) = CURDATE()";
        $stats['collected_today'] = (int) Db::getInstance()->getValue($sql);

        return $stats;
    }
}
