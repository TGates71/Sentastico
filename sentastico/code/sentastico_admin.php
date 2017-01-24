<?php
/*
// Sentastico Admin - Admin tool for Sentastico.
// Contact          : http://forums.sentora.org/
// Author           : TGates
*/

require_once('../../../cnf/db.php');
require_once('../../../dryden/db/driver.class.php');
require_once('../../../dryden/debug/logger.class.php');
require_once('../../../dryden/ctrl/auth.class.php');
require_once('../../../dryden/ctrl/users.class.php');
require_once('../../../inc/dbc.inc.php');

// auth
session_start();
if (!$_SESSION['zpuid']) {
	die ("Access Denied");
}

// use Sentora_Default css and js
?>
<!-- Stylesheets -->
<link href="../../../modules/sentastico/assets/bootstrap.min.css" rel="stylesheet">
<link href="../../../modules/sentastico/assets/default.css" rel="stylesheet" type="text/css">
<!-- Javascripts -->
<script src="../../../modules/sentastico/assets/jquery.js"></script>
<script src="../../../modules/sentastico/assets/bootstrap-tab.js"></script>
<script src="../../../modules/sentastico/assets/sorttable.js"></script>
<?php
// set packages path
$path = '../../../modules/sentastico/packages/';

// remove a package
if ((isset($_POST['pkg_zipname'])) && ($_POST['pkg_zipname'] != "")) {
	$pkg_delete = $_POST['pkg_zipname'];

	$PathFile = $path.$pkg_delete;
	// remove package zip file
	unlink($PathFile);
	$pkg_name = preg_replace("/.zip/", "", $pkg_delete);
	// remove package DB entry
	$sql = $zdbh->prepare("DELETE FROM `x_sentastico` WHERE pkg_zipname = :pkg_zipname");
	$sql->execute(array(':pkg_zipname' => $pkg_name));

	echo "<p>&nbsp;</p>";
	echo "<div class=\"alert alert-danger\">Package deleted.</div>";
	echo "<p>&nbsp;</p>";
	header("refresh:3;url=?module=sentastico");
	exit();
}

// install a package
if (isset($_POST['install']) && ($_POST['install'] == 'install') && (isset($_POST['pkg']))) {
	
	// cleaning and setting of some vars
	$newPackage = $_POST['pkg'];
	$newPkgFname = $newPackage.".zip";
	$newPkgFname = preg_replace('/\s+/', '', $newPkgFname);
	$extractPath = $path.$newPackage;
	$extractPath = preg_replace('/\s+/', '', $extractPath);

	// download the package if not already on the server
	if (!is_file($path.$newPkgFname)) {
		$parse = curl_init();
		$repoURL = "http://sen-packs.mach-hosting.com/packages/";
		$source = $repoURL.$newPkgFname;
		curl_setopt($parse, CURLOPT_URL, $source);
		curl_setopt($parse, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($parse);
		curl_close($parse);

		$destination = $path.$newPkgFname;
		$file = fopen($destination, "w+");
		fputs($file, $data);
		fclose($file);

	// parse the sentastico.xml and add it to the DB
	function getPackageXml($file) {
	  $zip = zip_open($file);
	  if ($zip) {
		while ($zip_entry = @zip_read($zip)) {
		  if (zip_entry_name($zip_entry) == 'sentastico.xml') {
			if (zip_entry_open($zip, $zip_entry)) {
			  $contents = zip_entry_read($zip_entry);
			  
			  preg_match_all("/\<name\>(.*?)\<\/name\>/", $contents, $nameArray, PREG_PATTERN_ORDER);
			  $name = $nameArray[0][0];
			  $name = preg_replace("/\<name\>/", "", $name);
			  $name = preg_replace("/\<\/name\>/", "", $name);

			  preg_match_all("/\<version\>(.*?)\<\/version\>/", $contents, $versionArray, PREG_PATTERN_ORDER);
			  $version = $versionArray[0][0];
			  $version = preg_replace("/\<version\>/", "", $version);
			  $version = preg_replace("/\<\/version\>/", "", $version);

			  preg_match_all("/\<zipname\>(.*?)\<\/zipname\>/", $contents, $zipnameArray, PREG_PATTERN_ORDER);
			  $zipname = $zipnameArray[0][0];
			  $zipname = preg_replace("/\<zipname\>/", "", $zipname);
			  $zipname = preg_replace("/\<\/zipname\>/", "", $zipname);
			  
			  preg_match_all("/\<type\>(.*?)\<\/type\>/", $contents, $typeArray, PREG_PATTERN_ORDER);
			  $type = $typeArray[0][0];
			  $type = preg_replace("/\<type\>/", "", $type);
			  $type = preg_replace("/\<\/type\>/", "", $type);
			  
			  preg_match_all("/\<info\>(.*?)\<\/info\>/", $contents, $infoArray, PREG_PATTERN_ORDER);
			  $info = $infoArray[0][0];
			  $info = preg_replace("/\<info\>/", "", $info);
			  $info = preg_replace("/\<\/info\>/", "", $info);
			  
			  preg_match_all("/\<db\>(.*?)\<\/db\>/", $contents, $dbArray, PREG_PATTERN_ORDER);
			  $db = $dbArray[0][0];
			  $db = preg_replace("/\<db\>/", "", $db);
			  $db = preg_replace("/\<\/db\>/", "", $db);

			  preg_match_all("/\<installer\>(.*?)\<\/installer\>/", $contents, $installerArray, PREG_PATTERN_ORDER);
			  $installer = $installerArray[0][0];
			  $installer = preg_replace("/\<installer\>/", "", $installer);
			  $installer = preg_replace("/\<\/installer\>/", "", $installer);
			  
			  zip_entry_close($zip_entry);

			  // put variables into array
			  $xmlArray = array();
			  $xmlArray['name'] = $name;
			  $xmlArray['version'] = $version;
			  $xmlArray['zipname'] = $zipname;
			  $xmlArray['type'] = $type;
			  $xmlArray['info'] = $info;
			  $xmlArray['db'] = $db;
			  $xmlArray['installer'] = $installer;
			}
		  }
		}
	  } 
		return $xmlArray;
} // end function

		$packageXml = getPackageXml($path.$newPkgFname);

		if (!isset($packageXml['name'])) {
			echo "<p>&nbsp;</p>";
			echo "Package: ".$packageXml;
			echo "<div class=\"alert alert-danger\">Error getting package information. Try again later.<br />If problem persists, contact your server administrator.</div>";
			echo "<p>&nbsp;</p>";
			// remove package zip file
			$PathFile = $path.$newPkgFname;
			unlink($PathFile);
			header("refresh:5;url=?module=sentastico");
			exit();
		} else {
		$sql = $zdbh->prepare("INSERT INTO `x_sentastico` SET
							pkg_name = :name,
							pkg_version = :version,
							pkg_zipname = :zipname,
							pkg_type = :type,
							pkg_info = :info,
							pkg_db = :db,
							pkg_installer = :installer
						");
		$sql->execute(array(
							':name' => $packageXml['name'],
							':version' => $packageXml['version'],
							':zipname' => $packageXml['zipname'],
							':type' => $packageXml['type'],
							':info' => $packageXml['info'],
							':db' => $packageXml['db'],
							':installer' => $packageXml['installer']
							));
		// end xml
		
		echo "<p>&nbsp;</p>";
		echo "<div class=\"alert alert-info\">Package Added.</div>";
		echo "<p>&nbsp;</p>";
		header("refresh:3;url=?module=sentastico");
		exit();
		}
	}
}

// get installed packages
// convert to use local DB
$ignore = Array(".", "..", ".htaccess", "thumbs.db", "index.html", "packages.xml");
$packagesL = array_diff(scandir($path), $ignore);
$packagesL = array_map('trim', $packagesL);
$packagesL = array_values($packagesL);

$sql = $zdbh->prepare("SELECT * FROM x_sentastico");
$sql->execute();
$packagesDB = $sql->fetchAll();

// get available packages from sen-packs repo
// convert to use remote XML file
$file = "http://sen-packs.mach-hosting.com/packages/packages.txt";
$file_headers = @get_headers($file);
$packageList = file($file);
$packageList = array_map('trim', $packageList);
$packageList = array_values($packageList);

// Sentastico Package Dev
if (file_exists("sen-dev.php")) {
	// get available packages from sen-packs dev repo
	// convert to use remote XML file
	$file2 = "http://sen-packs.mach-hosting.com/packages/packages_dev.txt";
	$file_headers2 = @get_headers($file2);
	$packageList2 = file($file2);
	$packageList2 = array_map('trim', $packageList2);
	$packageList2 = array_values($packageList2);
	$packageList = array_merge($packageList, $packageList2);
}
?>
<!-- Menu Start -->
<div class="tab-menu" role="tabpanel">
	<!-- Nav tabs -->
	<ul class="nav nav-tabs" id="tablist" role="tablist">
        <li class="active" role="presentation">
			<a onclick="javascript:location.href='?module=sentastico'" href="#sen_add" aria-controls="sen_add" role="tab" data-toggle="tab">Add Packages</a>
        </li>
        <li role="presentation">
            <a onclick="javascript:location.href='?module=sentastico'" href="#sen_del" aria-controls="sen_del" role="tab" data-toggle="tab">Remove Packages</a>
        </li>
	</ul>
	<!-- Tab panes -->
	<div class="tab-content">
		<!-- Add packages -->
        <div role="tabpanel" class="tab-pane active" id="sen_add">
			<h3>Add Packages</h3>
       		<?php
			$_POST = array();
			if ($file_headers[0] != 'HTTP/1.1 404 Not Found') {

				// compare the two lists and show those not installed
				$packagesLx = preg_replace('/.zip/', '', $packagesL);
				$package_diff = array_diff($packageList, $packagesLx);
				if (!$package_diff) {
					echo '<p>&nbsp;</p><table><tr><th>No new or updated packages to install.</th></tr></table>';
				} else {
			?>
				<table class='table table-striped sortable'>
				<form name="pkg_install" method="post">
					<tr class='form_header'>
						<th>Action</th>
						<th>Name</th>
						<th>Version</th>
					</tr>
				<?php
				foreach($package_diff as $packageInfo) {
					$packageInfo = htmlspecialchars($packageInfo);
					list($package_name, $package_version) = explode("_", $packageInfo);
					
					if ($packagesL == $packageInfo) {
						?>
						<tr>
								<td><span class="form_text">Added</span></td>
					<?php } else { ?>
						<tr>
								<td><button class="btn btn-success" name="pkg" value="<?php echo $packageInfo; ?>" type="submit">Add</button></td>
					<?php
					}
					?>
							<td><span class="form_text"><?php echo $package_name; ?></span>
							    <input type="hidden" name="install" value="install"></td>
							<td><span class="form_text"><?php echo $package_version; ?></span></td>
				<?php } ?>
						</tr>
				</form>
				</table>
			
				<?php }
            } else {
            	echo "
				<p>&nbsp;</p>
				<div class=\"alert alert-danger\">
				<strong>Error:</strong> There was an error contacting the update server.<br />Please try again later.</a>
				</div>
				<p>&nbsp;</p>";
			}
			?>
        </div>
		<!-- Remove Packages -->
        <div role="tabpanel" class="tab-pane" id="sen_del">
			<h3>Remove Packages</h3>
			<?php
			if (!$packagesL) {
				echo '<p>&nbsp;</p><table><tr><th>No packages installed.</th></tr></table>';
			} else {
			?>
			<table class='table table-striped sortable'>
				<tr class='form_header'>
					<th>Action</th>
					<th>Package name</th>
					<th>Version</th>
				</tr>
			<?php
			foreach($packagesL as $packageL){
				if(!in_array($packageL, $ignore)) {
					list($packageLname, $packageLVers) = explode("_", $packageL);
					$packageLVers = preg_replace('/.zip/', '', $packageLVers);
				?>
				<tr>
				<form name="pkg_delete" method="post">
					<td><button class="btn btn-danger" type="button" onclick="submit();">Delete</button></td>
					<td><span class="form_text"><?php echo $packageLname; ?></span></td>
					<td><span class="form_text"><?php echo $packageLVers; ?></span></td>
					<input type="hidden" name="pkg_zipname" value="<?php echo $packageL; ?>">
					<input type="hidden" name="pkg_del" value="delete">
				</form>
				<?php }
			} ?>
				</tr>
			</table>
		<?php } ?>
        </div>
	</div>
</div>
<script>
	$(function() { 
		$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
			// save the latest tab; use cookies if you like 'em better:
			localStorage.setItem('lastTab', $(this).attr('href'));
		});
	
		// go to the latest tab, if it exists:
		var lastTab = localStorage.getItem('lastTab');
		if (lastTab) {
			$('[href="' + lastTab + '"]').tab('show');
		}
	});
</script>
