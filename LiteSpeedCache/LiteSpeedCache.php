<?php

/*
 *  Traditional register file for LiteSpeedCache Extension
 *
 *  @since      1.0.0
 *  @author     LiteSpeed Technologies <info@litespeedtech.com>
 *  @copyright  Copyright (c) 2017-2018 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 *  @license    https://opensource.org/licenses/GPL-3.0
 */

if (function_exists('wfLoadExtension')) {
    wfLoadExtension('LiteSpeedCache');
    // Keep i18n globals so mergeMessageFileList.php doesn't break
    $wgMessagesDirs['LiteSpeedCache'] = __DIR__ . '/i18n';
    /* wfWarn(
      'Deprecated PHP entry point used for ImageMap extension. Please use wfLoadExtension instead, ' .
      'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
      ); */
    return true;
} else {
    die('This version of the LiteSpeedCache extension requires MediaWiki 1.25+');
}
