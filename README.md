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
1. Prior to the installation make a backup of the N4F configuration via SYSTEM | BACKUP/RESTORE | Download configuration
2. Use the system console shell or connect via ssh to your N4F server - for the installation you must be logged in as root
3. Change to a persistant place / data directory which should hold the extensions, in my case /mnt/DATA/extensions
CODE: SELECT ALL
cd /mnt/DATA/extensions
4. Download the latest version of the extension from above and copy it to your extensions directory.
5. Extract files and remove archive
CODE: SELECT ALL
tar xzvf btsync.tar.gz
rm btsync.tar.gz
6. Run installation script and choose option 1 to install (for update first uninstall and than install).
CODE: SELECT ALL
./btsync-install.php

HISTORY
-------
Version Date		Description
0.6.1.7	2014.10.06	C: btsync-installer: combined Install/Update option
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
0.6.1	2014.08.27	C: download path for BTS application
0.6		2014.01.01	introduce scheduler
0.5.7	2013.12.22	first public release
