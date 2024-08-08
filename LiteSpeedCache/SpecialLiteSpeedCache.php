<?php

/**
 * 
 * LiteSpeedCache Configuration Page, can be viewed using: Special:LiteSpeedCache
 *
 * @since      0.1
 * @author     LiteSpeed Technologies <info@litespeedtech.com>
 * @copyright  Copyright (c) 2016-2017 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 * @license    https://opensource.org/licenses/GPL-3.0
 */
use MediaWiki\MediaWikiServices;

class SpecialLiteSpeedCache extends SpecialPage
{
    private $actionResult="";
    
    public function __construct()
    {
        parent::__construct('LiteSpeedCache', '', true);
    }

    /**
     * Main execution function
     * @since    0.1
     * @param    $par array Parameters passed to the page
     */
    public function execute($par)
    {
        $this->setHeaders();
        $output = $this->getOutput();
        $output->addModuleStyles('ext.liteSpeedCache');
        $output->setPageTitle(' '. $this->msg('litespeedcache_title'));
        $output->addWikiMsg('litespeedcache_desc');

        if (!$this->isSysAdmin()) {
            $this->showView();
        } else if ($par == 'edit') {
             if (count($_POST) == 0) {
                $this->showForm();
            } else if (isset($_POST['save'])) {
                $request = $this->getRequest();
                $config = array(
                    'lscache_enabled' => ($request->getText('lscacheEnabled') == "on"),
                    'login_user_cachable' => ($request->getText('loginUserCachable') == "on"),
                    'logging_enabled' => ($request->getText('loggingEnabled') == "on"),
                    'public_cache_timeout' => $request->getText('publicCacheTimeout'),
                    'private_cache_timeout' => $request->getText('privateCacheTimeout'),
                );
                LiteSpeedCache::saveLiteSpeedSetting($config, $this->getUser(), $this->getPageTitle());
                $this->actionResult = $this->msg('litespeedcache_saved');;
                $this->showView();
            } else if (isset($_POST['purge'])) {
                LiteSpeedCache::purgeAll( $this->getUser(),  $this->getPageTitle()); 
                $this->actionResult = $this->msg('litespeedcache_purged');
                $this->showView();
            } else if (isset($_POST['restore'])) {
                LiteSpeedCache::restoreLiteSpeedSetting($this->getUser(), $this->getPageTitle());
                $this->showForm();
            } else if (isset($_POST['clear'])) {
                LiteSpeedCache::clearLiteSpeedLogging();
                $this->actionResult = $this->msg('litespeedcache_cleared');
                $this->showView();
            }
        } else {
            $this->showView(false);
        }
        $output->addHTML('<p><a class="lscache_logo" href="https://www.litespeedtech.com"></a>&nbsp;Powered by <a href="https://www.litespeedtech.com/solutions">LiteSpeed LSCache</a> solution.</p>');

    }

    /**
     * Show a configuration Form for administrators.
     * @since    0.1
     */
    private function showForm()
    {
        $this->setHeaders();
        $output = $this->getOutput();
        $config = LiteSpeedCache::getLiteSpeedSettig();

        $output->addHTML('<form action="" method="post"><fieldset><Legend>LiteSpeedCache Settings</Legend>');
        $output->addHtml('<table id="mw-htmlform-info">');
        $html = '<tr class="mw-htmlform-field-HTMLInfoField"><td class="mw-label"><label for="lscacheEnabled">' . $this->msg('litespeedcache_lscache_enabled') . '</label></td>';
        $output->addHTML($html);
        $html = '<td class="mw-input" ><input type="checkbox" id="lscacheEnabled" name="lscacheEnabled" ' . $this->check($config['lscache_enabled']) . '></td></tr>';
        $output->addHTML($html);

        $html = '<tr class="mw-htmlform-field-HTMLInfoField"><td class="mw-label"><label for="publicCacheTimeout">' . $this->msg('litespeedcache_public_cache_timeout') . '</label></td>';
        $output->addHTML($html);
        $html = '<td class="mw-input"><input type="text" id="publicCacheTimeout" name="publicCacheTimeout" value="' . $config['public_cache_timeout'] . '"></td></tr>';
        $output->addHTML($html);

        $html = '<tr class="mw-htmlform-field-HTMLInfoField"><td class="mw-label"><label for="loginUserCachable">&#42&nbsp;' . $this->msg('litespeedcache_login_user_cachable') . '</label></td>';
        $output->addHTML($html);
        $html = '<td class="mw-input"><input type="checkbox" id="loginUserCachable" name="loginUserCachable" ' . $this->check($config['login_user_cachable']) . '></td></tr>';
        $output->addHTML($html);

        $html = '<tr class="mw-htmlform-field-HTMLInfoField"><td class="mw-label"><label for="privateCacheTimeout">' . $this->msg('litespeedcache_private_cache_timeout') . '</label></td>';
        $output->addHTML($html);
        $html = '<td class="mw-input"><input type="text" id="privateCacheTimeout" name="privateCacheTimeout" value="' . $config['private_cache_timeout'] . '"></td></tr>';
        $output->addHTML($html);

        $html = '<tr class="mw-htmlform-field-HTMLInfoField"><td class="mw-label"><label for="loggingEnabled">' . $this->msg('litespeedcache_logging_enabled') . '</label></td>';
        $output->addHTML($html);
        $html = '<td class="mw-input"><input type="checkbox" id="loggingEnabled" name="loggingEnabled" ' . $this->check($config['logging_enabled']) . '>&nbsp;'.  $this->msg('litespeedcache_clearlabel') . '</td></tr></table>';
        $output->addHTML($html);

        $html = '<p><hr style="margin-top: 10px;margin-bottom: 10px;"/>&nbsp;&nbsp;<button type = "submit" name="save">' . $this->msg('litespeedcache_save') . '</button>&nbsp;<button type = "submit" name="restore">' . $this->msg('litespeedcache_restore') . '</button></p></fieldset>';
        $output->addHTML($html);
        $html = '<p>&nbsp;&nbsp;&nbsp;&nbsp;<button type = "submit" name="purge">' . $this->msg('litespeedcache_purge') . '</button>&nbsp;&nbsp;' . $this->msg('litespeedcache_purgecomment') . '</p> ';
        $output->addHTML($html);
        $html = '<p>&nbsp;&nbsp;&nbsp;&nbsp;<button type = "submit" name="clear">' . $this->msg('litespeedcache_clear') . '</button>&nbsp;&nbsp;' . $this->msg('litespeedcache_clearcomment') . '</p> ';
        $output->addHTML($html);
        $output->addHTML('</form>');
        $output->addHTML('<p><br/>&nbsp;&nbsp;&#42&nbsp;' . $this->msg('litespeedcache_beta') . '</p>');
        $output->addHTML('<p style="color:#777;">&nbsp;&nbsp;&nbsp;&nbsp;Copyright <i class="uk-icon-copyright"></i>2013-2018 LiteSpeed Technologies Inc. All Rights Reserved.</p>');
    }

    /**
     * Show LiteSpeedCache configuration for all users.
     * 
     * @since    0.1
     */
    private function showView($fromEdit=true)
    {
        $config = LiteSpeedCache::getLiteSpeedSettig();
        $output = $this->getOutput();
        if($this->actionResult!=""){
            $output->addHTML('<p><font color=red>'. $this->actionResult . '</font></p>');
        }
        $wikitext = $this->msg('litespeedcache_lscache_enabled') . $this->enabled($config['lscache_enabled']);
        $this->addWikiText($output,'<br/>' . $wikitext);
        $wikitext = $this->msg('litespeedcache_public_cache_timeout') . $config['public_cache_timeout'];
        $this->addWikiText($output,$wikitext);
        $wikitext = $this->msg('litespeedcache_login_user_cachable') . $this->enabled($config['login_user_cachable']);
        $this->addWikiText($output,$wikitext);
        $wikitext = $this->msg('litespeedcache_private_cache_timeout') . $config['private_cache_timeout'];
        $this->addWikiText($output,$wikitext);
        $wikitext = $this->msg('litespeedcache_logging_enabled') . $this->enabled($config['logging_enabled']);
        $this->addWikiText($output,$wikitext);
        if ($this->isSysAdmin()) {
            if($fromEdit){
                $output->addHTML('<a href="'. trim($_SERVER['REQUEST_URI']) .'">' . $this->msg( 'litespeedcache_change_setting') . '</a>');
                $output->addHTML('&nbsp;&nbsp;<a href="'. $this->getLogExtension() . '" target="_blank">' . $this->msg('litespeedcache_show_logs') . '</a>');
            }
            else{
                $output->addHTML('<a href="'. $this->getExtensionRoot() . '/edit">' . $this->msg('litespeedcache_change_setting') . '</a>');
                $output->addHTML('&nbsp;&nbsp;<a href="'. $this->getLogExtension() . '" target="_blank">' . $this->msg('litespeedcache_show_logs') . '</a>');
            }
        }
    }

    /**
     * Check if this page was viewed by an administrator.
     * 
     * @since    0.1
     */
    private function isSysAdmin()
    {
        if (!$this->getUser()) {
            return false;
        }
        $groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserGroups($user);
        return in_array("sysop", $groups, false);
    }

    
    private function check($val)
    {
        if ($val) {
            return "checked";
        } else {
            return "";
        }
    }

    
    private function enabled($val)
    {
        if ($val) {
            return $this->msg('litespeedcache_enabled');
        } else {
            return $this->msg('litespeedcache_disabled');
        }
    }

    
    protected function getGroupName()
    {
        return 'wiki';
    }


    protected function addWikiText($output,$text)
    {
        if ( method_exists( $output, 'addWikiTextAsInterface' ) ) {
                // MW 1.32+
                $output->addWikiTextAsInterface( $text );
        } else {
                $output->addWikiText( $text );
        }
    }
    
    protected  function getExtensionRoot()
    {
        $url = trim($_SERVER['REQUEST_URI']);
        if(strpos($url, 'index.php')===false){
            return './Special:LiteSpeedCache';
        } else {
            return $url;
        }
    }
    
    protected  function getLogExtension()
    {
        $url = trim($_SERVER['REQUEST_URI']);
        if(strpos($url, 'index.php')===false){
            return './Special:Log/litespeedcache';
        } else if(str_ends_with($url, '/edit')){
            $url = substr($url, 0, -5);
            return str_replace('LiteSpeedCache','Log',$url).'/litespeedcache';
        } else {
            return str_replace('LiteSpeedCache','Log',$url).'/litespeedcache';
        }
    }
    
}
