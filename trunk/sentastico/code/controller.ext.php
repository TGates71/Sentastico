<?php
// Sentastico Open Source Script Installer for Sentora CP
// File				: controller.ext.php
// Version          : 20.1.0.0 (09-15-2014)
// Updated By       : TGates for Sentora
// Additional Work  : Durandle
// Packages Updated : 05-05-2014 by TGates
// Contact Email    : tgates@mach-hosting.com
// Original Authors : Bobby Allen/Mudasir Mirza

// List domains in DropDown Menu
function ListDomain($uid){
    global $zdbh;
    $sql="SELECT * FROM x_vhosts WHERE vh_acc_fk ='".$uid."' and vh_active_in='1' and vh_deleted_ts is NULL";
    $numrows = $zdbh->query($sql);
    if (@mysql_num_rows($numrows) == 0) {
        $sql = $zdbh->prepare($sql);
        $html="";
        $html .="<select name=site_domain>";
        $sql->execute();
        while ($rowsettings = $sql->fetch()) {
            $domain = $rowsettings['vh_name_vc'];
            $html .= "<option value=\"".$domain."\">".$domain."</option>";
        }
        $html .= "</select>";
    } else {
        $html ="Unable to fetch domain list";
    }
	return $html;
}

// Get domain dirs
function FetchDomainDir($uid,$domain){
    global $zdbh;
    $sql="SELECT * FROM x_vhosts WHERE vh_acc_fk='" . $uid . "' AND vh_name_vc='" . $domain . "'";
    $numrows = $zdbh->query($sql);
    if (@mysql_num_rows($numrows) == 0) {
        $sql = $zdbh->prepare($sql);
        $sql->execute();
        while ($rowsettings = $sql->fetch()) {
            $domaindir = $rowsettings['vh_directory_vc'];
        }
        return $domaindir;
    } else {
        echo "Unable to fetch domain dir";
        exit();
    }
}

// Set file and folder permissions and ownership
function directoryToArray($directory, $recursive) {
    $array_items = array();
    if ($handle = opendir($directory)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                if (is_dir($directory. "/" . $file)) {
                    if($recursive) {
                        $array_items = array_merge($array_items, directoryToArray($directory. "/" . $file, $recursive));
                    }
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                } else {
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                }
            }
        }
        closedir($handle);
    }
    return $array_items;
}

// Function to clean the User Input
function clean($var){
    $clean=stripslashes(trim($var));
    return $clean;
}

// Function to create Directory
function CreateDir($completedir,$domaindir,$dir_to_install){
	if (!file_exists($completedir)) {
		$mkdir = mkdir($completedir);
		if($mkdir){
			return true;
		} else {
			return false;
		}
	}
}

// Prepare installation folder
function emptyDir($completedir) {
	$i = new DirectoryIterator($completedir);
		foreach($i as $f) {
			if($f->isFile()) {
				unlink($f->getRealPath());
			} else if(!$f->isDot() && $f->isDir()) {
				emptyDir($f->getRealPath());
		rmdir($f->getRealPath());
			}
		}
}

// Function to Unzip
function UnZip($zipfile,$dest_dir,$site_domain,$dir_to_install){
	global $controller;
    $zip = new ZipArchive;
    $res = $zip->open('modules/'.$controller->GetControllerRequest('URL', 'module').'/packages/'.$zipfile);
    if ($res === TRUE) {
		$zip->extractTo($dest_dir);
		$zip->close();
		return true;
	 } else {
		 return false;
    }
}

// Fix permissions of installed files since they will automatically be set to                       
// the apache user and group                                                                        
function fixPermissions($completedir){                                                         
    $sysOS = php_uname('s');                                                                        
    $zsudo = ctrl_options::GetOption('zsudo');                                                      
                                                                                                    
    switch($sysOS){                                                                                 
        case 'Linux':                                                                               
            exec("$zsudo chown -R ftpuser.ftpgroup " . $completedir);                               
            exec("$zsudo chmod -R 777 " . $completedir);                                            
        break;                                                                                      
        case 'Unix':                                                                                
            exec("$zsudo chown -R ftpuser:ftpgroup " . $completedir);                               
            exec("$zsudo chmod -R 777 " . $completedir);                                            
        break;                                                                                      
        default:                                                                                    
            //windows or incompilable operating system !!Do Nothing!!                               
        break;                                                                                      
    }                                                                                               
}

// Function to retrieve remote XML for update check
function check_remote_xml($xmlurl,$destfile){
    $feed = simplexml_load_file($xmlurl);
    if ($feed)
    {
        // $feed is valid, save it
        $feed->asXML($destfile);
    } elseif (file_exists($destfile)) {
        // $feed is not valid, grab the last backup
        $feed = simplexml_load_file($destfile);
    } else {
        die('Unable to retrieve XML file');
    }
}

// core module static functions
class module_controller {
    
    static $ok;

    static function getModuleVersion() {
        global $zdbh, $controller, $zlo;
        $module_path="./modules/".$controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/".$controller->GetControllerRequest('URL', 'module')."/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_version = $mod_config->document->version[0]->tagData;
		echo " ".$module_version."";
    }
	
    static function getCheckUpdate() {
        global $zdbh, $controller, $zlo;
        $module_path="./modules/".$controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/".$controller->GetControllerRequest('URL', 'module')."/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_updateurl = $mod_config->document->updateurl[0]->tagData;
        $module_version = $mod_config->document->version[0]->tagData;

        // Download XML in Update URL and get Download URL and Version
        $myfile = check_remote_xml($module_updateurl, $module_path."/".$controller->GetControllerRequest('URL', 'module').".xml");
        $update_config = new xml_reader(fs_filehandler::ReadFileContents($module_path."/".$controller->GetControllerRequest('URL', 'module').".xml"));
        $update_config->Parse();
        $update_url = $update_config->document->downloadurl[0]->tagData;
        $update_version = $update_config->document->latestversion[0]->tagData;

        if($update_version > $module_version) 
				return true;
        return false;
    }    
    
    static function getCheckDBUpdates() {
        global $zdbh;
        include(ctrl_options::GetOption('zpanel_root').'/cnf/db.php');

        // Updates
        $v_update_sql = $zdbh->prepare("UPDATE x_modules SET mo_version_in=20100 WHERE mo_name_vc='".ui_module::GetModuleName()."'");
        $v_update_sql->execute();
    }

    static function getCSFR_Tag() {
        return runtime_csfr::Token();
    }

    /* Load CSS and JS files */
    static function getInit() {
        global $controller;
		// load module spcific style sheet
        $line = '<link rel="stylesheet" type="text/css" href="modules/'.$controller->GetControllerRequest('URL', 'module').'/assets/sentastico.css">';
		// load module spcific JS
		$line .= '<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>';
        return $line;
    }

    static function getModuleDesc() {
        $module_desc = ui_language::translate(ui_module::GetModuleDescription());
        return $module_desc;
    }

    static function getModuleName() {
        $module_name = ui_module::GetModuleName();
        return $module_name;
    }

    static function getModuleIcon() {
        global $controller;
        $module_icon = "./modules/".$controller->GetControllerRequest('URL', 'module')."/assets/icon.png";
        return $module_icon;
    }

    static function getInstallerForm() {
        if (isset($_POST['startinstall']) && ($_POST['startinstall'] == 'true')) {
			return true;
		} else {
        	return false;
        }
    }

	// Package installer
    static function getRunInstallerForm() {
		global $controller;
        if (isset($_POST['startinstall']) && ($_POST['startinstall'] == 'true')) {

			//runtime_csfr::Protect();
			// set base vars
			$start = $_POST['startinstall'];
			$zipfile = $_POST['pkgzip'];
			$pkgInstall = $_POST['pkg'];
			$pkgdb = $_POST['pkgdb'];
			
			if($start){
				if(!isset($_POST['submit'])) {
					if (isset($_SESSION['zpuid'])){
						
						$userid = $_SESSION['zpuid'];
						$currentuser = ctrl_users::GetUserDetail($userid);
						$hostdatadir = ctrl_options::GetOption('hosted_dir')."".$currentuser['username'];
						$userName = $currentuser['username'];
						$random=rand();
						$sysOS=php_uname('s');

						$line ="<h2>Preparing to install ".$pkgInstall.":</h2>";
							if (!isset($startinstall)) {
								if ($pkgdb == "yes") {
										$line .="<font color=\"red\"><strong>This package requires a database and database user.</strong></font><br />";
										$line .="<a target=\"_blank\" href=\"../../../?module=mysql_databases\">&raquo;Open&laquo; </a> database manager.<br />";
										$line .="<a target=\"_blank\" href=\"../../../?module=mysql_users\">&raquo;Open&laquo; </a> database user manager.";
										$line .="<p>&nbsp;</p>";
									}
										$line .="<p>Please provide the domain and folder name to start the installation of ".$pkgInstall.".</p>";
										$line .="<form id=\"form\" name=\"doInstall\" action=\"/?module=sentastico\" method=\"post\">";
										$line .="<table>
												<tr>
													<td align=\"right\">
														<label for=\"site_domain\">Select domain: </label>
													</td>";
													$line .="<td align=\"center\">";
														$list = ListDomain($currentuser['userid']);
														$line .= $list;
													$line .="</td>
													<td>&nbsp;</td>
												</tr>
												<tr>
													<td align=\"right\">
														<label for=\"install_to_base_dir\">Tick&nbsp;to&nbsp;install to&nbsp;domain&nbsp;root:</label>
													</td>
													<td align=\"center\">
														<input type=\"hidden\" name=\"install_to_base_dir\" value=\"0\" />
														<input type=\"checkbox\" onClick=\"if (this.checked) { document.getElementById('hiderow').style.display = 'none'; } else { document.getElementById('hiderow').style.display = ''; }\" name=\"install_to_base_dir\" value=\"1\" />
													</td>
													<td>
														<font size=\"-1\" color=\"red\"><strong>ALL FILES AND FOLDERS WILL BE DELETED!</strong></font>
													</td>
												</tr>
												<tr id=\"hiderow\">
													<td align=\"right\">
														<label for=\"dir_to_install\">Install To Sub-Folder: public_html/[domain]/</label>
													</td>
													<td>
															<input type=\"text\" name=\"dir_to_install\" />
													</td>
													<td>
															<font color=\"red\">NOTE:</font> For multiple subfolders use: subfolder/subsubfolder
													</td>
												</tr>
												<tr><td><p>&nbsp;</p></td></tr>
												<tr>
													<td>
													<!-- inputs -->
													<input type=\"hidden\" name=\"startinstall\" value=\"true\"> 
													<input type=\"hidden\" name=\"u\" value=".$currentuser['userid']."> 
													<input type=\"hidden\" name=\"pkgzip\" value=".$zipfile."> 
													<input type=\"hidden\" name=\"pkg\" value='".$pkgInstall."'> 
													<input type=\"hidden\" name=\"pkgdb\" value=".$_POST['pkgdb'].">
													<input class=\"btn btn-primary btn-small\" type=\"submit\" name=\"submit\" value=\"Install\" onclick=\"$('#loading').show();\" />
													<input class=\"btn btn-danger btn-small\" type=\"button\" name=\"cancel\" value=\"Cancel\" onClick=\"javascript:location.href='?module=sentastico'\" />
													</td>
													<td></td><td></td>
											   </tr>
											</table>";
										$line .="</form>";
											$line .="<div id=\"loading\" style=\"display:none;\">
												Please wait...<br />
												<img src=\"modules/sentastico/assets/bar.gif\" alt=\"\" /><br />
												Unpacking ".$pkgInstall."...
											</div>";
									}
								}
				} else {
						$userid=$_POST['u'];
						$installed=$_SESSION['installed'];
						$install_to_base_dir=$_POST['install_to_base_dir'];
						$currentuser=ctrl_users::GetUserDetail($userid);
						$hostdatadir=ctrl_options::GetOption('hosted_dir')."".$currentuser['username'];
							   
						$site_domain=clean($_POST['site_domain']);
						$dir_to_install=clean($_POST['dir_to_install']);
						$install_to_base_dir=clean($_POST['install_to_base_dir']);
						
						// Retrieve the directory for the Domain selected
						$domaindir=FetchDomainDir($userid, $site_domain);
						$completedir=$hostdatadir."/public_html".$domaindir."/".$dir_to_install."" ;
						
						$line ="<h2>Automated " .$pkgInstall. " Installation Status:</h2>";
						
							if ((file_exists($completedir)) && ($install_to_base_dir != '1') && (empty($dir_to_install)) && ($installed != 'true')) {
								$line .="If not empty root folder<br><br>";
								$line .="<p><font color=\"red\"><strong>Destination folder already exists!</strong></font><br /><br />Sorry, the install folder (<strong>/public_html".$domaindir."/".$dir_to_install."</strong>) already exists or contains files.<br />Please go back and create a new folder.</p>";
								$line .="<p><button class=\"btn btn-danger btn-small\" type=\"button\" onClick=\"javascript:location.href='?module=sentastico'\">Start over</button></p>";
								
// START issue here with showing folder exists even if folder created upon unzip completion		
							} else if ((file_exists($completedir)) && ($install_to_base_dir != '1') && (isset($dir_to_install))
							 && ($installed != 'true')) {
								$line .="If not empty sub folder<br><br>";
								$line .="<p><font color=\"red\"><strong>Destination folder already exists!</strong></font><br /><br />Sorry, the install folder (<strong>/public_html".$domaindir."/".$dir_to_install."</strong>) already exists or contains files.<br />Please go back and create a new folder.</p>";
								$line .="<p><button class=\"btn btn-danger btn-small\" type=\"button\" onClick=\"javascript:location.href='?module=sentastico'\">Start over</button></p>";
// START issue here with showing folder exists even if folder created upon unzip completion		
								
							} else {
								$line .="Preparing folder: ";
								CreateDir($completedir,$domaindir,$dir_to_install);
								$line .="<font color=\"green\">Folder created Successfully!</font>";

								sleep(1);
								// Remove all Files in the install Folder
								emptyDir($completedir);
								sleep(3);
								set_time_limit(0);
								$line .="<br />Installing files: ";
								
								$line .="<form><input type='hidden' name='installed' value='InS'></form>";
								
								// Un-Compressing The ZIP Archive
								if (UnZip($zipfile.".zip",$completedir,$site_domain,$dir_to_install) == 'true'){
									$line .="<font color=\"green\">Unzip was successful</font><br />";
									$line .="Package unzipped to: http://".$site_domain."/".$dir_to_install."<br /><br />";
									if(file_exists($completedir."/sentastico-install.php")) {
											$line .="<a target=\"_blank\" href='http://".$site_domain."/".$dir_to_install."/sentastico-install.php'> <button class=\"btn btn-primary btn-small\" type=\"button\">Install Now</button> </a>";
											$line .="<button class=\"btn btn-danger btn-small\" onClick=\"javascript:location.href='?module=sentastico'\">Install Later</button>";
										} else {
											$line .="<a target=\"_blank\" href='http://".$site_domain."/".$dir_to_install."/'><button class=\"btn btn-primary btn-small\" type=\"button\">Install Now</button></a>";
											$line .="<button class=\"btn btn-danger btn-small\" onClick=\"javascript:location.href='?module=sentastico'\">Install Later</button>";
										}
										
									 } else {
									 $line .="<font color=\"red\">Unzip was not successful</font><br /><br />";
									 $line .="<p><button class=\"btn btn-danger btn-small\" type=\"button\" onClick=\"javascript:location.href='?module=sentastico'\">Start over</button></p>";
									 }
								$_SESSION['installed'] = 'true';	 
								sleep(5); 
								// Set file/folder ownership and permissions if on posix
									if (php_uname('s') != 'Windows NT') {
										$line .="Setting file and folder permissions: ".php_uname('s');
										fixPermissions($completedir);
									}
							}
						}
				}
		return $line;
    }
}

	static function getPackageSelection() {
		global $controller;
		$toReturn ="";
		$packages= new xml_reader(fs_filehandler::ReadFileContents("./modules/".$controller->GetControllerRequest('URL', 'module')."/packages/packages.xml"));
		$packages->Parse();

		$toReturn .="
		<table class=\"table table-striped\" border=\"0\" width=\"100%\">
		  <tr>
			<th>".ui_language::translate( "Package" )."<br />
			".ui_language::translate( "Name" )."</th>
			<th>".ui_language::translate( "Version" )."<br />
			".ui_language::translate( "Number" )."</th>
			<th>".ui_language::translate( "Package" )."<br />
			".ui_language::translate( "Type" )."</th>
			<th>".ui_language::translate( "Package" )."<br />
			".ui_language::translate( "Description" )."</th>
			<th>".ui_language::translate( "Database" )."<br />
			".ui_language::translate( "Required" )."?</th>
			<th>&nbsp;</th>
		  </tr>";
		foreach($packages->document->package as $package){
			// START - Info and DB tags by tgates
			if(@$package->db[0]->tagData=='yes') @$package->pkgdb[0]->tagData="yes";
			else @$package->pkgdb[0]->tagData="no";
			if($package->db[0]->tagData=='yes') $package->db[0]->tagData="<font color='green'><strong>".ui_language::translate( "YES" )."</strong></font>";
			else $package->db[0]->tagData="<font color='red'><strong>".ui_language::translate( "NO" )."</strong></font>";
			// END - Info and DB tags by tgates
		$toReturn .="<tr>
			<th>" .$package->name[0]->tagData. "</th>
			<th>" .$package->version[0]->tagData. "</th>
			<td>" .$package->type[0]->tagData. "</td>
			<td>" .$package->info[0]->tagData. "</td>
			<td><center>" .$package->db[0]->tagData. "</center></td>
			<td>
				<form id=\"install\" name=\"Install\" action=\"/?module=sentastico\" method=\"post\">
				<input type=\"hidden\" name=\"startinstall\" value=\"true\"> 
				<input type=\"hidden\" name=\"pkgzip\" value=".$package->zipname[0]->tagData."> 
				<input type=\"hidden\" name=\"pkg\" value='".$package->name[0]->tagData."'> 
				<input type=\"hidden\" name=\"pkgdb\" value=".$package->pkgdb[0]->tagData."> 
				<input class=\"btn btn-primary btn-small\" type=\"submit\" name=\"doInstall\" value=". ui_language::translate( "Install" )." />
				</form>
			</td>
		</tr>";
			}
		return $toReturn;
	}
// below needs rebuild-use code from above
	static function getCustomPackageSelection() {
		global $controller;
		$toReturn ="";
		if (file_exists("./modules/".$controller->GetControllerRequest('URL', 'module')."/packages/custom_packages.xml")) {
		$packages= new xml_reader(fs_filehandler::ReadFileContents("./modules/".$controller->GetControllerRequest('URL', 'module')."/packages/custom_packages.xml"));
		$packages->Parse();

		foreach($packages->document->package as $package){
			// START - Info and DB tags by tgates
			if($package->db[0]->tagData=='yes') $package->pkgdb[0]->tagData="yes";
			else $package->pkgdb[0]->tagData="no";
			if($package->db[0]->tagData=='yes') $package->db[0]->tagData="<font color='green'><strong>".ui_language::translate( "YES" )."</strong></font>";
			else $package->db[0]->tagData="<font color='red'><strong>".ui_language::translate( "NO" )."</strong></font>";
			// END - Info and DB tags by tgates
		$toReturn .="<tr>
			<th>" .$package->name[0]->tagData. "</th>
			<th>" .$package->version[0]->tagData. "</th>
			<td>" .$package->type[0]->tagData. "</td>
			<td>" .$package->info[0]->tagData. "</td>
			<td><center>" .$package->db[0]->tagData. "</center></td>
			<td><a href='/?module=sentastico&pkgzip=" .$package->zipname[0]->tagData. "&pkg=" .$package->name[0]->tagData. "&pkgdb=" .$package->pkgdb[0]->tagData. "&startinstall=true');\"><button type=\"button\" class=\"btn btn-primary btn-small\">".ui_language::translate( "Install" )."</button></a></td>
 			</tr>";
			}
			$toReturn .="</table><br />";
		} else { 
			$toReturn .="</table><br />";
		}
		//$toReturn .="</form>";
		return $toReturn;
	}
}
?>