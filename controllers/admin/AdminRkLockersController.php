<?php
/**
 * Admin Controller for Locker Management
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLocker.php';
require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLockerAssignment.php';
require_once _PS_MODULE_DIR_ . 'rklabels/classes/RkLockerLog.php';

class AdminRkLockersController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'rk_locker';
        $this->className = 'RkLocker';
        $this->identifier = 'id_locker';
        $this->lang = false;
        $this->addRowAction('edit');
        $this->addRowAction('delete');

        parent::__construct();

        $this->fields_list = [
            'id_locker' => [
                'title' => 'ID',
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'name' => [
                'title' => $this->l('Nombre'),
            ],
            'location' => [
                'title' => $this->l('Ubicación'),
            ],
            'ttlock_lock_id' => [
                'title' => $this->l('TTLock ID'),
            ],
            'active' => [
                'title' => $this->l('Activo'),
                'active' => 'status',
                'type' => 'bool',
                'align' => 'center',
                'class' => 'fixed-width-sm',
            ],
            'position' => [
                'title' => $this->l('Posición'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
        ];
    }

    public function renderList()
    {
        // Add dashboard before list
        $dashboard = $this->renderDashboard();
        
        return $dashboard . parent::renderList();
    }

    /**
     * Render visual dashboard with locker status
     */
    protected function renderDashboard()
    {
        $lockers = RkLocker::getAllWithStatus();
        $stats = RkLockerAssignment::getStats();
        $recentLogs = RkLockerLog::getRecent(10);
        $availableCount = RkLocker::getAvailableCount();
        $totalActive = count($lockers);

        // Build locker cards HTML
        $lockerCards = '';
        foreach ($lockers as $locker) {
            $isOccupied = !empty($locker['id_assignment']);
            $statusClass = $isOccupied ? 'panel-danger' : 'panel-success';
            $statusIcon = $isOccupied ? 'icon-lock' : 'icon-unlock';
            $statusText = $isOccupied ? 'Ocupado' : 'Disponible';
            
            $orderLink = '';
            $customerInfo = '';
            $dateInfo = '';
            
            if ($isOccupied) {
                $orderUrl = $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $locker['id_order'] . '&vieworder';
                $orderLink = '<a href="' . $orderUrl . '" class="btn btn-default btn-sm" target="_blank">'
                           . '<i class="icon-external-link"></i> Pedido #' . $locker['order_reference']
                           . '</a>';
                $customerInfo = '<p class="text-muted"><small>' . htmlspecialchars($locker['customer_name']) . '</small></p>';
                $assignmentStatus = $locker['assignment_status'] === 'ready' 
                    ? '<span class="label label-warning">Listo para recogida</span>' 
                    : '<span class="label label-info">Asignado</span>';
                $dateInfo = '<p class="text-muted"><small>Desde: ' . date('d/m/Y H:i', strtotime($locker['date_assigned'])) . '</small></p>';
            }
            
            $releaseBtn = $isOccupied 
                ? '<a href="' . $this->context->link->getAdminLink('AdminRkLockers') . '&action=release&id_locker=' . $locker['id_locker'] . '" class="btn btn-warning btn-xs" onclick="return confirm(\'¿Liberar este locker?\')"><i class="icon-unlock-alt"></i> Liberar</a>'
                : '';

            $lockerCards .= '
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="panel ' . $statusClass . '">
                    <div class="panel-heading">
                        <i class="' . $statusIcon . '"></i> ' . htmlspecialchars($locker['name']) . '
                        <span class="badge pull-right">' . $statusText . '</span>
                    </div>
                    <div class="panel-body" style="min-height: 120px;">
                        <p><strong>' . htmlspecialchars($locker['location']) . '</strong></p>
                        ' . ($isOccupied ? $assignmentStatus : '') . '
                        ' . $customerInfo . '
                        ' . $dateInfo . '
                        <div class="btn-group">
                            ' . $orderLink . '
                            ' . $releaseBtn . '
                        </div>
                    </div>
                </div>
            </div>';
        }

        // Build recent activity table
        $activityRows = '';
        foreach ($recentLogs as $log) {
            $eventLabel = RkLockerLog::getEventLabel($log['event_type']);
            $eventClass = $this->getEventClass($log['event_type']);
            
            $activityRows .= '<tr>
                <td>' . date('d/m/Y H:i', strtotime($log['date_add'])) . '</td>
                <td>' . htmlspecialchars($log['locker_name'] ?? '-') . '</td>
                <td><span class="label ' . $eventClass . '">' . $eventLabel . '</span></td>
                <td>' . ($log['order_reference'] ? '<a href="' . $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $log['id_order'] . '&vieworder" target="_blank">#' . $log['order_reference'] . '</a>' : '-') . '</td>
                <td>' . htmlspecialchars($log['employee_name'] ?? 'Sistema') . '</td>
            </tr>';
        }

        $html = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-dashboard"></i> Dashboard de Lockers
            </div>
            <div class="panel-body">
                <!-- Stats Row -->
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="panel panel-primary">
                            <div class="panel-body text-center">
                                <h1>' . $availableCount . '/' . $totalActive . '</h1>
                                <p>Lockers Disponibles</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="panel panel-info">
                            <div class="panel-body text-center">
                                <h1>' . ($stats['by_status']['assigned'] ?? 0) . '</h1>
                                <p>Asignados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="panel panel-warning">
                            <div class="panel-body text-center">
                                <h1>' . ($stats['by_status']['ready'] ?? 0) . '</h1>
                                <p>Listos para Recogida</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="panel panel-success">
                            <div class="panel-body text-center">
                                <h1>' . $stats['collected_today'] . '</h1>
                                <p>Recogidos Hoy</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Locker Cards -->
                <h4><i class="icon-th-large"></i> Estado de Lockers</h4>
                <div class="row">
                    ' . $lockerCards . '
                </div>

                <!-- Recent Activity -->
                <h4><i class="icon-time"></i> Actividad Reciente</h4>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Locker</th>
                            <th>Evento</th>
                            <th>Pedido</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . ($activityRows ?: '<tr><td colspan="5" class="text-center text-muted">Sin actividad reciente</td></tr>') . '
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <i class="icon-cogs"></i> Configuración de Lockers
                <span class="panel-heading-action">
                    <a href="' . $this->context->link->getAdminLink('AdminRkLockers') . '&addrt_locker" class="btn btn-default btn-sm">
                        <i class="icon-plus"></i> Añadir Locker
                    </a>
                </span>
            </div>
        </div>';

        return $html;
    }

    protected function getEventClass($eventType)
    {
        $classes = [
            'assigned' => 'label-info',
            'ready' => 'label-warning',
            'collected' => 'label-success',
            'cancelled' => 'label-danger',
            'expired' => 'label-default',
            'manual_release' => 'label-default',
        ];
        
        return $classes[$eventType] ?? 'label-default';
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Locker'),
                'icon' => 'icon-lock',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Nombre'),
                    'name' => 'name',
                    'required' => true,
                    'hint' => $this->l('Ej: Locker 1, Caja A'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Ubicación'),
                    'name' => 'location',
                    'hint' => $this->l('Descripción de la ubicación física'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('TTLock Lock ID'),
                    'name' => 'ttlock_lock_id',
                    'hint' => $this->l('ID del candado en la API TTLock'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Posición'),
                    'name' => 'position',
                    'class' => 'fixed-width-sm',
                    'hint' => $this->l('Orden de prioridad para asignación round-robin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Activo'),
                    'name' => 'active',
                    'is_bool' => true,
                    'values' => [
                        ['id' => 'on', 'value' => 1, 'label' => $this->l('Sí')],
                        ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Guardar'),
            ],
        ];

        return parent::renderForm();
    }

    /**
     * Handle release action
     */
    public function postProcess()
    {
        if (Tools::getValue('action') === 'release' && Tools::getValue('id_locker')) {
            $idLocker = (int) Tools::getValue('id_locker');
            $assignment = RkLockerAssignment::getActiveByLockerId($idLocker);
            
            if ($assignment) {
                $assignment->cancel('Liberación manual desde panel');
                
                // Log manual release
                RkLockerLog::add($idLocker, $assignment->id_order, $assignment->id, 'manual_release', [
                    'released_by' => $this->context->employee->email,
                ]);
                
                $this->confirmations[] = $this->l('Locker liberado correctamente');
            } else {
                $this->errors[] = $this->l('No hay asignación activa para este locker');
            }
        }

        return parent::postProcess();
    }

    /**
     * Set default values for new locker
     */
    public function getFieldsValue($obj)
    {
        $values = parent::getFieldsValue($obj);
        
        if (!$obj->id) {
            // Defaults for new locker
            $values['active'] = 1;
            
            // Get next position
            $maxPos = Db::getInstance()->getValue(
                'SELECT MAX(position) FROM ' . _DB_PREFIX_ . 'rk_locker'
            );
            $values['position'] = (int) $maxPos + 1;
        }
        
        return $values;
    }
}
