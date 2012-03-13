<?
	$types = $admin->getFieldTypes();
?>
<h1><span class="icon_developer_field_types"></span>Field Types</h1>
<? include BigTree::path("admin/modules/developer/field-types/_nav.php") ?>

<div class="table">
	<summary><h2>Field Types</h2></summary>
	<header>
		<span class="developer_field_types_name">Name</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul>
		<? foreach ($types as $type) { ?>
		<li>
			<section class="developer_field_types_name"><?=$type["name"]?></section>
			<section class="view_action">
				<a href="<?=$section_root?>edit/<?=$type["id"]?>/" class="icon_edit"></a>
			</section>
			<section class="view_action">
				<a href="<?=$section_root?>delete/<?=$type["id"]?>/" class="icon_delete"></a>
			</section>
		</li>
		<? } ?>
	</ul>
</div>

<script type="text/javascript">
	$(".icon_delete").click(function() {
		new BigTreeDialog("Delete Field Type",'<p class="confirm">Are you sure you want to delete this field type?<br /><br />Fields using this type will revert to text fields and your source files will be deleted.</p>',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>