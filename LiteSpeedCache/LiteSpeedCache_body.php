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
    private static $debugEnabled = true;
    private static $settingLoad = false;

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
        self::$lscInstance = LiteSpeedCacheCore::getInstance();
        global $wgExtensionDirectory;
        self::$lscInstance->setSiteOnlyTag(substr(md5($wgExtensionDirectory), 0, 4));
    }

    /**
     *
     * load LiteSpeedCache setting from DB
     *
     * @since 1.0.0
     */
    private static function loadSetting($reload = false)
    {
        self::log(__METHOD__);

        if (self::$settingLoad) {
            if (!$reload) {
                return;
            }
        }
        self::$settingLoad = true;

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

        self::loadSetting();
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

        self::loadSetting();
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

        self::loadSetting();
        if (!self::$lscache_enabled) {
            return;
        }

        $tag = $article->getTitle()->mUrlform;

        if (self::isUserLogin()) {
            if (self::$login_user_cachable) {
                self::$lscInstance->checkPrivateCookie();
                self::$lscInstance->cachePrivate($tag);
                self::$lscInstance->vary('Login');
            }
        } else {
            self::$lscInstance->cachePublic($tag);
            self::$lscInstance->vary('Logout');
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
        self::$lscInstance->checkPrivateCookie();
        self::$lscInstance->vary('Login');
        self::$userLogedin = true;

        self::loadSetting();
        if (!self::$lscache_enabled) {
            return;
        }

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

        self::$lscInstance->vary('Logout');
        self::$userLogedin = false;
        
        self::loadSetting();
        if (!self::$lscache_enabled) {
            return;
        }

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

        self::loadSetting();
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
    private static function isUserLogin()
    {
        if (isset(self::$userLogedin)) {
            return self::$userLogedin;
        }

        global $wgUser;
        
        if($wgUser==null){
            return false;
        }
        
        if(!$wgUser instanceof User){
            return false;
        }

        return $wgUser->isLoggedIn();
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
        self::loadSetting();

        $db = wfGetDB(DB_MASTER);

        if (($config["lscache_enabled"] != self::$lscache_enabled) || ($config["login_user_cachable"] != self::$login_user_cachable)) {
            self::$lscInstance->purgeAllPublic();
            self::log("changed cache option", $user, $target, self::$lscInstance->getLogBuffer());
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
            global $wgDBtype, $wgDBprefix;
            $sql="";
            if ($wgDBtype == "sqllite") {
                $sql = file_get_contents('extensions/LiteSpeedCache/sqllite.sql');
            } else {
                $sql = file_get_contents('extensions/LiteSpeedCache/mysql.sql');
            }
            if($wgDBprefix!=""){
                $sql = str_replace(self::_db_setting, $wgDBprefix.self::_db_setting, $sql);
            }
            $db->query($sql);
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
        $db = wfGetDB(DB_MASTER);
        $db->delete(self::_db_setting, '*');
        self::initLiteSpeedSetting();
        self::purgeAll();
        self::log("RestoreLiteSpeedSetting", $user, $target, self::$lscInstance->getLogBuffer());
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
    private static function log($action, $user = null, $target = null, $comment = "")
    {
        if ($action == null) {
            return;
        }

        if (self::$debugEnabled) {
            #wfDebug($action);
            self::simpleDebug($action . $comment);
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

        if ($comment == "") {
            return;
        }

        self::doLog($action, $user, $target, $comment);
    }

    /**
     *
     * A simple debug function take place wfDebug() function when needed .
     * This function need rights to write file to $debugFile in root directory of WIKI source code.
     *
     * @since   1.0.0
     */
    private static function simpleDebug($action, $user = null, $target = null, $comment = null)
    {
        $debugFile = "lscache.log";
        
        date_default_timezone_set("America/New_York");
        list( $usec, $sec ) = explode(' ', microtime());

        if (!defined("LOG_INIT")) {
            define("LOG_INIT", true);
            file_put_contents($debugFile, "\n\n" . date('m/d/y H:i:s') . substr($usec, 1, 4), FILE_APPEND);
        }
        file_put_contents($debugFile, date('m/d/y H:i:s') . substr($usec, 1, 4) . "\t" . $action . "\n", FILE_APPEND);
    }

    /**
     *
     * Use this method to log important message no matter logging enabled or not.
     *
     * @since   1.0.0
     */
    private static function doLog($action, $user, $target, $comment = "")
    {

        $logEntry = new ManualLogEntry('litespeedcache', $action);
        $logEntry->setPerformer($user);
        $logEntry->setTarget($target);
        $logEntry->setComment($comment);
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
