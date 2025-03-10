{literal}
<style type="text/css">
.header .top h1 {
	text-align: center;
    text-indent: 1.7em;
}
</style>
{/literal}

{include file="_head.tpl" title="Bonjour %s !"|args:$logged_user->name() current="home"}

{$banner|raw}

{*
<nav class="tabs">
	<aside>
		{button id="homescreen-btn" label="Installer comme application sur l'écran d'accueil" class="hidden" shape="plus"}
	</aside>
	{if $logged_user && $logged_user->exists()}
	<ul>
		<li><a href="{$admin_url}me/">Mes informations personnelles</a></li>
		<li><a href="{$admin_url}me/services.php">Suivi de mes activités et cotisations</a></li>
	</ul>
	{/if}
</nav>

<aside class="describe">
	<h3>{$config.org_name}</h3>
	{if !empty($config.org_address)}
	<p>
		{$config.org_address|escape|nl2br}
	</p>
	{/if}
	{if !empty($config.org_phone)}
	<p>
		Tél. : <a href="tel:{$config.org_phone}">{$config.org_phone}</a>
	</p>
	{/if}
	{if !empty($config.org_email)}
	<p>
		E-Mail : <a href="mailto:{$config.org_email}">{$config.org_email}</a>
	</p>
	{/if}
	{if $site_url}
	<p>
		Web : <a href="{$site_url}" target="_blank">{$site_url}</a>
	</p>
	{/if}
</aside>
*}

{if !$has_extensions && $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
<div class="expose-extensions block">
	<h2>Besoin d'autres fonctionnalités&nbsp;?</h2>
	<p>Découvrez ces extensions dans le menu <strong>Configuration</strong>, onglet <strong>Extensions</strong>&nbsp;:</p>

	<nav class="home">
		<ul>
		{foreach from=$buttons item="button"}
			<li>{$button|raw}</li>
		{/foreach}
		</ul>
	</nav>
</div>
{elseif !empty($buttons)}
	<nav class="home">
		<ul>
		{foreach from=$buttons item="button"}
			<li>{$button|raw}</li>
		{/foreach}
		</ul>
	</nav>
{/if}

{if $homepage}
	<article class="web-content">
		{$homepage|raw}
	</article>
{/if}

<script type="text/javascript" src="{$admin_url}static/scripts/homescreen.js" defer="defer"></script>

{include file="_foot.tpl"}