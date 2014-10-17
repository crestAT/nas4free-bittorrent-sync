#!/usr/local/bin/php-cgi -f
<?php
/* 
    btsync_start.php

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
require_once("config.inc");
require_once("functions.inc");
require_once("install.inc");
require_once("util.inc");

if ( !is_dir ( '/usr/local/www/ext/btsync')) { exec ("mkdir -p /usr/local/www/ext/btsync"); }
exec ("cp ".$config['btsync']['rootfolder']."ext/* /usr/local/www/ext/btsync/");
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
