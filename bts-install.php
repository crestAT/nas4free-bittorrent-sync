<?php
/* 
    bts_install.php
     
    Copyright (c) 2013 - 2018 Andreas Schmidhuber
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
$version = "v0.7.3.1";							// extension version
$appname = "BitTorrent Sync";
$config_name = "btsync";
$version_striped = str_replace(".", "", $version);

require_once("config.inc");

$arch = $g['arch'];
$platform = $g['platform'];
if (($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64")) { echo "unsupported architecture!\n"; exit(1);  }
if ($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb") { echo "unsupported platform!\n";  exit(1); }

// install extension
global $input_errors;
global $savemsg;

$install_dir = dirname(__FILE__)."/";                           // get directory where the installer script resides
if (!is_dir("{$install_dir}btsync/backup")) { mkdir("{$install_dir}btsync/backup", 0775, true); }
if (!is_dir("{$install_dir}btsync/update")) { mkdir("{$install_dir}btsync/update", 0775, true); }

// check FreeBSD release for fetch options >= 9.3
$release = explode("-", exec("uname -r"));
if ($release[0] >= 9.3) $verify_hostname = "--no-verify-hostname";
else $verify_hostname = "";

exec("killall btsync");		// to be sure rslsync can startup successfully
exec("killall rslsync");	// to be sure rslsync can startup successfully

$return_val = mwexec("fetch {$verify_hostname} -vo {$install_dir}master.zip 'https://github.com/crestAT/nas4free-bittorrent-sync/releases/download/{$version}/bts-{$version_striped}.zip'", false);
if ($return_val == 0) {
    $return_val = mwexec("tar -xf {$install_dir}master.zip -C {$install_dir} --exclude='.git*' --strip-components 1", true);
    if ($return_val == 0) {
		exec("rm {$install_dir}master.zip");
        exec("chmod -R 775 {$install_dir}btsync");
        require_once("{$install_dir}btsync/ext/extension-lib.inc");		// v1.1
        $config_file = "{$install_dir}btsync/ext/{$config_name}.conf";
        if (is_file("{$install_dir}btsync/version.txt")) { $file_version = exec("cat {$install_dir}btsync/version.txt"); }
        else { $file_version = "n/a"; }
        $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
    }
    else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip corrupt /"); return;}
}
else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip"); return;}

// install / update application
if (($configuration = ext_load_config($config_file)) === false) {
    $configuration = array();             // new installation or first time with json config
    $new_installation = true;
}
else $new_installation = false;

// check for $config['btsync'] entry in config.xml, convert it to new config file and remove it
if (isset($config[$config_name]) && is_array($config[$config_name])) {
    $configuration = $config[$config_name];								// load config
    unset($config[$config_name]);										// remove old config
}

$configuration['appname'] = $appname;
$configuration['rootfolder'] = "{$install_dir}btsync/";
$configuration['backupfolder'] = $configuration['rootfolder']."backup/";
$configuration['updatefolder'] = $configuration['rootfolder']."update/";
$configuration['product_executable'] = "rslsync"; 
$configuration['version'] = exec("cat {$configuration['rootfolder']}version.txt");
$configuration['postinit'] = "/usr/local/bin/php-cgi -f {$configuration['rootfolder']}bts-start.php";
$configuration['shutdown'] = "killall {$configuration['product_executable']}";
if ($arch == "i386" || $arch == "x86") { $configuration['architecture'] = "i386"; }
else { $configuration['architecture'] = "x64"; }
//  2016.01.30: https://download-cdn.getsync.com/stable/FreeBSD-{ARCHITECTURE}/BitTorrent-Sync_freebsd_{ARCHITECTURE}.tar.gz
//  2016.10.09: https://download-cdn.resilio.com/stable/FreeBSD-x64/resilio-sync_freebsd_x64.tar.gz
$configuration['download_url'] = "https://download-cdn.resilio.com/stable/FreeBSD-".$configuration['architecture']."/resilio-sync_freebsd_".$configuration['architecture'].".tar.gz";
$configuration['previous_url'] = $configuration['download_url'];
if (!is_dir ($configuration['rootfolder'].'.sync')) { exec ("mkdir -p ".$configuration['rootfolder'].'.sync'); }
if (!is_dir ($configuration['backupfolder'])) { exec ("mkdir -p ".$configuration['backupfolder']); }
if (!is_dir ($configuration['updatefolder'])) { exec ("mkdir -p ".$configuration['updatefolder']); }
$return_val = mwexec ("fetch -o {$configuration['rootfolder']}stable {$configuration['download_url']}", false);
if ($return_val != 0) {
    echo "\n"."Download of latest Resilio Sync executable failed, maybe the download URL has changed!";
    echo "\n"."After the installation proceed to Extensions|BitTorrent Sync|Maintenance, check/enter a new download URL, save and fetch/install the Resilio Sync executable!";
}
else {
    mwexec ("cd {$configuration['rootfolder']} && tar -xf stable", false);
	mwexec ("rm {$configuration['rootfolder']}stable", false);
    $configuration['product_version'] = exec ("{$configuration['rootfolder']}{$configuration['product_executable']} --help | awk '/Sync/ {print $3}'");
   	exec ("cp {$configuration['rootfolder']}{$configuration['product_executable']} {$configuration['backupfolder']}{$configuration['product_executable']}-{$configuration['product_version']}");
    $configuration['size'] = exec("fetch -s {$configuration['download_url']}");
    if ($configuration['product_version'] == '') { $configuration['product_version'] = 'n/a'; }
    if ($configuration['size'] == '') { $configuration['size'] = 'n/a'; }
	if ($new_installation) {
		echo "{$configuration['appname']} Extension Version {$configuration['version']} installed";
		echo "\nResilio Sync Version {$configuration['product_version']} installed\n";
	} 
}

ext_remove_rc_commands($config_name);
ext_remove_rc_commands($configuration['product_executable']);
$configuration['rc_uuid_start'] = $configuration['postinit'];
$configuration['rc_uuid_stop'] = $configuration['shutdown'];
ext_create_rc_commands($appname, $configuration['rc_uuid_start'], $configuration['rc_uuid_stop']);
ext_save_config($config_file, $configuration);

if ($new_installation) echo "\nInstallation completed, use WebGUI | Extensions | ".$appname." to configure the application!\n";
else $savemsg = sprintf(gettext("Update to version %s completed!"), $configuration['version']);
require_once("{$configuration['rootfolder']}bts-start.php");
?>
