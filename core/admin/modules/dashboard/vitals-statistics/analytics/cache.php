<h1>
	<span class="analytics"></span>Caching Data
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<? include BigTree::path($relative_path."_nav.php"); ?>
<div class="form_container">
	<section>
		<p><img src="<?=ADMIN_ROOT?>images/spinner.gif" alt="" /> &nbsp; Please wait while we retrieve your Google Analytics information.</p>
	</section>
</div>
<script type="text/javascript">
	$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/analytics/cache/", { success: function() {
		document.location.href = "<?=$mroot?>";
	}});
</script>