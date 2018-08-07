<?php
/*
	diag_log.inc

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
require_once("globals.inc");
require_once("rc.inc");

$domain = strtolower(get_product_name());
$localeOSDirectory = "/usr/local/share/locale";
$localeExtDirectory = "/usr/local/share/locale-bts";
bindtextdomain($domain, $localeExtDirectory);

$config_file = "ext/btsync/btsync.conf";
require_once("ext/btsync/extension-lib.inc");
if (($configuration = ext_load_config($config_file)) === false) $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "btsync.conf");
if (!isset($configuration['rootfolder']) && !is_dir($configuration['rootfolder'] )) $input_errors[] = gettext("Extension installed with fault");

// Get the location of the log files (may be modified via rc.conf variables).
$clogdir = rc_getenv_ex("clog_logdir", $g['varlog_path']);

// The log configuration
$loginfo = array(
	array(
		"visible" => TRUE,
		"desc" => gettext("Notifications"),
		"logfile" => "{$configuration['storage_path']}/sync.log",
		"filename" => "sync.log",
		"type" => "plain",
		"pattern" => "/^(\S+\s+\S+)\s+(.*)$/",
		"columns" => array(
			array("title" => gettext("Date & Time"), "class" => "listlr", "param" => "nowrap=\"nowrap\"", "pmid" => 1),
			array("title" => gettext("Event"), "class" => "listr", "param" => "", "pmid" => 2)
		)),
	array(
		"visible" => TRUE,
		"desc" => gettext("System"),
		"logfile" => "{$clogdir}/system.log",
		"filename" => "system.log",
		"type" => "clog",
		"size" => "262144",
		"pattern" => "/^(\S+\s+\d+\s+\S+)\s+(\S+)\s+(\S+)\s+(.*)$/",
		"columns" => array(
			array("title" => gettext("Date & Time"), "class" => "listlr", "param" => "nowrap", "pmid" => 1),
			array("title" => gettext("User"), "class" => "listr", "param" => "nowrap", "pmid" => 3),
			array("title" => gettext("Event"), "class" => "listr", "param" => "", "pmid" => 4)
		))
);

function log_get_contents($logfile, $type) {
	global $config;
    global $configuration;

	$content = array();

	$param = (isset($config['syslogd']['reverse']) ? "-r " : "");

	switch ($type) {
		case "clog":
        	$param .= "-n " . $config['syslogd']['nentries'];
		    $filter_str = ($_POST['searchstring'] == '') ? "" : "| grep -i \"".htmlspecialchars($_POST['searchstring'])."\"";
			exec("/usr/sbin/clog {$logfile} | /usr/bin/tail {$param} {$filter_str}", $content);
			break;

		case "plain":
		    $filter_icf = (isset($configuration['filter_icf']) ? "| grep -v \"Incoming connection from\"" : "");
		    $filter = (!empty($configuration['filter_str']) ? "| grep \"{$configuration['filter_str']}\"" : "");
		    $filter_str = ($_POST['searchstring'] == '') ? "" : "| grep -i \"".htmlspecialchars($_POST['searchstring'])."\"";
			exec("/bin/cat {$logfile} | /usr/bin/tail {$param} {$filter_icf} {$filter} {$filter_str}", $content);
	}

	return $content;
}

function log_display($loginfo) {
	if (!is_array($loginfo))
		return;

	// Create table header
	echo "<tr>";
	foreach ($loginfo['columns'] as $columnk => $columnv) {
		echo "<td {$columnv['param']} class='" . (($columnk == 0) ? "listhdrlr" : "listhdrr") . "'>".htmlspecialchars($columnv['title'])."</td>\n";
	}
	echo "</tr>";

	// Get log file content
	$content = log_get_contents($loginfo['logfile'], $loginfo['type']);
	if (empty($content))
		return;

	// Create table data
	foreach ($content as $contentv) {
		// Skip invalid pattern matches
		$result = preg_match($loginfo['pattern'], $contentv, $matches);
		if ((FALSE === $result) || (0 == $result))
			continue;

		// Skip empty lines
		if (count($loginfo['columns']) == 1 && empty($matches[1]))
			continue;

		echo "<tr valign=\"top\">\n";
		foreach ($loginfo['columns'] as $columnk => $columnv) {
			echo "<td {$columnv['param']} class='{$columnv['class']}'>" . htmlspecialchars($matches[$columnv['pmid']]) . "</td>\n";
		}
		echo "</tr>\n";
	}
}

function log_clear($loginfo) {
	if (!is_array($loginfo))
		return;

	switch ($loginfo['type']) {
		case "clog":
			exec("/usr/sbin/clog -i -s {$loginfo['size']} {$loginfo['logfile']}");
			break;

		case "plain":
			exec("/bin/cat /dev/null > {$loginfo['logfile']}");
	}
}

function log_download($loginfo) {
	if (!is_array($loginfo))
		return;

	$fs = get_filesize($loginfo['logfile']);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$loginfo['filename']}");
	header("Content-Length: {$fs}");
	header("Pragma: hack");

	switch ($loginfo['type']) {
		case "clog":
			exec("/usr/sbin/clog {$loginfo['logfile']}", $content);
			echo implode("\n", $content);
			break;

		case "plain":
			readfile($loginfo['logfile']);
	}
}
?>
