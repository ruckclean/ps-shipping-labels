<?php
/**
 * Ruckclean Shipping Labels
 * 
 * PrestaShop module for postal shipping label printing
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
        $this->version = '1.3.0';
        $this->author = 'Ruckclean';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ruckclean Shipping Labels');
        $this->description = $this->l('Impresión de etiquetas para envío postal de llaveros NFC');
        $this->confirmUninstall = $this->l('¿Seguro que quieres desinstalar?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('displayAdminOrdersListBefore')
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
            $tab->name[$lang['id_lang']] = 'Etiquetas Envío';
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
            'RKLABELS_PRINTER_TYPE' => 'pdf',
            'RKLABELS_PRINTER_CONNECTION' => 'usb',
            'RKLABELS_PRINTER_IP' => '',
            'RKLABELS_PRINTER_PORT' => '9100',
            'RKLABELS_LABEL_WIDTH' => '100',
            'RKLABELS_LABEL_HEIGHT' => '60',
            'RKLABELS_FONT_SIZE' => '12',
            'RKLABELS_SHOW_SENDER' => '1',
            'RKLABELS_SENDER_NAME' => 'Ruckclean',
            'RKLABELS_SENDER_ADDRESS' => 'Calle Jazmín, 6',
            'RKLABELS_SENDER_POSTCODE' => '28231',
            'RKLABELS_SENDER_CITY' => 'Las Rozas de Madrid',
            'RKLABELS_AUTO_STATUS' => '1',
            'RKLABELS_STATUS_SHIPPED' => '4',
            'RKLABELS_API_KEY' => '',
        ];

        foreach ($defaults as $key => $value) {
            Configuration::updateValue($key, $value);
        }

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
            $output .= $this->displayConfirmation($this->l('Configuración guardada'));
        }

        if (Tools::isSubmit('regenerateApiKey')) {
            Configuration::updateValue('RKLABELS_API_KEY', bin2hex(random_bytes(16)));
            $output .= $this->displayConfirmation($this->l('API Key regenerada'));
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
                    'title' => $this->l('Configuración de Etiquetas'),
                    'icon' => 'icon-print',
                ],
                'tabs' => [
                    'printer' => $this->l('Impresora'),
                    'label' => $this->l('Etiqueta'),
                    'sender' => $this->l('Remitente'),
                    'automation' => $this->l('Automatización'),
                    'api' => $this->l('API'),
                ],
                'input' => [
                    // PRINTER TAB
                    [
                        'type' => 'select',
                        'label' => $this->l('Tipo de impresora'),
                        'name' => 'RKLABELS_PRINTER_TYPE',
                        'tab' => 'printer',
                        'options' => [
                            'query' => [
                                ['id' => 'pdf', 'name' => 'PDF (Universal)'],
                                ['id' => 'escpos', 'name' => 'ESC/POS (Térmica)'],
                                ['id' => 'zpl', 'name' => 'ZPL (Zebra)'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Conexión'),
                        'name' => 'RKLABELS_PRINTER_CONNECTION',
                        'tab' => 'printer',
                        'options' => [
                            'query' => [
                                ['id' => 'usb', 'name' => 'USB (via navegador)'],
                                ['id' => 'network', 'name' => 'Red (IP)'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('IP Impresora'),
                        'name' => 'RKLABELS_PRINTER_IP',
                        'tab' => 'printer',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Puerto'),
                        'name' => 'RKLABELS_PRINTER_PORT',
                        'tab' => 'printer',
                        'class' => 'fixed-width-sm',
                    ],
                    // LABEL TAB
                    [
                        'type' => 'text',
                        'label' => $this->l('Ancho (mm)'),
                        'name' => 'RKLABELS_LABEL_WIDTH',
                        'tab' => 'label',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Alto (mm)'),
                        'name' => 'RKLABELS_LABEL_HEIGHT',
                        'tab' => 'label',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Tamaño fuente'),
                        'name' => 'RKLABELS_FONT_SIZE',
                        'tab' => 'label',
                        'class' => 'fixed-width-sm',
                    ],
                    // SENDER TAB
                    [
                        'type' => 'switch',
                        'label' => $this->l('Mostrar remitente'),
                        'name' => 'RKLABELS_SHOW_SENDER',
                        'tab' => 'sender',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Nombre'),
                        'name' => 'RKLABELS_SENDER_NAME',
                        'tab' => 'sender',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Dirección'),
                        'name' => 'RKLABELS_SENDER_ADDRESS',
                        'tab' => 'sender',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Código postal'),
                        'name' => 'RKLABELS_SENDER_POSTCODE',
                        'tab' => 'sender',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Ciudad'),
                        'name' => 'RKLABELS_SENDER_CITY',
                        'tab' => 'sender',
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
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Estado tras imprimir'),
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
                        'desc' => $this->l('Para operaciones batch y acceso externo'),
                    ],
                ],
                'buttons' => [
                    [
                        'title' => $this->l('Regenerar API Key'),
                        'name' => 'regenerateApiKey',
                        'type' => 'submit',
                        'class' => 'btn btn-default pull-right',
                        'icon' => 'process-icon-refresh',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar'),
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

        $this->context->smarty->assign([
            'order_id' => $orderId,
            'print_url' => $this->context->link->getAdminLink('AdminRkLabels') . '&action=printLabel&id_order=' . $orderId,
            'address' => $address,
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
