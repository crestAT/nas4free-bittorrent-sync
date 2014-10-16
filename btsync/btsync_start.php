#!/usr/local/bin/php-cgi -f
<?php
/* 
 * btsync_start.php
 * created 2013 by Andreas Schmidhuber
 */
require_once("config.inc");
require_once("functions.inc");
require_once("install.inc");
require_once("util.inc");

if ( !is_dir ( '/usr/local/www/ext/btsync')) { 
	exec ("mkdir -p /usr/local/www/ext/btsync"); 
	exec ("cp ".$config['btsync']['rootfolder']."ext/* /usr/local/www/ext/btsync/"); 
}
if ( !is_link ( "/usr/local/www/btsync.php")) { exec ("ln -s /usr/local/www/ext/btsync/btsync.php /usr/local/www/btsync.php"); }
if ( !is_link ( "/usr/local/www/btsync_log.php")) { exec ("ln -s /usr/local/www/ext/btsync/btsync_log.php /usr/local/www/btsync_log.php"); }
if ( !is_link ( "/usr/local/www/btsync_log.inc")) { exec ("ln -s /usr/local/www/ext/btsync/btsync_log.inc /usr/local/www/btsync_log.inc"); }
if ( !is_link ( "/usr/local/www/btsync_update.php")) { exec ("ln -s /usr/local/www/ext/btsync/btsync_update.php /usr/local/www/btsync_update.php"); }
if (isset($config['btsync']['enable'])) { 
    exec("logger btsync: enabled, start btsync ...");
    exec($config['btsync']['command']);
    if (exec('ps acx | grep btsync')) { exec("logger btsync: startup OK"); }    
    else { exec("logger btsync: startup NOT ok" ); } 
}
?>