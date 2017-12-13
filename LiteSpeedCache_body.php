<?php

/*
 *  Major Hook functions for LiteSpeedCache extention
 *
 *  @since      1.0.0
 *  @author     LiteSpeed Technologies <info@litespeedtech.com>
 *  @copyright  Copyright (c) 2017-2018 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 *  @license    https://opensource.org/licenses/GPL-3.0
 */

class LiteSpeedCache
{

    const _db_setting = 'litespeed_settings';

    private static $lscache_enabled = false;
    private static $login_user_cachable = false;
    private static $logging_enabled = false;
    private static $userLogedin;
    private static $lscInstance;
    private static $debugEnabled = false;
    private static $debugFile = 'lscache.log';

    /**
     *
     * register extension to system, be called before any other hooks function.
     *
     * @since 1.0.0
     */
    public static function onRegisterExtension()
    {
        global $wgLogTypes;

        array_push($wgLogTypes, "litespeedcache");
    }

    /**
     *
     * load LiteSpeedCache setting from DB
     *
     * @since 1.0.0
     */
    private static function init($reload = false)
    {
        self::log(__METHOD__);

        if (isset(self::$lscInstance)) {
            if (!$reload) {
                return;
            }
        }

        self::$lscInstance = LiteSpeedCacheCore::getInstance();
        global $wgExtensionDirectory;
        self::$lscInstance->setSiteOnlyTag(substr(md5($wgExtensionDirectory), 0, 4));

        $config = self::getLiteSpeedSettig();
        if ($config == null) {
            self::initLiteSpeedSetting();
            return;
        }
        self::$lscache_enabled = boolval($config['lscache_enabled']);
        self::$login_user_cachable = boolval($config['login_user_cachable']);
        self::$logging_enabled = boolval($config['logging_enabled']);
        self::$lscInstance->config($config);
    }

    /**
     *
     * Purge Article Cache once Article deleted
     *
     * @since   0.1
     */
    public static function onArticleDeleteComplete($article, User &$user, $reason, $id, Content $content = null, LogEntry $logEntry)
    {
        self::log(__METHOD__);

        self::init();
        if (!self::$lscache_enabled) {
            return;
        }

        $tag = $article->getTitle()->mUrlform;
        self::$lscInstance->purgePublic($tag);

        self::log("ArticleDelete", $user, $article->getTitle(), self::$lscInstance->getLogBuffer());
    }

    /**
     *
     * Purge Article Cache once Changed Article Content or Changed Discussion content for this Article.
     *
     * @since   0.1
     */
    public static function onPageContentSaveComplete($article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId, $undidRevId)
    {
        self::log(__METHOD__);

        self::init();
        if (!self::$lscache_enabled) {
            return;
        }

        $tag = $article->getTitle()->mUrlform;
        self::$lscInstance->purgePublic($tag);

        self::log("PageContentChange", $user, $article->getTitle(), self::$lscInstance->getLogBuffer());
    }

    /**
     *
     * Notify LSWS to cache this page once Article content was read from DB
     *
     * @since   0.1
     */
    public static function onArticlePageDataAfter(WikiPage $article, $row)
    {
        self::log(__METHOD__);

        if (self::isPostBack()) {
            return;
        }

        self::init();
        if (!self::$lscache_enabled) {
            return;
        }

        $tag = $article->getTitle()->mUrlform;

        if (self::isUserLogin()) {
            if (self::$login_user_cachable) {
                self::$lscInstance->cachePrivate($tag, null);
            }
        } else {
            self::$lscInstance->cachePublic($tag);
        }

        global $wgUser;
        self::log("ArticlePageLoaded", $wgUser, $article->getTitle(), self::$lscInstance->getLogBuffer());
    }

    /**
     *
     * Purge all private cache for this user after the user login
     *
     * @since   0.1
     */
    public static function onUserLoginComplete(User &$user, &$inject_html, $direct)
    {
        self::log(__METHOD__);
        self::init();
        if (!self::$lscache_enabled) {
            return;
        }

        self::$userLogedin = true;
        self::$lscInstance->checkPrivateCookie();
        self::$lscInstance->vary($user->mName);
        $_SESSION['_lsc_user'] = $user->mName;

        if (self::$login_user_cachable) {
            self::$lscInstance->purgeAllPrivate();
        }

        self::log("UserLogin", $user, $user->getUserPage(), self::$lscInstance->getLogBuffer());
    }

    /**
     *
     * Purge all private cache for this user once the user logout
     *
     * @since   0.1
     */
    public static function onUserLogoutComplete(&$user, &$inject_html, $old_name)
    {
        self::log(__METHOD__);
        self::init();
        if (!self::$lscache_enabled) {
            return;
        }

        self::$userLogedin = false;
        self::$lscInstance->checkPrivateCookie(false);
        self::$lscInstance->vary();

        if (self::$login_user_cachable) {
            self::$lscInstance->purgeAllPrivate();
        }

        self::log("UserLogout", $user, $user->getUserPage(), self::$lscInstance->getLogBuffer());
    }

    /**
     *
     * Purge all private cache for this user once the user changed settings
     *
     * @since   0.1
     */
    public static function onUserSaveSettings($user)
    {
        self::log(__METHOD__);

        self::init();
        if (!self::$lscache_enabled) {
            return;
        }

        if (self::$login_user_cachable) {
            self::$lscInstance->purgeAllPrivate();
        }

        self::log("UserSaveSettings", $user, $user->getUserPage(), self::$lscInstance->getLogBuffer());
    }

    /**
     *
     * Check current session was logged in by a user or not
     *
     * @since   0.1
     */
    public static function isUserLogin()
    {
        self::log(__METHOD__);
        if (isset(self::$userLogedin)) {
            return self::$userLogedin;
        }

        if (!isset($_COOKIE[LiteSpeedCacheCore::_private_cookie])) {
            return false;
        }

        # start checking session expired but cookie still exists
        if (version_compare(phpversion(), '5.4.0', '<')) {
            if (session_id() == '') {
                session_start();
            }
        } else if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION["_lsc_user"])) {
            self::$lscInstance->checkPrivateCookie(false);
            return false;
        }

        return true;
    }

    /**
     *
     * This function is to be called by System updater to create LiteSpeed_Settings table
     *
     * @since   1.0.0
     */
    public static function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater)
    {
        $dir = dirname(__FILE__);
        global $wgDBtype;
        if ($wgDBtype == "sqllite") {
            $updater->addExtensionTable(self::_db_setting, "$dir/sqllite.sql", true);
        } else {
            $updater->addExtensionTable(self::_db_setting, "$dir/mysql.sql", true);
        }
    }

    /**
     *
     * Read LiteSpeedCache setting from DB
     *
     * @since   1.0.0
     */
    public static function getLiteSpeedSettig()
    {

        self::log(__METHOD__);
        $db = wfGetDB(DB_SLAVE);

        if (!$db->tableExists(self::_db_setting)) {
            return null;
        }

        $result = $db->select(self::_db_setting, '*');

        if (!$result || !$result->numRows()) {
            return null;
        } else {
            $config = array();
            foreach ($result as $row) {
                $config[$row->lskey] = $row->lsvalue;
            }
            return $config;
        }
    }

    /**
     *
     * Save LiteSpeedCache setting to DB
     *
     * @since   1.0.0
     */
    public static function saveLiteSpeedSetting($config, $user = null, $target = null)
    {
        self::log(__METHOD__);
        self::init();

        $db = wfGetDB(DB_MASTER);

        if ($config["lscache_enabled"] != self::$lscache_enabled) {
            self::$lscInstance->purgeAllPublic();
            self::log("changed public cache option", $user, $target, self::$lscInstance->getLogBuffer());
        }

        if ($config["login_user_cachable"] != self::$login_user_cachable) {
            self::$lscInstance->purgeAllPrivate();
            self::log("changed private cache option", $user, $target, self::$lscInstance->getLogBuffer());
        }

        foreach ($config as $lskey => $lsval) {
            $conds = Array('lskey' => $lskey);
            $values = Array('lsvalue' => $lsval);
            $db->update(self::_db_setting, $values, $conds);
        }

        self::log("SaveLiteSpeedSetting", $user, $target, var_export($config, true));
    }

    /**
     *
     * Create LiteSpeedCache Table and insert initial records.
     *
     * @since   1.0.0
     */
    private static function initLiteSpeedSetting()
    {
        self::log(__METHOD__);
        $db = wfGetDB(DB_MASTER);

        $config = array(
            'lscache_enabled' => false,
            'login_user_cachable' => false,
            'logging_enabled' => false,
            'public_cache_timeout' => '864020',
            'private_cache_timeout' => '7200');

        if (!$db->tableExists(self::_db_setting)) {
            self::log("Table litespeed_settings not exists");
            global $wgDBtype;
            if ($wgDBtype == "sqllite") {
                $sql = file_get_contents('extensions/LiteSpeedCache/sqllite.sql');
                $db->query($sql);
            } else {
                $sql = file_get_contents('extensions/LiteSpeedCache/mysql.sql');
                $db->query($sql);
            }
        }

        foreach ($config as $lskey => $lsval) {
            $fields = Array('lskey' => $lskey, 'lsvalue' => $lsval);
            $db->insert(self::_db_setting, $fields);
        }
        $db->commit();
    }

    /**
     *
     * Restore LiteSpeedCache setting to default value.
     *
     * @since   1.0.0
     */
    public static function restoreLiteSpeedSetting($user = null, $target = null)
    {
        self::log(__METHOD__);
        self::init();
        $db = wfGetDB(DB_MASTER);
        $db->delete(self::_db_setting, '*');
        self::initLiteSpeedSetting();
        self::log("RestoreLiteSpeedSetting", $user, $target, "LiteSpeed Default Setting Restored.");
    }

    /**
     *
     * purge all cache for MediaWiki site.
     *
     * @since   1.0.0
     */
    public static function purgeAll($user = null, $target = null)
    {
        self::log(__METHOD__);
        self::init();
        self::$lscInstance->purgeAllPublic();
        self::log("PurgeAllCache", $user, $target, self::$lscInstance->getLogBuffer());
    }

    /**
     *
     * Clear all LiteSpeedCache logging.
     *
     * @since   1.0.0
     */
    public static function clearLiteSpeedLogging()
    {
        self::log(__METHOD__);
        $db = wfGetDB(DB_MASTER);
        $db->delete('logging', ['log_type' => 'litespeedcache']);
    }

    /**
     *
     * if logging enabled then do system log, otherwise only show debug informations.
     *
     * @since   1.0.0
     */
    public static function log($action, $user = null, $target = null, $comment = null)
    {
        if ($action == null) {
            return;
        }

        if (self::$debugEnabled) {
            #wfDebug($action);
            self::simpleDebug($action);
        }

        if (!self::$logging_enabled) {
            return;
        }

        if ($user == null) {
            return;
        }

        if ($target == null) {
            return;
        }

        if ($comment == null) {
            return;
        }

        self::doLog($action, $user, $target, $comment);
    }

    /**
     *
     * A simple debug function take place wfDebug() function when needed .
     * This function need to be able to access and write file to root directory of WIKI web source code.
     *
     * @since   1.0.0
     */
    private static function simpleDebug($action, $user = null, $target = null, $comment = null)
    {
        list( $usec, $sec ) = explode(' ', microtime());

        if (!defined("LOG_INIT")) {
            define("LOG_INIT", true);
            date_default_timezone_set("America/New_York");
            file_put_contents(self::$debugFile, "\n\n" . date('m/d/y H:i:s') . substr($usec, 1, 4), FILE_APPEND);
        }
        file_put_contents(self::$debugFile, date('m/d/y H:i:s') . substr($usec, 1, 4) . "\t" . $action . "\n", FILE_APPEND);
    }

    /**
     *
     * Use this method to log important message no matter logging enabled or not.
     *
     * @since   1.0.0
     */
    private static function doLog($action, $user, $target, $comment = null)
    {

        $logEntry = new ManualLogEntry('litespeedcache', $action);
        $logEntry->setPerformer($user);
        $logEntry->setTarget($target);
        if ($comment != null) {
            $logEntry->setComment($comment);
        }
        $logEntry->insert();
    }

    /**
     *
     * Check if current request is a post back request, then page will not be cached
     *
     * @since   1.0.0
     */
    private static function isPostBack()
    {
        self::log(__METHOD__);
        if (count($_GET) > 1) {
            return true;
        }
        if (count($_POST) > 0) {
            return true;
        }
        return false;
    }

}
