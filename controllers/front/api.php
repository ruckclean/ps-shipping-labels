<?php
/**
 * Public API Controller for Ruckclean Shipping Labels
 * Accessible without admin authentication, uses API key
 */

class RkLabelsApiModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        
        header('Content-Type: application/json');
        
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
            default:
                die(json_encode(['error' => 'Unknown action', 'success' => false]));
        }
    }

    protected function apiStatus()
    {
        $pendingCount = $this->getPendingOrdersCount();
        
        die(json_encode([
            'success' => true,
            'module' => 'rklabels',
            'version' => '1.3.0',
            'pending_count' => $pendingCount,
            'timestamp' => date('c'),
        ]));
    }

    protected function apiPending()
    {
        $orders = $this->getPendingOrders();
        
        die(json_encode([
            'success' => true,
            'count' => count($orders),
            'orders' => $orders,
        ]));
    }

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
            
            if (Configuration::get('RKLABELS_AUTO_STATUS')) {
                $newStatus = (int) Configuration::get('RKLABELS_STATUS_SHIPPED');
                if ($newStatus && $order->current_state != $newStatus) {
                    $history = new OrderHistory();
                    $history->id_order = $order->id;
                    $history->changeIdOrderState($newStatus, $order, true);
                    $history->addWithemail(true);
                }
            }
            
            PrestaShopLogger::addLog(
                'Label printed via API for order #' . $orderId,
                1, null, 'Order', $orderId, true
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

    protected function getPendingOrders()
    {
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
}
