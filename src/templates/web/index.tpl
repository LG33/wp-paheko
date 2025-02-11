{include file="_head.tpl" title="Site web" current="web" hide_title=true}

<div style="display: flex;flex-direction: column;align-items: stretch;position: absolute;width: 100%;height: calc(100vh - 1em);">

<div style="display: flex;align-items: center;gap: 0.6em;">
	<h4>Page à modifier :</h4>
	{dropdown value=$GET.id options=$pages title="Sélectionner une page à modifier" style="flex: 1"}
	<a href={$wp_admin_url} target="_blank" data-icon="🌐" class="icn-btn"><span>Ouvrir Wordpress</span></a>
</div>

<iframe src={$wp_admin_url}{if $_GET.id == 'new'}post-new.php?post_type=page{else}post.php?post={$_GET.id}&action=edit{/if} frameborder="0" style="flex: 1;margin: 1em -1em 0 0;border-top: 1px solid lightgray;border-left: 1px solid lightgray;border-top-left-radius: 1em;"></iframe>

</div>

{literal}
<style>
#user-btn {
	display: none;
}
</style>
{/literal}

{include file="_foot.tpl"}