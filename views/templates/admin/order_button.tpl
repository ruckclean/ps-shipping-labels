{**
 * Button to print label from order detail page
 * Shows shipping info and locker assignment
 *}

<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            <i class="material-icons">local_shipping</i>
            {l s='Etiqueta de Envío' mod='rklabels'}
        </h3>
    </div>
    <div class="card-body">
        <div class="shipping-address mb-3">
            <strong>{$address->firstname} {$address->lastname}</strong><br>
            {if $address->company}{$address->company}<br>{/if}
            {$address->address1}<br>
            {if $address->address2}{$address->address2}<br>{/if}
            <strong>{$address->postcode} {$address->city}</strong>
        </div>
        
        <a href="{$print_url}" class="btn btn-primary" target="_blank">
            <i class="material-icons">print</i>
            {l s='Imprimir Etiqueta' mod='rklabels'}
        </a>
    </div>
</div>

{* Locker Information Card *}
<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            <i class="material-icons">lock</i>
            {l s='Locker de Recogida' mod='rklabels'}
        </h3>
    </div>
    <div class="card-body">
        {if isset($locker_assignment) && $locker_assignment}
            <div class="locker-info">
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>{l s='Locker:' mod='rklabels'}</strong></div>
                    <div class="col-sm-8">{$locker_assignment.locker_name} ({$locker_assignment.locker_location})</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>{l s='Estado:' mod='rklabels'}</strong></div>
                    <div class="col-sm-8">
                        {if $locker_assignment.status == 'assigned'}
                            <span class="badge badge-info">{l s='Asignado' mod='rklabels'}</span>
                        {elseif $locker_assignment.status == 'ready'}
                            <span class="badge badge-warning">{l s='Listo para Recogida' mod='rklabels'}</span>
                        {elseif $locker_assignment.status == 'collected'}
                            <span class="badge badge-success">{l s='Recogido' mod='rklabels'}</span>
                        {elseif $locker_assignment.status == 'cancelled'}
                            <span class="badge badge-danger">{l s='Cancelado' mod='rklabels'}</span>
                        {else}
                            <span class="badge badge-secondary">{$locker_assignment.status}</span>
                        {/if}
                    </div>
                </div>
                {if $locker_assignment.pin_code}
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>{l s='PIN:' mod='rklabels'}</strong></div>
                    <div class="col-sm-8"><code>{$locker_assignment.pin_code}</code></div>
                </div>
                {/if}
                {if $locker_assignment.pin_valid_until}
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>{l s='Válido hasta:' mod='rklabels'}</strong></div>
                    <div class="col-sm-8">{$locker_assignment.pin_valid_until|date_format:"%d/%m/%Y %H:%M"}</div>
                </div>
                {/if}
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>{l s='Asignado:' mod='rklabels'}</strong></div>
                    <div class="col-sm-8">{$locker_assignment.date_assigned|date_format:"%d/%m/%Y %H:%M"}</div>
                </div>
                
                <hr>
                
                <div class="btn-group">
                    {if $locker_assignment.status == 'assigned'}
                        <a href="{$locker_ready_url}" class="btn btn-sm btn-warning" onclick="return confirm('{l s='¿Marcar como listo para recogida?' mod='rklabels'}')">
                            <i class="material-icons">check</i> {l s='Marcar Listo' mod='rklabels'}
                        </a>
                    {/if}
                    {if $locker_assignment.status == 'ready'}
                        <a href="{$locker_collected_url}" class="btn btn-sm btn-success" onclick="return confirm('{l s='¿Marcar como recogido?' mod='rklabels'}')">
                            <i class="material-icons">done_all</i> {l s='Marcar Recogido' mod='rklabels'}
                        </a>
                    {/if}
                    {if in_array($locker_assignment.status, ['assigned', 'ready'])}
                        <a href="{$locker_cancel_url}" class="btn btn-sm btn-danger" onclick="return confirm('{l s='¿Cancelar asignación de locker?' mod='rklabels'}')">
                            <i class="material-icons">close</i> {l s='Cancelar' mod='rklabels'}
                        </a>
                    {/if}
                </div>
            </div>
        {else}
            <div class="alert alert-secondary">
                <i class="material-icons">info</i>
                {l s='No hay locker asignado a este pedido' mod='rklabels'}
            </div>
            {if $lockers_available > 0}
                <a href="{$locker_assign_url}" class="btn btn-sm btn-primary">
                    <i class="material-icons">add</i> {l s='Asignar Locker' mod='rklabels'}
                </a>
                <small class="text-muted">({$lockers_available} disponibles)</small>
            {else}
                <div class="alert alert-warning">
                    <i class="material-icons">warning</i>
                    {l s='No hay lockers disponibles' mod='rklabels'}
                </div>
            {/if}
        {/if}
    </div>
</div>

{* Locker Event Log *}
{if isset($locker_logs) && $locker_logs|count > 0}
<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            <i class="material-icons">history</i>
            {l s='Historial de Locker' mod='rklabels'}
        </h3>
    </div>
    <div class="card-body">
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>{l s='Fecha' mod='rklabels'}</th>
                    <th>{l s='Evento' mod='rklabels'}</th>
                    <th>{l s='Detalles' mod='rklabels'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $locker_logs as $log}
                <tr>
                    <td>{$log.date_add|date_format:"%d/%m/%Y %H:%M"}</td>
                    <td>
                        {if $log.event_type == 'assigned'}
                            <span class="badge badge-info">{l s='Asignado' mod='rklabels'}</span>
                        {elseif $log.event_type == 'ready'}
                            <span class="badge badge-warning">{l s='Listo' mod='rklabels'}</span>
                        {elseif $log.event_type == 'collected'}
                            <span class="badge badge-success">{l s='Recogido' mod='rklabels'}</span>
                        {elseif $log.event_type == 'cancelled'}
                            <span class="badge badge-danger">{l s='Cancelado' mod='rklabels'}</span>
                        {elseif $log.event_type == 'manual_release'}
                            <span class="badge badge-secondary">{l s='Liberado' mod='rklabels'}</span>
                        {else}
                            <span class="badge badge-secondary">{$log.event_type}</span>
                        {/if}
                    </td>
                    <td class="text-muted">
                        {if $log.locker_name}{$log.locker_name}{/if}
                        {if $log.employee_name} - {$log.employee_name}{/if}
                    </td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
{/if}
