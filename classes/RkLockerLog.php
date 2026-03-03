<?php
/**
 * Locker Event Log Model
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class RkLockerLog extends ObjectModel
{
    public $id_log;
    public $id_locker;
    public $id_order;
    public $id_assignment;
    public $event_type;
    public $event_data;
    public $ip_address;
    public $id_employee;
    public $date_add;

    public static $definition = [
        'table' => 'rk_locker_log',
        'primary' => 'id_log',
        'fields' => [
            'id_locker' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_assignment' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'event_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 64],
            'event_data' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'ip_address' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 45],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * Add a log entry
     */
    public static function add($idLocker, $idOrder, $idAssignment, $eventType, $data = [])
    {
        $log = new RkLockerLog();
        $log->id_locker = (int) $idLocker;
        $log->id_order = (int) $idOrder;
        $log->id_assignment = (int) $idAssignment;
        $log->event_type = pSQL($eventType);
        $log->event_data = json_encode($data);
        $log->ip_address = Tools::getRemoteAddr();
        $log->date_add = date('Y-m-d H:i:s');
        
        // Get employee ID if in admin context
        if (isset(Context::getContext()->employee) && Context::getContext()->employee->id) {
            $log->id_employee = (int) Context::getContext()->employee->id;
        }
        
        return $log->save();
    }

    /**
     * Get logs for a specific order
     */
    public static function getByOrderId($idOrder, $limit = 100)
    {
        $sql = "SELECT l.*, 
                       lo.name as locker_name,
                       CONCAT(e.firstname, ' ', e.lastname) as employee_name
                FROM " . _DB_PREFIX_ . "rk_locker_log l
                LEFT JOIN " . _DB_PREFIX_ . "rk_locker lo ON l.id_locker = lo.id_locker
                LEFT JOIN " . _DB_PREFIX_ . "employee e ON l.id_employee = e.id_employee
                WHERE l.id_order = " . (int) $idOrder . "
                ORDER BY l.date_add DESC
                LIMIT " . (int) $limit;
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get logs for a specific locker
     */
    public static function getByLockerId($idLocker, $limit = 100)
    {
        $sql = "SELECT l.*, 
                       o.reference as order_reference,
                       CONCAT(e.firstname, ' ', e.lastname) as employee_name
                FROM " . _DB_PREFIX_ . "rk_locker_log l
                LEFT JOIN " . _DB_PREFIX_ . "orders o ON l.id_order = o.id_order
                LEFT JOIN " . _DB_PREFIX_ . "employee e ON l.id_employee = e.id_employee
                WHERE l.id_locker = " . (int) $idLocker . "
                ORDER BY l.date_add DESC
                LIMIT " . (int) $limit;
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get recent logs (global)
     */
    public static function getRecent($limit = 50)
    {
        $sql = "SELECT l.*, 
                       lo.name as locker_name,
                       o.reference as order_reference,
                       CONCAT(e.firstname, ' ', e.lastname) as employee_name
                FROM " . _DB_PREFIX_ . "rk_locker_log l
                LEFT JOIN " . _DB_PREFIX_ . "rk_locker lo ON l.id_locker = lo.id_locker
                LEFT JOIN " . _DB_PREFIX_ . "orders o ON l.id_order = o.id_order
                LEFT JOIN " . _DB_PREFIX_ . "employee e ON l.id_employee = e.id_employee
                ORDER BY l.date_add DESC
                LIMIT " . (int) $limit;
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get event type label (Spanish)
     */
    public static function getEventLabel($eventType)
    {
        $labels = [
            'assigned' => 'Locker asignado',
            'ready' => 'Listo para recogida',
            'collected' => 'Recogido',
            'cancelled' => 'Cancelado',
            'expired' => 'Expirado',
            'pin_generated' => 'PIN generado',
            'pin_used' => 'PIN utilizado',
            'locker_opened' => 'Locker abierto',
            'locker_closed' => 'Locker cerrado',
            'manual_release' => 'Liberación manual',
        ];
        
        return $labels[$eventType] ?? $eventType;
    }
}
