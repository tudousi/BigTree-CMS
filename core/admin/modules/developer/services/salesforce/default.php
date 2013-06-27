<div class="container">
	<?
		$salesforce = new BigTreeSalesforceAPI;
		if (!$salesforce->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/salesforce/activate/" class="module">	
		<section>
			<p>To activate the Salesforce API class you must follow these steps:</p>
			<hr />
			<ol>
				<li>Make sure that your site is accessible via HTTPS. Salesforce requires your Callback URL to be https://</li>
				<li>Create a Salesforce "Connected App" by logging into your Salesforce control panel and heading to "Build &raquo; Create &raquo; Apps" and clicking the "New" button at the bottom by "Connected Apps".</li>
				<li>Check off "Enable OAuth Settings"</li>
				<li>Set the Callback URL to <?=str_replace("http://","https://",DEVELOPER_ROOT)?>services/salesforce/return/</li>
				<li>Select all the available OAuth Scopes and Save your application.</li>
				<li>Enter the application's "Consumer Key" and "Consumer Secret" below.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your Salesforce account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Consumer Key</label>
				<input type="text" name="key" value="<?=htmlspecialchars($twitter->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Consumer Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($twitter->Settings["secret"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Salesforce API" />
		</footer>
	</form>
	<?
		} else {
	?>
	<section>
		<p>Currently connected to your account:</p>
		<div class="api_account_block">
			<img src="<?=$salesforce->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$salesforce->Settings["user_name"]?></strong>
			#<?=$salesforce->Settings["user_id"]?>
		</div>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/salesforce/disconnect/" class="button red">Disconnect</a>
	</footer>
	<?
		}
	?>
</div>