<?php
/* 
    btsync_start.php

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
if ($return_val != 0) mwexec("logger btsync-extension: error during startup, link creation failed with return value = {$return_val}");
else if ($configuration['enable']) {
	    mwexec("killall {$configuration['product_executable']}");
	    if ($configuration['enable_schedule'] && $configuration['schedule_prohibit'] && ($argc == 1)) mwexec("logger btsync-extension: {$configuration['product_executable']} start prohibited due to scheduler settings!");
	    else {
		    mwexec("logger btsync-extension: enabled, start {$configuration['product_executable']} ...");
		    exec($configuration['command']);
		    sleep(5);														// give time to startup
		    if (exec("ps acx | grep {$configuration['product_executable']}")) { mwexec("logger btsync-extension: startup OK"); }
		    else { mwexec("logger btsync-extension: startup NOT ok" ); }
		}
	}
?>
