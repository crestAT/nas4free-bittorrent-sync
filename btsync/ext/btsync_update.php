<?php
/*
 * btsync_update.php
 * created 2013 by Andreas Schmidhuber
 */
require("auth.inc");
require("guiconfig.inc");

$pgtitle = array(gettext("Extensions"), $config['btsync']['appname']." ".$config['btsync']['version'], gettext("Maintenance"));

$pconfig['product_version_new'] = !empty($config['btsync']['product_version_new']) ? $config['btsync']['product_version_new'] : "n/a";

if (isset($_POST['install_new']) && $_POST['install_new']) {
    if (isset($config['btsync']['enable'])) { 
        exec("killall btsync");
        $return_val = 0;
        while( $return_val == 0 ) { sleep(1); exec('ps acx | grep btsync', $output, $return_val); }
    }
    if (!copy($config['btsync']['updatefolder']."btsync", $config['btsync']['rootfolder']."btsync")) { $input_errors[] = gettext("Could not install new version!"); }
    else {
        if (isset($config['btsync']['enable'])) { exec($config['btsync']['command']); }
        $config['btsync']['product_version'] = $pconfig['product_version_new'];
        $config['btsync']['size'] = $config['btsync']['size_new'];
       	copy($config['btsync']['rootfolder']."btsync", $config['btsync']['backupfolder']."btsync-".$config['btsync']['product_version']);
        write_config();
        $savemsg = gettext("New version installed!");
    }	
}

if (isset($_POST['fetch']) && $_POST['fetch']) {
    unset($input_errors);
    $config['btsync']['size_new'] = exec ("fetch -s  http://download-new.utorrent.com/endpoint/btsync/os/FreeBSD-".$config['btsync']['architecture']."/track/stable");
//    if ($config['btsync']['size_new'] != $config['btsync']['size']) {
        exec ("fetch -o ".$config['btsync']['updatefolder']." http://download-new.utorrent.com/endpoint/btsync/os/FreeBSD-".$config['btsync']['architecture']."/track/stable");
        exec ("cd ".$config['btsync']['updatefolder']." && tar -xzvf stable");
        if ( !is_file ($config['btsync']['updatefolder'].'btsync') ) { $input_errors[] = gettext("Could not fetch new version!"); }
        else {
            $pconfig['product_version_new'] = exec ("{$config['btsync']['updatefolder']}btsync --help | grep BitTorrent | cut -d ' ' -f3");
            if ($pconfig['product_version_new'] == '') { $pconfig['product_version_new'] = 'n/a'; $input_errors[] = gettext("Could not retrieve new version!"); }
            else {
                if ("{$pconfig['product_version_new']}" == "{$config['btsync']['product_version']}") { $savemsg = gettext("No new version available!"); }
                else {
                    $savemsg = "New version {$pconfig['product_version_new']} available, push \"".gettext('Install')."\" button to install the new version!";            
                }
                $config['btsync']['product_version_new'] = !empty($pconfig['product_version_new']) ? $pconfig['product_version_new'] : "n/a";
                write_config();
            } 
        }
//    }
//    else { $savemsg = gettext("No new version available!"); }
}

if ( isset( $_POST['delete_backup'] ) && $_POST['delete_backup'] ) {
    if ( !isset($_POST['installfile']) ) { $input_errors[] = gettext("No file selected to delete!") ; }
    else {
        if (is_file($_POST['installfile'])) {
            exec("rm ".$_POST['installfile']);
            $savemsg = "File version ".$_POST['installfile']." deleted!";
        }
        else { $input_errors[] = "File ".$_POST['installfile']." not found!"; }
    }
}

if ( isset( $_POST['install_backup'] ) && $_POST['install_backup'] ) {
    if ( !isset($_POST['installfile']) ) { $input_errors[] = gettext("No file selected to install!") ; }
    else {
        if (is_file($_POST['installfile'])) {
            if (isset($config['btsync']['enable'])) { 
                exec("killall btsync");
                $return_val = 0;
                while( $return_val == 0 ) { sleep(1); exec('ps acx | grep btsync', $output, $return_val); }
            }
            if (!copy($_POST['installfile'], $config['btsync']['rootfolder']."btsync")) { $input_errors[] = gettext("Could not install backup version!"); }
            else {
                if (isset($config['btsync']['enable'])) { exec($config['btsync']['command']); }
                $config['btsync']['product_version'] = exec ("{$config['btsync']['rootfolder']}btsync --help | grep BitTorrent | cut -d ' ' -f3");
                $pconfig['product_version_new'] = "n/a"; 
                $config['btsync']['product_version_new'] = "n/a";
                $config['btsync']['size'] = "n/a"; 
                $config['btsync']['size_new'] = "n/a";
               	copy($config['btsync']['rootfolder']."btsync", $config['btsync']['backupfolder']."btsync-".$config['btsync']['product_version']);
                write_config();
                if (isset($config['btsync']['enable'])) { $savemsg = gettext("Backup version installed!"); }
                else { $savemsg = gettext("Backup version installed!")." Go to ".gettext('Configuration')." and enable, save & restart to run ".$config['btsync']['appname']."!"; }
            }
        }
        else { $input_errors[] = "File ".$_POST['installfile']." not found!"; }
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

if ( isset( $_POST['schedule'] ) && $_POST['schedule'] ) {
    if (isset($_POST['enable_schedule']) && ($_POST['startup'] == $_POST['closedown'])) { $input_errors[] = gettext("Startup and closedown hour must be different!"); }
    else {
        if (isset($_POST['enable_schedule'])) {
            $config['btsync']['enable_schedule'] = isset($_POST['enable_schedule']) ? true : false;
            $config['btsync']['schedule_startup'] = $_POST['startup'];
            $config['btsync']['schedule_closedown'] = $_POST['closedown'];
    
            $cronjob = array();
            $a_cronjob = &$config['cron']['job'];
            $uuid = isset($config['btsync']['schedule_uuid_startup']) ? $config['btsync']['schedule_uuid_startup'] : false;
            if (isset($uuid) && (FALSE !== ($cnid = array_search_ex($uuid, $a_cronjob, "uuid")))) {
            	$cronjob['enable'] = true;
            	$cronjob['uuid'] = $a_cronjob[$cnid]['uuid'];
            	$cronjob['desc'] = "BitTorrent Sync startup (@ {$config['btsync']['schedule_startup']}:00)";
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
//            	$cronjob['who'] = $config['btsync']['who'];
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = $config['btsync']['command']." && logger btsync: scheduled startup";
            } else {
            	$cronjob['enable'] = true;
            	$cronjob['uuid'] = uuid();
            	$cronjob['desc'] = "BitTorrent Sync startup (@ {$config['btsync']['schedule_startup']}:00)";
            	$cronjob['minute'] = 0;
            	$cronjob['hour'] = $config['btsync']['schedule_startup'];
            	$cronjob['day'] = true;
            	$cronjob['month'] = true;
            	$cronjob['weekday'] = true;
            	$cronjob['all_mins'] = 0;
            	$cronjob['all_hours'] = 0;
            	$cronjob['all_days'] = 1;
            	$cronjob['all_months'] = 1;
            	$cronjob['all_weekdays'] = 1;
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = $config['btsync']['command']." && logger btsync: scheduled startup";
                $config['btsync']['schedule_uuid_startup'] = $cronjob['uuid'];
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
            	$cronjob['desc'] = "BitTorrent Sync closedown (@ {$config['btsync']['schedule_closedown']}:00)";
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
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = 'killall btsync && logger btsync: scheduled closedown';
            } else {
            	$cronjob['enable'] = true;
            	$cronjob['uuid'] = uuid();
            	$cronjob['desc'] = "BitTorrent Sync closedown (@ {$config['btsync']['schedule_closedown']}:00)";
            	$cronjob['minute'] = 0;
            	$cronjob['hour'] = $config['btsync']['schedule_closedown'];
            	$cronjob['day'] = true;
            	$cronjob['month'] = true;
            	$cronjob['weekday'] = true;
            	$cronjob['all_mins'] = 0;
            	$cronjob['all_hours'] = 0;
            	$cronjob['all_days'] = 1;
            	$cronjob['all_months'] = 1;
            	$cronjob['all_weekdays'] = 1;
            	$cronjob['who'] = 'root';
            	$cronjob['command'] = 'killall btsync && logger btsync: scheduled closedown';
                $config['btsync']['schedule_uuid_closedown'] = $cronjob['uuid'];
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
            $config['btsync']['enable_schedule'] = isset($_POST['enable_schedule']) ? true : false;
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
    }   // end of schedule change
}

// Function name: 	filelist
// Inputs: 			file_list			array of filenames with suffix to create list for
//					exclude				Optional array used to remove certain results
// Outputs: 		file_list			html formatted block with a radio next to each file
// Description:		This function creates an html code block with the files listed on the right
//					and radio buttons next to each on the left.
function filelist ($contains , $exclude='') {
	global $config ;
	// This function creates a list of files that match a certain filename pattern
	$installFiles = "";
	if ( is_dir( $config['btsync']['rootfolder'] )) {
		$raw_list = glob("{$config['btsync']['backupfolder']}{$contains}.{*}", GLOB_BRACE);
		$file_list = array_unique( $raw_list );
		if ( $exclude ) {
			foreach ( $exclude as $search_pattern ) {
				$file_list = preg_grep( "/{$search_pattern}/" , $file_list , PREG_GREP_INVERT );
			}
		sort ( $file_list , SORT_NUMERIC );
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
	global $config ;		// import the global config array
	$installFiles = "";		// Initialize installFiles as an empty string so we can concatenate in the for loop
	if (is_dir($config['btsync']['rootfolder'])) {		// check if the folder is a directory, so it doesn't choke
		foreach ( $file_list as $file) {
			$file = str_replace($config['btsync']['rootfolder'] . "/", "", $file);
			$installFiles .= "<input type=\"radio\" name=\"installfile\" value=\"$file\"> "
			. str_replace($config['btsync']['backupfolder'], "", $file)
			. "<br/>";
			} // end of completed folder, filename, suffix creation
	} // end of verifying rootfolder as valid location
	return $installFiles ;
}

function get_process_info() {
    if (exec('ps acx | grep btsync')) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>running</b>&nbsp;&nbsp;</a>'; $proc_state = 'running'; }
    else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>stopped</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

if (is_ajax()) {
	$procinfo = get_process_info();
	render_ajax($procinfo);
}

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
		alert('Please wait for the previous operation to complete!!');
	else{
		return confirm('The selected operation will be completed. Please do not click any other buttons.');
	}
}

function enable_change(enable_change) {
	var endis = !(document.iform.enable_schedule.checked || enable_change);
	document.iform.startup.disabled = endis;
	document.iform.closedown.disabled = endis;
}

//-->
</script>
<form action="btsync_update.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabinact"><a href="btsync.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabact"><a href="btsync_update.php"><span><?=gettext("Maintenance");?></span></a></li>
			<li class="tabinact"><a href="btsync_log.php"><span><?=gettext("Log");?></span></a></li>
		</ul>
	</td></tr>
	<tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
            <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline(gettext("Update"));?>
			  <tr>
			    <td class="vncell"><?=gettext("Status");?></td>
                <td class="vtable"><span name="procinfo" id="procinfo"></span></td>
			  </tr>
			<?php html_text("version_current", gettext("Installed version"), $config['btsync']['product_version']);?>
			<tr>
				<td valign="top" class="vncell"><?=gettext("Latest version fetched from BitTorrent server");?>
				</td>
				<td class="vtable"><?=gettext($pconfig['product_version_new']." - push \"fetch\" button to check for new version");?>
                    <input id="fetch" name="fetch" type="submit" class="formbtn" value="<?=gettext("Fetch");?>" onClick="return fetch_handler();" />
                    <?php if (("{$pconfig['product_version_new']}" != "{$config['btsync']['product_version']}") && ("{$pconfig['product_version_new']}" != "n/a")) { ?> 
                        <input id="install_new" name="install_new" type="submit" class="formbtn" value="<?=gettext("Install");?>" onClick="return fetch_handler();" />
                    <?php } ?>
				</td>
			</tr>
			<?php html_separator();?>
            <?php html_titleline(gettext("Backup"));?>
            <tr>
                <td valign="top" class="vncell"><?=gettext("Existing backups");?></td>
                <td class="vtable">
                    <?php
                        $file_list = filelist("btsync-*");
                        $backups = radiolist($file_list);
                        if ( $backups ) { echo $backups; }
                        else { echo sprintf(gettext("No backup found")); }
                    ?>
                </td>
            </tr>
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
        	<?php html_titleline_checkbox("enable_schedule", gettext("Daily schedule"), isset($config['btsync']['enable_schedule']) ? true : false, gettext("Enable"), "enable_change(false)");?>
    		<?php $hours = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23); ?>
            <?php html_combobox("startup", gettext("Startup"), $config['btsync']['schedule_startup'], $hours, gettext("Choose a startup hour for")." ".$config['btsync']['appname'], true);?>
            <?php html_combobox("closedown", gettext("Closedown"), $config['btsync']['schedule_closedown'], $hours, gettext("Choose a closedown hour for")." ".$config['btsync']['appname'], true);?>
			<?php html_separator();?>
        </table>
        <div id="submit_schedule">
            <?php if (!isset($config['btsync']['command'])){ $disabled = "disabled"; } else { $disabled = ""; } ?>
            <input id="schedule" name="schedule" type="submit" <?=$disabled;?> class="formbtn" value="<?=gettext("Save and Restart");?>" onclick="enable_change(true)" />
        </div>
        <?php include("formend.inc");?>
    </td></tr>
</table>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
