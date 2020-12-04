<nav class="tabs">
	<ul>
		<li{if $current == 'index'} class="current"{/if}><a href="{$admin_url}config/">Général</a></li>
		<li{if $current == 'categories'} class="current"{/if}><a href="{$admin_url}config/categories/">Catégories de membres</a></li>
		<li{if $current == 'fiches_membres'} class="current"{/if}><a href="{$admin_url}config/membres.php">Fiche des membres</a></li>
		<li{if $current == 'site'} class="current"{/if}><a href="{$admin_url}config/site.php">Site public</a></li>
		<li{if $current == 'donnees'} class="current"{/if}><a href="{$admin_url}config/donnees/">Sauvegarde et restauration</a></li>
		<li{if $current == 'plugins'} class="current"{/if}><a href="{$admin_url}config/plugins.php">Extensions</a></li>
		{if ERRORS_ENABLE_LOG_VIEW}
		<li{if $current == 'logs'} class="current"{/if}><a href="{$admin_url}config/logs.php?type=errors">Journaux</a></li>
		{/if}
	</ul>
</nav>