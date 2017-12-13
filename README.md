LiteSpeedCache for MediaWiki
============================

The LiteSpeedCache plugin is a MediaWiki extension for MediaWiki sites running on a LiteSpeed webserver. Enable its LSCache feature to speed up page loading, reduce response time, and tremendously reduce server load for MediaWiki sites.

See https://www.litespeedtech.com/products/cache-plugins for more information.

The LiteSpeedCache extension was originally written by LiteSpeed Technologies. It is released under the GNU General Public Licence (GPL).



Prerequisites
-------------
This version of LiteSpeedCache requires MediaWiki 1.25 or later and LiteSpeed LSWS Server 5.1 or later.



Installing
-------------
Copy the LiteSpeedCache directory into the extensions folder of your MediaWiki installation. Then, add the following lines to your `LocalSettings.php` file (near the end):

    wfLoadExtension( 'LiteSpeedCache' );

After installation, You can navigate to **Special:Version** on your wiki to verify that the extension is successfully installed.

To find a link to the LiteSpeedCache plugin, navigate to **Special:SpecialPages** on your wiki, and look under the **Data and Tools** category.



Usage
-------------
After installation, login as admin and visit **Special:LiteSpeedCache** on your wiki to turn on LiteSpeedCache.