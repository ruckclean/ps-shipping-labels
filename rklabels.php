<?php
/**
 * Ruckclean Shipping Labels
 * 
 * PrestaShop module for thermal shipping label printing
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
    public function __construct()
    {
        $this->name = 'rklabels';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Ruckclean';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ruckclean Shipping Labels');
        $this->description = $this->l('Print shipping labels on thermal printers for postal mail');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('displayAdminOrdersListBefore')
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->installTab()
            && $this->installConfig();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTab()
            && $this->uninstallConfig();
    }

    /**
     * Install admin tab
     */
    protected function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminRkLabels';
        $tab->name = [];
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Shipping Labels';
        }
        
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentShipping');
        $tab->module = $this->name;
        
        return $tab->add();
    }

    protected function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminRkLabels');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    /**
     * Install default configuration
     */
    protected function installConfig()
    {
        $defaults = [
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
                    'title' => $this->l('Shipping Labels Configuration'),
                    'icon' => 'icon-cogs',
                ],
                'tabs' => [
                    'printer' => $this->l('Printer'),
                    'label' => $this->l('Label'),
                    'sender' => $this->l('Sender'),
                    'automation' => $this->l('Automation'),
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
                    // AUTOMATION TAB
                    [
                        'type' => 'switch',
                        'label' => $this->l('Auto-change status on print'),
                        'name' => 'RKLABELS_AUTO_STATUS',
                        'tab' => 'automation',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Automatically change order status when label is printed'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Status after printing'),
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
        ];
    }

    /**
     * Hook: Display button in order detail page
     */
    public function hookDisplayAdminOrderMain($params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        $address = new Address($order->id_address_delivery);
        $customer = new Customer($order->id_customer);

        $this->context->smarty->assign([
            'order_id' => $orderId,
            'print_url' => $this->context->link->getAdminLink('AdminRkLabels') . '&action=printLabel&id_order=' . $orderId,
            'address' => $address,
            'customer' => $customer,
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
