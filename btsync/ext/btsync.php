<?php
/* 
    btsync.php

    Copyright (c) 2013, 2014, Andreas Schmidhuber
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
require("auth.inc");
require("guiconfig.inc");

// Dummy standard message gettext calls for xgettext retrieval!!!
$dummy = gettext("The changes have been applied successfully.");
$dummy = gettext("The configuration has been changed.<br />You must apply the changes in order for them to take effect.");
$dummy = gettext("The following input errors were detected");

bindtextdomain("nas4free", "/usr/local/share/locale-bts");
$pgtitle = array(gettext("Extensions"), $config['btsync']['appname']." ".$config['btsync']['version']);

if ( !isset( $config['btsync']['rootfolder']) && !is_dir( $config['btsync']['rootfolder'] )) {
	$input_errors[] = gettext("Extension installed with fault!");
} 
if (!isset($config['btsync']) || !is_array($config['btsync'])) $config['btsync'] = array();

/** 
 * Clean comments of json content and decode it with json_decode(). 
 * Work like the original php json_decode() function with the same params 
 * 
 * @param   string  $json    The json string being decoded 
 * @param   bool    $assoc   When TRUE, returned objects will be converted into associative arrays. 
 * @param   integer $depth   User specified recursion depth. (>=5.3) 
 * @param   integer $options Bitmask of JSON decode options. (>=5.4) 
 * @return  string 
 */ 
function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0) {
    // search and remove comments like /* */ and //
    $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
    
    if(version_compare(phpversion(), '5.4.0', '>=')) {
        $json = json_decode($json, $assoc, $depth, $options);
    }
    elseif(version_compare(phpversion(), '5.3.0', '>=')) {
        $json = json_decode($json, $assoc, $depth);
    }
    else {
        $json = json_decode($json, $assoc);
    }

    return $json;
}

/* Check if the directory exists, the mountpoint has at least o=rx permissions and
 * set the permission to 775 for the last directory in the path
 */
function change_perms($dir) {
    global $input_errors;

    $path = rtrim($dir,'/');                                            // remove trailing slash
    if (strlen($path) > 1) {
        if (!is_dir($path)) {                                           // check if directory exists
            $input_errors[] = sprintf(gettext("Directory %s doesn't exist!"), $path);
        }
        else {
            $path_check = explode("/", $path);                          // split path to get directory names
            $path_elements = count($path_check);                        // get path depth
            $fp = substr(sprintf('%o', fileperms("/$path_check[1]/$path_check[2]")), -1);   // get mountpoint permissions for others
            if ($fp >= 5) {                                             // transmission needs at least read & search permission at the mountpoint
                $directory = "/$path_check[1]/$path_check[2]";          // set to the mountpoint
                for ($i = 3; $i < $path_elements - 1; $i++) {           // traverse the path and set permissions to rx
                    $directory = $directory."/$path_check[$i]";         // add next level
                    exec("chmod o=+r+x \"$directory\"");                // set permissions to o=+r+x
                }
                $path_elements = $path_elements - 1;
                $directory = $directory."/$path_check[$path_elements]"; // add last level
                exec("chmod 775 \"$directory\"");                     // set permissions to 775
                exec("chown {$_POST['who']} {$directory}*");
            }
            else
            {
                $input_errors[] = sprintf(gettext("BitTorrent Sync needs at least read & execute permissions at the mount point for directory %s! Set the Read and Execute bits for Others (Access Restrictions | Mode) for the mount point %s (in <a href='disks_mount.php'>Disks | Mount Point | Management</a> or <a href='disks_zfs_dataset.php'>Disks | ZFS | Datasets</a>) and hit Save in order to take them effect."), $path, "/{$path_check[1]}/{$path_check[2]}");
            }
        }
    }
}

if (isset($_POST['save']) && $_POST['save']) {
    unset($input_errors);
    $pconfig = $_POST;
    if (!empty($_POST['storage_path'])) { change_perms($_POST['storage_path']); }
	if (empty($input_errors)) {
		if (isset($_POST['enable'])) {
            $config['btsync']['enable'] = isset($_POST['enable']) ? true : false;
            if ($_POST['who'] != $config['btsync']['who']) { exec("chown {$_POST['who']} {$config['btsync']['rootfolder']}*"); } // btsync & sync.conf
            $config['btsync']['who'] = $_POST['who'];
            $config['btsync']['listen_to_all'] = isset($_POST['listen_to_all']) ? true : false;
            $config['btsync']['if'] = $_POST['if'];
            $config['btsync']['ipaddr'] = get_ipaddr($_POST['if']);
            $config['btsync']['port'] = $_POST['port'];
            $config['btsync']['storage_path'] = !empty($_POST['storage_path']) ? $_POST['storage_path'] : $config['btsync']['rootfolder'].".sync/";
            $config['btsync']['storage_path'] = rtrim($config['btsync']['storage_path'],'/')."/";                 // ensure to have a trailing slash
    		$savemsg = get_std_save_message(write_config());
    
            if (is_file($config['btsync']['rootfolder']."sync.conf")) {
                $sync_conf = file_get_contents($config['btsync']['rootfolder']."sync.conf");
                $sync_conf = utf8_encode($sync_conf);
                $sync_conf = json_clean_decode($sync_conf,true);
            }
    
            if (isset($_POST['resetuser'])) {
                $sync_conf['webui']['login'] = "admin";
                $sync_conf['webui']['password'] = "password"; 
            }
            else {
                if ($sync_conf['webui']['login'] == "admin") unset($sync_conf['webui']['login']);
                if ($sync_conf['webui']['password'] == "password") unset($sync_conf['webui']['password']);
            }
            $sync_conf['device_name'] = !empty($_POST['device_name']) ? $_POST['device_name'] : "";
            $sync_conf['storage_path'] = !empty($_POST['storage_path']) ? $_POST['storage_path'] : $config['btsync']['rootfolder'].".sync/";
            $sync_conf['pid_file'] = !empty($_POST['pid_file']) ? $_POST['pid_file'] : $sync_conf['storage_path']."sync.pid";
            $sync_conf['webui']['listen'] = isset($_POST['listen_to_all']) ? '0.0.0.0:'.$config['btsync']['port'] : $config['btsync']['ipaddr'].':'.$config['btsync']['port'];
            $sync_conf['webui']['force_https'] = isset($_POST['force_https']) ? true : false;
            $sync_conf['webui']['ssl_certificate'] = !empty($_POST['ssl_certificate']) ? $_POST['ssl_certificate'] : "";
            $sync_conf['webui']['ssl_private_key'] = !empty($_POST['ssl_private_key']) ? $_POST['ssl_private_key'] : "";
            $sync_conf['webui']['directory_root'] = !empty($_POST['directory_root']) ? $_POST['directory_root'] : "";
            if (!empty($_POST['dir_whitelist'])) { $sync_conf['webui']['dir_whitelist'] = explode(",", str_replace(" ", "", rtrim($_POST['dir_whitelist'],','))); } 
            else { unset($sync_conf['webui']['dir_whitelist']); } 
            $sync_conf['config_refresh_interval'] = (is_numeric($_POST['config_refresh_interval']) ? (int)$_POST['config_refresh_interval'] : 3600);
            $sync_conf['disk_low_priority'] = isset($_POST['disk_low_priority']) ? true : false;
            $sync_conf['listening_port'] = (is_numeric($_POST['listening_port']) ? (int)$_POST['listening_port'] : 0);
            $sync_conf['external_port'] = (is_numeric($_POST['external_port']) ? (int)$_POST['external_port'] : 0);
            $sync_conf['folder_defaults.delete_to_trash'] = isset($_POST['folder_defaults_delete_to_trash']) ? true : false;
            $sync_conf['folder_defaults.known_hosts'] = !empty($_POST['folder_defaults_known_hosts']) ? str_replace(" ", "", rtrim($_POST['folder_defaults_known_hosts'],',')) : "";
            $sync_conf['folder_defaults.use_dht'] = isset($_POST['folder_defaults_use_dht']) ? true : false;
            $sync_conf['folder_defaults.use_lan_broadcast'] = isset($_POST['folder_defaults_use_lan_broadcast']) ? true : false;
            $sync_conf['folder_defaults.use_relay'] = isset($_POST['folder_defaults_use_relay']) ? true : false;
            $sync_conf['folder_defaults.use_tracker'] = isset($_POST['folder_defaults_use_tracker']) ? true : false;
            $sync_conf['folder_rescan_interval'] = (is_numeric($_POST['folder_rescan_interval']) ? (int)$_POST['folder_rescan_interval'] : 600);
            $sync_conf['lan_encrypt_data'] = isset($_POST['lan_encrypt_data']) ? true : false;
            $sync_conf['lan_use_tcp'] = isset($_POST['lan_use_tcp']) ? true : false;
            $sync_conf['log_size'] = (is_numeric($_POST['log_size']) ? (int)$_POST['log_size'] : 10);
            $sync_conf['max_file_size_diff_for_patching'] = (is_numeric($_POST['max_file_size_diff_for_patching']) ? (int)$_POST['max_file_size_diff_for_patching'] : 1000);
            $sync_conf['max_file_size_for_versioning'] = (is_numeric($_POST['max_file_size_for_versioning']) ? (int)$_POST['max_file_size_for_versioning'] : 1000);
            $sync_conf['peer_expiration_days'] = (is_numeric($_POST['peer_expiration_days']) ? (int)$_POST['peer_expiration_days'] : 7);
            $sync_conf['profiler_enabled'] = isset($_POST['profiler_enabled']) ? true : false;
            $sync_conf['rate_limit_local_peers'] = isset($_POST['rate_limit_local_peers']) ? true : false;
            $sync_conf['recv_buf_size'] = (is_numeric($_POST['recv_buf_size']) ? (int)$_POST['recv_buf_size'] : 5);
            $sync_conf['send_buf_size'] = (is_numeric($_POST['send_buf_size']) ? (int)$_POST['send_buf_size'] : 5);
            $sync_conf['sync_max_time_diff'] = (is_numeric($_POST['sync_max_time_diff']) ? (int)$_POST['sync_max_time_diff'] : 600);
            $sync_conf['sync_trash_ttl'] = (is_numeric($_POST['sync_trash_ttl']) ? (int)$_POST['sync_trash_ttl'] : 30);
    
    		$config['btsync']['command'] = "su {$config['btsync']['who']} -c '{$config['btsync']['rootfolder']}btsync --config {$config['btsync']['rootfolder']}sync.conf'";
    		$savemsg = get_std_save_message(write_config());
    
            file_put_contents($config['btsync']['rootfolder']."sync.conf", json_encode($sync_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
            if (json_last_error() > 0) { $input_errors[] = gettext('Error during encoding/writing sync.conf file with error number: ').json_last_error(); };
    
            exec("killall btsync");
            $return_val = 0;
            while( $return_val == 0 ) { sleep(1); exec('ps acx | grep btsync', $output, $return_val); }
            unset ($output);
            exec($config['btsync']['command'], $output, $return_val);
            if ($return_val != 0) { $input_errors = $output; }
            if (isset($config['btsync']['enable_schedule'])) {  // if cronjobs exists -> activate
                $cronjob = array();
                $a_cronjob = &$config['cron']['job'];
                $uuid = isset($config['btsync']['schedule_uuid_startup']) ? $config['btsync']['schedule_uuid_startup'] : false;
                if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
                	$cronjob['enable'] = true;
                	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
                	$cronjob['desc'] = $a_cronjob[$cnid]['desc'];
                	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
                	$cronjob['hour'] = $config['btsync']['schedule_startup'];
                	$cronjob['day'] = $a_cronjob[$cnid]['day'];
                	$cronjob['month'] = $a_cronjob[$cnid]['month'];
                	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
                	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
                	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
                	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
                	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
                	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
                	$cronjob['who'] = $a_cronjob[$cnid]['who'];
                	$cronjob['command'] = $a_cronjob[$cnid]['command'];
                }
                if (isset($uuid) && (FALSE !== $cnid)) {
                		$a_cronjob[$cnid] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_MODIFIED;
                	} else {
                		$a_cronjob[] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_NEW;
                	}
                updatenotify_set("cronjob", $mode, $cronjob['uuid']);
                write_config();

                unset ($cronjob);
                $cronjob = array();
                $a_cronjob = &$config['cron']['job'];
                $uuid = isset($config['btsync']['schedule_uuid_closedown']) ? $config['btsync']['schedule_uuid_closedown'] : false;
                if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
                	$cronjob['enable'] = true;
                	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
                	$cronjob['desc'] = $a_cronjob[$cnid]['desc'];
                	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
                	$cronjob['hour'] = $config['btsync']['schedule_closedown'];
                	$cronjob['day'] = $a_cronjob[$cnid]['day'];
                	$cronjob['month'] = $a_cronjob[$cnid]['month'];
                	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
                	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
                	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
                	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
                	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
                	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
                	$cronjob['who'] = $a_cronjob[$cnid]['who'];
                	$cronjob['command'] = $a_cronjob[$cnid]['command'];
                }
                if (isset($uuid) && (FALSE !== $cnid)) {
                		$a_cronjob[$cnid] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_MODIFIED;
                	} else {
                		$a_cronjob[] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_NEW;
                	}
                updatenotify_set("cronjob", $mode, $cronjob['uuid']);
                write_config();
                header("Location: btsync.php");

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
            }   // end of activate cronjobs
        }   // end of enable extension
		else { 
            exec("killall btsync"); $savemsg = $savemsg." ".$config['btsync']['appname'].gettext(" is now disabled!"); 
            $config['btsync']['enable'] = isset($_POST['enable']) ? true : false;
            write_config();
            if (isset($config['btsync']['enable_schedule'])) {  // if cronjobs exists -> deactivate
                $cronjob = array();
                $a_cronjob = &$config['cron']['job'];
                $uuid = isset($config['btsync']['schedule_uuid_startup']) ? $config['btsync']['schedule_uuid_startup'] : false;
                if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
                	$cronjob['enable'] = false;
                	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
                	$cronjob['desc'] = $a_cronjob[$cnid]['desc'];
                	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
                	$cronjob['hour'] = $config['btsync']['schedule_startup'];
                	$cronjob['day'] = $a_cronjob[$cnid]['day'];
                	$cronjob['month'] = $a_cronjob[$cnid]['month'];
                	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
                	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
                	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
                	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
                	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
                	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
                	$cronjob['who'] = $a_cronjob[$cnid]['who'];
                	$cronjob['command'] = $a_cronjob[$cnid]['command'];
                } 
                if (isset($uuid) && (FALSE !== $cnid)) {
                		$a_cronjob[$cnid] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_MODIFIED;
                	} else {
                		$a_cronjob[] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_NEW;
                	}
                updatenotify_set("cronjob", $mode, $cronjob['uuid']);
                write_config();
    
                unset ($cronjob);
                $cronjob = array();
                $a_cronjob = &$config['cron']['job'];
                $uuid = isset($config['btsync']['schedule_uuid_closedown']) ? $config['btsync']['schedule_uuid_closedown'] : false;
                if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
                	$cronjob['enable'] = false;
                	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
                	$cronjob['desc'] = $a_cronjob[$cnid]['desc'];
                	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
                	$cronjob['hour'] = $config['btsync']['schedule_closedown'];
                	$cronjob['day'] = $a_cronjob[$cnid]['day'];
                	$cronjob['month'] = $a_cronjob[$cnid]['month'];
                	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
                	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
                	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
                	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
                	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
                	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
                	$cronjob['who'] = $a_cronjob[$cnid]['who'];
                	$cronjob['command'] = $a_cronjob[$cnid]['command'];
                } 
                if (isset($uuid) && (FALSE !== $cnid)) {
                		$a_cronjob[$cnid] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_MODIFIED;
                	} else {
                		$a_cronjob[] = $cronjob;
                		$mode = UPDATENOTIFY_MODE_NEW;
                	}
                updatenotify_set("cronjob", $mode, $cronjob['uuid']);
                write_config();
                header("Location: btsync.php");
    
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
            }   // end of deactivate cronjobs
        }   // end of disable extension
    }   // end of empty input_errors
}

if (is_file($config['btsync']['rootfolder']."sync.conf")) {
    $sync_conf = file_get_contents($config['btsync']['rootfolder']."sync.conf");
    $sync_conf = utf8_encode($sync_conf);
    $sync_conf = json_clean_decode($sync_conf,true);
}

$pconfig['enable'] = isset($config['btsync']['enable']);
$pconfig['who'] = !empty($config['btsync']['who']) ? $config['btsync']['who'] : "";
$pconfig['if'] = !empty($config['btsync']['if']) ? $config['btsync']['if'] : "";
$pconfig['ipaddr'] = !empty($config['btsync']['ipaddr']) ? $config['btsync']['ipaddr'] : "";
$pconfig['port'] = !empty($config['btsync']['port']) ? $config['btsync']['port'] : "8888";
$pconfig['listen_to_all'] = isset($config['btsync']['listen_to_all']) ? true : false;
$pconfig['device_name'] = !empty($sync_conf['device_name']) ? $sync_conf['device_name'] : "";
$pconfig['force_https'] = isset($sync_conf['webui']['force_https']) ? $sync_conf['webui']['force_https'] : true;
$pconfig['ssl_certificate'] = !empty($sync_conf['webui']['ssl_certificate']) ? $sync_conf['webui']['ssl_certificate'] : "";
$pconfig['ssl_private_key'] = !empty($sync_conf['webui']['ssl_private_key']) ? $sync_conf['webui']['ssl_private_key'] : "";
$pconfig['directory_root'] = !empty($sync_conf['webui']['directory_root']) ? $sync_conf['webui']['directory_root'] : "/mnt/";
$pconfig['dir_whitelist'] = !empty($sync_conf['webui']['dir_whitelist']) ? implode(",", $sync_conf['webui']['dir_whitelist']) : "";
$pconfig['storage_path'] = !empty($sync_conf['storage_path']) ? $sync_conf['storage_path'] : $config['btsync']['rootfolder'].".sync/";
$pconfig['pid_file'] = !empty($sync_conf['pid_file']) ? $sync_conf['pid_file'] : $pconfig['storage_path']."sync.pid";
$pconfig['config_refresh_interval'] = !empty($sync_conf['config_refresh_interval']) ? $sync_conf['config_refresh_interval'] : 3600;
$pconfig['disk_low_priority'] = isset($sync_conf['disk_low_priority']) ? $sync_conf['disk_low_priority'] : true;
$pconfig['listening_port'] = !empty($sync_conf['listening_port']) ? $sync_conf['listening_port'] : 0;
$pconfig['external_port'] = !empty($sync_conf['external_port']) ? $sync_conf['external_port'] : 0;
$pconfig['folder_defaults_delete_to_trash'] = isset($sync_conf['folder_defaults.delete_to_trash']) ? $sync_conf['folder_defaults.delete_to_trash'] : true;
$pconfig['folder_defaults_known_hosts'] = !empty($sync_conf['folder_defaults.known_hosts']) ? $sync_conf['folder_defaults.known_hosts'] : "";
$pconfig['folder_defaults_use_dht'] = isset($sync_conf['folder_defaults.use_dht']) ? $sync_conf['folder_defaults.use_dht'] : false;
$pconfig['folder_defaults_use_lan_broadcast'] = isset($sync_conf['folder_defaults.use_lan_broadcast']) ? $sync_conf['folder_defaults.use_lan_broadcast'] : true;
$pconfig['folder_defaults_use_relay'] = isset($sync_conf['folder_defaults.use_relay']) ? $sync_conf['folder_defaults.use_relay'] : true;
$pconfig['folder_defaults_use_tracker'] = isset($sync_conf['folder_defaults.use_tracker']) ? $sync_conf['folder_defaults.use_tracker'] : true;
$pconfig['folder_rescan_interval'] = !empty($sync_conf['folder_rescan_interval']) ? $sync_conf['folder_rescan_interval'] : 600;
$pconfig['lan_encrypt_data'] = isset($sync_conf['lan_encrypt_data']) ? $sync_conf['lan_encrypt_data'] : true;
$pconfig['lan_use_tcp'] = isset($sync_conf['lan_use_tcp']) ? $sync_conf['lan_use_tcp'] : false;
$pconfig['log_size'] = !empty($sync_conf['log_size']) ? $sync_conf['log_size'] : 10;
$pconfig['max_file_size_diff_for_patching'] = !empty($sync_conf['max_file_size_diff_for_patching']) ? $sync_conf['max_file_size_diff_for_patching'] : 1000;
$pconfig['max_file_size_for_versioning'] = !empty($sync_conf['max_file_size_for_versioning']) ? $sync_conf['max_file_size_for_versioning'] : 1000;
$pconfig['peer_expiration_days'] = !empty($sync_conf['peer_expiration_days']) ? $sync_conf['peer_expiration_days'] : 7;
$pconfig['profiler_enabled'] = isset($sync_conf['profiler_enabled']) ? $sync_conf['profiler_enabled'] : false;
$pconfig['rate_limit_local_peers'] = isset($sync_conf['rate_limit_local_peers']) ? $sync_conf['rate_limit_local_peers'] : false;
$pconfig['recv_buf_size'] = !empty($sync_conf['recv_buf_size']) ? $sync_conf['recv_buf_size'] : 5;
$pconfig['send_buf_size'] = !empty($sync_conf['send_buf_size']) ? $sync_conf['send_buf_size'] : 5;
$pconfig['sync_max_time_diff'] = !empty($sync_conf['sync_max_time_diff']) ? $sync_conf['sync_max_time_diff'] : 600;
$pconfig['sync_trash_ttl'] = !empty($sync_conf['sync_trash_ttl']) ? $sync_conf['sync_trash_ttl'] : 30;

$a_interface = get_interface_list();
// Add VLAN interfaces (from user Vasily1)
if (isset($config['vinterfaces']['vlan']) && is_array($config['vinterfaces']['vlan']) && count($config['vinterfaces']['vlan'])) {
   foreach ($config['vinterfaces']['vlan'] as $vlanv) {
      $a_interface[$vlanv['if']] = $vlanv;
      $a_interface[$vlanv['if']]['isvirtual'] = true;
   }
}
// Add LAGG interfaces (from user Vasily1)
if (isset($config['vinterfaces']['lagg']) && is_array($config['vinterfaces']['lagg']) && count($config['vinterfaces']['lagg'])) {
   foreach ($config['vinterfaces']['lagg'] as $laggv) {
      $a_interface[$laggv['if']] = $laggv;
      $a_interface[$laggv['if']]['isvirtual'] = true;
   }
}

// Use first interface as default if it is not set.
if (empty($pconfig['if']) && is_array($a_interface)) $pconfig['if'] = key($a_interface);

function get_process_info() {
    if (exec('ps acx | grep btsync')) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gettext("running").'</b>&nbsp;&nbsp;</a>'; }
    else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gettext("stopped").'</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

if (is_ajax()) {
	$procinfo = get_process_info();
	render_ajax($procinfo);
}

bindtextdomain("nas4free", "/usr/local/share/locale");
include("fbegin.inc");?>  
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'btsync.php', null, function(data) {
		$('#procinfo').html(data.data);
	});
});
//]]>
</script>
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis = !(document.iform.enable.checked || enable_change);
	document.iform.resetuser.disabled = endis;
	document.iform.listen_to_all.disabled = endis;
	document.iform.device_name.disabled = endis;
	document.iform.force_https.disabled = endis;
	document.iform.ssl_certificate.disabled = endis;
	document.iform.ssl_certificatebrowsebtn.disabled = endis;
	document.iform.ssl_private_key.disabled = endis;
	document.iform.ssl_private_keybrowsebtn.disabled = endis;
	document.iform.directory_root.disabled = endis;
	document.iform.directory_rootbrowsebtn.disabled = endis;
	document.iform.dir_whitelist.disabled = endis;
	document.iform.who.disabled = endis;
	document.iform.port.disabled = endis;
    document.iform.pid_file.disabled = endis;
	document.iform.pid_filebrowsebtn.disabled = endis;
	document.iform.storage_path.disabled = endis;
	document.iform.storage_pathbrowsebtn.disabled = endis;
    document.iform.config_refresh_interval.disabled = endis;
	document.iform.disk_low_priority.disabled = endis;
    document.iform.listening_port.disabled = endis;
    document.iform.external_port.disabled = endis;
    document.iform.folder_defaults_delete_to_trash.disabled = endis;
    document.iform.folder_defaults_known_hosts.disabled = endis;
    document.iform.folder_defaults_use_dht.disabled = endis;
    document.iform.folder_defaults_use_lan_broadcast.disabled = endis;
    document.iform.folder_defaults_use_relay.disabled = endis;
    document.iform.folder_defaults_use_tracker.disabled = endis;
	document.iform.folder_rescan_interval.disabled = endis;
	document.iform.xif.disabled = endis;
	document.iform.lan_encrypt_data.disabled = endis;
	document.iform.lan_use_tcp.disabled = endis;
    document.iform.log_size.disabled = endis;
	document.iform.max_file_size_diff_for_patching.disabled = endis;
	document.iform.max_file_size_for_versioning.disabled = endis;
    document.iform.peer_expiration_days.disabled = endis;
    document.iform.profiler_enabled.disabled = endis;
	document.iform.rate_limit_local_peers.disabled = endis;
	document.iform.recv_buf_size.disabled = endis;
	document.iform.send_buf_size.disabled = endis;
	document.iform.sync_max_time_diff.disabled = endis;
	document.iform.sync_trash_ttl.disabled = endis;
}

function as_change() {
	switch(document.iform.as_enable.checked) {
		case false:
			showElementById('who_tr','hide');
			showElementById('xif_tr','hide');
			showElementById('storage_path_tr','hide');
			showElementById('pid_file_tr','hide');
			showElementById('ssl_certificate_tr','hide');
			showElementById('ssl_private_key_tr','hide');
			showElementById('directory_root_tr','hide');
			showElementById('dir_whitelist_tr','hide');
			showElementById('config_refresh_interval_tr','hide');
    		showElementById('disk_low_priority_tr','hide');
    		showElementById('listening_port_tr','hide');
    		showElementById('external_port_tr','hide');
    		showElementById('folder_defaults_delete_to_trash_tr','hide');
    		showElementById('folder_defaults_known_hosts_tr','hide');
    		showElementById('folder_defaults_use_dht_tr','hide');
    		showElementById('folder_defaults_use_lan_broadcast_tr','hide');
    		showElementById('folder_defaults_use_relay_tr','hide');
    		showElementById('folder_defaults_use_tracker_tr','hide');
    		showElementById('folder_rescan_interval_tr','hide');
    		showElementById('lan_encrypt_data_tr','hide');
    		showElementById('lan_use_tcp_tr','hide');
    		showElementById('log_size_tr','hide');
    		showElementById('max_file_size_diff_for_patching_tr','hide');
    		showElementById('max_file_size_for_versioning_tr','hide');
    		showElementById('peer_expiration_days_tr','hide');
    		showElementById('profiler_enabled_tr','hide');
    		showElementById('rate_limit_local_peers_tr','hide');
    		showElementById('recv_buf_size_tr','hide');
    		showElementById('send_buf_size_tr','hide');
    		showElementById('sync_max_time_diff_tr','hide');
    		showElementById('sync_trash_ttl_tr','hide');
			break;

		case true:
			showElementById('who_tr','show');
			showElementById('xif_tr','show');
			showElementById('storage_path_tr','show');
			showElementById('pid_file_tr','show');
			showElementById('ssl_certificate_tr','show');
			showElementById('ssl_private_key_tr','show');
			showElementById('directory_root_tr','show');
			showElementById('dir_whitelist_tr','show');
			showElementById('config_refresh_interval_tr','show');
    		showElementById('disk_low_priority_tr','show');
    		showElementById('listening_port_tr','show');
    		showElementById('external_port_tr','show');
    		showElementById('folder_defaults_delete_to_trash_tr','show');
    		showElementById('folder_defaults_known_hosts_tr','show');
    		showElementById('folder_defaults_use_dht_tr','show');
    		showElementById('folder_defaults_use_lan_broadcast_tr','show');
    		showElementById('folder_defaults_use_relay_tr','show');
    		showElementById('folder_defaults_use_tracker_tr','show');
    		showElementById('folder_rescan_interval_tr','show');
    		showElementById('lan_encrypt_data_tr','show');
    		showElementById('lan_use_tcp_tr','show');
    		showElementById('log_size_tr','show');
    		showElementById('max_file_size_diff_for_patching_tr','show');
    		showElementById('max_file_size_for_versioning_tr','show');
    		showElementById('peer_expiration_days_tr','show');
    		showElementById('profiler_enabled_tr','show');
    		showElementById('rate_limit_local_peers_tr','show');
    		showElementById('recv_buf_size_tr','show');
    		showElementById('send_buf_size_tr','show');
    		showElementById('sync_max_time_diff_tr','show');
    		showElementById('sync_trash_ttl_tr','show');
			break;
	}
}
//-->
</script>
<form action="btsync.php" method="post" name="iform" id="iform">
<?php bindtextdomain("nas4free", "/usr/local/share/locale-bts"); ?>
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabact"><a href="btsync.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabinact"><a href="btsync_update.php"><span><?=gettext("Maintenance");?></span></a></li>
			<li class="tabinact"><a href="btsync_update_extension.php"><span><?=gettext("Extension Maintenance");?></span></a></li>
			<li class="tabinact"><a href="btsync_log.php"><span><?=gettext("Log");?></span></a></li>
		</ul>
	</td></tr>
    <tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<?php html_titleline($config['btsync']['appname']." ".gettext("Information"));?>
			<?php html_text("version", gettext("Version"), $config['btsync']['product_version']);?>
			<?php html_text("architecture", gettext("Architecture"), $config['btsync']['architecture']);?>		
            <tr>
                <td class="vncell"><?=gettext("Status");?></td>
                <td class="vtable"><span name="procinfo" id="procinfo"></span></td>
            </tr>
            <?php
                $if = get_ifname($pconfig['if']);
                $ipaddr = get_ipaddr($if);
                $url = htmlspecialchars("http://{$ipaddr}:{$pconfig['port']}");
                $text = "<a href='{$url}' target='_blank'>{$url}</a>";
                html_text("url", gettext("WebGUI")." ".gettext("URL"), $text);
            ?>
			<?php html_separator();?>
        	<?php html_titleline_checkbox("enable", $config['btsync']['appname'], !empty($pconfig['enable']) ? true : false, gettext("Enable"), "enable_change(false)");?>
            <?php html_inputbox("device_name", gettext("Device name"), $pconfig['device_name'], gettext("Name of this device. Default is the hostname."), true, 15);?>
			<tr>
				<td valign="top" class="vncellreq"><?=gettext("Interface selection");?></td>
				<td class="vtable">
				<select name="if" class="formfld" id="xif">
					<?php foreach($a_interface as $if => $ifinfo):?>
						<?php $ifinfo = get_interface_info($if); if (("up" == $ifinfo['status']) || ("associated" == $ifinfo['status'])):?>
						<option value="<?=$if;?>"<?php if ($if == $pconfig['if']) echo "selected=\"selected\"";?>><?=$if?></option>
						<?php endif;?>
					<?php endforeach;?>
				</select>
				<br /><?=gettext("Select which interface to use (only selectable if your server has more than one).");?>
				</td>
			</tr>
			<?php html_inputbox("port", gettext("WebUI")." ".gettext("Port"), $pconfig['port'], sprintf(gettext("Port to listen on. Only dynamic or private ports can be used (from %d through %d). Default port is %d."), 1025, 65535, 8888), true, 5);?>
            <?php html_checkbox("listen_to_all", gettext("External access"), $pconfig['listen_to_all'], gettext("Enable / disable external (Internet) access. If enabled the WebUI listens to all IP addresses (0.0.0.0) instead of the chosen interface IP address."), gettext("Default is disabled."), true);?>
            <?php html_checkbox("force_https", gettext("Secure connection"), $pconfig['force_https'], gettext("If enabled, Hypertext Transfer Protocol Secure (HTTPS) will be used for the BitTorrent Sync WebUI."), gettext("Default is enabled."), true);?>
            <?php html_checkbox("resetuser", gettext("Reset WebUI user"), false, "<b><font color='#FF0000'>".gettext("Set username to 'admin' and password to 'password'. Use the BitTorrent Sync WebUI to define a new username and password, after that save and restart again with unselected checkbox!")."</font></b>", "", false);?>
			<?php html_separator();?>
        	<?php html_titleline_checkbox("as_enable", gettext("Advanced settings"), isset($_POST['as_enable']) ? true : false, gettext("Show"), "as_change()");?>
    		<?php $a_user = array(); foreach (system_get_user_list() as $userk => $userv) { $a_user[$userk] = htmlspecialchars($userk); }?>
            <?php html_combobox("who", gettext("Username"), $pconfig['who'], $a_user, gettext("Specifies the username which the service will run as."), false);?>
			<?php html_filechooser("storage_path", gettext("Storage path"), $pconfig['storage_path'], gettext("Where to save auxilliary app files."), $g['media_path'], false, 60);?>
 			<?php html_filechooser("pid_file", gettext("PID file"), $pconfig['pid_file'], gettext("Where to save the pid file."), $g['media_path'], false, 60);?>
            <?php html_filechooser("ssl_certificate", gettext("Certificate"), $pconfig['ssl_certificate'], gettext("Path to certificate file (in X.509 PEM format)."), $g['media_path'], false, 60);?>
            <?php html_filechooser("ssl_private_key", gettext("Private key"), $pconfig['ssl_private_key'], gettext("Path to private key file (in PEM format)."), $g['media_path'], false, 60);?>
            <?php html_filechooser("directory_root", gettext("Directory root"), $pconfig['directory_root'], gettext("Defines where the WebUI Folder browser starts. Default is /mnt."), $g['media_path'], false, 60);?>
            <?php html_inputbox("dir_whitelist", gettext("Directory whitelist"), $pconfig['dir_whitelist'], gettext("Defines which directories (comma-separated - no other delimiters allowed) can be shown to user or have folders added, relative paths are relative to 'Directory root' setting."), false, 60);?>
            <?php html_inputbox("config_refresh_interval", "config_refresh_interval", $pconfig['config_refresh_interval'], sprintf(gettext("Controls how often settings are saved to storage. Can be adjusted to prevent HDD from low-power mode. Default is %d seconds."), 3600), false, 5);?>
            <?php html_checkbox("disk_low_priority", "disk_low_priority", $pconfig['disk_low_priority'], gettext("Sets priority for the file operations on disc."), gettext("Default is enabled."), false);?>
            <?php html_inputbox("listening_port", "listening_port", $pconfig['listening_port'], sprintf(gettext("Allows you to configure the port BitTorrent Sync will be using for incoming and outgoing UDP packets and incoming TCP connections. Default is %d (random value)."), 0), false, 5);?>
            <?php html_inputbox("external_port", "external_port", $pconfig['external_port'], sprintf(gettext("External (i.e. relative to NAT) port value. Default is %d (not set)."), 0), false, 5);?>
            <?php html_checkbox("folder_defaults_delete_to_trash", "folder_defaults.delete_to_trash", $pconfig['folder_defaults_delete_to_trash'], gettext("Default setting for folder preference 'Store deleted files in folder archive'."), gettext("Default is enabled."), false);?>
            <?php html_inputbox("folder_defaults_known_hosts", "folder_defaults.known_hosts", $pconfig['folder_defaults_known_hosts'], sprintf(gettext("Default setting for folder preference 'Use predefined hosts'. Hosts should be entered as single line of IP:port pairs (or DNSname:port pairs) comma-separated (no other delimiters allowed)."), ""), false, 80);?>
            <?php html_checkbox("folder_defaults_use_dht", "folder_defaults.use_dht", $pconfig['folder_defaults_use_dht'], gettext("Default setting for folder preference 'Search DHT network'."), gettext("Default is disabled."), false);?>
            <?php html_checkbox("folder_defaults_use_lan_broadcast", "folder_defaults.use_lan_broadcast", $pconfig['folder_defaults_use_lan_broadcast'], gettext("Default setting for folder preference 'Search LAN'."), gettext("Default is enabled."), false);?>
            <?php html_checkbox("folder_defaults_use_relay", "folder_defaults.use_relay", $pconfig['folder_defaults_use_relay'], gettext("Default setting for folder preference 'Use relay server when required'."), gettext("Default is enabled."), false);?>
            <?php html_checkbox("folder_defaults_use_tracker", "folder_defaults.use_tracker", $pconfig['folder_defaults_use_tracker'], gettext("Default setting for folder preference 'Use tracker server'."), gettext("Default is enabled."), false);?>
            <?php html_inputbox("folder_rescan_interval", "folder_rescan_interval", $pconfig['folder_rescan_interval'], sprintf(gettext("Sets a time interval for rescanning sync. Default is %d seconds."), 600), false, 5);?>
            <?php html_checkbox("lan_encrypt_data", "lan_encrypt_data", $pconfig['lan_encrypt_data'], gettext("If enabled, will use encryption in the local network."), gettext("Default is enabled."), false);?>
            <?php html_checkbox("lan_use_tcp", "lan_use_tcp", $pconfig['lan_use_tcp'], gettext("If enabled, Sync will use TCP instead of UDP in local network."), gettext("Default is disabled."), false);?>
            <?php html_inputbox("log_size", "log_size", $pconfig['log_size'], sprintf(gettext("Amount of file size allocated for sync.log and debug log. After reaching selected amount, sync.log renamed to sync.log.old (overwriting old instance), and empty sync.log created. Default is %d MB."), 10), false, 5);?>
            <?php html_inputbox("max_file_size_diff_for_patching", "max_file_size_diff_for_patching", $pconfig['max_file_size_diff_for_patching'], sprintf(gettext("Determines a size difference between versions of one file for patching. Default is %d MB."), 1000), false, 5);?>
            <?php html_inputbox("max_file_size_for_versioning", "max_file_size_for_versioning", $pconfig['max_file_size_for_versioning'], sprintf(gettext("Determines maximum file size for creating file versions. Default is %d MB."), 1000), false, 5);?>
            <?php html_inputbox("peer_expiration_days", "peer_expiration_days", $pconfig['peer_expiration_days'], sprintf(gettext("Amount of days to pass before peer is removed from peer list. Default is %d days."), 7), false, 5);?>
            <?php html_checkbox("profiler_enabled", "profiler_enabled", $pconfig['profiler_enabled'], gettext("Requires client restart to activate. Starts recording data for speed issue analysis. Data is stored in proffer.dat in storage folder, rotated every 10 minutes."), gettext("Default is disabled."), false);?>
            <?php html_checkbox("rate_limit_local_peers", "rate_limit_local_peers", $pconfig['rate_limit_local_peers'], gettext("Applies speed limits to the peers in local network."), gettext("Default is disabled."), false);?>
            <?php html_inputbox("recv_buf_size", "recv_buf_size", $pconfig['recv_buf_size'], sprintf(gettext("The amount of real memory that will be used for cached receive operations, can be set in the range from %d to %d MB. Default is %d MB."), 1, 100, 5), false, 5);?>
            <?php html_inputbox("send_buf_size", "send_buf_size", $pconfig['send_buf_size'], sprintf(gettext("The amount of real memory that will be used for cached send operations, can be set in the range from %d to %d MB. Default is %d MB."), 1, 100, 5), false, 5);?>
            <?php html_inputbox("sync_max_time_diff", "sync_max_time_diff", $pconfig['sync_max_time_diff'], sprintf(gettext("Maximum allowed time difference between devices. Default is %d seconds."), 600), false, 5);?>
            <?php html_inputbox("sync_trash_ttl", "sync_trash_ttl", $pconfig['sync_trash_ttl'], sprintf(gettext("Sets the number of days after reaching which files will be automatically deleted from the .SyncArchive folder. Default is %d days."), 30), false, 5);?>
        </table>
        <div id="remarks">
            <?php html_remark("note", gettext("Note"), sprintf(gettext("These parameters will be added to %s."), "{$config['btsync']['rootfolder']}sync.conf")." ".sprintf(gettext("Please check the <a href='%s' target='_blank'>documentation</a>."), "http://sync-help.bittorrent.com/"));?>
        </div>
        <div id="submit">
			<input id="save" name="save" type="submit" class="formbtn" value="<?=gettext("Save and Restart");?>"/>
        </div>
	</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
as_change();
//-->
</script>
<?php include("fend.inc");?>
