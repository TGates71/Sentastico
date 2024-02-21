<?php
/*
// Sentastico Open Source Script Installer for Sentora CP
// File             : controller.ext.php
// Version          : 2.1.1 2024-02-20
// Updated By       : TGates for Sentora
// Additional Work  : Durandle, Mudasir Mirza
// Credit to        : Bobby Allen (Zantastico for ZPanel v1)
// Contact          : http://forums.sentora.org/
*/

# List domains in drop down menu
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

# Clean the user input
function clean($var)
{
    $clean = stripslashes(trim($var));
    return $clean;
}

# Check destination folder status
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

# Create folder
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

# Remove default index.html if exists
function removeIndex($completedir)
{
	if (file_exists($completedir . "index.html"))
	{
	   unlink($completedir . "index.html");
	}
}

# Prepare installation folder (Remove all files and folders)
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

# Unzip the Package
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

# Fix permissions of installed files                                                               
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
            # Windows or incompilable operating system !!Do Nothing!!                               
        break;                                                                                      
    }                                                                                               
}

# Core module static functions
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
		  function resizeIframe(obj)
		  {
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
# tg - not working
	# Show DB info
	static function getNewDBinfo($pkgInstall)
	{
		global $controller;
		
		$toReturn = "
		<div class=\"panel\">
			<div class=\"panel-heading\">
				<div class=\"zmodule_title\">
					" . ui_language::translate("Database Information") . "
				</div>
				<div class=\"zmodule_desc\">
					(" . ui_language::translate("Double click to highlight, right click to copy, then panelinto the") . " " . $pkgInstall . " " . ui_language::translate("installer form") . ")
				</div>
			</div>
			<p>
				" . ui_language::translate("Database host") . ": 
				<input type=\"text\" name=\"stc_dbhost\" id=\"stc_dbhost\" readonly value=" . $_POST['db']['stc_dbhost'] . " size=\"35\" /><br />
				" . ui_language::translate("Database name") . ": 
				<input type=\"text\" name=\"stc_dbname\" id=\"stc_dbname\" readonly value=" . $_POST['db']['stc_dbname'] . " size=\"35\" /><br />
				" . ui_language::translate("Database user") . ": 
				<input type=\"text\" name=\"stc_dbuser\" id=\"stc_dbuser\" readonly value=" . $_POST['db']['stc_dbuser'] . " size=\"35\" /><br />
				" . ui_language::translate("Database password") . ": 
				<input type=\"text\" name=\"stc_dbpass\" id=\"stc_dbpass\" readonly value=" . $_POST['db']['stc_dbpass'] . " size=\"35\" />
			</p>
		</div>";

		return $toReturn;
	}

# convert sentastico_admin.php to be inside controller.ext.php instead of using iframe
	# Load external Sentastico admin file.
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

	# Auto-create DB and DB user
	# Auto-create DB
    static function ExecuteCreateDatabase($uid, $packageName)
    {
        global $zdbh;
        global $controller;
		
		# Create DB name
		$packageName = substr($packageName, 0, strpos($packageName, '_'));
		$databasename = substr($packageName, 0, 8) . date("ymdHis"); // package name with date and time

        $currentuser = ctrl_users::GetUserDetail($uid);
        $databasename = strtolower(str_replace(' ', '', $databasename));
        if (fs_director::CheckForEmptyValue($currentuser['username'], $databasename))
		{
            return false;
        }
        runtime_hook::Execute('OnBeforeCreateDatabase');
        try
		{
            $db = $zdbh->mysqlRealEscapeString($currentuser['username'] . "_" . $databasename);
            $sql = $zdbh->prepare("CREATE DATABASE `$db` DEFAULT CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';");
            $sql->execute();
            $sql = $zdbh->prepare("FLUSH PRIVILEGES");
            $sql->execute();
            $sql = $zdbh->prepare("INSERT INTO x_mysql_databases
									(
									my_acc_fk,
									my_name_vc,
									my_created_ts
									)
									VALUES
									(
									:userid,
									:name,
									:time
									)");
            $time = time();
            $name = $currentuser['username'] . "_" . $databasename;

            $sql->bindParam(':userid', $currentuser['userid']);
            $sql->bindParam(':time', $time);
            $sql->bindParam(':name', $name);
            $sql->execute();
        }
		catch (PDOException $e)
		{
            return false;
        }
        runtime_hook::Execute('OnAfterCreateDatabase');
		return $db;
    }

	# Auto-create DB user
    static function ExecuteCreateUser($uid, $database)
    {
        global $zdbh;
        global $controller;

		# Use DB name as DB user name
		$currentuser = ctrl_users::GetUserDetail($uid);
		$username = $currentuser['username'];
		# Leaving below code in for future update to make DB user name different than DB name
		# START
		# Remove DB name prefix
		$username = ltrim(stristr($database, '_'), '_');
        # Check for spaces and remove if found...
        $username = strtolower(str_replace(' ', '', $username));
		# Add prefix to DB user name
		$username = $currentuser['username'] . "_" . $username;
		# END
		
        # If errors are found, then exit before creating user...
        if (fs_director::CheckForEmptyValue($username, $database))
		{
            return false;
        }
		$access = "%";
        runtime_hook::Execute('OnBeforeCreateDatabaseUser');
        $password = fs_director::GenerateRandomPassword(16, 4);
        # Create user in MySQL
        $sql = $zdbh->prepare("CREATE USER :username@:access;");
        $sql->bindParam(':username', $username);
        $sql->bindParam(':access', $access);
        $sql->execute();
        # Set MySQL password for new user...
		if (sys_versions::ShowMySQLVersion() <= "5.7.5")
		{
			# MySQL 5.7 or OLDER
			$sql = $zdbh->prepare("SET PASSWORD FOR :username@:access=PASSWORD(:password)");
        }
		else
		{
			# MySQL 5.7 + 
			$sql = $zdbh->prepare("ALTER USER :username@:access IDENTIFIED BY :password");
		}
        $sql->bindParam(':username', $username);
        $sql->bindParam(':access', $access);
        $sql->bindParam(':password', $password);
        $sql->execute();

        # Get the database ID
        $numrows = $zdbh->prepare("SELECT * FROM x_mysql_databases WHERE my_name_vc=:username AND my_deleted_ts IS NULL");
        $numrows->bindParam(':username', $username);
        $numrows->execute();
        $rowdb = $numrows->fetch();

        # Remove all priveledges to all databases
        $sql = $zdbh->prepare("GRANT USAGE ON *.* TO :username@:access");
        $sql->bindParam(':username', $username);
        $sql->bindParam(':access', $access);
        $sql->execute();

        # Grant privileges for new user to the assigned database
        $usernameClean = $zdbh->mysqlRealEscapeString($username);
        $accessClean = $zdbh->mysqlRealEscapeString($access);
        $my_name_vc = $zdbh->mysqlRealEscapeString($rowdb['my_name_vc']);
        $sql = $zdbh->prepare("GRANT ALL PRIVILEGES ON `$my_name_vc`.* TO `$usernameClean`@`$accessClean`");
        $sql->execute();

        $sql = $zdbh->prepare("FLUSH PRIVILEGES");
        $sql->execute();

        # Add user to Sentora database
        $sql = $zdbh->prepare("INSERT INTO x_mysql_users (
								mu_acc_fk,
								mu_name_vc,
								mu_database_fk,
								mu_pass_vc,
								mu_access_vc,
								mu_created_ts) VALUES (
								:userid,
								:username,
								:databaseid,
								:password,
								:access,
								:time)");
        $sql->bindParam(':userid', $uid);
        $sql->bindParam(':username', $username);
        $sql->bindParam(':databaseid', $rowdb['my_id_pk']);
        $sql->bindParam(':password', $password);
        $sql->bindParam(':access', $access);
        $time = time();
        $sql->bindParam(':time', $time);
        $sql->execute();

        # Get the new user's id
        $numrows = $zdbh->prepare("SELECT * FROM x_mysql_users WHERE mu_name_vc=:username AND mu_acc_fk=:userid AND mu_deleted_ts IS NULL");
        $numrows->bindParam(':username', $username);
        $numrows->bindParam(':userid', $uid);
        $numrows->execute();
        $rowuser = $numrows->fetch();

        # Add database to Sentora user account
        self::ExecuteAddDB($uid, $rowuser['mu_id_pk'], $rowdb['my_id_pk']);
        runtime_hook::Execute('OnAfterCreateDatabaseUser');
		
		$dbInfo = array("stc_dbhost"=>"localhost",
						"stc_dbname"=>$database,
						"stc_dbuser"=>$username,
						"stc_dbpass"=>$password);
		return $dbInfo;
    }

    static function ExecuteAddDB($uid, $myuserid, $dbid)
    {
        global $zdbh;
		
        if (fs_director::CheckForEmptyValue($myuserid, $dbid))
		{
            return false;
        }
        if (!isset($uid) || $uid == NULL || $uid == '')
		{
            $currentuser = ctrl_users::GetUserDetail();
            $uid = $currentuser['userid'];
        }
        runtime_hook::Execute('OnBeforeAddDatabaseAccess');

        $numrows = $zdbh->prepare("SELECT * FROM x_mysql_databases WHERE my_id_pk=:dbid AND my_deleted_ts IS NULL");
        $numrows->bindParam(':dbid', $dbid);
        $numrows->execute();
        $rowdb = $numrows->fetch();

        $numrows = $zdbh->prepare("SELECT * FROM x_mysql_users WHERE mu_id_pk=:myuserid AND mu_deleted_ts IS NULL");
        $numrows->bindParam(':myuserid', $myuserid);
        $numrows->execute();
        $rowuser = $numrows->fetch();

        $my_name_vc = $zdbh->mysqlRealEscapeString($rowdb['my_name_vc']);
        $mu_name_vc = $zdbh->mysqlRealEscapeString($rowuser['mu_name_vc']);
        $mu_access_vc = $zdbh->mysqlRealEscapeString($rowuser['mu_access_vc']);
        $sql = $zdbh->prepare("GRANT ALL PRIVILEGES ON `$my_name_vc`.* TO `$mu_name_vc`@`$mu_access_vc`");
        $sql->bindParam(':my_name_vc', $rowdb['my_name_vc'], PDO::PARAM_STR);
        $sql->bindParam(':mu_name_vc', $rowuser['mu_name_vc'], PDO::PARAM_STR);
        $sql->bindParam(':mu_access_vc', $rowuser['mu_access_vc'], PDO::PARAM_STR);
        $sql->execute();
        $sql = $zdbh->prepare("FLUSH PRIVILEGES");
        $sql->execute();
        $sql2 = $zdbh->prepare("
			INSERT INTO x_mysql_dbmap (
							mm_acc_fk,
							mm_user_fk,
							mm_database_fk) VALUES (
							:uid,
							:myuserid,
							:dbid
                                                        )");
        $sql2->bindParam(':uid', $uid);
        $sql2->bindParam(':myuserid', $myuserid);
        $sql2->bindParam(':dbid', $dbid);
        $sql2->execute();
        runtime_hook::Execute('OnAfterAddDatabaseAccess');
        return true;
    }
	# END Auto-create DB and DB user

	# Package installer
    static function getRunInstallerForm()
	{
		global $controller;
		
        if (isset($_POST['startinstall']) && ($_POST['startinstall'] == 'true'))
		{
			//runtime_csfr::Protect();
			# Set base vars
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
						$hostdatadir = ctrl_options::GetOption('hosted_dir') . $currentuser['username'];
						$userName = $currentuser['username'];
						$random = rand();
						$sysOS = php_uname('s');

						$line ="<h3>" . ui_language::translate("Preparing to install") . ": " . $pkgInstall . "</h3>";
						if (!isset($startinstall))
						{
							if ($pkgdb == "yes")
							{
								$line .= "<div class=\"alert alert-info\" role=\"alert\"><h4>" . ui_language::translate("This package requires a database and database user") . ":</h4>";
								$line .= "<p><b>" . ui_language::translate("Use the Auto-create feature in the form below") . "</b></p>";
								$line .= "<h4>- " . ui_language::translate("or") . " -</h4>";
								$line .= "<ol><li><p><a class=\"btn btn-success btn-small\" type=\"button\" target=\"_blank\" href=\"../../../?module=mysql_databases\">" . ui_language::translate("Create a database manually") . "</a></p></li>";
								$line .= "<li><p><a class=\"btn btn-success btn-small\" type=\"button\" target=\"_blank\" href=\"../../../?module=mysql_users\">" . ui_language::translate("Create a user for the database manually") . "</a></p></li></ol></div>";
							}
							$line .= "<h4>" . ui_language::translate("Please select the domain and folder information to start the installation of") . " " . $pkgInstall . ".</h4>";
							$line .= "<form id=\"form\" name=\"doInstall\" action=\"/?module=sentastico\" method=\"post\">";
							$line .= "<table class=\"table table-striped\">
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
										<font color=\"red\">" . ui_language::translate("NOTE:") . "</font>" . ui_language::translate("The new folder will be created") . ".
									</td>
								</tr>";
								if ($pkgdb == "yes")
								{
									$line .= "<tr>
										<td align=\"right\">
											<label for=\"dir_to_install\">" . ui_language::translate("Auto-create Database and database user") . ":</label>
										</td>
										<td align=\"center\">
											<input type=\"checkbox\" id=\"auto_create\" name=\"auto_create\" value=\"ac_true\">
										</td>
										<td align=\"center\">
											<font color=\"red\">" . ui_language::translate("NOTE") . ":</font>" . ui_language::translate("The database information will be displayed for you to copy and paste into the package's installer form.") . ".
										</td>
									</tr>";
								}
								$line .= "<tr>
									<td colspan=\"3\" align=\"center\">
										<div class=\"alert alert-danger\" role=\"alert\">
											<font color=\"red\">
												<b>
													" . ui_language::translate("Tick the box below to delete all files and folders in the selected location") . ":
													<br />
													" . ui_language::translate("This can not be undone!") . "
												</b>
											</font>
											<br />
											<input type=\"checkbox\" id=\"over_write\" name=\"over_write\" value=\"1\">
										</div>
									</td>
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
					
					# Remove leading and trailing slashes so we can control them
					if ($dir_to_install) $dir_to_install = trim($dir_to_install, "/");
					
					# Add url seperator slash
					if ($dir_to_install) $dir_to_install = "/" . $dir_to_install;
					
					# Retrieve the directory for the selected domain
					$domaindir = FetchDomainDir($userid, $site_domain);
					$completedir = $hostdatadir . "/public_html" . $domaindir . $dir_to_install;
					
					# Check if folder exists and is empty, if not create it or empty it
					$over_write = clean($_POST['over_write']);
					if (isset($over_write) && $over_write == '1')
					{
						emptyDir($completedir);
						$is_dir_ready = true;
					}
					else
					{
						$is_dir_ready = check_folder($completedir);
					}
					# Remove default index.html if exists
					removeIndex($completedir);
					
					$line  = "<h4>" . ui_language::translate("Automated") . " " . $pkgInstall . " " . ui_language::translate("Installation Status") . ":</h4>";

					if (isset($_POST['info'])) $line .= $_POST['info'];
					$info = "";
					$info .= "<div class=\"alert alert-success\" role=\"alert\">";
					if (($is_dir_ready == true) && (file_exists($completedir)))
					{
						$info .= ui_language::translate("Preparing folder") . ": ";
						$info .= "<font color=\"green\">" . ui_language::translate("Folder prepared successfully") . "!</font>";

						set_time_limit(0);
						$info .= "<br>" . ui_language::translate("Installing files") . ": ";
						
						# Un-Compressing The ZIP Archive
						if (UnZip($zipfile . ".zsp", $completedir, $site_domain, $dir_to_install) == 'true')
						{
							$info .= "<font color=\"green\">" . ui_language::translate("Unzip was successful!") . "</font><br>";
							# Set file/folder ownership and permissions if on 'nix
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
							# Auto-create DB and DB user
							if ((!isset($_POST['db']['was_run'])) && (isset($_POST['auto_create']) && $_POST['auto_create'] == "ac_true"))
							{
								$newDBName = self::ExecuteCreateDatabase($userid, $zipfile);
								$_POST['db'] = self::ExecuteCreateUser($userid, $newDBName);
								$_POST['db']['was_run'] = "Yes";
							}
						}
						else
						{
							$line .= "<div class=\"alert alert-warning\" role=\"alert\">";
							$line .= "<font color=\"red\">" . ui_language::translate("Unzip was not successful") . "</font></div>";
							$line .= '<div>
								<form action="?module=sentastico" method="post">
									<input type="hidden" name="startinstall" value="true"> 
									<input type="hidden" name="pkgzip" value="' . $_POST['pkgzip'] . '"> 
									<input type="hidden" name="pkg" value="' . $_POST['pkg'] . '"> 
									<input type="hidden" name="pkgdb" value="' . $_POST['pkgdb'] . '"> 
									<input type="hidden" name="pkginstaller" value="' . $_POST['pkginstaller'] . '">
									<input class="btn btn-primary" type="submit" name="doInstall" value="' . ui_language::translate("Start over") . '" />
								</form>
							</div>';
						}
						# tg - Figure out a better way to do this so we don't need duplicate code
						#    - For some reason these are needed to show the completion links if 'delete all' is checked
						# START
						if ((isset($pkginstaller)) && ($pkginstaller != "") && ($pkginstaller != NULL) && (isset($_POST['install'])) && ($_POST['install'] == 'install'))
						{
							$pkginstaller = "/" . $pkginstaller;
							
							if ((isset($_POST['db']['stc_dbhost'])) && (isset($_POST['db']['stc_dbname'])) && (isset($_POST['db']['stc_dbuser'])) && (isset($_POST['db']['stc_dbpass'])))
							{
								$line .= "
								<div class=\"panel\">
									<div class=\"panel-heading\">
										<div class=\"zmodule_title\">
											" . ui_language::translate("Database Information") . "
										</div>
										<div class=\"zmodule_desc\">
											(" . ui_language::translate("Double click to highlight, right click to copy, then panelinto the") . " " . $pkgInstall . " " . ui_language::translate("installer form") . ")
										</div>
									</div>
									<p>
										" . ui_language::translate("Database host") . ": 
										<input type=\"text\" name=\"stc_dbhost\" id=\"stc_dbhost\" readonly value=" . $_POST['db']['stc_dbhost'] . " size=\"35\" /><br />
										" . ui_language::translate("Database name") . ": 
										<input type=\"text\" name=\"stc_dbname\" id=\"stc_dbname\" readonly value=" . $_POST['db']['stc_dbname'] . " size=\"35\" /><br />
										" . ui_language::translate("Database user") . ": 
										<input type=\"text\" name=\"stc_dbuser\" id=\"stc_dbuser\" readonly value=" . $_POST['db']['stc_dbuser'] . " size=\"35\" /><br />
										" . ui_language::translate("Database password") . ": 
										<input type=\"text\" name=\"stc_dbpass\" id=\"stc_dbpass\" readonly value=" . $_POST['db']['stc_dbpass'] . " size=\"35\" />
									</p>
								</div>";
							}
							$line .= "<a target=\"_blank\" href='http://" . $site_domain . $dir_to_install . $pkginstaller . "/'> <button class=\"btn btn-primary\" type=\"button\">" . ui_language::translate("Complete package setup now") . "</button> </a>";
							$line .= "<button class=\"btn btn-danger\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Complete package setup later") . "</button>";
						}
						else if ((isset($_POST['install'])) && ($_POST['install'] == 'install'))
						{
							if ((isset($_POST['db']['stc_dbhost'])) && (isset($_POST['db']['stc_dbname'])) && (isset($_POST['db']['stc_dbuser'])) && (isset($_POST['db']['stc_dbpass'])))
							{
								$line .= "
								<div class=\"panel\">
									<div class=\"panel-heading\">
										<div class=\"zmodule_title\">
											" . ui_language::translate("Database Information") . "
										</div>
										<div class=\"zmodule_desc\">
											(" . ui_language::translate("Double click to highlight, right click to copy, then panelinto the") . " " . $pkgInstall . " " . ui_language::translate("installer form") . ")
										</div>
									</div>
									<p>
										" . ui_language::translate("Database host") . ": 
										<input type=\"text\" name=\"stc_dbhost\" id=\"stc_dbhost\" readonly value=" . $_POST['db']['stc_dbhost'] . " size=\"35\" /><br />
										" . ui_language::translate("Database name") . ": 
										<input type=\"text\" name=\"stc_dbname\" id=\"stc_dbname\" readonly value=" . $_POST['db']['stc_dbname'] . " size=\"35\" /><br />
										" . ui_language::translate("Database user") . ": 
										<input type=\"text\" name=\"stc_dbuser\" id=\"stc_dbuser\" readonly value=" . $_POST['db']['stc_dbuser'] . " size=\"35\" /><br />
										" . ui_language::translate("Database password") . ": 
										<input type=\"text\" name=\"stc_dbpass\" id=\"stc_dbpass\" readonly value=" . $_POST['db']['stc_dbpass'] . " size=\"35\" />
									</p>
								</div>";
							}
							$line .= "<a target=\"_blank\" href='http://" . $site_domain . $dir_to_install . "'> <button class=\"btn btn-primary\" type=\"button\">" . ui_language::translate("Complete package setup now") . "</button> </a>";
							$line .= "<button class=\"btn btn-danger\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Complete package setup later") . "</button>";
						}
						# END
					}
					else if ((isset($pkginstaller)) && ($pkginstaller != "") && ($pkginstaller != NULL) && (isset($_POST['install'])) && ($_POST['install'] == 'install'))
					{
						$pkginstaller = "/" . $pkginstaller;
						
						if ((isset($_POST['db']['stc_dbhost'])) && (isset($_POST['db']['stc_dbname'])) && (isset($_POST['db']['stc_dbuser'])) && (isset($_POST['db']['stc_dbpass'])))
						{
							$line .= "
							<div class=\"panel\">
								<div class=\"panel-heading\">
									<div class=\"zmodule_title\">
										" . ui_language::translate("Database Information") . "
									</div>
									<div class=\"zmodule_desc\">
										(" . ui_language::translate("Double click to highlight, right click to copy, then panelinto the") . " " . $pkgInstall . " " . ui_language::translate("installer form") . ")
									</div>
								</div>
								<p>
									" . ui_language::translate("Database host") . ": 
									<input type=\"text\" name=\"stc_dbhost\" id=\"stc_dbhost\" readonly value=" . $_POST['db']['stc_dbhost'] . " size=\"35\" /><br />
									" . ui_language::translate("Database name") . ": 
									<input type=\"text\" name=\"stc_dbname\" id=\"stc_dbname\" readonly value=" . $_POST['db']['stc_dbname'] . " size=\"35\" /><br />
									" . ui_language::translate("Database user") . ": 
									<input type=\"text\" name=\"stc_dbuser\" id=\"stc_dbuser\" readonly value=" . $_POST['db']['stc_dbuser'] . " size=\"35\" /><br />
									" . ui_language::translate("Database password") . ": 
									<input type=\"text\" name=\"stc_dbpass\" id=\"stc_dbpass\" readonly value=" . $_POST['db']['stc_dbpass'] . " size=\"35\" />
								</p>
							</div>";
						}
						$line .= "<a target=\"_blank\" href='http://" . $site_domain . $dir_to_install . $pkginstaller . "/'> <button class=\"btn btn-primary\" type=\"button\">" . ui_language::translate("Complete package setup now") . "</button> </a>";
						$line .= "<button class=\"btn btn-danger\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Complete package setup later") . "</button>";
					}
					else if ((isset($_POST['install'])) && ($_POST['install'] == 'install'))
					{
						if ((isset($_POST['db']['stc_dbhost'])) && (isset($_POST['db']['stc_dbname'])) && (isset($_POST['db']['stc_dbuser'])) && (isset($_POST['db']['stc_dbpass'])))
						{
							$line .= "
							<div class=\"panel\">
								<div class=\"panel-heading\">
									<div class=\"zmodule_title\">
										" . ui_language::translate("Database Information") . "
									</div>
									<div class=\"zmodule_desc\">
										(" . ui_language::translate("Double click to highlight, right click to copy, then panelinto the") . " " . $pkgInstall . " " . ui_language::translate("installer form") . ")
									</div>
								</div>
								<p>
									" . ui_language::translate("Database host") . ": 
									<input type=\"text\" name=\"stc_dbhost\" id=\"stc_dbhost\" readonly value=" . $_POST['db']['stc_dbhost'] . " size=\"35\" /><br />
									" . ui_language::translate("Database name") . ": 
									<input type=\"text\" name=\"stc_dbname\" id=\"stc_dbname\" readonly value=" . $_POST['db']['stc_dbname'] . " size=\"35\" /><br />
									" . ui_language::translate("Database user") . ": 
									<input type=\"text\" name=\"stc_dbuser\" id=\"stc_dbuser\" readonly value=" . $_POST['db']['stc_dbuser'] . " size=\"35\" /><br />
									" . ui_language::translate("Database password") . ": 
									<input type=\"text\" name=\"stc_dbpass\" id=\"stc_dbpass\" readonly value=" . $_POST['db']['stc_dbpass'] . " size=\"35\" />
								</p>
							</div>";

						}
						$line .= "<a target=\"_blank\" href='http://" . $site_domain . $dir_to_install . $pkginstaller . "'> <button class=\"btn btn-primary\" type=\"button\">" . ui_language::translate("Complete package setup now") . "</button> </a>";
						$line .= "<button class=\"btn btn-danger\" onClick=\"javascript:location.href='?module=sentastico'\">" . ui_language::translate("Complete package setup later") . "</button>";
					}
					else
					{
						$line .= "<div class=\"alert alert-warning\" role=\"alert\"><h4><font color=\"red\">" . ui_language::translate("Destination folder is not empty!") . "</font></h4>" . ui_language::translate("The selected domain or folder") . "  (<strong>/public_html" . $domaindir . "/" . $dir_to_install . "</strong>) " . ui_language::translate("already contains files or folders") . ".<br>" . ui_language::translate("Start over and create a new folder, manually delete the files and folders using FTP") . ".</div>";
						$line .= '<div>
							<form action="?module=sentastico" method="post">
								<input type="hidden" name="startinstall" value="true"> 
								<input type="hidden" name="pkgzip" value="' . $_POST['pkgzip'] . '"> 
								<input type="hidden" name="pkg" value="' . $_POST['pkg'] . '"> 
								<input type="hidden" name="pkgdb" value="' . $_POST['pkgdb'] . '"> 
								<input type="hidden" name="pkginstaller" value="' . $_POST['pkginstaller'] . '">
								<input class="btn btn-primary" type="submit" name="doInstall" value="' . ui_language::translate("Change install settings") . '" />
							</form>
						  </div>';
						  return $line;
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
		# Get package list from database
		$sql = "SELECT * FROM x_sentastico";
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
		  # Check to see if any packages exist
		  $res = $zdbh->prepare('SELECT COUNT(*) FROM x_sentastico');
		  $res->execute();
		  $num_rows = $res->fetchColumn();

		if ($num_rows != 0)
		{
			$sql = $zdbh->prepare($sql);
			$sql->execute();
		
			while ($rowsettings = $sql->fetch())
			{
				# START - Info and DB tags
				if ($rowsettings['pkg_db'] == 'yes') $rowsettings['pkg_dbr'] = "<font color='green'><strong>" . ui_language::translate("YES") . "</strong></font>";
				else $rowsettings['pkg_dbr'] = "<font color='red'><strong>" . ui_language::translate("NO") . "</strong></font>";
				# END - Info and DB tags
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
        $copyright = '<font face="ariel" size="2">' . ui_module::GetModuleName() . ' v2.1.1 &copy; 2013-' . date("Y") . ' by <a target="_blank" href="http://forums.sentora.org/member.php?action=profile&uid=2">TGates</a> for <a target="_blank" href="http://sentora.org">Sentora Control Panel</a> &#8212; ' . ui_language::translate("Help support future development of this module and donate today!") . '</font>';
        return $copyright;
    }
}
?>