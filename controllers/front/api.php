<?php
/**
 * Public API Controller for Ruckclean Shipping Labels & Lockers
 * Accessible without admin authentication, uses API key
 */

require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLocker.php';
require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLockerAssignment.php';
require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLockerLog.php';

class RkLabelsApiModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        
        header('Content-Type: application/json');
        
        // Validate API key
        $apiKey = Tools::getValue('api_key');
        $storedKey = Configuration::get('RKLABELS_API_KEY');
        
        if (empty($apiKey) || $apiKey !== $storedKey) {
            die(json_encode(['error' => 'Invalid API key', 'success' => false]));
        }
        
        $action = Tools::getValue('api_action', 'status');
        
        switch ($action) {
            case 'status':
                $this->apiStatus();
                break;
            case 'pending':
                $this->apiPending();
                break;
            case 'print':
                $this->apiPrint();
                break;
            case 'order':
                $this->apiOrder();
                break;
            // Locker actions
            case 'lockers':
                $this->apiLockers();
                break;
            case 'locker_assign':
                $this->apiLockerAssign();
                break;
            case 'locker_ready':
                $this->apiLockerReady();
                break;
            case 'locker_collected':
                $this->apiLockerCollected();
                break;
            case 'locker_status':
                $this->apiLockerStatus();
                break;
            case 'locker_logs':
                $this->apiLockerLogs();
                break;
            default:
                die(json_encode(['error' => 'Unknown action', 'success' => false]));
        }
    }

    /**
     * Get module status
     */
    protected function apiStatus()
    {
        $pendingCount = $this->getPendingOrdersCount();
        
        die(json_encode([
            'success' => true,
            'version' => '1.0.0',
            'pending_count' => $pendingCount,
            'timestamp' => date('c'),
        ]));
    }

    /**
     * Get pending orders
     */
    protected function apiPending()
    {
        $orders = $this->getPendingOrders();
        
        die(json_encode([
            'success' => true,
            'count' => count($orders),
            'orders' => $orders,
        ]));
    }

    /**
     * Get single order details
     */
    protected function apiOrder()
    {
        $orderId = (int) Tools::getValue('id_order');
        
        if (!$orderId) {
            die(json_encode(['error' => 'No order ID specified', 'success' => false]));
        }
        
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            die(json_encode(['error' => 'Order not found', 'success' => false]));
        }
        
        $label = $this->generateLabelData($order);
        
        die(json_encode([
            'success' => true,
            'order' => $label,
        ]));
    }

    /**
     * Mark orders as printed/shipped
     */
    protected function apiPrint()
    {
        $ids = Tools::getValue('ids');
        
        if (empty($ids)) {
            die(json_encode(['error' => 'No order IDs specified', 'success' => false]));
        }
        
        $orderIds = array_map('intval', explode(',', $ids));
        $results = [];
        
        foreach ($orderIds as $orderId) {
            $order = new Order($orderId);
            
            if (!Validate::isLoadedObject($order)) {
                $results[] = [
                    'id' => $orderId,
                    'status' => 'error',
                    'message' => 'Order not found',
                ];
                continue;
            }
            
            $label = $this->generateLabelData($order);
            
            // Update order status if configured
            if (Configuration::get('RKLABELS_AUTO_STATUS')) {
                $newStatus = (int) Configuration::get('RKLABELS_STATUS_SHIPPED');
                if ($newStatus && $order->current_state != $newStatus) {
                    $history = new OrderHistory();
                    $history->id_order = $order->id;
                    $history->changeIdOrderState($newStatus, $order, true);
                    $history->addWithemail(true);
                }
            }
            
            // Log the print
            PrestaShopLogger::addLog(
                'Label printed via API for order #' . $orderId,
                1,
                null,
                'Order',
                $orderId,
                true
            );
            
            $results[] = [
                'id' => $orderId,
                'status' => 'printed',
                'label' => $label,
            ];
        }
        
        die(json_encode([
            'success' => true,
            'results' => $results,
        ]));
    }

    /**
     * Get pending orders from database
     */
    protected function getPendingOrders()
    {
        // Order states: 2=Payment accepted, 3=Processing, 10=Awaiting payment, 11=Remote payment accepted
        $sql = '
            SELECT o.id_order, o.reference, o.date_add, o.total_paid,
                   CONCAT(a.firstname, " ", a.lastname) as recipient_name,
                   a.company, a.address1, a.address2, a.postcode, a.city,
                   a.phone, a.phone_mobile,
                   cl.name as country_name,
                   osl.name as status_name,
                   c.email
            FROM ' . _DB_PREFIX_ . 'orders o
            LEFT JOIN ' . _DB_PREFIX_ . 'customer c ON o.id_customer = c.id_customer
            LEFT JOIN ' . _DB_PREFIX_ . 'address a ON o.id_address_delivery = a.id_address
            LEFT JOIN ' . _DB_PREFIX_ . 'country_lang cl ON a.id_country = cl.id_country AND cl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
            LEFT JOIN ' . _DB_PREFIX_ . 'order_state_lang osl ON o.current_state = osl.id_order_state AND osl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
            WHERE o.current_state IN (2, 3, 10, 11, 12)
            ORDER BY o.date_add DESC
            LIMIT 100
        ';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get count of pending orders
     */
    protected function getPendingOrdersCount()
    {
        $sql = '
            SELECT COUNT(*) as cnt
            FROM ' . _DB_PREFIX_ . 'orders
            WHERE current_state IN (2, 3, 10, 11, 12)
        ';
        
        $result = Db::getInstance()->getRow($sql);
        return (int) $result['cnt'];
    }

    /**
     * Generate label data for an order
     */
    protected function generateLabelData(Order $order)
    {
        $address = new Address($order->id_address_delivery);
        $customer = new Customer($order->id_customer);
        $country = new Country($address->id_country);
        $state = $address->id_state ? new State($address->id_state) : null;
        
        $langId = (int) Configuration::get('PS_LANG_DEFAULT');
        
        $label = [
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'date' => date('Y-m-d', strtotime($order->date_add)),
            'total_paid' => $order->total_paid,
            'recipient' => [
                'name' => trim($address->firstname . ' ' . $address->lastname),
                'company' => $address->company,
                'address1' => $address->address1,
                'address2' => $address->address2,
                'postcode' => $address->postcode,
                'city' => $address->city,
                'state' => $state ? $state->name : '',
                'country' => isset($country->name[$langId]) ? $country->name[$langId] : $country->iso_code,
                'phone' => $address->phone ?: $address->phone_mobile,
                'email' => $customer->email,
            ],
        ];
        
        // Add sender if configured
        if (Configuration::get('RKLABELS_SHOW_SENDER')) {
            $label['sender'] = [
                'name' => Configuration::get('RKLABELS_SENDER_NAME'),
                'address' => Configuration::get('RKLABELS_SENDER_ADDRESS'),
                'postcode' => Configuration::get('RKLABELS_SENDER_POSTCODE'),
                'city' => Configuration::get('RKLABELS_SENDER_CITY'),
            ];
        }
        
        return $label;
    }

    // ============================================
    // LOCKER API ENDPOINTS
    // ============================================

    /**
     * Get all lockers with current status
     */
    protected function apiLockers()
    {
        $lockers = RkLocker::getAllWithStatus();
        $stats = [
            'total' => count($lockers),
            'available' => RkLocker::getAvailableCount(),
            'occupied' => count($lockers) - RkLocker::getAvailableCount(),
        ];

        die(json_encode([
            'success' => true,
            'stats' => $stats,
            'lockers' => $lockers,
        ]));
    }

    /**
     * Assign locker to order (round-robin)
     */
    protected function apiLockerAssign()
    {
        $orderId = (int) Tools::getValue('id_order');
        
        if (!$orderId) {
            die(json_encode(['error' => 'No order ID specified', 'success' => false]));
        }
        
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            die(json_encode(['error' => 'Order not found', 'success' => false]));
        }
        
        $assignment = RkLockerAssignment::assignToOrder($orderId);
        
        if (!$assignment) {
            die(json_encode([
                'success' => false,
                'error' => 'No lockers available',
            ]));
        }
        
        $locker = new RkLocker($assignment->id_locker);
        
        die(json_encode([
            'success' => true,
            'assignment' => [
                'id_assignment' => $assignment->id,
                'id_locker' => $assignment->id_locker,
                'locker_name' => $locker->name,
                'locker_location' => $locker->location,
                'status' => $assignment->status,
                'date_assigned' => $assignment->date_assigned,
            ],
        ]));
    }

    /**
     * Mark locker as ready for pickup (keychain deposited)
     */
    protected function apiLockerReady()
    {
        $orderId = (int) Tools::getValue('id_order');
        $pinCode = Tools::getValue('pin_code');
        $validHours = (int) Tools::getValue('valid_hours', Configuration::get('RKLABELS_PIN_VALID_HOURS') ?: 72);
        
        if (!$orderId) {
            die(json_encode(['error' => 'No order ID specified', 'success' => false]));
        }
        
        $assignment = RkLockerAssignment::getByOrderId($orderId);
        
        if (!$assignment) {
            die(json_encode(['error' => 'No locker assigned to this order', 'success' => false]));
        }
        
        if (!$assignment->markReady($pinCode, $validHours)) {
            die(json_encode(['error' => 'Failed to update assignment', 'success' => false]));
        }
        
        die(json_encode([
            'success' => true,
            'message' => 'Locker marked as ready for pickup',
            'assignment' => [
                'id_assignment' => $assignment->id,
                'status' => $assignment->status,
                'pin_valid_until' => $assignment->pin_valid_until,
            ],
        ]));
    }

    /**
     * Mark locker as collected (frees the locker)
     */
    protected function apiLockerCollected()
    {
        $orderId = (int) Tools::getValue('id_order');
        $idLocker = (int) Tools::getValue('id_locker');
        
        // Find assignment by order or locker
        if ($orderId) {
            $assignment = RkLockerAssignment::getByOrderId($orderId);
        } elseif ($idLocker) {
            $assignment = RkLockerAssignment::getActiveByLockerId($idLocker);
        } else {
            die(json_encode(['error' => 'No order ID or locker ID specified', 'success' => false]));
        }
        
        if (!$assignment) {
            die(json_encode(['error' => 'No active assignment found', 'success' => false]));
        }
        
        if (!$assignment->markCollected()) {
            die(json_encode(['error' => 'Failed to update assignment', 'success' => false]));
        }
        
        // Optionally update order status
        $collectedStatus = (int) Configuration::get('RKLABELS_STATUS_COLLECTED');
        if ($collectedStatus && $orderId) {
            $order = new Order($assignment->id_order);
            if ($order->current_state != $collectedStatus) {
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->changeIdOrderState($collectedStatus, $order, true);
                $history->addWithemail(true);
            }
        }
        
        die(json_encode([
            'success' => true,
            'message' => 'Locker marked as collected',
            'id_locker' => $assignment->id_locker,
            'id_order' => $assignment->id_order,
        ]));
    }

    /**
     * Get locker status for a specific order
     */
    protected function apiLockerStatus()
    {
        $orderId = (int) Tools::getValue('id_order');
        
        if (!$orderId) {
            die(json_encode(['error' => 'No order ID specified', 'success' => false]));
        }
        
        $assignment = RkLockerAssignment::getByOrderId($orderId);
        
        if (!$assignment) {
            die(json_encode([
                'success' => true,
                'has_locker' => false,
                'assignment' => null,
            ]));
        }
        
        $locker = new RkLocker($assignment->id_locker);
        
        die(json_encode([
            'success' => true,
            'has_locker' => true,
            'assignment' => [
                'id_assignment' => $assignment->id,
                'id_locker' => $assignment->id_locker,
                'locker_name' => $locker->name,
                'locker_location' => $locker->location,
                'status' => $assignment->status,
                'pin_code' => $assignment->pin_code,
                'pin_valid_until' => $assignment->pin_valid_until,
                'date_assigned' => $assignment->date_assigned,
                'date_ready' => $assignment->date_ready,
                'date_collected' => $assignment->date_collected,
            ],
        ]));
    }

    /**
     * Get logs for order or locker
     */
    protected function apiLockerLogs()
    {
        $orderId = (int) Tools::getValue('id_order');
        $idLocker = (int) Tools::getValue('id_locker');
        $limit = (int) Tools::getValue('limit', 50);
        
        if ($orderId) {
            $logs = RkLockerLog::getByOrderId($orderId, $limit);
        } elseif ($idLocker) {
            $logs = RkLockerLog::getByLockerId($idLocker, $limit);
        } else {
            $logs = RkLockerLog::getRecent($limit);
        }
        
        // Add human-readable labels
        foreach ($logs as &$log) {
            $log['event_label'] = RkLockerLog::getEventLabel($log['event_type']);
            $log['event_data_decoded'] = json_decode($log['event_data'], true);
        }
        
        die(json_encode([
            'success' => true,
            'count' => count($logs),
            'logs' => $logs,
        ]));
    }
}
