<?php
# vim: set sw=4 sts=4 et tw=0 :

require 'jsmin-1.1.1.php';
clearstatcache();
$cache   = 'playlists/min.js';
$cachemt = 0;
$jsfiles = array(
                'json.js',
                'prototype.js',
                'prototype-plus.js',
                //'scriptaculous.js',
                'yahoo.color.js',
                'colorPicker.js',
                'graph.js'
            );
if ( file_exists($cache) ){
    $cachemt = filemtime($cache);
}
$replaceCache = 0;
foreach ($jsfiles as $file){
    if ( $cachemt < filemtime("js/${file}") ){
        $replaceCache = 1;
        break;
    }
}
$jsmin = '';
if ( $replaceCache == 1 ){
    foreach ($jsfiles as $file){
        $jsmin .= JSMin::minify(file_get_contents("js/${file}"));
        //echo JSMin::minify(file_get_contents("js/${file}"));
        //var_dump( $jsmin);
    }
    $fp = fopen("$cache","w");
    fwrite($fp,$jsmin);
    fclose($fp);
}

header("content-type: application/x-javascript");

ob_start ("ob_gzhandler");

include($cache);

?>
