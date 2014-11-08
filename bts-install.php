<?php
/* 
    bts_install.php
     
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
// 2014.11.01   0.6.4       N: language support
//		                    N: added Russian language
//      					N: added German language
//                          N: quick check for BitTorrent Sync updates
// 2014.10.27   0.6.3.7     N: language support
// 2014.10.17   0.6.3.6     C: GitHub
// 2014.10.16   0.6.3.5     C: change to GitHub repo
// 2014.10.14   0.6.3.4     C: update check 4
// 2014.10.14   0.6.3.2     C: update check 2 without refresh advice
// 2014.10.14   0.6.3.1     C: update check 1
// 2014.10.14   0.6.3       C: back to 0.6.1.9e
// 2014.10.13   0.6.1.9e-h  C: repo on secure server
// 2014.10.12   0.6.1.9d    N: tab for online extension update & uninstall
// 2014.10.12   0.6.1.9c    F: fixes for online extension update & uninstall
// 2014.10.11   0.6.1.9b    N: online extension update & uninstall
// 2014.10.09   0.6.1.9a    C: installation procedure
// 2014.10.08   0.6.1.9     N: listening_port -> because in BTS setting it is ignored
// 2014.10.07   0.6.1.8     N: new installer -> use with WebGUI | ADVANCED | COMMAND
// 2014.10.06   0.6.1.7     C: extension installer combined Install and Update option
// *            0.6.1.6     N: on user change set files permissions and check/change directory path for accessibility for the new user
// *            0.6.1.5     N: one-time download of previous versions, VLAN & LAGG support
// *            0.6.1.4     N: implementation of 1.4.xx features of BTS
// *                        N: download URL handling
// *            0.6.1.3     F: position of sync.log file from storage_path
// *                        N: update documentation URL
// *            0.6.1.2     C: save ALL params in sync.conf (not only those which are used in extension WebGUI!)
// *            0.6.1.1     N: search function in logs, 2 columns in log view
// *            0.6.1       C: download path for BTS application
// *            0.6         introduce scheduler
// *            0.5.7       correct display of boolean values from sync.conf file in btsync.php
// *            0.5.6       introduce sync.conf file
// *            0.5.5       log file filter
// *            0.5.4.1     backup management
// *            0.5a        fetch update, remove startup error msg

// $version = "0.6.4";  -> read from version.txt file
$appname = "BitTorrent Sync";

require_once("config.inc");

$arch = $g['arch'];
$platform = $g['platform'];
if (($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64")) { echo "unsupported architecture!\n"; exit(1);  }
if ($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb") { echo "unsupported platform!\n";  exit(1); }

// install extension
mwexec("fetch -o master.zip http://sourceforge.net/projects/nas4freeextensionbts/files/BitTorrent%20Sync/bts-v064.zip/download", true);
exec("tar -xvf master.zip --exclude='.git*' --strip-components 1");
exec("rm master.zip");
exec("chmod -R 770 *");

// install application on server
if ( !isset($config['btsync']) || !is_array($config['btsync'])) {
	$cwdir = getcwd();
    $config['btsync'] = array();
	$path1 = pathinfo($cwdir);
	$config['btsync']['appname'] = $appname;
	$config['btsync']['rootfolder'] = $path1['dirname']."/".$path1['basename']."/btsync/";
	$config['btsync']['backupfolder'] = $config['btsync']['rootfolder']."backup/";
	$config['btsync']['updatefolder'] = $config['btsync']['rootfolder']."update/";
    $config['btsync']['version'] = exec("cat {$config['btsync']['rootfolder']}version.txt");
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
	$config['btsync']['download_url'] = "http://download-new.utorrent.com/endpoint/btsync/os/FreeBSD-".$config['btsync']['architecture']."/track/stable";
	$config['btsync']['previous_url'] = $config['btsync']['download_url'];
    exec ("fetch -o {$cwdir} {$config['btsync']['download_url']}");
    exec ("cd ".$cwdir." && tar -xzvf stable");
    if ( !is_file ($cwdir.'btsync') ) { echo ('Executable file "btsync" not found, installation aborted!'); exit (3); }
    $config['btsync']['product_version'] = exec ($cwdir."btsync --help | awk '/".$appname."/ {print $3}'");
    if (!is_dir ($config['btsync']['rootfolder'].'.sync')) { exec ("mkdir -p ".$config['btsync']['rootfolder'].'.sync'); }
    if (!is_dir ($config['btsync']['backupfolder'])) { exec ("mkdir -p ".$config['btsync']['backupfolder']); }
    if (!is_dir ($config['btsync']['updatefolder'])) { exec ("mkdir -p ".$config['btsync']['updatefolder']); }
   	exec ("cp ".$cwdir."btsync ".$config['btsync']['backupfolder']."btsync-".$config['btsync']['product_version']);
    $config['btsync']['size'] = exec ("fetch -s {$config['btsync']['download_url']}");
    if ($config['btsync']['product_version'] == '') { $config['btsync']['product_version'] = 'n/a'; }
    if ($config['btsync']['size'] == '') { $config['btsync']['size'] = 'n/a'; }
    write_config();
    require_once("{$config['btsync']['rootfolder']}btsync_start.php");
    echo "\n".$appname." Version ".$config['btsync']['product_version']." installed";
    echo "\n\nInstallation completed, use WebGUI | Extensions | ".$appname." to configure \nthe application (don't forget to refresh the WebGUI before use)!\n";
}
else { 
    require_once("{$config['btsync']['rootfolder']}bts-start.php");
}
?>
