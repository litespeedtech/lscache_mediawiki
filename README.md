LiteSpeedCache for MediaWiki
============================

The LiteSpeedCache plugin is a MediaWiki extension for MediaWiki sites run on a
LiteSpeed webserver, enable its LSCache feature to speed up page loading, reduce
response time, and tremendously reduce server compute resource cost for 
MediaWiki sites.

See https://www.litespeedtech.com/products/cache-plugins for more information.

The LiteSpeedCache extension was originally written by LiteSpeed Technologies.
it is released under the GNU General Public Licence (GPL).



Prerequisites
-------------
This version of LiteSpeedCache requires MediaWiki 1.25 or later.
requires runs on LiteSpeed LSWS Server 5.1 or later.



Installing
-------------
Copy the LiteSpeedCache directory into the extensions folder of your
MediaWiki installation. Then add the following lines to your
LocalSettings.php file (near the end):

	wfLoadExtension( 'LiteSpeedCache' );

After installation, You can Navigate to Special:Version on your wiki to verify 
that the extension is successfully installed.

You can Navigate to Special:SpecialPages on your wiki, under Data and Tools
category you can find a link to LiteSpeedCache plugin.



Usage
-------------
After installation, You can login as admin and visit to Special:LiteSpeedCache 
on your wiki to turn on LiteSpeedCache.