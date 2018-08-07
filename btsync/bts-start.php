<?php
/* 
    btsync_start.php

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
require_once("config.inc");

$rootfolder = dirname(__FILE__)."/";
$config_file = "{$rootfolder}ext/btsync.conf";
require_once("{$rootfolder}ext/extension-lib.inc");
if (($configuration = ext_load_config($config_file)) === false) {
    exec("logger btsync-extension: configuration file {$config_file} not found, startup aborted!");
    exit;
}

if (is_file("{$configuration['rootfolder']}version.txt")) {
    $file_version = exec("cat {$configuration['rootfolder']}version.txt");
    if ($configuration['version'] != $file_version) {
        $configuration['version'] = $file_version;
		ext_save_config($config_file, $configuration);
    }
}

if (is_dir("/usr/local/www/ext/btsync")) mwexec("rm -R /usr/local/www/ext/btsync");		// cleanup of previous versions
$return_val = 0;
// create links to extension files
$return_val += mwexec("mkdir -p /usr/local/www/ext");									// if it is the first extension we need this directory
$return_val += mwexec("ln -sfw {$rootfolder}ext /usr/local/www/ext/btsync", true);
$return_val += mwexec("ln -sfw {$rootfolder}locale-bts /usr/local/share/", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/btsync.php /usr/local/www/btsync.php", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/btsync_log.php /usr/local/www/btsync_log.php", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/btsync_log.inc /usr/local/www/btsync_log.inc", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/btsync_update.php /usr/local/www/btsync_update.php", true);
$return_val += mwexec("ln -sfw {$rootfolder}ext/btsync_update_extension.php /usr/local/www/btsync_update_extension.php", true);
// check for product name and eventually rename translation files for new product name (XigmaNAS)
$domain = strtolower(get_product_name());
if ($domain <> "nas4free") {
	$return_val += mwexec("find {$rootfolder}locale-bts -name nas4free.mo -execdir mv nas4free.mo {$domain}.mo \;", true);
}
if ($return_val != 0) mwexec("logger btsync-extension: error during startup, link creation failed with return value = {$return_val}");
else if ($configuration['enable']) {
	    mwexec("killall {$configuration['product_executable']}");
		$check_hour = date("G");	    
	    if ($configuration['enable_schedule'] && $configuration['schedule_prohibit'] && (($check_hour < $configuration['schedule_startup']) || ($check_hour >= $configuration['schedule_closedown']))) { 
			mwexec("logger btsync-extension: {$configuration['product_executable']} start prohibited due to scheduler settings!"); 
			touch("/tmp/extended-gui_btsync_schedule_stopped.lock");		// to avoid alarming for Extended GUI service monitoring
		}
	    else {
		    mwexec("logger btsync-extension: enabled, start {$configuration['product_executable']} ...");
		    if (is_file("/tmp/extended-gui_btsync_schedule_stopped.lock")) unlink("/tmp/extended-gui_btsync_schedule_stopped.lock");
		    if (is_file("{$configuration['storage_path']}sync.log.old")) {
				$return_val = mwexec("rm {$configuration['storage_path']}sync.log.old", true);	// cleanup old log
				if ($return_val != 0) mwexec("logger btsync-extension: error cleanup old log: {$configuration['storage_path']}sync.log.old");
				else {
				    $return_val = mwexec("mv {$configuration['storage_path']}sync.log {$configuration['storage_path']}sync.log.old", true);						// save current log
					if ($return_val != 0) mwexec("logger btsync-extension: error backup log file");
				}
			} 
		    exec($configuration['command']);
		    sleep(5);														// give time to startup
		    if (exec("ps acx | grep {$configuration['product_executable']}")) { mwexec("logger btsync-extension: startup OK"); }
		    else { mwexec("logger btsync-extension: startup NOT ok" ); }
		}
	}
?>
