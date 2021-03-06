<?php
	// Turn off errors
	ini_set("log_errors",false);
	error_reporting(0); 
	
	// Allow for passing in $_POST via command line for automatic installs.
	if (isset($argv) && count($argv) > 1) {
		// Cut off the first argument.
		$vars = array_slice($argv, 1);
		// Loop through the variables passed in.
		foreach ($vars as $v) {
			list($key,$val) = explode("=",$v);
			$_POST[$key] = $val;
		}
	}
	
	//!Server Parameters
	$warnings = array();
	if (!extension_loaded('json')) {
		$warnings[] = "JSON Extension is missing (this could affect API and Foundry usage).";
	}
	if (!extension_loaded("mysql")) {
		$warnings[] = "MySQL Extension is missing (this is a FATAL ERROR).";
	}
	if (get_magic_quotes_gpc()) {
		$warnings[] = "magic_quotes_gpc is on. BigTree will attempt to override this at runtime but it is advised that you turn it off in php.ini.";
	}
	if (!ini_get('file_uploads')) {
		$warnings[] = "PHP does not have file uploads enabled. This will severely limit BigTree's functionality.";
	}
	if (!ini_get('short_open_tag')) {
		$warnings[] = "PHP does not currently allow short_open_tags. BigTree will attempt to override this at runtime but you may need to enable it in php.ini manually.";
	}
	if (!extension_loaded('gd')) {
		$warnings[] = "PHP does not have the GD library enabled. This will severely limit your ability to do anything with images in BigTree.";
	}
	if (intval(ini_get('upload_max_filesize')) < 4) {
		$warnings[] = "Max upload filesize is currently less than 4MB. 8MB or higher is recommended.";
	}
	if (intval(ini_get('upload_max_filesize')) < 4) {
		$warnings[] = "Max upload filesize (upload_max_filesize in php.ini) is currently less than 4MB. 8MB or higher is recommended.";
	}
	if (intval(ini_get('post_max_size')) < 4) {
		$warnings[] = "Max POST size (post_max_size in php.ini) is currently less than 4MB. 8MB or higher is recommended.";
	}
	if (intval(ini_get("memory_limit")) < 32) {
		$warnings[] = "PHP's memory limit is currently under 32MB. BigTree recommends at least 32MB of memory be available to PHP.";
	}

	if (function_exists("apache_get_modules")) {
		$apache_modules = apache_get_modules();
		if (in_array('mod_rewrite', $apache_modules) === false) {
			$warnings[] = "BigTree requires Apache to have mod_rewrite installed (this is a FATAL ERROR).";
		}
	}

	// Clean all post variables up, prevent SESSION hijacking.
	foreach ($_POST as $key => $val) {
		if (substr($key,0,1) != "_") {
			$$key = $val;
		}
	}
	
	$success = false;
	$installed = false;

	if (count($_POST) && !($db && $host && $user && $password && $cms_user && $cms_pass)) {
		$error = "Errors found! Please fix the highlighted fields before submitting.";
	} elseif (!is_writable(".")) {
		$error = "Please make the current working directory writable.";
	} elseif (count($_POST)) {
		if ($write_host && $write_user && $write_password) {
			$con = @mysql_connect($write_host,$write_user,$write_password,$db);
		} else {
			$con = @mysql_connect($host,$user,$password);
		}
		if (!$con) {
			$error = "Could not connect to database.";
		} else {
			$select = mysql_select_db($db, $con);
			if (!$select) {
				$error = "Could not select database &ldquo;$db&rdquo;.";
			}
		}
	}
	
	if (!$error && count($_POST)) {
		
		$find = array(
			"[host]",
			"[db]",
			"[user]",
			"[password]",
			"[write_host]",
			"[write_db]",
			"[write_user]",
			"[write_password]",
			"[domain]",
			"[wwwroot]",
			"[staticroot]",
			"[email]",
			"[settings_key]",
			"[force_secure_login]",
			"[routing]"
		);
		
		// Let domain/www_root/static_root be set by post for command line installs
		if (!isset($domain)) {
			$domain = "http://".$_SERVER["HTTP_HOST"];
			if ($routing == "basic") {
				$static_root = $domain.str_replace("install.php","",$_SERVER["REQUEST_URI"])."site/";
				$www_root = $static_root."index.php/";
			} else {
				$www_root = $domain.str_replace("install.php","",$_SERVER["REQUEST_URI"]);
				$static_root = $www_root;
			}
		}	
		
		$replace = array(
			$host,
			$db,
			$user,
			$password,
			(isset($loadbalanced)) ? $write_host : "",
			(isset($loadbalanced)) ? $write_db : "",
			(isset($loadbalanced)) ? $write_user : "",
			(isset($loadbalanced)) ? $write_password : "",
			$domain,
			$www_root,
			$static_root,
			$cms_user,
			$settings_key,
			(isset($force_secure_login)) ? "true" : "false",
			($routing == "basic") ? "basic" : "htaccess"
		);
		
		$sql_queries = explode("\n",file_get_contents("bigtree.sql"));
		foreach ($sql_queries as $query) {
			$query = trim($query);
			if ($query != "") {
				$q = mysql_query($query);
			}
		}
		
		mysql_query("UPDATE bigtree_pages SET id = '0' WHERE id = '1'");
		
		include "core/inc/lib/PasswordHash.php";
		$phpass = new PasswordHash(8, TRUE);
		$enc_pass = mysql_real_escape_string($phpass->HashPassword($cms_pass));
		mysql_query("INSERT INTO bigtree_users (`email`,`password`,`name`,`level`) VALUES ('$cms_user','$enc_pass','Developer','2')");
		
		function bt_mkdir_writable($dir) {
			global $root;
			mkdir($root.$dir);
			chmod($root.$dir,0777);
		}
		
		function bt_touch_writable($file,$contents = "") {
			file_put_contents($file,$contents);
			chmod($file,0777);
		}
		
		function bt_copy_dir($from,$to) {
			global $root;
			$d = opendir($root.$from);
			if (!file_exists($root.$to)) {
				@mkdir($root.$to);
				@chmod($root.$to,0777);
			}
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					if (is_dir($root.$from.$f)) {
						bt_copy_dir($from.$f."/",$to.$f."/");
					} else {
						@copy($from.$f,$to.$f);
						@chmod($to.$f,0777);
					}
				}
			}
		}
		
		$root = "";
		
		bt_mkdir_writable("cache/");
		bt_mkdir_writable("custom/");
		bt_mkdir_writable("custom/admin/");
		bt_mkdir_writable("custom/admin/ajax/");
		bt_mkdir_writable("custom/admin/css/");
		bt_mkdir_writable("custom/admin/images/");
		bt_mkdir_writable("custom/admin/images/modules/");
		bt_mkdir_writable("custom/admin/images/templates/");
		bt_mkdir_writable("custom/admin/modules/");
		bt_mkdir_writable("custom/admin/pages/");
		bt_mkdir_writable("custom/admin/form-field-types/");
		bt_mkdir_writable("custom/admin/form-field-types/draw/");
		bt_mkdir_writable("custom/admin/form-field-types/process/");
		bt_mkdir_writable("custom/inc/");
		bt_mkdir_writable("custom/inc/modules/");
		bt_mkdir_writable("custom/inc/required/");
		bt_mkdir_writable("site");
		bt_mkdir_writable("site/css/");
		bt_mkdir_writable("site/files/");
		bt_mkdir_writable("site/files/pages/");
		bt_mkdir_writable("site/files/resources/");
		bt_mkdir_writable("site/images/");
		bt_mkdir_writable("site/js/");
		bt_mkdir_writable("templates");
		bt_mkdir_writable("templates/ajax/");
		bt_mkdir_writable("templates/layouts/");
		bt_touch_writable("templates/layouts/_header.php");
		bt_touch_writable("templates/layouts/default.php",'<? include "_header.php" ?>
<?=$bigtree["content"]?>
<? include "_footer.php" ?>');
		bt_touch_writable("templates/layouts/_footer.php");
		bt_mkdir_writable("templates/routed/");
		bt_mkdir_writable("templates/basic/");
		bt_touch_writable("templates/basic/_404.php","<h1>404 - Page Not Found</h1>");
		bt_touch_writable("templates/basic/_sitemap.php","<h1>Sitemap</h1>");
		bt_touch_writable("templates/basic/home.php");
		bt_touch_writable("templates/basic/content.php",'<h1><?=$page_header?></h1>
<?=$page_content?>');
		bt_mkdir_writable("templates/callouts/");
		
		bt_touch_writable("templates/config.php",str_replace($find,$replace,file_get_contents("core/config.example.php")));
		
		
		// Create site/index.php, site/.htaccess, and .htaccess (masks the 'site' directory)
		bt_touch_writable("site/index.php",'<?
	// Setup the BigTree variable "namespace"
	$bigtree = array();
	
	$bigtree["config"] = array();
	$bigtree["config"]["debug"] = false;
	include str_replace("site/index.php","templates/config.php",strtr(__FILE__, "\\\\", "/"));
	$bigtree["config"] = isset($config) ? $config : $bigtree["config"]; // Backwards compatibility
	$bigtree["config"]["debug"] = isset($debug) ? $debug : $bigtree["config"]["debug"]; // Backwards compatibility

	// For shared core setups
	$server_root = str_replace("site/index.php","",strtr(__FILE__, "\\\\", "/"));
	
	if (isset($bigtree["config"]["routing"]) && $bigtree["config"]["routing"] == "basic") {
		if (!isset($_SERVER["PATH_INFO"])) {
			$bigtree["path"] = array();
		} else {
			$bigtree["path"] = explode("/",trim($_SERVER["PATH_INFO"],"/"));
		}
	} else {
		if (!isset($_GET["bigtree_htaccess_url"])) {
			$_GET["bigtree_htaccess_url"] = "";
		}
	
		$bigtree["path"] = explode("/",rtrim($_GET["bigtree_htaccess_url"],"/"));
	}
	$path = $bigtree["path"]; // Backwards compatibility
	
	// Let admin bootstrap itself.  New setup here so the admin can live at any path you choose for obscurity.
	$parts_of_admin = explode("/",trim(str_replace($bigtree["config"]["www_root"],"",$bigtree["config"]["admin_root"]),"/"));
	$in_admin = true;
	$x = 0;

	// Go through each route, make sure the path matches the admin\'s route paths.
	if (count($bigtree["path"]) < count($parts_of_admin)) {
		$in_admin = false;
	} else {
		foreach ($parts_of_admin as $part) {
			if ($part != $bigtree["path"][$x])	{
				$in_admin = false;
			}
			$x++;
		}
	}
	
	// If we are in the admin, let it bootstrap itself.
	if ($in_admin) {
		// Cut off additional routes from the path, some parts of the admin assume path[0] is "admin" and path[1] begins the routing.
		if ($x > 1) {
			$bigtree["path"] = array_slice($bigtree["path"],$x - 1);
		}
		if (file_exists("../custom/admin/router.php")) {
			include "../custom/admin/router.php";
		} else {
			include "../core/admin/router.php";
		}
		die();
	}
	
	// We\'re not in the admin, see if caching is enabled and serve up a cached page if it exists
	if ($bigtree["config"]["cache"] && $bigtree["path"][0] != "_preview" && $bigtree["path"][0] != "_preview-pending") {
		$cache_location = $_GET["bigtree_htaccess_url"];
		if (!$cache_location) {
			$cache_location = "!";
		}
		$file = "../cache/".base64_encode($cache_location).".page";
		// If the file is at least 5 minutes fresh, serve it up.
		clearstatcache();
		if (file_exists($file) && filemtime($file) > (time()-300)) {
			// If the web server supports X-Sendfile headers, use that instead of taking up memory by opening the file and echoing it.
			if ($bigtree["config"]["xsendfile"]) {
				header("X-Sendfile: ".str_replace("site/index.php","",strtr(__FILE__, "\\\\", "/"))."cache/".base64_encode($cache_location).".page");
				header("Content-Type: text/html");
				die();
			// Fall back on file_get_contents otherwise.
			} else {
				die(file_get_contents($file));
			}
		}
	}
	
	// Clean up the variables we set.
	unset($config,$debug,$in_admin,$parts_of_admin,$x);

	// Bootstrap BigTree
	if (file_exists("../custom/bootstrap.php")) {
		include "../custom/bootstrap.php";
	} else {
		include "../core/bootstrap.php";
	}
	// Route BigTree
	if (file_exists("../custom/router.php")) {
		include "../custom/router.php";
	} else {
		include "../core/router.php";
	}
?>');
		
		if ($routing == "advanced") {
			bt_touch_writable("site/.htaccess",'<IfModule mod_deflate.c>
  # force deflate for mangled headers developer.yahoo.com/blogs/ydn/posts/2010/12/pushing-beyond-gzipping/
  <IfModule mod_setenvif.c>
	<IfModule mod_headers.c>
	  SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s,?\s(gzip|deflate)?|X{4,13}|~{4,13}|-{4,13})$ HAVE_Accept-Encoding
	  RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding
	</IfModule>
  </IfModule>
  
  # html, txt, css, js, json, xml, htc:
  <IfModule filter_module>
   FilterDeclare   COMPRESS
   FilterProvider  COMPRESS  DEFLATE resp=Content-Type /text/(html|css|javascript|plain|x(ml|-component))/
   FilterProvider  COMPRESS  DEFLATE resp=Content-Type /application/(javascript|json|xml|x-javascript)/
   FilterChain	 COMPRESS
   FilterProtocol  COMPRESS  change=yes;byteranges=no
 </IfModule>
 
 # Legacy versions of Apache
 <IfModule !mod_filter.c>
   AddOutputFilterByType DEFLATE text/html text/plain text/css application/json
   AddOutputFilterByType DEFLATE text/javascript application/javascript application/x-javascript 
   AddOutputFilterByType DEFLATE text/xml application/xml text/x-component
 </IfModule>
 
 # webfonts and svg:
 <FilesMatch "\.(ttf|otf|eot|svg)$">
   SetOutputFilter DEFLATE
 </FilesMatch>
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/gif "access plus 1 month"
  ExpiresByType image/png "access plus 1 month"
  ExpiresByType image/jpeg "access plus 1 month"
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType text/javascript "access plus 1 month"
  ExpiresByType application/x-javascript "access plus 1 month"
  ExpiresByType application/x-shockwave-flash "access plus 1 month"
  
  ExpiresByType application/vnd.ms-fontobject "access plus 1 month"
  ExpiresByType font/ttf "access plus 1 month"
  ExpiresByType font/otf "access plus 1 month"
  ExpiresByType font/x-woff "access plus 1 month"
  ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

<IfModule mod_headers.c>
  <FilesMatch "\.(ttf|otf|eot|woff)$">
	Header set Access-Control-Allow-Origin "*"
  </FilesMatch>
</IfModule>

IndexIgnore */*

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]

RewriteRule .* - [E=HTTP_IF_MODIFIED_SINCE:%{HTTP:If-Modified-Since}]

php_flag short_open_tag On
php_flag magic_quotes_gpc Off');
			
		} elseif ($routing == "simple") {
			bt_touch_writable("site/.htaccess",'IndexIgnore */*

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?bigtree_htaccess_url=$1 [QSA,L]

RewriteRule .* - [E=HTTP_IF_MODIFIED_SINCE:%{HTTP:If-Modified-Since}]');
		} else {
			bt_touch_writable("index.php",'<? header("Location: site/index.php/"); ?>');
		}
		
		if ($routing != "basic") {
			bt_touch_writable(".htaccess",'RewriteEngine On
RewriteRule ^$ site/ [L]
RewriteRule (.*) site/$1 [L]');
		}
		
		// Install the example site if they asked for it.
		if ($install_example_site) {
			bt_copy_dir("core/example-site/","");
			$sql_queries = explode("\n",file_get_contents("example-site.sql"));
			foreach ($sql_queries as $query) {
				$query = trim($query);
				if ($query != "") {
					$q = mysql_query($query);
				}
			}
			
			// Update the config file with CSS/Javascript for the example site.
			$config_data = str_replace($find,$replace,file_get_contents("templates/config.php")); 
			$config_data = str_replace('// "javascript_file.js"','"jquery-1.7.1.min.js",
		"jquery.ba-dotimeout.min.js",
		"jquery.breakpoints.js",
		"main.js"',$config_data);
			$config_data = str_replace('// "style_sheet.css"','"griddle.css",
		"master.css"',$config_data);
			$config_data = str_replace('$bigtree["config"]["css"]["prefix"] = false;','$bigtree["config"]["css"]["prefix"] = true;',$config_data);
			file_put_contents("templates/config.php",$config_data);
		}

		$installed = true;
	}
	
	// Set localhost as the default MySQL host
	if (!$host) {
		$host = "localhost";
	}
	
	// Make a random settings key
	if (!$settings_key) {
		$settings_key = uniqid("",true);
	}
?>
<!doctype html> 
<!--[if lt IE 7 ]> <html lang="en" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>	<html lang="en" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>	<html lang="en" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>	<html lang="en" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en" class="no-js"> <!--<![endif]-->
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title>Install BigTree 4.0RC2</title>
		<?php if ($installed) { ?>
		<link rel="stylesheet" href="<?=$www_root?>admin/css/main.css" type="text/css" media="all" />
		<?php } else { ?>
		<link rel="stylesheet" href="core/admin/css/main.css" type="text/css" media="all" />
		<script src="core/admin/js/lib.js"></script>
		<script src="core/admin/js/main.js"></script>
		<?php } ?>
	</head>
	<body class="install">
		<div class="install_wrapper">
			<?php if ($installed) { ?>
			<h1>BigTree 4.0RC2 Installed</h1>
			<form method="post" action="" class="module">
				<h2 class="getting_started"><span></span>Installation Complete</h2>
				<fieldset class="clear">
					<p>Your new BigTree site is ready to go! Login to the CMS using your newly created account.</p>
					<? if ($routing == "basic") { ?>
					<p class="delete_message">Remember to delete install.php from your root folder as it is publicly accessible in Basic Routing mode.</p>
					<? } ?>
				</fieldset>
				
				<hr />
				
				<h2>Public Site</h2>
				<fieldset class="clear">
					<p><small>URL</small><a href="<?php echo $www_root; ?>"><?php echo $www_root; ?></a></p>
				</fieldset>
				<br /><br />
				<h2>Administration Area</h2>
				<fieldset class="clear">
					<p>
						<small>URL</small><a href="<?php echo $www_root."admin/"; ?>"><?php echo $www_root."admin/"; ?></a><br />
						<small>EMAIL</small><?php echo $cms_user; ?><br />
						<small>PASSWORD</small><?php for ($i = 0, $count = strlen($cms_pass); $i < $count; $i++) { echo "*"; } ?><br />
					</p>
				</fieldset>
				
				<br class="clear" /><br />
			</form>
			<?php } else { ?>
			<h1>Install BigTree 4.0RC2</h1>
			<form method="post" action="" class="module">
				<h2 class="getting_started"><span></span>Getting Started</h2>
				<fieldset class="clear">
					<p>Welcome to the BigTree installer. If you have not done so already, please make the current working directory writable and create a MySQL database for your new BigTree powered site.</p>
					<br />
				</fieldset>
				<?php
					if (count($warnings)) {
						echo '<br />';
						foreach ($warnings as $warning) {
				?>
				<p class="warning_message clear"><?php echo $warning?></p>
				<?php
						}
					}
					
					if ($error) {
				?>
				<p class="error_message clear"><?php echo $error?></p>
				<?php
					}
				?>
				<hr />
				
				<h2 class="database"><span></span>Database Properties</h2>
				<fieldset class="clear">
					<p>Enter your MySQL database information below.</p>
					<br />
				</fieldset>
				<fieldset class="left<?php if (count($_POST) && !$host) { ?> form_error<?php } ?>">
					<label>Hostname</label>
					<input class="text" type="text" id="db_host" name="host" value="<?php echo htmlspecialchars($host) ?>" tabindex="1" />
				</fieldset>
				<fieldset class="right<?php if (count($_POST) && !$db) { ?> form_error<?php } ?>">
					<label>Database</label>
					<input class="text" type="text" id="db_name" name="db" value="<?php echo htmlspecialchars($db) ?>" tabindex="2" />
				</fieldset>
				<br class="clear" /><br />
				<fieldset class="left<?php if (count($_POST) && !$user) { ?> form_error<?php } ?>">
					<label>Username</label>
					<input class="text" type="text" id="db_user" name="user" value="<?php echo htmlspecialchars($user) ?>" tabindex="3" />
				</fieldset>
				<fieldset class="right<?php if (count($_POST) && !$password) { ?> form_error<?php } ?>">
					<label>Password</label>
					<input class="text" type="password" id="db_pass" name="password" value="<?php echo htmlspecialchars($password) ?>" tabindex="4" />
				</fieldset>
				<fieldset>
					<br />
					<input type="checkbox" class="checkbox" name="loadbalanced" id="loadbalanced"<?php if ($loadbalanced) { ?> checked="checked"<?php } ?> tabindex="5" />
					<label class="for_checkbox">Load Balanced MySQL</label>
				</fieldset>
				
				<div id="loadbalanced_settings"<?php if (!$loadbalanced) { ?> style="display: none;"<?php } ?>>
					<hr />
					
					<h2 class="database"><span></span>Write Database Properties</h2>
					<fieldset class="clear">
						<p>If you are hosting a load balanced setup with multiple MySQL servers, enter the master write server information below.</p>
						<br />
					</fieldset>
					<fieldset class="left<?php if (count($_POST) && !$write_host) { ?> form_error<?php } ?>">
						<label>Hostname</label>
						<input class="text" type="text" id="db_write_host" name="write_host" value="<?php echo htmlspecialchars($host) ?>" tabindex="6" />
					</fieldset>
					<fieldset class="right<?php if (count($_POST) && !$write_db) { ?> form_error<?php } ?>">
						<label>Database</label>
						<input class="text" type="text" id="db_write_name" name="write_db" value="<?php echo htmlspecialchars($db) ?>" tabindex="7" />
					</fieldset>
					<br class="clear" /><br />
					<fieldset class="left<?php if (count($_POST) && !$write_user) { ?> form_error<?php } ?>">
						<label>Username</label>
						<input class="text" type="text" id="db_write_user" name="write_user" value="<?php echo htmlspecialchars($user) ?>" tabindex="8" />
					</fieldset>
					<fieldset class="right<?php if (count($_POST) && !$write_password) { ?> form_error<?php } ?>">
						<label>Password</label>
						<input class="text" type="password" id="db_write_pass" name="write_password" value="<?php echo htmlspecialchars($password) ?>" tabindex="9" />
					</fieldset>
					<br class="clear" />
					<br />
				</div>
				
				<hr />
				
				<h2 class="security"><span></span>Site Security</h2>
				<fieldset class="clear">
					<p>Customize your site's security settings below.</p>
					<br />
				</fieldset>
				<fieldset class="left<?php if (count($_POST) && !$settings_key) { ?> form_error<?php } ?>">
					<label>Settings Encryption Key</label>
					<input class="text" type="text" name="settings_key" id="settings_key" value="<?php echo htmlspecialchars($settings_key) ?>" tabindex="10" />
				</fieldset>
				<fieldset class="clear">
					<br />
					<input type="checkbox" class="checkbox" name="force_secure_login" id="force_secure_login"<?php if ($force_secure_login) { ?> checked="checked"<?php } ?> tabindex="11" />
					<label class="for_checkbox">Force HTTPS Logins</label>
				</fieldset>
				
				<hr />
				
				<h2 class="account"><span></span>Administrator Account</h2>
				<fieldset class="clear">
					<p>Create the default account your administration area.</p>
					<br />
				</fieldset>
				<fieldset class="left<?php if (count($_POST) && !$cms_user) { ?> form_error<?php } ?>">
					<label>Email Address</label>
					<input class="text" type="text" id="cms_user" name="cms_user" value="<?php echo htmlspecialchars($cms_user) ?>" tabindex="12" />
				</fieldset>
				<fieldset class="right<?php if (count($_POST) && !$cms_pass) { ?> form_error<?php } ?>">
					<label>Password</label>
					<input class="text" type="password" id="cms_pass" name="cms_pass" value="<?php echo htmlspecialchars($cms_pass) ?>" tabindex="13" />
				</fieldset>
				
				<br class="clear" />
				<br />
				<hr />
				
				<h2 class="routing"><span></span>Site Routing</h2>
				<fieldset class="clear">
					<p>BigTree makes your URLs pretty but mod_rewrite support can make them even more pretty. If your server supports .htaccess overrides and mod_rewrite support you can remove the /index.php/ from your URLs.</p>
					<ul>
						<li>Choose <strong>"Basic Routing"</strong> if you are unsure your server supports .htaccess overrides and mod_rewrite.</li>
						<li>Choose <strong>"Simple Rewrite Routing"</strong> if your server supports .htaccess and mod_rewrite but does not allow for php_flags and content compression.</li>
						<li>Choose <strong>"Advanced Routing"</strong> to install an .htaccess that enables caching, compression, and routing.</li>
					</ul>
				</fieldset>
				<fieldset class="clear">
					<select name="routing">
						<option value="basic"<? if (!$routing || $routing == "basic") { ?> selected="selected"<? } ?>>Basic Routing</option>
						<option value="simple"<? if ($routing == "simple") { ?> selected="selected"<? } ?>>Simple Rewrite Routing</option>
						<option value="advanced"<? if ($routing == "advanced") { ?> selected="selected"<? } ?>>Advanced Routing</option>
					</select>
				</fieldset>
				
				<br />
				<hr />
				
				<h2 class="example"><span></span>Example Site</h2>
				<fieldset class="clear">
					<p>If you would also like to install the BigTree example site, check the box below. These optional demo files include example templates and modules to help learn how BigTree works, behind the scenes.</p>
				</fieldset>
				<fieldset class="clear">
					<br />
					<input type="checkbox" class="checkbox" name="install_example_site" id="install_example_site"<?php if ($install_example_site) { ?> checked="checked"<?php } ?> tabindex="14" />
					<label class="for_checkbox">Install Example Site</label>
				</fieldset>
								
				<fieldset class="lower">
					<input type="submit" class="button blue" value="Install Now" tabindex="15" />
				</fieldset>
			</form>
		    <script>
		        $(document).ready(function() {
		        	$("#loadbalanced").on("change", function() {
		        		if ($(this).is(':checked')) {
		        			$("#loadbalanced_settings").css({ display: "block" });
		        		} else {
		        			$("#loadbalanced_settings").css({ display: "none" });
		        		}
		        	});
		        });
		    </script>
			<?php } ?>
			<a href="http://www.bigtreecms.com" class="install_logo" target="_blank">BigTree</a>
			<a href="http://www.fastspot.com" class="install_copyright" target="_blank">&copy; <?php echo date("Y") ?> Fastspot</a>
		</div>
	</body>
</html>