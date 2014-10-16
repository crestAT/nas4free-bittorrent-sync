#!/usr/local/bin/php-cgi -f
<?php
/* 
 * btsync-install.php 
 * created 2013 by Andreas Schmidhuber
 *
 * 0.6.1    C: download path for BTS application
 * 0.6      introduce scheduler
 * 0.5.7    correct display of boolean values from sync.conf file in btsync.php
 * 0.5.6    introduce sync.conf file
 * 0.5.5    log file filter
 * 0.5.4.1  backup management 
 * 0.5a     fetch update, remove startup error msg
*/
$version = "v0.6.1";
$appname = "BitTorrent Sync";

require_once("config.inc");
require_once("functions.inc");
require_once("install.inc");
require_once("util.inc");
require_once("tui.inc");
require("guiconfig.inc");

function cronjob_process_updatenotification($mode, $data) {
	global $config;
	$retval = 0;
	switch ($mode) {
		case UPDATENOTIFY_MODE_NEW:
		case UPDATENOTIFY_MODE_MODIFIED:
			break;
		case UPDATENOTIFY_MODE_DIRTY:
			if (is_array($config['cron']['job'])) {
				$index = array_search_ex($data, $config['cron']['job'], "uuid");
				if (false !== $index) {
					unset($config['cron']['job'][$index]);
					write_config();
				}
			}
			break;
	}
	return $retval;
}

$arch = $g['arch'];
$platform = $g['platform'];
if (($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64")) { echo "\funsupported architecture!\n"; exit(1);  }
if ($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb") { echo "\funsupported platform!\n";  exit(1); }

// display installation option
$amenuitem['1']['tag'] = "1";
$amenuitem['1']['item'] = "Install {$appname} extension";
$amenuitem['2']['tag'] = "2";
$amenuitem['2']['item'] = "Uninstall {$appname} extension";
$result = tui_display_menu(" ".$appname." Extension ".$version." ", "Select Install or Uninstall", 60, 10, 6, $amenuitem, $installopt);
if (0 != $result) { echo "\fInstallation aborted!\n"; exit(0);}

if ($installopt == 2 ) { 
// kill running process
    exec("killall -KILL btsync");
// remove application section
    if ( is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
		for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) {
    		if (preg_match('/btsync/', $config['rc']['postinit']['cmd'][$i])) {	unset($config['rc']['postinit']['cmd'][$i]);} else{}
		++$i;
		}
	}
	if ( is_array($config['rc']['shutdown'] ) && is_array( $config['rc']['shutdown']['cmd'] ) ) {
		for ($i = 0; $i < count($config['rc']['shutdown']['cmd']); ) {
            if (preg_match('/btsync/', $config['rc']['shutdown']['cmd'][$i])) {	unset($config['rc']['shutdown']['cmd'][$i]); } else {}
		++$i;	
		}
	}
// unlink created  links
	if (is_dir ("/usr/local/www/ext/btsync")) {
	foreach ( glob( "{$config['btsync']['rootfolder']}ext/*.php" ) as $file ) {
	$file = str_replace("{$config['btsync']['rootfolder']}ext/", "/usr/local/www", $file);
	if ( is_link( $file ) ) { unlink( $file ); } else {} }
	mwexec ("rm -rf /usr/local/www/ext/btsync");
	}
// remove cronjobs
    if (isset($config['btsync']['enable_schedule'])) {
    	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $config['btsync']['schedule_uuid_startup']);
    	if (is_array($config['cron']['job'])) {
    				$index = array_search_ex($data, $config['cron']['job'], "uuid");
    				if (false !== $index) {
    					unset($config['cron']['job'][$index]);
    				}
    			}
    	write_config();
    	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $config['btsync']['schedule_uuid_closedown']);
    	if (is_array($config['cron']['job'])) {
    				$index = array_search_ex($data, $config['cron']['job'], "uuid");
    				if (false !== $index) {
    					unset($config['cron']['job'][$index]);
    				}
    			}
    	write_config();
        $retval = 0;
        if (!file_exists($d_sysrebootreqd_path)) {
        	$retval |= updatenotify_process("cronjob", "cronjob_process_updatenotification");
        	config_lock();
        	$retval |= rc_update_service("cron");
        	config_unlock();
        }
        $savemsg = get_std_save_message($retval);
        if ($retval == 0) {
        	updatenotify_delete("cronjob");
        }
    }
// remove application section from config.xml
	if ( is_array($config['btsync'] ) ) { unset( $config['btsync'] ); write_config();}
	echo "\f".$appname." entries removed. Remove files manually!\n"; 
}

// install application on server
if ($installopt == 1 ) {
	$cwdir = getcwd();
	if ( !isset($config['btsync']) || !is_array($config['btsync'])) {
        $config['btsync'] = array();
		$path1 = pathinfo($cwdir);
		$config['btsync']['appname'] = $appname;
        $config['btsync']['version'] = $version; 
		$config['btsync']['rootfolder'] = $path1['dirname']."/".$path1['basename']."/btsync/";
		$config['btsync']['backupfolder'] = $config['btsync']['rootfolder']."backup/";
		$config['btsync']['updatefolder'] = $config['btsync']['rootfolder']."update/";
		$cwdir = $config['btsync']['rootfolder'];
        $i = 0;
        if ( is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
            for ($i; $i < count($config['rc']['postinit']['cmd']);) {
                if (preg_match('/btsync/', $config['rc']['postinit']['cmd'][$i])) break;
                ++$i;
            }
        }
        $config['rc']['postinit']['cmd'][$i] = $config['btsync']['rootfolder']."btsync_start.php";
        if ($arch == "i386" || $arch == "x86") { $config['btsync']['architecture'] = "i386"; }
        else { $config['btsync']['architecture'] = "x64"; }
        echo ("\f");
        exec ("fetch -o ".$cwdir." http://download-new.utorrent.com/endpoint/btsync/os/FreeBSD-".$config['btsync']['architecture']."/track/stable");
        exec ("cd ".$cwdir." && tar -xzvf stable");
        if ( !is_file ($cwdir.'btsync') ) { echo ('Executable file "btsync" not found, installation aborted!'); exit (3); }
        $config['btsync']['product_version'] = exec ($cwdir."btsync --help | awk '/".$appname."/ {print $3}'");
        if (!is_dir ($config['btsync']['rootfolder'].'.sync')) { exec ("mkdir -p ".$config['btsync']['rootfolder'].'.sync'); }
        if (!is_dir ($config['btsync']['backupfolder'])) { exec ("mkdir -p ".$config['btsync']['backupfolder']); }
        if (!is_dir ($config['btsync']['updatefolder'])) { exec ("mkdir -p ".$config['btsync']['updatefolder']); }
       	exec ("cp ".$cwdir."btsync ".$config['btsync']['backupfolder']."btsync-".$config['btsync']['product_version']);
        $config['btsync']['size'] = exec ("fetch -s  http://download-new.utorrent.com/endpoint/btsync/os/FreeBSD-".$config['btsync']['architecture']."/track/stable");
        if ($config['btsync']['product_version'] == '') { $config['btsync']['product_version'] = 'n/a'; }
        if ($config['btsync']['size'] == '') { $config['btsync']['size'] = 'n/a'; }
        write_config();
        require_once("{$config['btsync']['rootfolder']}btsync_start.php");
        echo "\n".$appname." Version ".$config['btsync']['product_version']." installed";
        echo "\n\nInstallation completed, use WebGUI | Extensions | ".$appname." to configure the application!\n";
    }
	else { echo "\f".$appname." is already installed!\n"; }
}
?>