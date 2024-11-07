LiteSpeedCache for MediaWiki
============================

The LiteSpeedCache plugin is a MediaWiki extension for MediaWiki sites running on a LiteSpeed webserver. Enable its LSCache feature to speed up page loading, reduce response time, and tremendously reduce server load for MediaWiki sites.

* It's simple, easy to use, and will speed up your wiki site up to 100 times faster, after 3 minutes of setup, with no extra cost.
* A special page integrated into MediaWiki provides full control of cache behavior.
* No more worring about cache sync problems. LScache will automatically purge a page when related article content has changed. You can set a longer cache expiration time to improve visitor experience, confident that the cache will be purged when relevant content changes.
* An optional private cache (for logged-in users) also will sync automatically when article content changes. No matter which user changes an article, all of the logged-in users will be served the new article content, regardless of the private cache expiration setting.

The LiteSpeedCache extension was originally written by LiteSpeed Technologies. It is released under the GNU General Public Licence (GPL).

See https://www.litespeedtech.com/products/cache-plugins for more information.



Prerequisites
-------------
This version of LiteSpeedCache requires MediaWiki 1.25+ and LiteSpeed LSWS Server 5.2.3+.


Installing
-------------

Download latest release and exact to extensions folder.


If you use Composer to manage dependencies use the following command in the root folder:

```
composer require litespeed/lscache-mediawiki
```

Modify .htaccess file in MediaWiki site directory, adding the following directives:

```
    <IfModule LiteSpeed>
    CacheLookup on
    </IfModule>
```

If your MediaWiki site has enabled MobileFrontend extension, adding the following directives:

```
    <IfModule LiteSpeed> 
    RewriteEngine On
    RewriteCond %{HTTP_USER_AGENT} Mobile|Android|Silk/|Kindle|BlackBerry|Opera\ Mini|Opera\ Mobi [NC] RewriteRule .* - [E=Cache-Control:vary=ismobile]
    RewriteRule .* - [E=Cache-Vary:stopMobileRedirect,mf_useformat]
    </IfModule>
```


Copy the LiteSpeedCache directory into the extensions folder of your MediaWiki installation. Then, add the following lines to your `LocalSettings.php` file (near the end):

```
    wfLoadExtension( 'LiteSpeedCache' );
```

After installation, You can navigate to **Special:Version** on your wiki to verify that the extension is successfully installed.

To find a link to the LiteSpeedCache plugin, navigate to **Special:SpecialPages** on your wiki, and look under the **Data and Tools** category.



Usage
-------------
After installation, login as admin and visit **Special:LiteSpeedCache** on your wiki to turn on LiteSpeedCache.