{include file="admin/_head.tpl" title="Extension — `$plugin.nom`" current="plugin_`$plugin.id`"}
{include file="`$plugin_root`/templates/_menu.tpl" current="index"}

{if $error}
    <p class="error">
        {$error|escape}
    </p>
{/if}


{include file="admin/_foot.tpl"}
