<?php
/*
	btsync_log.php
	
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
require("btsync_log.inc");

if (isset($_GET['log']))
	$log = $_GET['log'];
if (isset($_POST['log']))
	$log = $_POST['log'];
if (empty($log))
	$log = 0;

bindtextdomain("nas4free", "/usr/local/share/locale-bts");

$config_file = "ext/btsync/btsync.conf";
require_once("ext/btsync/extension-lib.inc");
if (($configuration = ext_load_config($config_file)) === false) $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "btsync.conf");
if (!isset($configuration['rootfolder']) && !is_dir($configuration['rootfolder'] )) $input_errors[] = gettext("Extension installed with fault");

$pgtitle = array(gettext("Extensions"), $configuration['appname']." ".$configuration['version'], gettext("Log"));

if (isset($_POST['save']) && $_POST['save']) {
    $configuration['filter_icf'] = isset($_POST['filter_icf']) ? true : false;
    $configuration['filter_str'] = !empty($_POST['filter_str']) ? htmlspecialchars($_POST['filter_str']) : false;
	$savemsg = get_std_save_message(ext_save_config($config_file, $configuration));
}

if (isset($_POST['clear']) && $_POST['clear']) {
	log_clear($loginfo[$log]);
	header("Location: btsync_log.php?log={$log}");
	exit;
}

if (isset($_POST['download']) && $_POST['download']) {
	log_download($loginfo[$log]);
	exit;
}

if (isset($_POST['refresh']) && $_POST['refresh']) {
	header("Location: btsync_log.php?log={$log}");
	exit;
}
bindtextdomain("nas4free", "/usr/local/share/locale");
?>
<?php include("fbegin.inc");?>
<script type="text/javascript">
<!--
function log_change() {
	// Reload page
	window.document.location.href = 'btsync_log.php?log=' + document.iform.log.value;
}
//-->
</script>
<form action="btsync_log.php" method="post" name="iform" id="iform">
<?php bindtextdomain("nas4free", "/usr/local/share/locale-bts"); ?>
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
    	<tr><td class="tabnavtbl">
    		<ul id="tabnav">
    			<li class="tabinact"><a href="btsync.php"><span><?=gettext("Configuration");?></span></a></li>
    			<li class="tabinact"><a href="btsync_update.php"><span><?=gettext("Maintenance");?></span></a></li>
    			<li class="tabinact"><a href="btsync_update_extension.php"><span><?=gettext("Extension Maintenance");?></span></a></li>
    			<li class="tabact"><a href="btsync_log.php"><span><?=gettext("Log");?></span></a></li>
    		</ul>
    	</td></tr>
    	<tr><td class="tabcont">
            <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
            <?php if (!empty($savemsg)) print_info_box($savemsg);?>
            <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <?php html_titleline(gettext("Filter"));?>
                <?php html_checkbox("filter_icf", gettext("Incoming connections"), isset($configuration['filter_icf']) ? true : false, gettext("Hide \"Incoming connection from\" messages."), false );?>
                <?php html_inputbox("filter_str", gettext("Filter string"), !empty($configuration['filter_str']) ? $configuration['filter_str'] : "", gettext("Enter filter string (case sensitive) and hit \"Save Filter\" button to use the filter string permanently."), false, 15);?>
                <?php html_separator();?>
            </table>
    		<select id="log" class="formfld" onchange="log_change()" name="log">
    			<?php foreach($loginfo as $loginfok => $loginfov):?>
    			<?php if (FALSE === $loginfov['visible']) continue;?>
    			<option value="<?=$loginfok;?>" <?php if ($loginfok == $log) echo "selected=\"selected\"";?>><?=htmlspecialchars($loginfov['desc']);?></option>
    			<?php endforeach;?>
    		</select>
    		<input name="clear" type="submit" class="formbtn" value="<?=gettext("Clear");?>" />
    		<input name="download" type="submit" class="formbtn" value="<?=gettext("Download");?>" />
    		<input name="refresh" type="submit" class="formbtn" value="<?=gettext("Refresh");?>" />
    		<input name="save" type="submit" class="formbtn" value="<?=gettext("Save Filter");?>" />
			<span class="label">&nbsp;&nbsp;&nbsp;<?=gettext("Search string");?></span>
			<input size="30" id="searchstring" name="searchstring" value="<?=$searchstring;?>" />
			<input name="search" type="submit" class="formbtn" value="<?=gettext("Search");?>" />
            <br /><br />
    		<table width="100%" border="0" cellpadding="0" cellspacing="0">
                <?php log_display($loginfo[$log]);?>
    		</table>
    		<?php include("formend.inc");?>
         </td></tr>
    </table>
</form>
<?php include("fend.inc");?>
