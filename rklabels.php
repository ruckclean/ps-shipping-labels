<?php
/**
 * Ruckclean Shipping Labels & Lockers
 * 
 * PrestaShop module for thermal shipping label printing and locker management
 * 
 * @author Ruckclean
 * @copyright 2025 Ruckclean
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class RkLabels extends Module
{
    /**
     * Load locker classes on demand
     */
    protected function loadLockerClasses()
    {
        if (!class_exists('RkLocker')) {
            require_once __DIR__ . '/classes/RkLocker.php';
            require_once __DIR__ . '/classes/RkLockerAssignment.php';
            require_once __DIR__ . '/classes/RkLockerLog.php';
        }
    }

    public function __construct()
    {
        $this->name = 'rklabels';
        $this->tab = 'shipping_logistics';
        $this->version = '1.2.0';
        $this->author = 'Ruckclean';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ruckclean Shipping Labels & Lockers');
        $this->description = $this->l('Print shipping labels and manage pickup lockers for NFC keychains');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? This will delete all locker data.');
    }

    public function install()
    {
        return parent::install()
            && $this->installDatabase()
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('displayAdminOrdersListBefore')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->installTab()
            && $this->installConfig();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallDatabase()
            && $this->uninstallTab()
            && $this->uninstallConfig();
    }

    /**
     * Install database tables for lockers
     */
    protected function installDatabase()
    {
        $sqlFile = dirname(__FILE__) . '/sql/install.sql';
        if (!file_exists($sqlFile)) {
            return true; // No SQL file, skip
        }

        $sql = file_get_contents($sqlFile);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        
        // Execute each statement
        $queries = preg_split('/;\s*[\r\n]+/', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!Db::getInstance()->execute($query)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Uninstall database tables
     */
    protected function uninstallDatabase()
    {
        $sqlFile = dirname(__FILE__) . '/sql/uninstall.sql';
        if (!file_exists($sqlFile)) {
            return true;
        }

        $sql = file_get_contents($sqlFile);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        
        $queries = preg_split('/;\s*[\r\n]+/', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                Db::getInstance()->execute($query);
            }
        }

        return true;
    }

    /**
     * Install admin tabs
     */
    protected function installTab()
    {
        // Create parent tab for Ruckclean
        $parentTab = new Tab();
        $parentTab->active = 1;
        $parentTab->class_name = 'AdminRuckclean';
        $parentTab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $parentTab->name[$lang['id_lang']] = 'Ruckclean';
        }
        $parentTab->id_parent = 0;
        $parentTab->module = $this->name;
        $parentTab->icon = 'local_car_wash';
        
        if (!$parentTab->add()) {
            return false;
        }

        $parentId = (int) Tab::getIdFromClassName('AdminRuckclean');

        // Labels tab
        $labelsTab = new Tab();
        $labelsTab->active = 1;
        $labelsTab->class_name = 'AdminRkLabels';
        $labelsTab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $labelsTab->name[$lang['id_lang']] = 'Etiquetas';
        }
        $labelsTab->id_parent = $parentId;
        $labelsTab->module = $this->name;
        
        if (!$labelsTab->add()) {
            return false;
        }

        // Lockers tab
        $lockersTab = new Tab();
        $lockersTab->active = 1;
        $lockersTab->class_name = 'AdminRkLockers';
        $lockersTab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $lockersTab->name[$lang['id_lang']] = 'Lockers';
        }
        $lockersTab->id_parent = $parentId;
        $lockersTab->module = $this->name;
        
        return $lockersTab->add();
    }

    protected function uninstallTab()
    {
        $tabs = ['AdminRkLabels', 'AdminRkLockers', 'AdminRuckclean'];
        
        foreach ($tabs as $className) {
            $id_tab = (int) Tab::getIdFromClassName($className);
            if ($id_tab) {
                $tab = new Tab($id_tab);
                $tab->delete();
            }
        }
        
        return true;
    }

    /**
     * Install default configuration
     */
    protected function installConfig()
    {
        $defaults = [
            // Label settings
            'RKLABELS_PRINTER_TYPE' => 'pdf',           // pdf, escpos, zpl
            'RKLABELS_PRINTER_CONNECTION' => 'usb',     // usb, network, bluetooth
            'RKLABELS_PRINTER_IP' => '',
            'RKLABELS_PRINTER_PORT' => '9100',
            'RKLABELS_LABEL_WIDTH' => '100',            // mm
            'RKLABELS_LABEL_HEIGHT' => '60',            // mm
            'RKLABELS_FONT_SIZE' => '12',
            'RKLABELS_SHOW_SENDER' => '1',
            'RKLABELS_SENDER_NAME' => 'Ruckclean',
            'RKLABELS_SENDER_ADDRESS' => '',
            'RKLABELS_SENDER_POSTCODE' => '',
            'RKLABELS_SENDER_CITY' => '',
            'RKLABELS_AUTO_STATUS' => '1',              // Change status on print
            'RKLABELS_STATUS_SHIPPED' => '4',           // Order status ID for "shipped"
            'RKLABELS_API_KEY' => '',                   // For external/batch access
            
            // Locker settings
            'RKLABELS_AUTO_ASSIGN_LOCKER' => '1',       // Auto-assign locker on new order
            'RKLABELS_ASSIGN_ALL_ORDERS' => '1',        // Assign to all orders (not just pickup)
            'RKLABELS_STATUS_COLLECTED' => '5',         // Order status for "collected"
            'RKLABELS_PIN_VALID_HOURS' => '72',         // PIN validity in hours
            
            // TTLock API settings
            'RKLABELS_TTLOCK_CLIENT_ID' => '',
            'RKLABELS_TTLOCK_CLIENT_SECRET' => '',
            'RKLABELS_TTLOCK_ACCESS_TOKEN' => '',
            'RKLABELS_TTLOCK_REFRESH_TOKEN' => '',
            'RKLABELS_TTLOCK_TOKEN_EXPIRES' => '',
        ];

        foreach ($defaults as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        // Generate API key for batch operations
        if (empty(Configuration::get('RKLABELS_API_KEY'))) {
            Configuration::updateValue('RKLABELS_API_KEY', bin2hex(random_bytes(16)));
        }

        return true;
    }

    protected function uninstallConfig()
    {
        $keys = [
            'RKLABELS_PRINTER_TYPE',
            'RKLABELS_PRINTER_CONNECTION',
            'RKLABELS_PRINTER_IP',
            'RKLABELS_PRINTER_PORT',
            'RKLABELS_LABEL_WIDTH',
            'RKLABELS_LABEL_HEIGHT',
            'RKLABELS_FONT_SIZE',
            'RKLABELS_SHOW_SENDER',
            'RKLABELS_SENDER_NAME',
            'RKLABELS_SENDER_ADDRESS',
            'RKLABELS_SENDER_POSTCODE',
            'RKLABELS_SENDER_CITY',
            'RKLABELS_AUTO_STATUS',
            'RKLABELS_STATUS_SHIPPED',
            'RKLABELS_API_KEY',
            'RKLABELS_AUTO_ASSIGN_LOCKER',
            'RKLABELS_ASSIGN_ALL_ORDERS',
            'RKLABELS_STATUS_COLLECTED',
            'RKLABELS_PIN_VALID_HOURS',
            'RKLABELS_TTLOCK_CLIENT_ID',
            'RKLABELS_TTLOCK_CLIENT_SECRET',
            'RKLABELS_TTLOCK_ACCESS_TOKEN',
            'RKLABELS_TTLOCK_REFRESH_TOKEN',
            'RKLABELS_TTLOCK_TOKEN_EXPIRES',
        ];

        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitRkLabelsConfig')) {
            $this->saveConfig();
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        if (Tools::isSubmit('regenerateApiKey')) {
            Configuration::updateValue('RKLABELS_API_KEY', bin2hex(random_bytes(16)));
            $output .= $this->displayConfirmation($this->l('API Key regenerated'));
        }

        return $output . $this->renderConfigForm();
    }

    protected function saveConfig()
    {
        $fields = [
            'RKLABELS_PRINTER_TYPE',
            'RKLABELS_PRINTER_CONNECTION',
            'RKLABELS_PRINTER_IP',
            'RKLABELS_PRINTER_PORT',
            'RKLABELS_LABEL_WIDTH',
            'RKLABELS_LABEL_HEIGHT',
            'RKLABELS_FONT_SIZE',
            'RKLABELS_SHOW_SENDER',
            'RKLABELS_SENDER_NAME',
            'RKLABELS_SENDER_ADDRESS',
            'RKLABELS_SENDER_POSTCODE',
            'RKLABELS_SENDER_CITY',
            'RKLABELS_AUTO_STATUS',
            'RKLABELS_STATUS_SHIPPED',
            'RKLABELS_AUTO_ASSIGN_LOCKER',
            'RKLABELS_ASSIGN_ALL_ORDERS',
            'RKLABELS_STATUS_COLLECTED',
            'RKLABELS_PIN_VALID_HOURS',
            'RKLABELS_TTLOCK_CLIENT_ID',
            'RKLABELS_TTLOCK_CLIENT_SECRET',
        ];

        foreach ($fields as $field) {
            Configuration::updateValue($field, Tools::getValue($field));
        }
    }

    protected function renderConfigForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $helper->submit_action = 'submitRkLabelsConfig';

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    protected function getConfigForm()
    {
        // Get order statuses for dropdown
        $statuses = OrderState::getOrderStates($this->context->language->id);
        $statusOptions = [];
        foreach ($statuses as $status) {
            $statusOptions[] = [
                'id' => $status['id_order_state'],
                'name' => $status['name'],
            ];
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Ruckclean Configuration'),
                    'icon' => 'icon-cogs',
                ],
                'tabs' => [
                    'printer' => $this->l('Impresora'),
                    'label' => $this->l('Etiqueta'),
                    'sender' => $this->l('Remitente'),
                    'lockers' => $this->l('Lockers'),
                    'ttlock' => $this->l('TTLock API'),
                    'automation' => $this->l('Automatización'),
                    'api' => $this->l('API'),
                ],
                'input' => [
                    // PRINTER TAB
                    [
                        'type' => 'select',
                        'label' => $this->l('Printer Type'),
                        'name' => 'RKLABELS_PRINTER_TYPE',
                        'tab' => 'printer',
                        'options' => [
                            'query' => [
                                ['id' => 'pdf', 'name' => 'PDF (Universal)'],
                                ['id' => 'escpos', 'name' => 'ESC/POS (Thermal)'],
                                ['id' => 'zpl', 'name' => 'ZPL (Zebra)'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Select your printer type. PDF works with any printer.'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Connection Type'),
                        'name' => 'RKLABELS_PRINTER_CONNECTION',
                        'tab' => 'printer',
                        'options' => [
                            'query' => [
                                ['id' => 'usb', 'name' => 'USB (via browser)'],
                                ['id' => 'network', 'name' => 'Network (IP)'],
                                ['id' => 'bluetooth', 'name' => 'Bluetooth'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Printer IP'),
                        'name' => 'RKLABELS_PRINTER_IP',
                        'tab' => 'printer',
                        'desc' => $this->l('Only for network printers'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Printer Port'),
                        'name' => 'RKLABELS_PRINTER_PORT',
                        'tab' => 'printer',
                        'desc' => $this->l('Default: 9100'),
                    ],
                    // LABEL TAB
                    [
                        'type' => 'text',
                        'label' => $this->l('Label Width (mm)'),
                        'name' => 'RKLABELS_LABEL_WIDTH',
                        'tab' => 'label',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Label Height (mm)'),
                        'name' => 'RKLABELS_LABEL_HEIGHT',
                        'tab' => 'label',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Font Size'),
                        'name' => 'RKLABELS_FONT_SIZE',
                        'tab' => 'label',
                        'class' => 'fixed-width-sm',
                    ],
                    // SENDER TAB
                    [
                        'type' => 'switch',
                        'label' => $this->l('Show Sender on Label'),
                        'name' => 'RKLABELS_SHOW_SENDER',
                        'tab' => 'sender',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Sender Name'),
                        'name' => 'RKLABELS_SENDER_NAME',
                        'tab' => 'sender',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Sender Address'),
                        'name' => 'RKLABELS_SENDER_ADDRESS',
                        'tab' => 'sender',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Sender Postcode'),
                        'name' => 'RKLABELS_SENDER_POSTCODE',
                        'tab' => 'sender',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Sender City'),
                        'name' => 'RKLABELS_SENDER_CITY',
                        'tab' => 'sender',
                    ],
                    // LOCKERS TAB
                    [
                        'type' => 'switch',
                        'label' => $this->l('Asignar locker automáticamente'),
                        'name' => 'RKLABELS_AUTO_ASSIGN_LOCKER',
                        'tab' => 'lockers',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Asignar locker automáticamente cuando se crea un pedido'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Asignar a todos los pedidos'),
                        'name' => 'RKLABELS_ASSIGN_ALL_ORDERS',
                        'tab' => 'lockers',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Si no, solo se asigna a pedidos con método de envío "recogida"'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Estado "Recogido"'),
                        'name' => 'RKLABELS_STATUS_COLLECTED',
                        'tab' => 'lockers',
                        'options' => [
                            'query' => $statusOptions,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Estado que marca el pedido como recogido (libera el locker)'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Validez del PIN (horas)'),
                        'name' => 'RKLABELS_PIN_VALID_HOURS',
                        'tab' => 'lockers',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Horas que el PIN de acceso al locker es válido'),
                    ],
                    // TTLOCK TAB
                    [
                        'type' => 'text',
                        'label' => $this->l('Client ID'),
                        'name' => 'RKLABELS_TTLOCK_CLIENT_ID',
                        'tab' => 'ttlock',
                        'desc' => $this->l('Obtener en https://euopen.ttlock.com/'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Client Secret'),
                        'name' => 'RKLABELS_TTLOCK_CLIENT_SECRET',
                        'tab' => 'ttlock',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Access Token'),
                        'name' => 'RKLABELS_TTLOCK_ACCESS_TOKEN',
                        'tab' => 'ttlock',
                        'readonly' => true,
                        'desc' => $this->l('Se genera automáticamente'),
                    ],
                    // AUTOMATION TAB
                    [
                        'type' => 'switch',
                        'label' => $this->l('Cambiar estado al imprimir'),
                        'name' => 'RKLABELS_AUTO_STATUS',
                        'tab' => 'automation',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Cambiar estado del pedido al imprimir etiqueta'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Estado después de imprimir'),
                        'name' => 'RKLABELS_STATUS_SHIPPED',
                        'tab' => 'automation',
                        'options' => [
                            'query' => $statusOptions,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    // API TAB
                    [
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'name' => 'RKLABELS_API_KEY',
                        'tab' => 'api',
                        'readonly' => true,
                        'desc' => $this->l('Use this key for batch operations and external integrations'),
                    ],
                ],
                'buttons' => [
                    [
                        'title' => $this->l('Regenerate API Key'),
                        'name' => 'regenerateApiKey',
                        'type' => 'submit',
                        'class' => 'btn btn-default pull-right',
                        'icon' => 'process-icon-refresh',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    protected function getConfigValues()
    {
        return [
            'RKLABELS_PRINTER_TYPE' => Configuration::get('RKLABELS_PRINTER_TYPE'),
            'RKLABELS_PRINTER_CONNECTION' => Configuration::get('RKLABELS_PRINTER_CONNECTION'),
            'RKLABELS_PRINTER_IP' => Configuration::get('RKLABELS_PRINTER_IP'),
            'RKLABELS_PRINTER_PORT' => Configuration::get('RKLABELS_PRINTER_PORT'),
            'RKLABELS_LABEL_WIDTH' => Configuration::get('RKLABELS_LABEL_WIDTH'),
            'RKLABELS_LABEL_HEIGHT' => Configuration::get('RKLABELS_LABEL_HEIGHT'),
            'RKLABELS_FONT_SIZE' => Configuration::get('RKLABELS_FONT_SIZE'),
            'RKLABELS_SHOW_SENDER' => Configuration::get('RKLABELS_SHOW_SENDER'),
            'RKLABELS_SENDER_NAME' => Configuration::get('RKLABELS_SENDER_NAME'),
            'RKLABELS_SENDER_ADDRESS' => Configuration::get('RKLABELS_SENDER_ADDRESS'),
            'RKLABELS_SENDER_POSTCODE' => Configuration::get('RKLABELS_SENDER_POSTCODE'),
            'RKLABELS_SENDER_CITY' => Configuration::get('RKLABELS_SENDER_CITY'),
            'RKLABELS_AUTO_STATUS' => Configuration::get('RKLABELS_AUTO_STATUS'),
            'RKLABELS_STATUS_SHIPPED' => Configuration::get('RKLABELS_STATUS_SHIPPED'),
            'RKLABELS_API_KEY' => Configuration::get('RKLABELS_API_KEY'),
            'RKLABELS_AUTO_ASSIGN_LOCKER' => Configuration::get('RKLABELS_AUTO_ASSIGN_LOCKER'),
            'RKLABELS_ASSIGN_ALL_ORDERS' => Configuration::get('RKLABELS_ASSIGN_ALL_ORDERS'),
            'RKLABELS_STATUS_COLLECTED' => Configuration::get('RKLABELS_STATUS_COLLECTED'),
            'RKLABELS_PIN_VALID_HOURS' => Configuration::get('RKLABELS_PIN_VALID_HOURS'),
            'RKLABELS_TTLOCK_CLIENT_ID' => Configuration::get('RKLABELS_TTLOCK_CLIENT_ID'),
            'RKLABELS_TTLOCK_CLIENT_SECRET' => Configuration::get('RKLABELS_TTLOCK_CLIENT_SECRET'),
            'RKLABELS_TTLOCK_ACCESS_TOKEN' => Configuration::get('RKLABELS_TTLOCK_ACCESS_TOKEN'),
        ];
    }

    /**
     * Hook: New order validated - auto-assign locker
     */
    public function hookActionValidateOrder($params)
    {
        if (!Configuration::get('RKLABELS_AUTO_ASSIGN_LOCKER')) {
            return;
        }

        $this->loadLockerClasses();
        $order = $params['order'];
        
        // Only assign if pickup method selected (customize this logic as needed)
        $carrier = new Carrier($order->id_carrier);
        $isPickup = (strpos(strtolower($carrier->name), 'recogida') !== false
                  || strpos(strtolower($carrier->name), 'pickup') !== false
                  || strpos(strtolower($carrier->name), 'locker') !== false);
        
        if ($isPickup || Configuration::get('RKLABELS_ASSIGN_ALL_ORDERS')) {
            $assignment = RkLockerAssignment::assignToOrder($order->id);
            
            if ($assignment) {
                // Add order note
                $locker = new RkLocker($assignment->id_locker);
                $message = new Message();
                $message->id_order = $order->id;
                $message->private = 1;
                $message->message = sprintf(
                    'Locker asignado automáticamente: %s (%s)',
                    $locker->name,
                    $locker->location
                );
                $message->save();
            }
        }
    }

    /**
     * Hook: Order status update - handle pickup/delivery status changes
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $this->loadLockerClasses();
        $newStatus = $params['newOrderStatus'];
        $order = new Order($params['id_order']);
        
        // Check if status is "delivered" or "picked up"
        $deliveredStates = [
            (int) Configuration::get('PS_OS_DELIVERED'),
            (int) Configuration::get('RKLABELS_STATUS_COLLECTED'),
        ];
        
        if (in_array((int) $newStatus->id, $deliveredStates)) {
            // Mark locker as collected
            $assignment = RkLockerAssignment::getByOrderId($order->id);
            if ($assignment && in_array($assignment->status, ['assigned', 'ready'])) {
                $assignment->markCollected();
            }
        }
        
        return true;
    }

    /**
     * Hook: Display button in order detail page
     */
    public function hookDisplayAdminOrderMain($params)
    {
        $this->loadLockerClasses();
        
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        $address = new Address($order->id_address_delivery);
        $customer = new Customer($order->id_customer);

        // Get locker assignment for this order
        $lockerAssignment = null;
        $lockerLogs = [];
        
        $assignment = RkLockerAssignment::getByOrderId($orderId);
        if ($assignment) {
            $locker = new RkLocker($assignment->id_locker);
            $lockerAssignment = [
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
            ];
            $lockerLogs = RkLockerLog::getByOrderId($orderId, 10);
        }

        $baseUrl = $this->context->link->getAdminLink('AdminRkLabels');

        $this->context->smarty->assign([
            'order_id' => $orderId,
            'print_url' => $baseUrl . '&action=printLabel&id_order=' . $orderId,
            'address' => $address,
            'customer' => $customer,
            // Locker data
            'locker_assignment' => $lockerAssignment,
            'locker_logs' => $lockerLogs,
            'lockers_available' => RkLocker::getAvailableCount(),
            'locker_assign_url' => $baseUrl . '&action=assignLocker&id_order=' . $orderId,
            'locker_ready_url' => $baseUrl . '&action=markReady&id_order=' . $orderId,
            'locker_collected_url' => $baseUrl . '&action=markCollected&id_order=' . $orderId,
            'locker_cancel_url' => $baseUrl . '&action=cancelLocker&id_order=' . $orderId,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/order_button.tpl');
    }

    /**
     * Hook: Display batch button in orders list
     */
    public function hookDisplayAdminOrdersListBefore($params)
    {
        $this->context->smarty->assign([
            'batch_url' => $this->context->link->getAdminLink('AdminRkLabels') . '&action=batchPrint',
        ]);

        return $this->display(__FILE__, 'views/templates/admin/orders_list_button.tpl');
    }
}
