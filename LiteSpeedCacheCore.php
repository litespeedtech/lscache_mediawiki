<?php

/**
 * General Core function of communicating with LSWS for LSCache operations
 * 
 *
 * @since      1.0.0
 * @author     LiteSpeed Technologies <info@litespeedtech.com>
 * @copyright  Copyright (c) 2017-2018 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 * @license    https://opensource.org/licenses/GPL-3.0
 */
class LiteSpeedCacheCore
{

    const _public_cache_control = 'X-LiteSpeed-Cache-Control:public,max-age=';
    const _private_cache_control = 'X-LiteSpeed-Cache-Control:private,max-age=';
    const _Cache_Purge = 'X-LiteSpeed-Purge:';
    const _Cache_Tag = 'X-LiteSpeed-Tag:';
    const _vary_cookie = '_lscache_vary';
    const _private_cookie = 'lsc_private';

    private static $singleton;
    private $site_only_tag = "";
    private $public_cache_timeout = '1000000';
    private $private_cache_timeout = '5000';
    private $logbuffer = "";

    /**
     *
     *  get a singleton instance for this class
     *
     * @since   1.0.0
     */
    public static function getInstance()
    {
        if (!isset(self::$singleton)) {
            self::$singleton = new LiteSpeedCacheCore();
        }
        return self::$singleton;
    }

    /**
     *
     *  load class configuration from $config param
     *
     * @since   1.0.0
     */
    public function config($config)
    {
        $this->public_cache_timeout = $config['public_cache_timeout'];
        $this->private_cache_timeout = $config['private_cache_timeout'];
    }

    /**
     *
     *  set the specified tag for this site
     *
     * @since   1.0.0
     */
    public function setSiteOnlyTag($tag)
    {
        $this->site_only_tag = $tag;
    }

    /**
     *
     * put tag into Array in the format for this site only.
     *
     * @since   1.0.0
     */
    private function tagsForSite(Array &$tagArray, $rawTags, $prefix = "")
    {
        if (!isset($rawTags)) {
            return "";
        }

        if ($rawTags == "") {
            return "";
        }

        $tags = explode(",", $rawTags);
        
        foreach ($tags as $tag) {
            $tagStr = $prefix . $this->site_only_tag . $tag;
            if(!in_array($tagStr, $tagArray, false)){
                array_push($tagArray, $tagStr);
            }
        }
    }

    
    /**
     *
     * put tag in Array together to make an head command .
     *
     * @since   1.0.0
     */
    private function tagCommand($start, Array $tagArray){
        $cmd = $start;
        
        foreach ($tagArray as $tag) {
            $cmd .= $tag . ",";
        }
        return substr($cmd,0,-1);
    }
    
    /**
     *
     *  purge public cache with specified tags for this site.
     *
     * @since   1.0.0
     */
    public function purgePublic($publicTags)
    {
        if ((!isset($publicTags)) || ($publicTags == "")) {
            return;
        }
        
        $siteTags = Array();
        $this->tagsForSite($siteTags, $publicTags);
        $LShead = $this->tagCommand(self::_Cache_Purge . 'public,' ,  $siteTags) ;
        $this->liteSpeedHeader($LShead);
    }

    /**
     *
     *  purge private cache with specified tags for this site.
     *
     * @since   1.0.0
     */
    public function purgePrivate($privateTags)
    {
        if ((!isset($privateTags)) || ($privateTags == "")) {
            return;
        }

        $siteTags = Array();
        $this->tagsForSite($siteTags, $privateTags);
        $LShead = $this->tagCommand( self::_Cache_Purge . 'private,' ,  $siteTags);
        $this->liteSpeedHeader($LShead);
    }

    /**
     *
     *  purge all public cache of this site
     *
     * @since   1.0.0
     */
    public function purgeAllPublic()
    {
        if ($this->site_only_tag == "") {
            $LShead = self::_Cache_Purge . 'public,*';
            $this->liteSpeedHeader($LShead);
            return;
        }

        $LShead = self::_Cache_Purge . 'public,' . $this->site_only_tag;
        $this->liteSpeedHeader($LShead);
    }


    /**
     *
     *  purge all private cache of this session
     *
     * @since   0.1
     */
    public function purgeAllPrivate()
    {
        if ($this->site_only_tag == "") {
            $LShead = self::_Cache_Purge . 'private,*';
            $this->liteSpeedHeader($LShead);
            return;
        }
        $LShead = self::_Cache_Purge . 'private,' . $this->site_only_tag;
        $this->liteSpeedHeader($LShead);
    }

    /**
     *
     * Cache this page for public use if not cached before
     *
     * @since   1.0.0
     * @param string $tags
     */
    public function cachePublic($publicTags)
    {
        if (($publicTags == null) || !isset($publicTags)) {
            return;
        }

        $LShead = self::_public_cache_control . $this->public_cache_timeout;
        self::liteSpeedHeader($LShead);

        $siteTags = Array();
        if ($this->site_only_tag != "") {
            array_push($siteTags, $this->site_only_tag);
        }
        $this->tagsForSite($siteTags, $publicTags);
        $LShead = $this->tagCommand( self::_Cache_Tag ,  $siteTags);

        self::liteSpeedHeader($LShead);
    }

    /**
     *
     * Cache this page for private session if not cached before
     *
     * @since   0.1
     */
    public function cachePrivate($publicTags, $privateTags = "")
    {
        if (($privateTags == "") || !isset($privateTags)) {

            if (($publicTags == "") || !isset($publicTags) ) {
                return;
            }
        }

        $LShead = self::_private_cache_control . $this->private_cache_timeout;
        self::liteSpeedHeader($LShead);

        $siteTags = Array();
        $this->tagsForSite($siteTags, $publicTags, "public:");

        if(($publicTags!="")&&($this->site_only_tag != "")){
            array_push($siteTags, "public:" . $this->site_only_tag);
        }

        if($privateTags!=""){
            $this->tagsForSite($siteTags, $privateTags);

            if($this->site_only_tag!=""){
                array_push($siteTags,  $this->site_only_tag);
            }
        }
        $LShead = $this->tagCommand( self::_Cache_Tag ,  $siteTags);
        self::liteSpeedHeader($LShead);
    }

    /**
     *
     * Cache this page for private use if not cached before
     *
     * @since   1.0.0
     */
    private function liteSpeedHeader($LShead)
    {
        $this->logbuffer .= $LShead . "\t";
        header($LShead);
    }

    /**
     *
     *  set or delete private cookie.
     *
     * @since   1.0.0
     */
    public function checkPrivateCookie($cachePrivate = true)
    {
        if ($cachePrivate) {
            if (!isset($_COOKIE[self::_private_cookie])) {
                setcookie(self::_private_cookie, rand());
            }
        } else {
            if (isset($_COOKIE[self::_private_cookie])) {
                setcookie(self::_private_cookie, "", time() - 3600);
            }
        }
    }

    /**
     *
     *  set or delete cache vary cookie
     *
     * @since   1.0.0
     */
    public function vary($value = "")
    {
        if ($value == "") {
            setcookie(self::_vary_cookie, "", time() - 3600);
            return;
        }
        setcookie(self::_vary_cookie, $value);
    }

    /**
     *
     *  get LiteSpeedCache special head log
     *
     * @since   1.0.0
     */
    public function getLogBuffer()
    {
        $retVal = $this->logbuffer;
        $this->logbuffer = '';
        return $retVal;
    }

}
