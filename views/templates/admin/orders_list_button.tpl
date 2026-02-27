{**
 * Batch print button for orders list
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-print"></i> {l s='Shipping Labels' mod='rklabels'}
    </div>
    <div class="panel-body">
        <a href="{$batch_url}" class="btn btn-default">
            <i class="icon-print"></i> {l s='Print Selected Labels' mod='rklabels'}
        </a>
        <p class="help-block">
            {l s='Select orders and click to print shipping labels in batch.' mod='rklabels'}
        </p>
    </div>
</div>
