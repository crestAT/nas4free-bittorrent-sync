BitTorrent Sync
---------------

Extension to install / configure / backup / update / manage and remove BitTorrent Sync (BTS) application on NAS4Free (N4F) servers.

The extension
- works on all plattforms
- does not need jail or pkg_add.
- add pages to NAS4Free WebGUI extensions
- features configuration, application update & backup management, scheduling and log view with filter / search capabilities

INSTALLATION
------------
1. Prior to the installation make a backup of the N4F configuration via SYSTEM | BACKUP/RESTORE | Download configuration.
2. Got to the N4F Webgui menu entry ADVANCED | COMMAND, copy the following line (change the path /mnt/DATA/extensions to 
    your needs - a persistant place where all extensions are/should be) paste it to the command field and push "Execute", this will copy the installer to your system:
        cd /mnt/DATA/extensions && fetch https://raw.github.com/crestAT/nas4free-bittorrent-sync/master/bts_install.php && chmod 770 bts_install.php && echo "fetch OK"
3. After you see "fetch OK" execute the following line (changed the path /mnt/DATA/extensions to your persistant place), this will install the extension on your system: 
        /mnt/DATA/extensions/bts_install.php
4. After successful completion you can access the extension from the WebGUI menu entry EXTENSIONS | BitTorrent Sync.

<pre>
HISTORY
-------
Version Date        Description
0.6.3.6 2014.10.17  N: BTS installation, new tab "Extension Maintainance" for online extension update and remove via the WebGUI
0.6.1.7 2014.10.06  C: btsync-installer: combined Install/Update option
                    C: Configuration: improvements for user change, take care about permissions
                    N: Configuration: VLAN/LAGG support -> taken from user Vasily1
                    N: Configuration: http/https switch
                    N: Configuration: external tick box => listen to 0.0.0.0
                    N: Configuration: all directly edited changes in sync.conf will be taken as they are 
                    N: Configuration: all newly introduced BTS options editable/choosable in Advanced section
                    N: Configuration: updated documentation URL
                    N: Maintainance => editable update URL for the BitTorrent Sync application, so we are future-proof  ;) 
                    N: Maintainance => switch to previuosely saved update URL, just to be sure ...
                    N: Maintainance => one-time download and installation of previous BTS application versions  
                    C: Logview => only two columns
                    N: Logview => new search field
0.6.1   2014.08.27  C: download path for BTS application
0.6     2014.01.01  introduce scheduler
0.5.7   2013.12.22  first public release
</pre>
