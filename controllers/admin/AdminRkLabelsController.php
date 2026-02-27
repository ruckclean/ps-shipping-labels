<?php
/**
 * Admin Controller for Ruckclean Shipping Labels
 */

class AdminRkLabelsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'orders';
        $this->className = 'Order';
        $this->identifier = 'id_order';
        
        parent::__construct();
        
        $this->toolbar_title = $this->l('Shipping Labels');
    }

    public function initContent()
    {
        parent::initContent();

        $action = Tools::getValue('action');
        
        switch ($action) {
            case 'printLabel':
                $this->processPrintLabel();
                break;
            case 'batchPrint':
                $this->processBatchPrint();
                break;
            case 'api':
                $this->processApi();
                break;
            default:
                $this->renderPendingOrders();
                break;
        }
    }

    /**
     * Render list of orders pending label printing
     */
    protected function renderPendingOrders()
    {
        // Get orders that are paid but not yet shipped
        $sql = '
            SELECT o.id_order, o.reference, o.date_add, 
                   c.firstname, c.lastname, c.email,
                   a.address1, a.address2, a.postcode, a.city,
                   cl.name as country_name,
                   osl.name as status_name
            FROM ' . _DB_PREFIX_ . 'orders o
            LEFT JOIN ' . _DB_PREFIX_ . 'customer c ON o.id_customer = c.id_customer
            LEFT JOIN ' . _DB_PREFIX_ . 'address a ON o.id_address_delivery = a.id_address
            LEFT JOIN ' . _DB_PREFIX_ . 'country_lang cl ON a.id_country = cl.id_country AND cl.id_lang = ' . (int)$this->context->language->id . '
            LEFT JOIN ' . _DB_PREFIX_ . 'order_state_lang osl ON o.current_state = osl.id_order_state AND osl.id_lang = ' . (int)$this->context->language->id . '
            WHERE o.current_state IN (2, 3, 10, 11)
            ORDER BY o.date_add DESC
            LIMIT 100
        ';
        
        $orders = Db::getInstance()->executeS($sql);
        
        $this->context->smarty->assign([
            'orders' => $orders,
            'print_url' => $this->context->link->getAdminLink('AdminRkLabels'),
            'batch_print_url' => $this->context->link->getAdminLink('AdminRkLabels') . '&action=batchPrint',
        ]);
        
        $this->setTemplate('pending_orders.tpl');
    }

    /**
     * Print single label
     */
    protected function processPrintLabel()
    {
        $orderId = (int) Tools::getValue('id_order');
        
        if (!$orderId) {
            $this->errors[] = $this->l('No order specified');
            return;
        }
        
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = $this->l('Order not found');
            return;
        }
        
        $label = $this->generateLabel($order);
        
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
        $this->logPrint($orderId);
        
        // Return based on printer type
        $printerType = Configuration::get('RKLABELS_PRINTER_TYPE');
        
        switch ($printerType) {
            case 'pdf':
                $this->outputPDF($label);
                break;
            case 'escpos':
                $this->outputESCPOS($label);
                break;
            case 'zpl':
                $this->outputZPL($label);
                break;
            default:
                $this->outputPDF($label);
        }
    }

    /**
     * Batch print multiple labels
     */
    protected function processBatchPrint()
    {
        $orderIds = Tools::getValue('orderBox', []);
        
        if (empty($orderIds)) {
            // Get from URL if passed
            $ids = Tools::getValue('ids');
            if ($ids) {
                $orderIds = explode(',', $ids);
            }
        }
        
        if (empty($orderIds)) {
            $this->errors[] = $this->l('No orders selected');
            $this->renderPendingOrders();
            return;
        }
        
        $labels = [];
        foreach ($orderIds as $orderId) {
            $order = new Order((int) $orderId);
            if (Validate::isLoadedObject($order)) {
                $labels[] = $this->generateLabel($order);
                
                // Update status
                if (Configuration::get('RKLABELS_AUTO_STATUS')) {
                    $newStatus = (int) Configuration::get('RKLABELS_STATUS_SHIPPED');
                    if ($newStatus && $order->current_state != $newStatus) {
                        $history = new OrderHistory();
                        $history->id_order = $order->id;
                        $history->changeIdOrderState($newStatus, $order, true);
                        $history->addWithemail(true);
                    }
                }
                
                $this->logPrint($orderId);
            }
        }
        
        $this->outputBatchPDF($labels);
    }

    /**
     * API endpoint for external access (batch operations)
     */
    protected function processApi()
    {
        header('Content-Type: application/json');
        
        $apiKey = Tools::getValue('api_key');
        $storedKey = Configuration::get('RKLABELS_API_KEY');
        
        if ($apiKey !== $storedKey) {
            die(json_encode(['error' => 'Invalid API key']));
        }
        
        $apiAction = Tools::getValue('api_action');
        
        switch ($apiAction) {
            case 'pending':
                // Get pending orders
                $orders = $this->getPendingOrders();
                die(json_encode(['success' => true, 'orders' => $orders]));
                
            case 'print':
                // Print specific orders
                $ids = Tools::getValue('ids');
                $orderIds = explode(',', $ids);
                $results = [];
                
                foreach ($orderIds as $orderId) {
                    $order = new Order((int) $orderId);
                    if (Validate::isLoadedObject($order)) {
                        $label = $this->generateLabel($order);
                        
                        if (Configuration::get('RKLABELS_AUTO_STATUS')) {
                            $newStatus = (int) Configuration::get('RKLABELS_STATUS_SHIPPED');
                            if ($newStatus) {
                                $history = new OrderHistory();
                                $history->id_order = $order->id;
                                $history->changeIdOrderState($newStatus, $order, true);
                                $history->addWithemail(true);
                            }
                        }
                        
                        $this->logPrint($orderId);
                        $results[] = ['id' => $orderId, 'status' => 'printed', 'label' => $label];
                    } else {
                        $results[] = ['id' => $orderId, 'status' => 'error', 'message' => 'Order not found'];
                    }
                }
                
                die(json_encode(['success' => true, 'results' => $results]));
                
            case 'status':
                // Get module status
                die(json_encode([
                    'success' => true,
                    'version' => '1.0.0',
                    'pending_count' => count($this->getPendingOrders()),
                ]));
                
            default:
                die(json_encode(['error' => 'Unknown action']));
        }
    }

    /**
     * Generate label data for an order
     */
    protected function generateLabel(Order $order)
    {
        $address = new Address($order->id_address_delivery);
        $customer = new Customer($order->id_customer);
        $country = new Country($address->id_country);
        $state = $address->id_state ? new State($address->id_state) : null;
        
        $label = [
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'date' => date('Y-m-d', strtotime($order->date_add)),
            'recipient' => [
                'name' => trim($address->firstname . ' ' . $address->lastname),
                'company' => $address->company,
                'address1' => $address->address1,
                'address2' => $address->address2,
                'postcode' => $address->postcode,
                'city' => $address->city,
                'state' => $state ? $state->name : '',
                'country' => $country->name[$this->context->language->id] ?? $country->iso_code,
                'phone' => $address->phone ?: $address->phone_mobile,
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

    /**
     * Get list of pending orders
     */
    protected function getPendingOrders()
    {
        $sql = '
            SELECT o.id_order, o.reference, o.date_add,
                   CONCAT(a.firstname, " ", a.lastname) as recipient_name,
                   a.address1, a.postcode, a.city
            FROM ' . _DB_PREFIX_ . 'orders o
            LEFT JOIN ' . _DB_PREFIX_ . 'address a ON o.id_address_delivery = a.id_address
            WHERE o.current_state IN (2, 3, 10, 11)
            ORDER BY o.date_add DESC
            LIMIT 100
        ';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Log print action
     */
    protected function logPrint($orderId)
    {
        // Could be extended to log to database
        PrestaShopLogger::addLog(
            'Label printed for order #' . $orderId,
            1,
            null,
            'Order',
            $orderId,
            true
        );
    }

    /**
     * Output PDF label
     */
    protected function outputPDF($label)
    {
        require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLabelPDF.php';
        
        $width = (int) Configuration::get('RKLABELS_LABEL_WIDTH');
        $height = (int) Configuration::get('RKLABELS_LABEL_HEIGHT');
        $fontSize = (int) Configuration::get('RKLABELS_FONT_SIZE');
        
        $pdf = new RkLabelPDF($width, $height, $fontSize);
        $pdf->generateLabel($label);
        $pdf->output('label_' . $label['order_reference'] . '.pdf');
        exit;
    }

    /**
     * Output batch PDF with multiple labels
     */
    protected function outputBatchPDF($labels)
    {
        require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLabelPDF.php';
        
        $width = (int) Configuration::get('RKLABELS_LABEL_WIDTH');
        $height = (int) Configuration::get('RKLABELS_LABEL_HEIGHT');
        $fontSize = (int) Configuration::get('RKLABELS_FONT_SIZE');
        
        $pdf = new RkLabelPDF($width, $height, $fontSize);
        $pdf->generateBatchLabels($labels);
        $pdf->output('labels_batch_' . date('Y-m-d_His') . '.pdf');
        exit;
    }

    /**
     * Output ESC/POS format
     */
    protected function outputESCPOS($label)
    {
        require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLabelESCPOS.php';
        
        $escpos = new RkLabelESCPOS();
        $data = $escpos->generateLabel($label);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="label_' . $label['order_reference'] . '.bin"');
        echo $data;
        exit;
    }

    /**
     * Output ZPL format
     */
    protected function outputZPL($label)
    {
        require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLabelZPL.php';
        
        $width = (int) Configuration::get('RKLABELS_LABEL_WIDTH');
        $height = (int) Configuration::get('RKLABELS_LABEL_HEIGHT');
        
        $zpl = new RkLabelZPL($width, $height);
        $data = $zpl->generateLabel($label);
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="label_' . $label['order_reference'] . '.zpl"');
        echo $data;
        exit;
    }
}
