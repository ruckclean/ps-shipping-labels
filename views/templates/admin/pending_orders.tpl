{**
 * Pending orders list for batch label printing
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-list"></i> {l s='Pending Orders - Ready for Shipping' mod='rklabels'}
    </div>
    
    <form method="post" action="{$batch_print_url}">
        <div class="panel-body">
            {if $orders && count($orders) > 0}
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th class="text-center">
                                    <input type="checkbox" id="select-all" onclick="toggleAll(this)">
                                </th>
                                <th>{l s='ID' mod='rklabels'}</th>
                                <th>{l s='Reference' mod='rklabels'}</th>
                                <th>{l s='Date' mod='rklabels'}</th>
                                <th>{l s='Customer' mod='rklabels'}</th>
                                <th>{l s='Address' mod='rklabels'}</th>
                                <th>{l s='Status' mod='rklabels'}</th>
                                <th>{l s='Actions' mod='rklabels'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$orders item=order}
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="orderBox[]" value="{$order.id_order}">
                                    </td>
                                    <td>{$order.id_order}</td>
                                    <td><strong>{$order.reference}</strong></td>
                                    <td>{$order.date_add|date_format:"%d/%m/%Y"}</td>
                                    <td>{$order.firstname} {$order.lastname}</td>
                                    <td>
                                        {$order.address1}<br>
                                        <strong>{$order.postcode} {$order.city}</strong>
                                    </td>
                                    <td>{$order.status_name}</td>
                                    <td>
                                        <a href="{$print_url}&action=printLabel&id_order={$order.id_order}" 
                                           class="btn btn-default btn-sm" 
                                           target="_blank"
                                           title="{l s='Print Label' mod='rklabels'}">
                                            <i class="icon-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
                
                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-print"></i> {l s='Print Selected Labels' mod='rklabels'}
                    </button>
                    <span class="help-block" style="display: inline; margin-left: 15px;">
                        {l s='Selected orders will be marked as shipped after printing.' mod='rklabels'}
                    </span>
                </div>
            {else}
                <div class="alert alert-info">
                    {l s='No pending orders found.' mod='rklabels'}
                </div>
            {/if}
        </div>
    </form>
</div>

<script>
function toggleAll(source) {
    var checkboxes = document.querySelectorAll('input[name="orderBox[]"]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>
