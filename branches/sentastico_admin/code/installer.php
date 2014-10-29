<?php
/**
 *
 * Zantastico X Installer for ZPX
 * Version  : 1.0.0
 * Author   :  Mudasir Mirza
 * Modified by TGates (http://www.zpanelcp.com)
 * Email    : mudasirmirza@gmail.com
 * 
 */

$zipfile = $_GET["pkgzip"];
$pkgInstall = $_GET['pkg'];
$start = $_GET['startinstall'];

if($start){
include('../../../cnf/db.php');
include('../../../dryden/db/driver.class.php');
include('../../../dryden/debug/logger.class.php');
include('../../../dryden/runtime/dataobject.class.php');
include('../../../dryden/sys/versions.class.php');
include('../../../dryden/ctrl/options.class.php');
include('../../../dryden/ctrl/auth.class.php');
include('../../../dryden/ctrl/users.class.php');
include('../../../dryden/fs/director.class.php');
include('../../../inc/dbc.inc.php');
include('functions.php');

if(!isset($_POST['submit'])) {
  
session_start();
if (isset($_SESSION['zpuid'])){
$userid = $_SESSION['zpuid'];
$currentuser = ctrl_users::GetUserDetail($userid);
$hostdatadir = ctrl_options::GetOption('hosted_dir')."".$currentuser['username'];
$random=rand();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>ZPanel &gt; Zantastico X Admin - <?php echo $pkgInstall ?></title>
<link href="../../../etc/styles/<?php echo $currentuser['usertheme']; ?>/css/<?php echo $currentuser['usercss']; ?>.css" rel="stylesheet" type="text/css">
<link href="../assets/install-form.css?<?php echo $random; ?>" rel="stylesheet" type="text/css">
<link href="../assets/tooltip.css?<?php echo $random; ?>" rel="stylesheet" type="text/css">
<script src="../assets/ajaxsbmt.js?<?php echo $random; ?>" type="text/javascript"></script>
<script src="http://code.jquery.com/jquery-latest.js"></script>
<body style="background: #F3F3F3; font-size:12px">

<div style="margin-left:20px;margin-right:20px;">
<h2>ZPanel Zantastico X - <?php echo $pkgInstall ?></h2>
<div id="RunSubmit" style="height:100%;margin:auto;">
<p>Please provide the domain and folder name to start the installation of <?php echo $pkgInstall ?>.</p>
     
<form name="doInstall" action="installer.php?startinstall=true&u=<?php echo $currentuser['userid']; ?>&pkgzip=<?php echo $zipfile ?>&pkg=<?php echo $pkgInstall ?>" method="post" onsubmit="xmlhttpPost('installer.php?startinstall=true&u=<?php echo $currentuser['userid']; ?>&pkgzip=<?php echo $zipfile ?>&pkg=<?php echo $pkgInstall ?>', 'doInstall', 'RunResult', 'Running the Installer!, please wait...<br /><img src=\'../assets/bar.gif\'>'); return false;">
    <table border="0" cellspacing="1" cellpadding="1" align="center" width="600px">
        <tr>
            <td>
                <label for="dir_to_install">Install Folder: </label>
            </td>
            <td align="left">
                <input type="text" length="50" maxsize="100" name="dir_to_install" />
            </td>
        </tr>
        <tr>
            <td height="25px">
            </td>
            <td>
            </td>
        </tr>
        <tr align="center">
            <td></td>
            <td align="center">
                <button class="fg-button ui-state-default ui-corner-all" id="SubmitRun" name="submit" align="center" type="submit" value="">Start Install</button>
            </td>
        </tr>
    </table>
</form>
	<br><center><input  class="fg-button ui-state-default ui-corner-all" type="button" type="button" value="Cancel" onclick="self.close()"></center>
</div>
<div id="RunResult" style="display:block;height:100%;margin:auto;">
    <br /><br />
Running the <?php echo $pkgInstall ?> Installer!, please wait...<br /><br /><img src='../assets/bar.gif'>
</div>
</div>
</body>
</html>
<?php
} else { ?>
<body style="background: #F3F3F3;">
<h2>Unauthorized Access!</h2>
You have no permission to view this module.
</body>
<?php 

}
}else {
    
    $userid=$_GET['u'];
    $currentuser=ctrl_users::GetUserDetail($userid);
    $hostdatadir = ctrl_options::GetOption('hosted_dir')."".$currentuser['username'];
           
    $site_domain=clean($_POST['site_domain']);
    $dir_to_install=clean($_POST['dir_to_install']);
    
    // Retrieve the directory for the Domain selected
    $domaindir=FetchDomainDir($userid, $site_domain);
    
    $completedir=$hostdatadir . "/public_html" . $domaindir . "/" . $dir_to_install . "";
    
    echo "<br /><b>Automated " .$pkgInstall. " Installation Status:</b><br /><br />";
		if(file_exists($completedir)) {
		echo "<div><font color=\"red\"><strong>Destination already exists!</strong></font><br /><br />Sorry, the install folder (<strong>/public_html" . $domaindir . "/" . $dir_to_install . "</strong>) already exists, please go back and create a new folder!<br />";
		echo "<p><center><form><input class=\"fg-button ui-state-default ui-corner-all\" type=\"button\" type=\"button\" onClick=\"history.go(0)\" value=\"Go Back\"></form></center></p></div>";
			} else {
echo "If no errors, you may close this window.";
echo "then <input  class=\"fg-button ui-state-default ui-corner-all\" type=\"button\" type=\"button\" value=\"Close this window\" onclick=\"self.close()\">";
			}
}

}else{
    echo "<font color=\"red\">Unable to start Install Process</font>";
    exit();
}

?>

<script type="text/javascript">
	$(document).ready(function() { 
		$("#RunResult").hide();
			$("#SubmitRun").click(function(){
			$("#RunSubmit").hide();
			$("#RunResult").show();
    	}); 
	})
</script>