<?php
/* 
    bts_install.php
     
    Copyright (c) 2013 - 2016 Andreas Schmidhuber
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

    The views and conclusions contained in the software and documentation are those
    of the authors and should not be interpreted as representing official policies,
    either expressed or implied, of the FreeBSD Project.
*/
$vstg = "v0.7.1";                           // extension version
$appname = "BitTorrent Sync";

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

$vs = str_replace(".", "", $vstg);                          
$return_val = mwexec("fetch {$verify_hostname} -vo {$install_dir}master.zip 'https://github.com/crestAT/nas4free-bittorrent-sync/releases/download/{$vstg}/bts-{$vs}.zip'", true);
if ($return_val == 0) {
    $return_val = mwexec("tar -xf {$install_dir}master.zip -C {$install_dir} --exclude='.git*' --strip-components 1", true);
    if ($return_val == 0) {
		exec("rm {$install_dir}master.zip");
        exec("chmod -R 775 {$install_dir}btsync");
        if (is_file("{$install_dir}btsync/version.txt")) { $file_version = exec("cat {$install_dir}btsync/version.txt"); }
        else { $file_version = "n/a"; }
        $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
    }
    else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip corrupt /"); }
}
else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip"); }

// install application on server
if ( !isset($config['btsync']) || !is_array($config['btsync'])) $config['btsync'] = array();
$config['btsync']['appname'] = $appname;
$config['btsync']['rootfolder'] = "{$install_dir}btsync/";
$config['btsync']['backupfolder'] = $config['btsync']['rootfolder']."backup/";
$config['btsync']['updatefolder'] = $config['btsync']['rootfolder']."update/";
$config['btsync']['product_executable'] = "rslsync"; 
$config['btsync']['version'] = exec("cat {$config['btsync']['rootfolder']}version.txt");
//--- remove an existing old format entry
if (is_array($config['rc']) && is_array($config['rc']['postinit']) && is_array( $config['rc']['postinit']['cmd'])) {
    for ($i = 0; $i < count($config['rc']['postinit']['cmd']); ++$i) {
        if (preg_match('/btsync/', $config['rc']['postinit']['cmd'][$i])) unset($config['rc']['postinit']['cmd'][$i]);
    }
}

// remove existing entries for new rc format
$sphere_array = &$config['rc']['param'];
if (is_array($config['rc']) && is_array($config['rc']['param'])) {
    for ($i = 0; $i < count($config['rc']['param']); ++$i) {
		if (false !== ($index = array_search_ex("{$config['btsync']['appname']} Extension", $sphere_array, 'name'))) unset($sphere_array[$index]);
	}
}

if ($release[0] >= 11.0) {	// new rc format
	// postinit command
	$rc_param = [];
	$rc_param['uuid'] = uuid();
	$rc_param['name'] = "{$appname} Extension";
	$rc_param['value'] = "/usr/local/bin/php-cgi -f {$config['btsync']['rootfolder']}bts-start.php";
	$rc_param['comment'] = "Start {$appname} (Resilio Sync)";
	$rc_param['typeid'] = '2';
	$rc_param['enable'] = true;
	$config['rc']['param'][] = $rc_param;
	$config['btsync']['rc_uuid_start'] = $rc_param['uuid'];
	
	unset($rc_param);
	/* shutdown command */
	$rc_param = [];
	$rc_param['uuid'] = uuid();
	$rc_param['name'] = "{$appname} Extension";
	$rc_param['value'] = "killall {$config['btsync']['product_executable']}";
	$rc_param['comment'] = "Stop {$appname} (Resilio Sync)";
	$rc_param['typeid'] = '3';
	$rc_param['enable'] = true;
	$config['rc']['param'][] = $rc_param;
	$config['btsync']['rc_uuid_stop'] = $rc_param['uuid'];
}
else $config['rc']['postinit']['cmd'][$i] = "/usr/local/bin/php-cgi -f {$config['btsync']['rootfolder']}bts-start.php";

if ($arch == "i386" || $arch == "x86") { $config['btsync']['architecture'] = "i386"; }
else { $config['btsync']['architecture'] = "x64"; }
//  2016.01.30: https://download-cdn.getsync.com/stable/FreeBSD-{ARCHITECTURE}/BitTorrent-Sync_freebsd_{ARCHITECTURE}.tar.gz
//  2016.10.09: https://download-cdn.resilio.com/stable/FreeBSD-x64/resilio-sync_freebsd_x64.tar.gz
$config['btsync']['download_url'] = "https://download-cdn.resilio.com/stable/FreeBSD-".$config['btsync']['architecture']."/resilio-sync_freebsd_".$config['btsync']['architecture'].".tar.gz";
$config['btsync']['previous_url'] = $config['btsync']['download_url'];
if (!is_dir ($config['btsync']['rootfolder'].'.sync')) { exec ("mkdir -p ".$config['btsync']['rootfolder'].'.sync'); }
if (!is_dir ($config['btsync']['backupfolder'])) { exec ("mkdir -p ".$config['btsync']['backupfolder']); }
if (!is_dir ($config['btsync']['updatefolder'])) { exec ("mkdir -p ".$config['btsync']['updatefolder']); }
$return_val = mwexec ("fetch -o {$config['btsync']['rootfolder']}stable {$config['btsync']['download_url']}", true);
if ($return_val != 0) {
    echo "\n"."Download of latest Resilio Sync executable failed, maybe the download URL has changed!";
    echo "\n"."After the installation proceed to Extensions|BitTorrent Sync|Maintenance, check/enter a new download URL, save and fetch/install the Resilio Sync executable!";
}
else {
    mwexec ("cd {$config['btsync']['rootfolder']} && tar -xf stable", true);
	mwexec ("rm {$config['btsync']['rootfolder']}stable", true);
    $config['btsync']['product_version'] = exec ("{$config['btsync']['rootfolder']}{$config['btsync']['product_executable']} --help | awk '/Sync/ {print $3}'");
   	exec ("cp {$config['btsync']['rootfolder']}{$config['btsync']['product_executable']} {$config['btsync']['backupfolder']}{$config['btsync']['product_executable']}-{$config['btsync']['product_version']}");
    $config['btsync']['size'] = exec("fetch -s {$config['btsync']['download_url']}");
    if ($config['btsync']['product_version'] == '') { $config['btsync']['product_version'] = 'n/a'; }
    if ($config['btsync']['size'] == '') { $config['btsync']['size'] = 'n/a'; }
	echo "{$config['btsync']['appname']} Extension Version {$config['btsync']['version']} installed";
	echo "\nResilio Sync Version {$config['btsync']['product_version']} installed\n";
}
write_config();
require_once("{$config['btsync']['rootfolder']}bts-start.php");
echo "\nInstallation completed, use WebGUI | Extensions | ".$appname." to configure \nthe application (don't forget to refresh the WebGUI before use)!\n";
?>
