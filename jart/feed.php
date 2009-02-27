<?php
# vim: set sw=4 sts=4 et tw=0 :

//header("Content-Type: text/plain");
error_reporting(E_ERROR);
$prot = "http://";
$prot = "https://";

$d = 'playlists';

# I know I should just include some lib, but I'm not ready to
# restructure like that yet.

function ls_dir($dir){
    global $rrddir, $webdir;
    if ( ! preg_match("`^$rrddir.*`", $dir) && ! preg_match("`^$webdir`", $dir)){
        return "Nope.\n";
    }
    if ( preg_match('`\.\.`', $dir) ){
        return "Uh, no.\n";
    }
    if ( is_dir($dir) && is_readable($dir) && is_executable($dir) && $dir != 'lost+found' ){
        $dh = opendir($dir) or die('can not open dir, pal');
        while (($file = readdir($dh)) != false) {
            if ( $file != '.' && $file != '..' && $file{0} != '.' && $file != 'lost+found' ){
                if ( is_dir($dir.'/'.$file) ){
                    $nodes['dirs'][] = $dir.'/'.$file;
                }else{
                    if ( $file{0} != '.'){
                        $nodes['files'][] = $dir.'/'.$file;
                    }
                }
            }
        }
        // if the user has a map (dsfinder.php) honor it
        if ( is_array($nodes['files']) ){
            natsort($nodes['files']);
        }
        if ( is_array($nodes['dirs']) ){
            natsort($nodes['dirs']);
        }
        if ( is_array($nodes['dirs']) ){
            $nodes['dirs'] = array_values($nodes['dirs']);
        }
        if ( is_array($nodes['files']) ){
            $nodes['files'] = array_values($nodes['files']);
        }
        return $nodes;
    }
}

function findMinusName($path,$out){
    if ( $out == null ){
        $out = array();
    }
    $nodes = ls_dir($path);
    // for each dir in the results, ls_dir
    foreach ($nodes['dirs'] as $v) {
        $out = findMinusName($v,$out);
    }
    // add each array of files to a master array that gets returned
    if ( isset($nodes['files']) ){
        $out[] = $nodes['files'];
    }
    return $out;
}
$files = findMinusName($d,null);
foreach ($files as $filearray){
    foreach($filearray as $file){
        if ( preg_match('/\.pspl$/',$file) ){
            $stat = stat($file);
            $pl   = preg_replace("/$d\//",'',$file);
            $o[$pl] = $stat[9];
        }
    }
}
arsort($o);
$latest = array_slice($o,0,10,true);
$mydir = preg_replace('/(.*)\/.*/','$1/',$_SERVER['PHP_SELF']);
$self = $prot.$_SERVER['HTTP_HOST'].$mydir;
//var_dump( $latest );
try{
    include('feed/FeedGenerator.php');
    $feeds = new FeedGenerator;
    $feeds->setGenerator(new AtomGenerator);
    $feeds->setAuthor('Jart');
    $feeds->setTitle('New and Updated Jart Playlists');
    $feeds->setChanneLink($self);
    $feeds->setLink($self);
    $feeds->setDescription('New and Updated Jart Playlists');
    $feeds->setID($self);
    foreach($latest as $playlist => $pldate){
        $shortpl = preg_replace('/.pspl$/','',$playlist);
        $name    = preg_replace('/.*\/(.*)/','$1',$shortpl);
        $author  = preg_replace('|([^/]*)/.*|','$1',$playlist);
        $item    = new FeedItem("$self?pl=$shortpl",$name,"$self?pl=$shortpl","<a href=\"$self?pl=$shortpl\">$name</a>",date3339($pldate));
        $item->author = $author;
        $feeds->addItem($item);
    }
    $feeds->display();
}
catch(FeedGeneratorException $e){
    echo 'Error: '.$e->getMessage();
}
?>
