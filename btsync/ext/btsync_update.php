<?php
/*
    btsync_update.php
    
    Copyright (c) 2013 - 2017 Andreas Schmidhuber <info@a3s.at>
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

bindtextdomain("nas4free", "/usr/local/share/locale-bts");

$config_file = "ext/btsync/btsync.conf";
require_once("ext/btsync/extension-lib.inc");
if (($configuration = ext_load_config($config_file)) === false) $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "btsync.conf");
if (!isset($configuration['rootfolder']) && !is_dir($configuration['rootfolder'] )) $input_errors[] = gettext("Extension installed with fault");

$pgtitle = array(gettext("Extensions"), $configuration['appname']." ".$configuration['version'], gettext("Maintenance"));
 
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $size_server = exec("fetch -s {$configuration['download_url']}", $size_server, $return_val);
    if ( $return_val == 0) {
        if ($size_server != $configuration['size']) {
        $savemsg = sprintf(gettext("New %s version available - push '%s' button to download the new version!"), "Resilio Sync", gettext("Fetch"))."<br />"; }
    }
}

$pconfig['product_version_new'] = !empty($configuration['product_version_new']) ? $configuration['product_version_new'] : "n/a";

function fetch_file($file_url) {
    global $config_file;
    global $configuration;
    global $input_errors;
    global $savemsg;
    global $pconfig;
        
    unset($input_errors);
    $configuration['size_new'] = exec ("fetch -s {$file_url}");
	mwexec ("fetch -o {$configuration['updatefolder']}stable {$file_url}", false);
    exec ("cd {$configuration['updatefolder']} && tar -xf stable");
    if ( !is_file ("{$configuration['updatefolder']}{$configuration['product_executable']}") ) { $input_errors[] = gettext("Could not fetch file!"); }
    else {
    $pconfig['product_version_new'] = exec ("{$configuration['updatefolder']}{$configuration['product_executable']} --help | awk '/Sync/ {print $3}'");
        if ($pconfig['product_version_new'] == '') { $pconfig['product_version_new'] = 'n/a'; $input_errors[] = gettext("Could not retrieve new version!"); }
        else {
            if ("{$pconfig['product_version_new']}" == "{$configuration['product_version']}") { 
				$savemsg = gettext("No new version available!"); 
				$configuration['size'] = $configuration['size_new'];
			}
            else {
                $savemsg = sprintf(gettext("New version %s available, push '"), $pconfig['product_version_new']).gettext('Install').gettext("' button to install the new version!");
            }
            $configuration['product_version_new'] = !empty($pconfig['product_version_new']) ? $pconfig['product_version_new'] : "n/a";
			ext_save_config($config_file, $configuration);
        }
    }
} 

if (isset($_POST['save_url']) && $_POST['save_url']) {
    if (!empty($_POST['download_url'])) {
        $configuration['previous_url'] = $configuration['download_url'];
        $configuration['download_url'] = $_POST['download_url'];
		ext_save_config($config_file, $configuration);
        $savemsg = gettext("New download URL saved!");
    }
}

if (isset($_POST['revert_url']) && $_POST['revert_url']) {
    $configuration['download_url'] = $configuration['previous_url'];
	ext_save_config($config_file, $configuration);
    $savemsg = gettext("Previous download URL activated!");
}

if (isset($_POST['get_file']) && $_POST['get_file']) {
    if (!empty($_POST['download_url'])) { fetch_file($_POST['download_url']); }
}

if (isset($_POST['install_new']) && $_POST['install_new']) {
    if (isset($configuration['enable'])) { 
		killbyname($configuration['product_executable']);
        $return_val = 0;
        while( $return_val == 0 ) { sleep(1); exec("ps acx | grep {$configuration['product_executable']}", $output, $return_val); }
    }
    if (!copy($configuration['updatefolder']."{$configuration['product_executable']}", $configuration['rootfolder']."{$configuration['product_executable']}")) { $input_errors[] = gettext("Could not install new version!"); }
    else {
        exec("chmod +x {$configuration['rootfolder']}{$configuration['product_executable']}");
        if (isset($configuration['enable'])) { exec($configuration['command']); }
        $configuration['product_version'] = $pconfig['product_version_new'];
        $configuration['size'] = $configuration['size_new'];
	   	exec ("cp {$configuration['product_executable']} {$configuration['backupfolder']}{$configuration['product_executable']}-{$configuration['product_version']}");
		ext_save_config($config_file, $configuration);
        $savemsg = gettext("New version installed!");
    }	
}

if (isset($_POST['fetch']) && $_POST['fetch']) { fetch_file($configuration['download_url']); }

if ( isset( $_POST['delete_backup'] ) && $_POST['delete_backup'] ) {
    if ( !isset($_POST['installfile']) ) { $input_errors[] = gettext("No file selected to delete!") ; }
    else {
        if (is_file($_POST['installfile'])) {
            exec("rm {$_POST['installfile']}");
            $savemsg = sprintf(gettext("File version %s deleted!"), $_POST['installfile']);
        }
        else { $input_errors[] = sprintf(gettext("File %s not found!"), $_POST['installfile']); }
    }
}

if ( isset( $_POST['install_backup'] ) && $_POST['install_backup'] ) {
    if ( !isset($_POST['installfile']) ) { $input_errors[] = gettext("No file selected to install!") ; }
    else {
        if (is_file($_POST['installfile'])) {
            if (isset($configuration['enable'])) { 
	            killbyname($configuration['product_executable']);
                $return_val = 0;
                while( $return_val == 0 ) { sleep(1); exec("ps acx | grep {$configuration['product_executable']}", $output, $return_val); }
            }
            if (!copy($_POST['installfile'], $configuration['rootfolder']."{$configuration['product_executable']}")) { $input_errors[] = gettext("Could not install backup version!"); }
            else {
                if (isset($configuration['enable'])) { exec($configuration['command']); }
    			$configuration['product_version'] = exec ("{$configuration['rootfolder']}{$configuration['product_executable']} --help | awk '/Sync/ {print $3}'");
                $pconfig['product_version_new'] = "n/a"; 
                $configuration['product_version_new'] = "n/a";
                $configuration['size'] = "n/a"; 
                $configuration['size_new'] = "n/a";
				exec ("cp {$configuration['product_executable']} {$configuration['backupfolder']}{$configuration['product_executable']}-{$configuration['product_version']}");
				ext_save_config($config_file, $configuration);
                if (isset($configuration['enable'])) { $savemsg = gettext("Backup version installed!"); }
                else { $savemsg = sprintf(gettext("Backup version installed! Go to %s and enable, save & restart to run %s !"), gettext('Configuration'), $configuration['appname']); }
            }
        }
        else { $input_errors[] = sprintf(gettext("File %s not found!"), $_POST['installfile']); }
    }
}

function cronjob_process_updatenotification($mode, $data) {
	global $config;
	$retval = 0;
	switch ($mode) {
		case UPDATENOTIFY_MODE_NEW:
		case UPDATENOTIFY_MODE_MODIFIED:
			break;
		case UPDATENOTIFY_MODE_DIRTY:
        	if (is_array($config['cron']) && is_array($config['cron']['job'])) {
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

if ( isset( $_POST['schedule'] ) && $_POST['schedule'] ) {
    if (isset($_POST['enable_schedule']) && ($_POST['startup'] == $_POST['closedown'])) { $input_errors[] = gettext("Startup and closedown hour must be different!"); }
    else {
        if (isset($_POST['enable_schedule'])) {
            $configuration['enable_schedule'] = isset($_POST['enable_schedule']);
            $configuration['schedule_startup'] = $_POST['startup'];
            $configuration['schedule_closedown'] = $_POST['closedown'];
            $configuration['schedule_prohibit'] = isset($_POST['prohibit']);

            $cronjob = array();
			if (!is_array($config['cron'])) $config['cron'] = [];
            $a_cronjob = &$config['cron']['job'];
            $uuid = isset($configuration['schedule_uuid_startup']) ? $configuration['schedule_uuid_startup'] : false;
            if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
            	$cronjob['enable'] = true;
            	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
            	$cronjob['desc'] = "BitTorrent Sync startup (@ {$configuration['schedule_startup']}:00)";
            	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
            	$cronjob['hour'] = $configuration['schedule_startup'];
            	$cronjob['day'] = $a_cronjob[$cnid]['day'];
            	$cronjob['month'] = $a_cronjob[$cnid]['month'];
            	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
            	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
            	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
            	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
            	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
            	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = $configuration['command']." && logger btsync: scheduled startup";
            } else {
            	$cronjob['enable'] = true;
            	$cronjob['uuid'] = uuid();
            	$cronjob['desc'] = "BitTorrent Sync startup (@ {$configuration['schedule_startup']}:00)";
            	$cronjob['minute'] = 0;
            	$cronjob['hour'] = $configuration['schedule_startup'];
            	$cronjob['day'] = true;
            	$cronjob['month'] = true;
            	$cronjob['weekday'] = true;
            	$cronjob['all_mins'] = 0;
            	$cronjob['all_hours'] = 0;
            	$cronjob['all_days'] = 1;
            	$cronjob['all_months'] = 1;
            	$cronjob['all_weekdays'] = 1;
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = $configuration['command']." && logger btsync: scheduled startup";
                $configuration['schedule_uuid_startup'] = $cronjob['uuid'];
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
			if (!is_array($config['cron'])) $config['cron'] = [];
            $a_cronjob = &$config['cron']['job'];
            $uuid = isset($configuration['schedule_uuid_closedown']) ? $configuration['schedule_uuid_closedown'] : false;
            if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
            	$cronjob['enable'] = true;
            	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
            	$cronjob['desc'] = "BitTorrent Sync closedown (@ {$configuration['schedule_closedown']}:00)";
            	$cronjob['minute'] = $a_cronjob[$cnid]['minute'];
            	$cronjob['hour'] = $configuration['schedule_closedown'];
            	$cronjob['day'] = $a_cronjob[$cnid]['day'];
            	$cronjob['month'] = $a_cronjob[$cnid]['month'];
            	$cronjob['weekday'] = $a_cronjob[$cnid]['weekday'];
            	$cronjob['all_mins'] = $a_cronjob[$cnid]['all_mins'];
            	$cronjob['all_hours'] = $a_cronjob[$cnid]['all_hours'];
            	$cronjob['all_days'] = $a_cronjob[$cnid]['all_days'];
            	$cronjob['all_months'] = $a_cronjob[$cnid]['all_months'];
            	$cronjob['all_weekdays'] = $a_cronjob[$cnid]['all_weekdays'];
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = "killall {$configuration['product_executable']} && logger btsync: scheduled closedown";
            } else {
            	$cronjob['enable'] = true;
            	$cronjob['uuid'] = uuid();
            	$cronjob['desc'] = "BitTorrent Sync closedown (@ {$configuration['schedule_closedown']}:00)";
            	$cronjob['minute'] = 0;
            	$cronjob['hour'] = $configuration['schedule_closedown'];
            	$cronjob['day'] = true;
            	$cronjob['month'] = true;
            	$cronjob['weekday'] = true;
            	$cronjob['all_mins'] = 0;
            	$cronjob['all_hours'] = 0;
            	$cronjob['all_days'] = 1;
            	$cronjob['all_months'] = 1;
            	$cronjob['all_weekdays'] = 1;
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = "killall {$configuration['product_executable']} && logger btsync: scheduled closedown";
                $configuration['schedule_uuid_closedown'] = $cronjob['uuid'];
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
        }   // end of enable_schedule
        else {
            $configuration['enable_schedule'] = isset($_POST['enable_schedule']) ? true : false;
        	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $configuration['schedule_uuid_startup']);
        	if (is_array($config['cron']) && is_array($config['cron']['job'])) {
				$index = array_search_ex($data, $config['cron']['job'], "uuid");
				if (false !== $index) unset($config['cron']['job'][$index]);
			}
        	write_config();
        	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $configuration['schedule_uuid_closedown']);
        	if (is_array($config['cron']) && is_array($config['cron']['job'])) {
				$index = array_search_ex($data, $config['cron']['job'], "uuid");
				if (false !== $index) unset($config['cron']['job'][$index]);
			}
        	write_config();
        }   // end of disable_schedule -> remove cronjobs
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
		$savemsg = get_std_save_message(ext_save_config($config_file, $configuration));
    }   // end of schedule change
}

// Function name: 	filelist
// Inputs: 			file_list			array of filenames with suffix to create list for
//					exclude				Optional array used to remove certain results
// Outputs: 		file_list			html formatted block with a radio next to each file
// Description:		This function creates an html code block with the files listed on the right
//					and radio buttons next to each on the left.
function filelist ($contains , $exclude='') {
    global $configuration;
	// This function creates a list of files that match a certain filename pattern
	$installFiles = "";
	if ( is_dir( $configuration['rootfolder'] )) {
		$raw_list = glob("{$configuration['backupfolder']}{$contains}.{*}", GLOB_BRACE);
		$file_list = array_unique( $raw_list );
		if ( $exclude ) {
			foreach ( $exclude as $search_pattern ) {
				$file_list = preg_grep( "/{$search_pattern}/" , $file_list , PREG_GREP_INVERT );
			}
//		sort ( $file_list , SORT_NUMERIC );
		natsort ( $file_list );
		}
	} // end of verifying rootfolder as valid location
	return $file_list ;
}

// Function name: 	radiolist
// Inputs: 			file_list			array of filenames with suffix to create list for
// Outputs: 		installFiles		html formatted block with a radio next to each file
// Description:		This function creates an html code block with the files listed on the right
//					and radio buttons next to each on the left.
function radiolist ($file_list) {
    global $configuration;
	$installFiles = "";		// Initialize installFiles as an empty string so we can concatenate in the for loop
	if (is_dir($configuration['rootfolder'])) {		// check if the folder is a directory, so it doesn't choke
		foreach ( $file_list as $file) {
			$file = str_replace($configuration['rootfolder'] . "/", "", $file);
			$installFiles .= "<input type=\"radio\" name=\"installfile\" value=\"$file\"> "
			. str_replace($configuration['backupfolder'], "", $file)
			. "<br/>";
			} // end of completed folder, filename, suffix creation
	} // end of verifying rootfolder as valid location
	return $installFiles ;
}

function get_process_info() {
    global $configuration;
    if (exec("ps acx | grep {$configuration['product_executable']}")) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gettext("running").'</b>&nbsp;&nbsp;</a>'; $proc_state = 'running'; }
    else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gettext("stopped").'</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

if (is_ajax()) {
	$procinfo = get_process_info();
	render_ajax($procinfo);
}

if (!is_file("{$configuration['rootfolder']}version_server.txt") || filemtime("{$configuration['rootfolder']}version_server.txt") < time() - 86400) {	// test if file exists or is older than 24 hours
	$return_val = mwexec("fetch -o {$configuration['rootfolder']}version_server.txt https://raw.github.com/crestAT/nas4free-bittorrent-sync/master/btsync/version.txt", false);
	if ($return_val == 0) {
	    $server_version = exec("cat {$configuration['rootfolder']}version_server.txt");
	    if ($server_version != $current_version) $savemsg .= sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"), $server_version, gettext("Extension Maintenance"));
	}
}

bindtextdomain("nas4free", "/usr/local/share/locale");
include("fbegin.inc");?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'btsync_update.php', null, function(data) {
		$('#procinfo').html(data.data);
	});
});
//]]>
</script>
<script type="text/javascript">
<!--
function update_change() {
	// Reload page
	window.document.location.href = 'btsync_update.php?update=' + document.iform.update.value;
}

<!-- This function allows the pages to render the buttons impotent whilst carrying out various functions -->

function fetch_handler() {
	if ( document.iform.beenSubmitted )
		alert('Please wait for the previous operation to complete!');
	else{
		return confirm('The selected operation will be completed. Please do not click any other buttons.');
	}
}

function enable_change(enable_change) {
	var endis = !(document.iform.enable_schedule.checked || enable_change);
	document.iform.startup.disabled = endis;
	document.iform.closedown.disabled = endis;
	document.iform.prohibit.disabled = endis;
}

//-->
</script>
<!-- The Spinner Elements -->
<?php include("ext/btsync/spinner.inc");?>
<script src="ext/btsync/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->

<form action="btsync_update.php" method="post" name="iform" id="iform" onsubmit="spinner()">
<?php bindtextdomain("nas4free", "/usr/local/share/locale-bts"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabinact"><a href="btsync.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabact"><a href="btsync_update.php"><span><?=gettext("Maintenance");?></span></a></li>
			<li class="tabinact"><a href="btsync_update_extension.php"><span><?=gettext("Extension Maintenance");?></span></a></li>
			<li class="tabinact"><a href="btsync_log.php"><span><?=gettext("Log");?></span></a></li>
		</ul>
	</td></tr>
	<tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline($configuration['appname']." ".gettext("Update"));?>
			  <tr>
			    <td class="vncell"><?=gettext("Status");?></td>
                <td class="vtable"><span name="procinfo" id="procinfo"></span></td>
			  </tr>
			<?php html_text("version_current", gettext("Installed version"), $configuration['product_version']);?>
			<tr>
				<td valign="top" class="vncell"><?=gettext("Latest version fetched from BitTorrent server");?>
				</td>
				<td class="vtable"><?=$pconfig['product_version_new'].gettext(" - push 'fetch' button to check for new version");?>
                    <span class="label">&nbsp;&nbsp;&nbsp;</span>
                    <input id="fetch" name="fetch" type="submit" class="formbtn" value="<?=gettext("Fetch");?>" onClick="return fetch_handler();" />
                    <?php if (("{$pconfig['product_version_new']}" != "{$configuration['product_version']}") && ("{$pconfig['product_version_new']}" != "n/a")) { ?> 
                        <input id="install_new" name="install_new" type="submit" class="formbtn" value="<?=gettext("Install");?>" onClick="return fetch_handler();" />
                    <?php } ?>
                    <a href='https://www.getsync.com' target='_blank'>&nbsp;&nbsp;&nbsp;-> Resilio Sync</a>
				</td>
			</tr>
            <?php html_inputbox("download_url", gettext("Download URL"), $configuration['download_url'], sprintf(gettext("Define a new permanent application download URL or an URL for a one-time download of a previous version.<br />Previous download URL was <b>%s</b>"), $configuration['previous_url']), false, 100);?>
        </table>
        <div id="remarks">
            <?php html_remark("note_url", gettext("Note"), sprintf(gettext("Use 'Save URL' to change the download URL permanently, 'Revert URL' to activate a previously saved URL or 'Get File' to download a previous version - for example <b>http://syncapp.bittorrent.com/1.4.83/btsync_freebsd_x64-1.4.83.tar.gz</b> (after the download push 'Install' to install the newly downloaded file).")));?>
        </div>
        <div id="submit">
            <input id="save_url" name="save_url" type="submit" class="formbtn" value="<?=gettext("Save URL");?>" onClick="return fetch_handler();" />
            <input id="revert_url" name="revert_url" type="submit" class="formbtn" value="<?=gettext("Revert URL");?>" onClick="return fetch_handler();" />
            <input id="get_file" name="get_file" type="submit" class="formbtn" value="<?=gettext("Get File");?>" onClick="return fetch_handler();" />
        </div>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<?php html_separator();?>
            <?php html_titleline(gettext("Backup"));?>
            <?php
                $file_list = filelist("{$configuration['product_executable']}-*");
                $backups = radiolist($file_list);
                if ( $backups ) { $backup_list = $backups; }
                else { $backup_list = gettext("No backup found!"); }
            ?>
            <?php html_text("backup_list", gettext("Existing backups"), "{$backup_list}");?>
        </table>
        <div id="remarks">
            <?php html_remark("note", gettext("Note"), sprintf(gettext("Choose a backup to delete or install.")));?>
        </div>
        <div id="submit">
            <input id="delete_backup" name="delete_backup" type="submit" class="formbtn" value="<?=gettext("Delete Backup");?>" onClick="return fetch_handler();" />
            <input id="install_backup" name="install_backup" type="submit" class="formbtn" value="<?=gettext("Install Backup");?>" onClick="return fetch_handler();" />
        </div>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<?php html_separator();?>
        	<?php html_titleline_checkbox("enable_schedule", gettext("Daily schedule"), $configuration['enable_schedule'], gettext("Enable"), "enable_change(false)");?>
    		<?php $hours = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23); ?>
            <?php html_combobox("startup", gettext("Startup"), $configuration['schedule_startup'], $hours, gettext("Choose a startup hour for")." ".$configuration['appname'], true);?>
            <?php html_combobox("closedown", gettext("Closedown"), $configuration['schedule_closedown'], $hours, gettext("Choose a closedown hour for")." ".$configuration['appname'], true);?>
            <?php html_checkbox("prohibit", gettext("System Startup"), $configuration['schedule_prohibit'], sprintf(gettext("Prohibit %s start on system startup if scheduling is activated and the server startup time is outside the range of the defined startup and closedown hour."), "Resilio Sync"), false);?>
			<?php html_separator();?>
        </table>
        <div id="submit_schedule">
            <?php if (!isset($configuration['command'])){ $disabled = "disabled"; } else { $disabled = ""; } ?>
            <input id="schedule" name="schedule" type="submit" <?=$disabled;?> class="formbtn" value="<?=gettext("Save and Restart");?>" onclick="enable_change(true)" />
        </div>
        <?php 
        include("formend.inc");?>
    </td></tr>
</table>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
