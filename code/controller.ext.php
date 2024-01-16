<?php
# Sentastico Open Source Script Installer for Sentora CP
# Version			: 2.0.4 2024-01-09
# Updated By        : TGates for Sentora
# Additional Work   : Durandle, Mudasir Mirza
# Contact Email     : tgates@sentora.org
# Original Author   : Bobby Allen

# List domains in DropDown Menu
function ListDomain($uid)
{
    global $zdbh;
    $sql="SELECT * FROM x_vhosts WHERE vh_acc_fk ='" . $uid."' and vh_active_in='1' and vh_deleted_ts is NULL";
    $numrows = $zdbh->query($sql);
    if (@mysqli_num_rows($numrows) == 0)
	{
        $sql = $zdbh->prepare($sql);
        $html="";
        $html .= "<select name = site_domain style=\"width: 300px\">";
        $sql->execute();
        while ($rowsettings = $sql->fetch())
		{
            $domain = $rowsettings['vh_name_vc'];
            $html .= "<option value=\"" . $domain."\">" . $domain."</option>";
        }
        $html .= "</select>";
    }
	else
	{
        $html = ui_language::translate("Unable to fetch domain list");
    }
	return $html;
}

# Get domain dirs
function FetchDomainDir($uid, $domain)
{
    global $zdbh;
    $sql="SELECT * FROM x_vhosts WHERE vh_acc_fk='" . $uid . "' AND vh_name_vc='" . $domain . "'";
    $numrows = $zdbh->query($sql);
    if (@mysqli_num_rows($numrows) == 0)
	{
        $sql = $zdbh->prepare($sql);
        $sql->execute();
        while ($rowsettings = $sql->fetch())
		{
            $domaindir = $rowsettings['vh_directory_vc'];
        }
        return $domaindir;
    }
	else
	{
        echo ui_language::translate("Unable to fetch domain dir");
        exit();
    }
}

# Get folder information
function directoryToArray($directory, $recursive)
{
    $array_items = array();
    if ($handle = opendir($directory))
	{
        while (false !== ($file = readdir($handle)))
		{
            if ($file != "." && $file != "..")
			{
                if (is_dir($directory. "/" . $file))
				{
                    if ($recursive)
					{
                        $array_items = array_merge($array_items, directoryToArray($directory . "/" . $file, $recursive));
                    }
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                }
				else
				{
                    $file = $directory . "/" . $file;
                    $array_items[] = preg_replace("/\/\//si", "/", $file);
                }
            }
        }
        closedir($handle);
    }
    return $array_items;
}

# Function to clean the User Input
function clean($var)
{
    $clean=stripslashes(trim($var));
    return $clean;
}

# Function to create Directory
function create_dir($completedir)
{
	if (!file_exists($completedir))
	{
		$mkdir = @mkdir($completedir);
		if ($mkdir)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

# Check if folder is empty
function check_folder($dir)
{
	if (file_exists($dir))
	{
		$handle = opendir($dir);
		while (false !== ($entry = readdir($handle)))
		{
			if ($entry != "." && $entry != ".." && $entry != "_errorpages" && $entry != "index.html")
			{
				closedir($handle);
				return false;
			}
		}
		closedir($handle);
		return true;
	}
	else if (!file_exists($dir))
	{
		create_dir($dir);
		return true;
	}
}

# Remove default index.html if exists
function removeIndex($completedir)
{
	if (file_exists($completedir . "index.html"))
	{
	   unlink($completedir . "index.html");
	}
}

# Prepare installation folder
function emptyDir($completedir)
{
	if (file_exists($completedir))
	{
		$i = new DirectoryIterator($completedir);
		foreach ($i as $f)
		{
			if ($f->isFile())
			{
				@unlink($f->getRealPath());
			}
			else if (!$f->isDot() && $f->isDir())
			{
				emptyDir($f->getRealPath());
				@rmdir($f->getRealPath());
			}
		}
	}
}

# Function to Unzip
function UnZip($zipfile, $dest_dir, $site_domain, $dir_to_install)
{
	global $controller;
    $zip = new ZipArchive;
    $res = $zip->open('modules/' . $controller->GetControllerRequest('URL', 'module') . '/packages/' . $zipfile);
    if ($res === TRUE)
	{
		$zip->extractTo($dest_dir);
		$zip->close();
		return true;
	 }
	 else
	 {
		 return false;
    }
}

# Fix permissions of installed files since they will automatically be set to                       
# the apache user and group                                                                        
function fixPermissions($completedir)
{                                                         
    $sysOS = php_uname('s');                                                                        
    $zsudo = ctrl_options::GetOption('zsudo');                                                      
                                                                                                    
    switch($sysOS)
	{                                                                                 
        case 'Linux':                                                                               
            exec("$zsudo chown -R apache.apache " . $completedir);                               
            exec("$zsudo chmod -R 777 " . $completedir);                                            
        break;                                                                                      
        case 'Unix':                                                                                
            exec("$zsudo chown -R apache:apache " . $completedir);                               
            exec("$zsudo chmod -R 777 " . $completedir);                                            
        break;                                                                                      
        default:                                                                                    
            #windows or incompilable operating system !!Do Nothing!!                               
        break;                                                                                      
    }                                                                                               
}

# core module static functions
class module_controller extends ctrl_module
{
    static $ok;

    /* Load CSS and JS files */
    static function getInit()
	{
        global $controller;
		# load module spcific style sheet
        $line = '<link rel="stylesheet" type="text/css" href="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/sentastico.css">';
        $line .= '<link rel="stylesheet" type="text/css" href="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/sentastico_admin.css">';
		# load module spcific JS
		$line .= '<script src="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/sorttable.js"></script>';
		$line .= '<script language="javascript" type="text/javascript">
		  function resizeIframe(obj) {
			obj.style.height = obj.contentWindow.document.body.scrollHeight + "px";
		  }
		</script>';
        return $line;
    }

    static function getInstallerForm()
	{
        if (isset($_POST['startinstall']) && ($_POST['startinstall'] == 'true'))
		{
			return true;
		}
		else
		{
        	return false;
        }
    }

    static function getIsAdmin()
	{
		$currentuser = ctrl_users::GetUserDetail();
		$currentusergid = $currentuser['usergroupid'];
        if ($currentusergid == "1")
		{
			return true;
		}
		else
		{
        	return false;
        }
    }

# convert sentastico_admin.php to be inside controller.ext.php instead of using iframe
	# Load external Sentastico Admin file.
	static function getSentasticoAdmin()
	{
		$toReturn = "<iframe
						id=\"iframe\"
						width=\"100%\"
						height=\"500px\"
						frameborder=\"0\"
						scrolling=\"no\"
						onload=\"resizeIframe(this)\"
						src=\"modules/sentastico/code/sentastico_admin.php\" >
					</iframe>";
		return $toReturn;
	}

# start admin conversion
//	static function getSentasticoAdmin2() {
//		$admin = "Do Admin Stuff!";
//		return $admin;
//	}

# end admin conversion

	# Package installer
    static function getRunInstallerForm()
	{
		global $controller;
        if (isset($_POST['startinstall']) && ($_POST['startinstall'] == 'true'))
		{
			//runtime_csfr::Protect();
			# set base vars
			$start = $_POST['startinstall'];
			$zipfile = $_POST['pkgzip'];
			$pkgInstall = $_POST['pkg'];
			$pkgdb = $_POST['pkgdb'];
			$pkginstaller = $_POST['pkginstaller'];

			if ($start)
			{
				if (!isset($_POST['submit']))
				{
					if (isset($_SESSION['zpuid']))
					{
						$userid = $_SESSION['zpuid'];
						$currentuser = ctrl_users::GetUserDetail($userid);
						# tg why is . "" . in this line? should be . "/" . ??
						$hostdatadir = ctrl_options::GetOption('hosted_dir') . "" . $currentuser['username'];
						$userName = $currentuser['username'];
						$random = rand();
						$sysOS = php_uname('s');

						$line ="<h3>" . ui_language::translate("Preparing to install") . ": " . $pkgInstall . "</h3>";
						if (!isset($startinstall))
						{
							if ($pkgdb == "yes")
							{
								$line .= "<div class=\"alert alert-info\" role=\"alert\"><h4>" . ui_language::translate("This package requires a database and database user") . ":</h4>";
								$line .= "<ol><li><p><a class=\"btn btn-success btn-small\" type=\"button\" target=\"_blank\" href=\"../../../?module=mysql_databases\">" . ui_language::translate("Create a database") . "</a></p></li>";
								$line .= "<li><p><a class=\"btn btn-success btn-small\" type=\"button\" target=\"_blank\" href=\"../../../?module=mysql_users\">" . ui_language::translate("Create a user for the database") . "</a></p></li></ol></div>";
							}
							$line .= "<h4>" . ui_language::translate("Please select the domain and folder information to start the installation of") . " " . $pkgInstall . ".</h4>";
							
							$line .= "<div class=\"alert alert-danger\" role=\"alert\"><font color=\"red\"><b>" . ui_language::translate("NOTICE: ALL files and folders in the selected domain or sub-folder will be deleted!") . ":</b>";
							$line .= "<ol><li>" . ui_language::translate("Backup the folder to protect any files or folders it may contain!") . "</li>";
							$line .= "<li>" . ui_language::translate("Or install to a sub-folder and move all files and folders into the prefered folder.") . "</li></ol></font></div>";

							$line .= "<form id=\"form\" name=\"doInstall\" action=\"/?module=sentastico\" method=\"post\">";
							$line .= "<table>
									<tr>
										<td align=\"right\">
											<label for=\"site_domain\">" . ui_language::translate("Select domain:") . " </label>
										</td>";
							$line .= "<td align=\"center\">";
								$list = ListDomain($currentuser['userid']);
								$line .= $list;
							$line .= "</td>
									<td> </td>
								</tr>
								<tr>
									<td align=\"right\">
										<label for=\"dir_to_install\">" . ui_language::translate("Install to new sub-folder: [domain.tld]/") . "</label>
									</td>
									<td align=\"center\">
										<input type=\"text\" name=\"dir_to_install\" style=\"width: 300px\"/><br />
										(" . ui_language::translate("Leave blank to install to domain root folder.") . ")
									</td>
									<td align=\"center\">
										<font color=\"red\">" . ui_language::translate("NOTE:") . "</font>" . ui_language::translate("No slashes") . ". " . ui_language::translate("The new folder will be created") . ".
									</td>
								</tr>
								<tr>
									<td colspan=\"3\"><p></p></td>
								</tr>
								<tr>
									<td colspan=\"3\" style=\"text-align: center\">
										<!-- inputs -->
										<input type=\"hidden\" name=\"startinstall\" value=\"true\"> 
										<input type=\"hidden\" name=\"u\" value=" . $currentuser['userid'] . "> 
										<input type=\"hidden\" name=\"pkgzip\" value=" . $zipfile . "> 
										<input type=\"hidden\" name=\"pkg\" value='" . $pkgInstall . "'> 
										<input type=\"hidden\" name=\"pkgdb\" value=" . $_POST['pkgdb'] . ">
										<input type=\"hidden\" name=\"pkginstaller\" value=" . $pkginstaller . ">
										<button class=\"btn btn-success\" type=\"submit\" name=\"submit\" value=\"Install\" onclick=\"$('#loading').show();\">" . ui_language::translate("Install Package Files") . "</button>  
										<button class=\"btn btn-danger\" type=\"button\" name=\"cancel\" value=\"Cancel\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Cancel") . "</button>
									</td>
							   </tr>
							</table>";
							$line .= "</form>";
							$line .= "<div id=\"loading\" style=\"display:none;\">
								" . ui_language::translate("Please wait") . "...<br>
								<img src=\"modules/sentastico/assets/bar.gif\" alt=\"\" /><br>
								" . ui_language::translate("Unpacking") . ":<br>" . $pkgInstall . "...
							</div>";
						}
					}
				}
				else
				{
					$userid = $_POST['u'];
					$installed = @$_SESSION['installed'];
					$currentuser = ctrl_users::GetUserDetail($userid);
					$hostdatadir = ctrl_options::GetOption('hosted_dir') . "" . $currentuser['username'];

					$site_domain = clean($_POST['site_domain']);
					$dir_to_install = clean($_POST['dir_to_install']);
					
					# Retrieve the directory for the Domain selected
					$domaindir = FetchDomainDir($userid, $site_domain);
					$completedir = $hostdatadir . "/public_html" . $domaindir . "/" . $dir_to_install . "" ;
					
					# Check if folder exists and is empty, if not create it
					$is_dir_ready = check_folder($completedir);
					# Remove default index.html if exists
					removeIndex($completedir);
					
					$line  = "<h4>" . ui_language::translate("Automated") . " " . $pkgInstall . " " . ui_language::translate("Installation Status") . ":</h4>";
					if (isset($_POST['info'])) $line .= $_POST['info'];
					$info = "";
					$info .= "<div class=\"alert alert-success\" role=\"alert\">";
					if (($is_dir_ready == true) && (file_exists($completedir)))
					{
						$info .= ui_language::translate("Preparing folder") . ": ";
						$info .= "<font color=\"green\">" . ui_language::translate("Folder created Successfully") . "!</font>";

						set_time_limit(0);
						$info .= "<br>" . ui_language::translate("Installing files") . ": ";
						
						# Un-Compressing The ZIP Archive
						if ((UnZip($zipfile . ".zsp", $completedir, $site_domain, $dir_to_install) == 'true'))
						{
							$info .= "<font color=\"green\">" . ui_language::translate("Unzip was successful!") . "</font><br>";
							# Set file/folder ownership and permissions if on posix
							sleep(5); 
							if (php_uname('s') != 'Windows NT')
							{
								$info .= ui_language::translate("Setting file and folder permissions:") . " ";
								fixPermissions($completedir);
								$info .= "<font color=\"green\">" . ui_language::translate("Completed!") . "</font><br />";
							}
							$info .= "" . ui_language::translate("Package unzipped to") . ": http://" . $site_domain . "/" . $dir_to_install . "<br>";
							
							$info .= "</div>";
							$_POST['info'] = $info;
							$_POST['install'] = 'install';
						}
						else
						{
							 $line .= "<font color=\"red\">" . ui_language::translate("Unzip was not successful") . "</font><br><br>";
							 $line .= "<p><button class=\"btn btn-danger\" type=\"button\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Start over") . "</button></p>";
						}
					}
					else if ((isset($pkginstaller)) && ($pkginstaller != "") && ($pkginstaller != NULL) && (isset($_POST['install'])) && ($_POST['install'] == 'install'))
					{
						$line .= "<a target=\"_blank\" href='http://" . $site_domain . "/" . $dir_to_install .  "/" . $pkginstaller . "'> <button class=\"btn btn-primary\" type=\"button\">" . ui_language::translate("Complete package setup now") . "</button> </a>";
						$line .= "<button class=\"btn btn-danger\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Complete package setup later") . "</button>";
					}
					else if ((isset($_POST['install'])) && ($_POST['install'] == 'install'))
					{
						$line .= "<a target=\"_blank\" href='http://" . $site_domain . "/" . $dir_to_install .  "/" . $pkginstaller . "'> <button class=\"btn btn-primary\" type=\"button\">" . ui_language::translate("Complete package setup now") . "</button> </a>";
						$line .= "<button class=\"btn btn-danger\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Complete package setup later") . "</button>";
					}
					else
					{
						$line .= "<div class=\"alert alert-warning\" role=\"alert\"><font color=\"red\"><strong>" . ui_language::translate("Destination folder contains files or folders!") . "</strong></font><br><br>" . ui_language::translate("Sorry, the destination folder") . "  (<strong>/public_html" . $domaindir . "/" . $dir_to_install . "</strong>) " . ui_language::translate("already exists or contains files") . ".<br>" . ui_language::translate("Please go back and create a new folder") . ".</div>";
						$line .= "<p><button class=\"btn btn-danger\" type=\"button\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Go Back") . "</button></p>";
					}
				}
			}
		return $line;
    }
}

	static function getPackageSelection()
	{
		global $zdbh, $controller;
		$toReturn = "";
		# get package list from database
		$sql="SELECT * FROM x_sentastico";
		$numrows = $zdbh->query($sql);

		$toReturn .= "
		<div>
		(" . ui_language::translate("Sortable columns - click on column header") . ")
		<table class=\"table table-striped sortable\" border=\"0\" width=\"100%\">
		  <tr>
			<th>" . ui_language::translate("Package") . "<br>
			" . ui_language::translate("Name") . "</th>
			<th>" . ui_language::translate("Version") . "<br>
			" . ui_language::translate("Number") . "</th>
			<th>" . ui_language::translate("Package") . "<br>
			" . ui_language::translate("Type") . "</th>
			<th>" . ui_language::translate("Package") . "<br>
			" . ui_language::translate("Description") . "</th>
			<th>" . ui_language::translate("Database") . "<br>
			" . ui_language::translate("Required") . "?</th>
			<th> </th>
		  </tr>";
		  # check to see if any packages exist
		  $res = $zdbh->prepare('SELECT COUNT(*) FROM x_sentastico');
		  $res->execute();
		  $num_rows = $res->fetchColumn();

		if ($num_rows != 0)
		{
			$sql = $zdbh->prepare($sql);
			$sql->execute();
		
			while ($rowsettings = $sql->fetch())
			{
				# START - Info and DB tags by tgates
				if ($rowsettings['pkg_db'] == 'yes') $rowsettings['pkg_dbr'] = "<font color='green'><strong>" . ui_language::translate("YES") . "</strong></font>";
				else $rowsettings['pkg_dbr'] = "<font color='red'><strong>" . ui_language::translate("NO") . "</strong></font>";
				# END - Info and DB tags by tgates
			$toReturn .= "<tr>
				<td>" . $rowsettings['pkg_name'] . "</td>
				<td>" . $rowsettings['pkg_version'] . "</td>
				<td>" . $rowsettings['pkg_type'] . "</td>
				<td>" . $rowsettings['pkg_info'] . "</td>
				<td><center>" . $rowsettings['pkg_dbr'] . "</center></td>
				<td>
					<form id=\"install\" name=\"Install\" action=\"/?module=sentastico\" method=\"post\">
					<input type=\"hidden\" name=\"startinstall\" value=\"true\"> 
					<input type=\"hidden\" name=\"pkgzip\" value=" . $rowsettings['pkg_zipname'] . "> 
					<input type=\"hidden\" name=\"pkg\" value='" . $rowsettings['pkg_name'] . "'> 
					<input type=\"hidden\" name=\"pkgdb\" value=" . $rowsettings['pkg_db'] . "> 
					<input type=\"hidden\" name=\"pkginstaller\" value=" . $rowsettings['pkg_installer'] . ">
					<input class=\"btn btn-primary\" type=\"submit\" name=\"doInstall\" value=" . ui_language::translate("Install") . " />
					</form>
				</td>
			</tr>";
			}
		}
		else
		{
			$toReturn .= "<tr><td colspan='6'>" . ui_language::translate("There are no packages installed. Please contact your web hosting administrator.") . "</td></tr>";
		}
			$toReturn .= "</table>";
		return $toReturn;
	}

    static function getDonation()
	{
        $donation = '<br>' . ui_language::translate("Donate to the module developer") . ': <form action="https://www.paypal.com/donate" method="post" target="_blank">
<input type="hidden" name="hosted_button_id" value="MCDRPGAZFNEMY" />
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" height="25" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
</form>';
        return $donation;
    }
	
    static function getCopyright()
	{
        $copyright = '<font face="ariel" size="2">' . ui_module::GetModuleName() . ' v2.0.4 &copy; 2013-' . date("Y") . ' by <a target="_blank" href="http://forums.sentora.org/member.php?action=profile&uid=2">TGates</a> for <a target="_blank" href="http://sentora.org">Sentora Control Panel</a> &#8212; ' . ui_language::translate("Help support future development of this module and donate today!") . '</font>';
        return $copyright;
    }
}
?>