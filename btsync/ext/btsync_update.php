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
    if (isset($config['btsync']['enable'])) { exec("killall btsync"); sleep(5); }
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
    $config['btsync']['size_new'] = exec ("fetch -s  http://download-lb.utorrent.com/endpoint/btsync/os/FreeBSD-".$config['btsync']['architecture']."/track/stable");
//    if ($config['btsync']['size_new'] != $config['btsync']['size']) {
        exec ("fetch -o ".$config['btsync']['updatefolder']." http://download-lb.utorrent.com/endpoint/btsync/os/FreeBSD-".$config['btsync']['architecture']."/track/stable");
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
            if (isset($config['btsync']['enable'])) { exec("killall btsync"); sleep(5); }
//            exec("rm ".$config['btsync']['rootfolder']."btsync");
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

//-->
</script>
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
        <form action="btsync_update.php" method="post" name="iform" id="iform">
            <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline(gettext($config['btsync']['appname']." Update"));?>
			  <tr>
			    <td width="25%" class="vncellreq"><?=$config['btsync']['appname']." ".gettext("Status");?></td>
                <td width="75%" class="vtable"><span name="procinfo" id="procinfo"></span></td>
			  </tr>
			<?php html_text("version_current", gettext("Installed version"), $config['btsync']['product_version']);?>
			<tr>
				<td width="15%" valign="top" class="vncell"><?=gettext("Latest version fetched from BitTorrent server");?>
				</td>
				<td width="85%" class="vtable"><?=gettext($pconfig['product_version_new']." - push \"fetch\" button to check for new version");?>
                    <input id="fetch" name="fetch" type="submit" class="formbtn" value="<?=gettext("Fetch");?>" onClick="return fetch_handler();" />
                    <?php if (("{$pconfig['product_version_new']}" != "{$config['btsync']['product_version']}") && ("{$pconfig['product_version_new']}" != "n/a")) { ?> 
                        <input id="install_new" name="install_new" type="submit" class="formbtn" value="<?=gettext("Install");?>" onClick="return fetch_handler();" />
                    <?php } ?>
				</td>
			</tr>
			<?php html_separator();?>
            <?php html_titleline($config['btsync']['appname']." ".gettext("Backup"));?>
            <tr>
                <td width="22%" valign="top" class="vncell"><?=gettext("Existing backups");?></td>
                <td width="78%" class="vtable">
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
            <?php html_remark("note", gettext("Note"), sprintf(gettext("Choose backup to delete or install.")));?>
        </div>
        <div id="submit">
            <input id="delete_backup" name="delete_backup" type="submit" class="formbtn" value="<?=gettext("Delete Backup");?>" onClick="return fetch_handler();" />
            <input id="install_backup" name="install_backup" type="submit" class="formbtn" value="<?=gettext("Install Backup");?>" onClick="return fetch_handler();" />
        </div>
        <?php include("formend.inc");?>
        </form>
    </td></tr>
</table>
<?php include("fend.inc");?>
