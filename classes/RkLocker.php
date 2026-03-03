<?php
/**
 * Locker Model
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class RkLocker extends ObjectModel
{
    public $id_locker;
    public $name;
    public $ttlock_lock_id;
    public $location;
    public $active = 1;
    public $position = 0;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'rk_locker',
        'primary' => 'id_locker',
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 64],
            'ttlock_lock_id' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128],
            'location' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Get all lockers ordered by position
     */
    public static function getAll($activeOnly = true)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('rk_locker');
        if ($activeOnly) {
            $sql->where('active = 1');
        }
        $sql->orderBy('position ASC, id_locker ASC');
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get locker with current assignment status
     */
    public static function getAllWithStatus()
    {
        $sql = "SELECT l.*, 
                       a.id_assignment,
                       a.id_order,
                       a.status as assignment_status,
                       a.pin_code,
                       a.date_assigned,
                       a.date_ready,
                       o.reference as order_reference,
                       CONCAT(c.firstname, ' ', c.lastname) as customer_name
                FROM " . _DB_PREFIX_ . "rk_locker l
                LEFT JOIN " . _DB_PREFIX_ . "rk_locker_assignment a 
                    ON l.id_locker = a.id_locker 
                    AND a.status IN ('assigned', 'ready')
                LEFT JOIN " . _DB_PREFIX_ . "orders o ON a.id_order = o.id_order
                LEFT JOIN " . _DB_PREFIX_ . "customer c ON o.id_customer = c.id_customer
                WHERE l.active = 1
                ORDER BY l.position ASC, l.id_locker ASC";
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get next available locker using round-robin
     * Returns the locker that has been free the longest
     */
    public static function getNextAvailable()
    {
        // First, get lockers without active assignments
        $sql = "SELECT l.id_locker
                FROM " . _DB_PREFIX_ . "rk_locker l
                LEFT JOIN " . _DB_PREFIX_ . "rk_locker_assignment a 
                    ON l.id_locker = a.id_locker 
                    AND a.status IN ('assigned', 'ready')
                WHERE l.active = 1 
                AND a.id_assignment IS NULL
                ORDER BY l.position ASC, l.id_locker ASC
                LIMIT 1";
        
        $result = Db::getInstance()->getValue($sql);
        
        if ($result) {
            return new RkLocker($result);
        }
        
        return null;
    }

    /**
     * Get available locker count
     */
    public static function getAvailableCount()
    {
        $sql = "SELECT COUNT(*) 
                FROM " . _DB_PREFIX_ . "rk_locker l
                LEFT JOIN " . _DB_PREFIX_ . "rk_locker_assignment a 
                    ON l.id_locker = a.id_locker 
                    AND a.status IN ('assigned', 'ready')
                WHERE l.active = 1 
                AND a.id_assignment IS NULL";
        
        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Check if this locker is currently available
     */
    public function isAvailable()
    {
        if (!$this->active) {
            return false;
        }

        $sql = "SELECT COUNT(*) 
                FROM " . _DB_PREFIX_ . "rk_locker_assignment 
                WHERE id_locker = " . (int) $this->id 
              . " AND status IN ('assigned', 'ready')";
        
        return (int) Db::getInstance()->getValue($sql) === 0;
    }
}
