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
0.6.1	2014.08.27	C: download path for BTS application
0.6		2014.01.01	introduce scheduler
0.5.7	2013.12.22	first public release
