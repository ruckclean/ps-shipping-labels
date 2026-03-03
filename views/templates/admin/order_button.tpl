{**
 * Button to print shipping label from order detail page
 *}

<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            <i class="material-icons">local_shipping</i>
            {l s='Etiqueta de Envío Postal' mod='rklabels'}
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
