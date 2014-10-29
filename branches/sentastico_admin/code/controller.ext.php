<?php

/**
 *
 * Zantastico X Installer for ZPX
 * Version  : 1.0.0
 * Author   : Mudasir Mirza
 * Modified by TGates (http://www.zpanelcp.com)
 * Email    : mudasirmirza@gmail.com
 * 
 */

// Function to retrieve remote XML for update check
function check_remote_xml($xmlurl,$destfile){
    $feed = simplexml_load_file($xmlurl);
    if ($feed)
    {
        // $feed is valid, save it
        $feed->asXML($destfile);
    }
    elseif (file_exists($destfile))
    {
        // $feed is not valid, grab the last backup
        $feed = simplexml_load_file($destfile);
    }
    else
    {
        die('Unable to retrieve XML file');
    }
}


class module_controller {

    static $error;
    static $ok;
    static $error_message;

    static function getCheckUpdate() {
        global $zdbh;
        global $controller;
        global $zlo;
        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_updateurl = $mod_config->document->updateurl[0]->tagData;
        $module_version = $mod_config->document->version[0]->tagData;

        // Download XML in Update URL and get Download URL and Version
        $myfile = check_remote_xml($module_updateurl, $module_path."/zantasticox_admin.xml");
        $update_config = new xml_reader(fs_filehandler::ReadFileContents($module_path."/zantasticox_admin.xml"));
        $update_config->Parse();
        $update_url = $update_config->document->downloadurl[0]->tagData;
        $update_version = $update_config->document->latestversion[0]->tagData;

        if($update_version > $module_version)
            return true;
        return false;
        
    }    
    
    static function doUpdateModule() {
        global $zdbh, $controller, $zlo;

        $myzsudo=ctrl_options::GetOption('zsudo');
        $zproot = ctrl_options::GetOption('zpanel_root');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_updateurl = $mod_config->document->updateurl[0]->tagData;

        // Download XML in Update URL and get Download URL and Version
        exec("$myzsudo wget -O /tmp/".$controller->GetControllerRequest('URL', 'module').".xml $module_updateurl");
        sleep(2);
        exec("$myzsudo chmod 777 /tmp/".$controller->GetControllerRequest('URL', 'module').".xml");
        $update_xml = "/tmp/".$controller->GetControllerRequest('URL', 'module').".xml";
        $update_config = new xml_reader(fs_filehandler::ReadFileContents($update_xml));
        $update_config->Parse();
        $update_url = $update_config->document->downloadurl[0]->tagData;
        $file_name = basename($update_url);
        $folder_name= basename($update_url, ".zpp");
        
        if (fs_director::CheckFileExists("etc/zppy-cache/package-downloads/" . $file_name . "")) {
            exec("$myzsudo rm -f etc/zppy-cache/package-downloads/" . $file_name . "");
        }
        sleep(1);
        exec("$myzsudo wget -O " . $zproot . "etc/zppy-cache/package-downloads/" . $file_name . " ". $update_url ."");
        exec("$myzsudo chmod 777 " . $zproot . "etc/zppy-cache/package-downloads/" . $file_name . "");
       
        exec("$myzsudo mkdir /tmp/" . $folder_name . "");
        sleep(1);
        exec("$myzsudo unzip " . $zproot . "etc/zppy-cache/package-downloads/" . $folder_name . ".zpp  -d /tmp/" . $folder_name."");
        sleep(1);
        exec("$myzsudo \cp -fr /tmp/" . $folder_name. "/*  " . $zproot . "modules/" . $folder_name . "/");
        sleep(1);
        exec("$myzsudo chown -R apache.apache " . $zproot . "modules/" . $folder_name . "/");
        exec("$myzsudo chmod -R 777 " . $zproot . "modules/" . $folder_name . "/");
        sleep(1);
        exec("$myzsudo \rm -f /tmp/" . $folder_name . ".xml");
        exec("$myzsudo \rm -fr /tmp/" . $folder_name . "");
        }
		
    static function getCheckDBUpdates() {
        global $zdbh;
        include(ctrl_options::GetOption('zpanel_root') . '/cnf/db.php');

        // Updates for versoin 310
        $v_update_sql = $zdbh->prepare("UPDATE x_modules set mo_version_in=100 where mo_name_vc='" . ui_module::GetModuleName() . "'");
        $v_update_sql->execute();

    }
   
 
    static function getModuleInfoName() {
        global $controller;
        $info = ui_module::GetModuleXMLTags($controller->GetControllerRequest('URL', 'showinfo'));
        return $info['name'];
    }

    static function getModuleDescription() {
        global $controller;
        $info = ui_module::GetModuleXMLTags($controller->GetControllerRequest('URL', 'showinfo'));
        return $info['desc'];
    }

    static function getModuleDeveloperName() {
        global $controller;
        $info = ui_module::GetModuleXMLTags($controller->GetControllerRequest('URL', 'showinfo'));
        return $info['authorname'];
    }

    static function getModuleDeveloperEmail() {
        global $controller;
        $info = ui_module::GetModuleXMLTags($controller->GetControllerRequest('URL', 'showinfo'));
        return $info['authoremail'];
    }

    static function getModuleVersion() {
        global $controller;
        $info = ui_module::GetModuleXMLTags($controller->GetControllerRequest('URL', 'showinfo'));
        return $info['version'];
    }

    static function getModuleDeveloperURL() {
        global $controller;
        $info = ui_module::GetModuleXMLTags($controller->GetControllerRequest('URL', 'showinfo'));
        return $info['authorurl'];
    }

    static function getModuleUpdateURL() {
        global $controller;
        global $zdbh;
        $retval = $zdbh->query("SELECT mo_updateurl_tx FROM x_modules WHERE mo_folder_vc = '" . $controller->GetControllerRequest('URL', 'showinfo') . "'")->Fetch();
        $retval = $retval['mo_updateurl_tx'];
        return $retval;
    }

    static function getLatestVersion() {
        global $controller;
        global $zdbh;
        $retval = $zdbh->query("SELECT mo_updatever_vc FROM x_modules WHERE mo_folder_vc = '" . $controller->GetControllerRequest('URL', 'showinfo') . "'")->Fetch();
        $retval = $retval['mo_updatever_vc'];
        return $retval;
    }

    static function getPackageType() {
        global $controller;
        global $zdbh;
        $retval = $zdbh->query("SELECT mo_type_en FROM x_modules WHERE mo_folder_vc = '" . $controller->GetControllerRequest('URL', 'showinfo') . "'")->Fetch();
        $retval = $retval['mo_type_en'];
        return $retval;
    }

    static function doInstallPackage() {
        self::$error_message = "";
        self::$error = false;
        if ($_FILES['modulefile']['error'] > 0) {
            self::$error_message = "Couldn't upload the file, " . $_FILES['modulefile']['error'] . "";
        } else {
            $archive_ext = fs_director::GetFileExtension($_FILES['modulefile']['name']);
            $module_folder = fs_director::GetFileNameNoExtentsion($_FILES['modulefile']['name']);
            if (!fs_director::CheckFolderExists(ctrl_options::GetSystemOption('zpanel_root') . 'modules/' . $module_folder)) {
                if ($archive_ext != 'zpp') {
                    self::$error_message = "Package type was not detected as a .zpp (ZPanel Package) archive.";
                } else {
                    if (fs_director::CreateDirectory(ctrl_options::GetSystemOption('zpanel_root') . 'modules/' . $module_folder)) {
                        if (sys_archive::Unzip($_FILES['modulefile']['tmp_name'], ctrl_options::GetSystemOption('zpanel_root') . 'modules/' . $module_folder . '/')) {
                            if (!fs_director::CheckFileExists(ctrl_options::GetSystemOption('zpanel_root') . 'modules/' . $module_folder . '/module.xml')) {
                                self::$error_message = "No module.xml file found in the unzipped archive.";
                            } else {
                                ui_module::ModuleInfoToDB($module_folder);
                                $extra_config = ctrl_options::GetSystemOption('zpanel_root') . "modules/" . $module_folder . "/deploy/install.run";
                                if (fs_director::CheckFileExists($extra_config))
                                    exec(ctrl_options::GetSystemOption('php_exer') . " " . $extra_config . "");
                                self::$ok = true;
                            }
                        } else {
                            self::$error_message = "Couldn't unzip the archive (" . $_FILES['modulefile']['tmp_name'] . ") to " . ctrl_options::GetSystemOption('zpanel_root') . 'modules/' . $module_folder . '/';
                        }
                    } else {
                        self::$error_message = "Couldn't create module folder in " . ctrl_options::GetSystemOption('zpanel_root') . 'modules/' . $module_folder . "";
                    }
                }
            } else {
                self::$error_message = "The module " . $module_folder . " is already installed on this server!";
            }
        }
        return;
    }

    static function getModuleName() {
        $module_name = ui_language::translate(ui_module::GetModuleName());
        return $module_name;
    }

    static function getModuleIcon() {
        global $controller;
        $module_icon = "modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/icon.png";
        return $module_icon;
    }

	static function getPackageSelection() {

		$packages= new xml_reader(fs_filehandler::ReadFileContents('modules/zantasticox_admin/packages/packages.xml'));
		$packages->Parse();

		echo "
		<br /><br />
		<table border=\"1\" width=\"97%\" class=\"zgrid\">
		  <tr>
			<th>Package<br />Name</th>
			<th>Version<br />Number</th>
			<th>Type of<br />Package</th>
			<th>Package<br />Description</th>
			<th>Database<br />Required?</th>
			<th>&nbsp;</th>
		  </tr>";
		foreach($packages->document->package as $package){
			// START - Info and DB tags by tgates
			if($package->db[0]->tagData=='yes') $package->db[0]->tagData="<font color='green'><strong>YES</strong></font>";
			else $package->db[0]->tagData="<font color='red'><strong>NO</strong></font>";
			// END - Info and DB tags by tgates
		echo "<tr>
			<th>" .$package->name[0]->tagData. "</th>
			<th>" .$package->version[0]->tagData. "</th>
			<td>" .$package->type[0]->tagData. "</td>
			<!-- START - Info and DB tags by tgates -->
			<td>" .$package->info[0]->tagData. "</td>
			<td><center>" .$package->db[0]->tagData. "</center></td>
			<!-- END - Info and DB tags by tgates -->
			<!--<td>" .$package->zipname[0]->tagData. "</td> <!-- USED FOR DEBUGGING -->
			<td><a href=\"JavaScript:newPopup('modules/zantasticox_admin/code/installer.php?pkgzip=" .$package->zipname[0]->tagData. "&pkg=" .$package->name[0]->tagData. "&startinstall=true');\"><button class=\"fg-button ui-state-default ui-corner-all\" type=\"button\"><: Install :></button></a></td>
 			</tr>";
		  		}
		echo "</table>
                <br />";
			}
}
?>