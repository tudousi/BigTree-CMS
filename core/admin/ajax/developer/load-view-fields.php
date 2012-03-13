<?
	if ($_GET["table"]) {
		$table = $_GET["table"];
	}
	
	$reserved = $admin->ReservedColumns;

	$used = array();
	$unused = array();
	
	$tblfields = array();
	$q = sqlquery("DESCRIBE $table");
	while ($f = sqlfetch($q)) {
		$tblfields[] = $f["Field"];
	}
	
	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used[] = $key;
		}
		// Figure out the fields we're not using so we can offer them back.
		foreach ($tblfields as $field) {
			if (!in_array($field,$reserved) && !in_array($field,$used)) {
				$unused[$field] = ucwords(str_replace("_"," ",$field));
			}
		}		
	}
	
	$preview_field = $view["preview_field"] ? $view["preview_field"] : "id";

	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["module"];
?>
<fieldset id="fields">
	<label>Fields</label>
	
	<div class="form_table">
		<header>
			<a href="#" class="add add_field">Add</a>
			<select id="unused_field" class="custom_control">
				<? foreach ($unused as $field => $title) { ?>
				<option value="<?=htmlspecialchars($title)?>"><?=htmlspecialchars($field)?></option>
				<? } ?>
				<option value="">-- Custom --</option>
			</select>
		</header>
		<div class="labels">
			<span class="developer_view_title">Title</span>
			<span class="developer_view_parser">Parser</span>
			<span class="developer_resource_action">Delete</span>
		</div>
		<ul id="sort_table">
			<?
				// If we're loading an existing data set.
				$mtm_count = 0;
				if (isset($fields)) {
					foreach ($fields as $key => $field) {
						$used[] = $key;
			?>
			<li id="row_<?=$key?>">
				<input type="hidden" name="fields[<?=$key?>][width]" value="<?=$field["width"]?>" />
				<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[<?=$key?>][title]" value="<?=$field["title"]?>" /></section>
				<section class="developer_view_parser"><input type="text" name="fields[<?=$key?>][parser]" value="<?=htmlspecialchars($field["parser"])?>" class="parser" placeholder="PHP code to transform $value (which contains the column value.)" /></section>
				<section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>
			</li>
			<?
					}			
				// Otherwise we're loading a new data set based on a table.
				} else {
					if (!isset($table)) {
						$table = $_POST["table"];
					}
					$q = sqlquery("describe ".$table);
					while ($f = sqlfetch($q)) {
						if (!in_array($f["Field"],$reserved)) {
							$key = $f["Field"];
			?>
			<li id="row_<?=$key?>">
				<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[<?=$key?>][title]" value="<?=htmlspecialchars(ucwords(str_replace("_"," ",$f["Field"])))?>" /></section>
				<section class="developer_view_parser"><input type="text" name="fields[<?=$key?>][parser]" value="" class="parser" /></section>
				<section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>
			</li>
			<?	
						}
					}
				}
			?>
		</ul>
	</div>
</fieldset>
<fieldset>
	<label>Actions <small>(click to deselect)</small></label>
	<ul class="developer_action_list">
		<?
			if (!empty($actions)) {
				foreach ($actions as $action) {
					if ($action != "on") {
						$data = json_decode($action,true);
		?>
		<li>
			<input class="custom_control" type="checkbox" name="actions[<?=$data["route"]?>]" checked="checked" value="<?=htmlspecialchars($action)?>" />
			<a href="#" class="action active">
				<span class="<?=$action["class"]?>"></span>
			</a>
		</li>
		<?
					}
				}
			}
			foreach ($admin->ViewActions as $key => $action) {
				if (in_array($action["key"],$tblfields) || $allow_all_actions) {
					$checked = false;
					if ($actions[$key] || (!isset($actions) && !$allow_all_actions) || ($allow_all_actions && ($key == "edit" || $key == "delete"))) {
						$checked = true;
					}
		?>
		<li>
			<input class="custom_control" type="checkbox" name="actions[<?=$key?>]" value="on" <? if ($checked) { ?>checked="checked" <? } ?>/>
			<a href="#" class="action<? if ($checked) { ?> active<? } ?>">
				<span class="<?=$action["class"]?>"></span>
			</a>
		</li>
		<?
				}
			}
		?>
		<li><a href="#" class="button add_action">Add</a></li>
	</ul>
</fieldset>

<script type="text/javascript">
	var current_editing_key;
	
	$(".form_table .icon_delete").live("click",function() {
		new BigTreeDialog("Delete Resource",'<p class="confirm">Are you sure you want to delete this field?</p>',$.proxy(function() {
			tf = $(this).parents("li").find("section").find("input");
			
			title = tf.val();
			key = tf.attr("name").substr(7);
			key = key.substr(0,key.length-8);
			
			sel = $("#unused_field").get(0);
			sel.options[sel.options.length] = new Option(key,title,false,false);
			$(this).parents("li").remove();
		},this),"delete",false,"OK");
		
		return false;
	});
		
	
	$(".developer_action_list .action").click(function() {
		if ($(this).hasClass("active")) {
			$(this).removeClass("active");
			$(this).prev("input").val("");
		} else {
			$(this).addClass("active");
			$(this).prev("input").val("on");
		}
		
		return false;
	});
		
	$("#field_area .add").click(function() {
		un = $("#unused_field").get(0);
		key = un.options[un.selectedIndex].text;
		title = un.options[un.selectedIndex].value;
		
		if (title) {
			li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
		
			un.remove(un.selectedIndex);
			$("#sort_table").append(li);
			_local_hooks();
		} else {
			new BigTreeDialog("Add Custom Column",'<fieldset><label>Column Key <small>(must be unique)</small></label><input type="text" name="key" /></fieldset><fieldset><label>Column Title</label><input type="text" name="title" /></fieldset>',function(data) {
				key = htmlspecialchars(data.key);
				title = htmlspecialchars(data.title);
				
				li = $('<li id="row_' + key + '">');
				li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
				$("#sort_table").append(li);
				_local_hooks();
			});
		}

		return false;
	});
	
	
	$(".add_action").click(function() {
		new BigTreeDialog("Add Custom Action",'<fieldset><label>Action Name</label><input type="text" name="name" /></fieldset><fieldset><label>Action Image Class <small>(i.e. button_edit)</small></label><input type="text" name="class" /></fieldset><fieldset><label>Action Route</label><input type="text" name="route" /></fieldset><fieldset><label>Link Function <small>(if you need more than simply /route/id/)</small></label><input type="text" name="function" /></fieldset>',function(data) {
			li = $('<li>');
			li.load("<?=$admin_root?>ajax/developer/add-view-action/", data);
			$(".developer_action_list li:first-child").before(li);
		});
		
		return false;
	});
	
	function _local_hooks() {
		$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	}
	
	_local_hooks();
</script>