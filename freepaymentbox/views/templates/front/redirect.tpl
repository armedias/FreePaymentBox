<form method="POST" action="{$pb_url}" id="freepaymentbox_form">
    {foreach $params as $value}
       <input type="hidden" name="{$value@key}" value="{$value}">
    {/foreach}
    <input type="submit" value="Accéder au paiement sécurisé">
</form>
    
<script type="text/javascript">
            {literal}
                    $(document).ready(function() {
                            $('#freepaymentbox_form').submit();
                    });
            {/literal}
</script>
