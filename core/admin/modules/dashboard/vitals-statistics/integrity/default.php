<h1>
	<span class="integrity"></span>Site Integrity Check
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>

<div class="form_container">
	<section>
		<p>The site integrity check will search your site for broken/dead links and alert you to their presence should they exist.</p>
		<p>Including external links will take <strong>significantly longer</strong> the integrity check and <strong>may throw false positives</strong>.</p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=true" class="button"><span class="icon_small icon_small_world"></span>Include External Links</a>
		&nbsp;
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=false" class="button"><span class="icon_small icon_small_server"></span>Only Internal Links</a>
	</footer>
</div>

