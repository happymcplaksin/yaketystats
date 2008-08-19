<?php

require("Sajax.php");
$rrddir = '/rrd';
$webdir = dirname(__FILE__);

if ( isset ($_REQUEST['rra']) ) {
  $rra = $_REQUEST['rra'];
} else {
  $rra = "MAX";
}

function ls_dir($dir){
    global $rrddir, $webdir;
    if ( ! preg_match("`^$rrddir.*`", $dir) && ! preg_match("`^$webdir`", $dir)){
        return "Nope.\n";
    }
    if ( preg_match('`\.\.`', $dir) ){
        return "Uh, no.\n";
    }
    if ( is_dir($dir) && is_readable($dir) && is_executable($dir) ){
        $dh = opendir($dir) or die('can not open dir, pal');
        while (($file = readdir($dh)) != false) {
            if ( $file != '.' && $file != '..' ){
                if ( is_dir($dir.'/'.$file) ){
                    $nodes['dirs'][] = $dir.'/'.$file;
                }else{
                    if ( $file{0} != '.'){
                        $nodes['files'][] = $dir.'/'.$file;
                    }
                }
            }
        }
        if ( is_array($nodes['files']) ){
            natsort($nodes['files']);
        }
        if ( is_array($nodes['dirs']) ){
            natsort($nodes['dirs']);
        }
        return $nodes;
    }
}

function notempty($s){
    return ( !empty($s) );
}

function cleanempty($a){
    if ( ! is_array($a) ){
        return FALSE;
    }
    $a=array_filter($a,'notempty');
    $b=array_values($a);
    return $b;
}

function graphone ($files, $rra, $start, $end, $graphname,$currentgraph) {
    $str = $graphname.'^~~|~~^';

    $colors[] = 'ff0000';
    $colors[] = '00ff00';
    $colors[] = '0000ff';
    $colors[] = '00ffff';
    $colors[] = 'CC6600';
    $colors[] = 'ff00ff';
    $colors[] = '6600CC';
    $colors[] = '0f0f0f';
    $colors[] = 'aabb00';
    $colors[] = 'FF8000';
    $colors[] = '7A7AFF';
    $colors[] = 'f5b800';
    $colors[] = 'D175D1';
    $colors[] = 'aabbcc';
    $colors[] = 'aaccbb';
    $colors[] = 'aaddee';
    $names[]  = 'one';
    $names[]  = 'two';
    $names[]  = 'three';
    $names[]  = 'four';
    $names[]  = 'five';
    $names[]  = 'six';
    $names[]  = 'seven';
    $names[]  = 'eight';
    $names[]  = 'nine';
    $names[]  = 'ten';
    $names[]  = 'eleven';
    $names[]  = 'twelve';
    $names[]  = 'thirteen';
    $names[]  = 'fourteen';
    $names[]  = 'fifteen';
    $names[]  = 'sixteen';


    if ( $start == -1 ){
        unset($start);
    }
    if ( $end == -1 ){
        unset($end);
    }
    // extra bonus check for times
    if ( ! isset ($start) || ! isset ($end) ) {
        $opts = array( "--start", "-4d");
        // For putting the date in the graph (below)
        $end = time ();
        $start = $end - 86400 * 4;
    } else {
        $opts = array ( "-s $start", "-e $end");
    }
    foreach ($files as $k => $v) {
        if ( $v == "An error occured trying to deal with that path."){
            return "An error occured trying to deal with that path.";
        }
        $ev = escapeshellarg($v);
        $q = `/bin/readlink -f $ev`;
        $q = trim($q);
        //$label  = basename(preg_replace('#\.rrd$#','',$v));
        //$label  = preg_replace('`.*/rrd/`','',$v);
        $label  = preg_replace('`.*/rrd/([^.]*\.[^.]*)[^/]*(.*)\.rrd$`','$1$2',$v);
        $label  = preg_replace('`/`',' ',$label);
        $label  = trim($label);
        $title  = preg_replace('#\./rrd/[^/]*/([^/]*/[^\.]*).*#',"\$1",$v);
        $title  = preg_replace('#/[^/]*$#','',$title);
        $opts[] = '-t '.$title;
        # escape colons!
        $hack = preg_replace('#:#', '\:', $v);
        $opts[] = 'DEF:'.$names[$k]."=${hack}:yabba:$rra";
        $opts[] = 'LINE2:'.$names[$k].'#'.$colors[$k].":$label";

        $thislast = rrd_last($v);
        if ( $thislast > $last ) {
          $last = $thislast;
        }
        $graphelementsstr .= '<a href="#" onClick="setCurrentGraph(\''.$currentgraph.'\'); rmRrdFromGraph(\'~~|~~'.$v.'\'); return false;">Remove: '.$label.'</a><br />'."\n";
        $lhost = preg_replace('`.*/rrd/([^/]*)/.*`','$1',$q);
        exec("/usr/bin/sudo /bin/su - stats -c /usr/local/stats/bin/log2rrds $lhost 2>&1", $logrrdsout);
        $str .= join($logrrdsout, "\n");

    }
    //$nam = preg_replace('#[/\.]#','',$v);
    //$graphname = uniquename($v,$start,$end);
    $i++;
    if ( $i % 2 == 0 ){
        $str .= '<br class="clear" />'."\n\n";
    }
    $date_format = "m/d/y H:i:s";
    $opts[] = "COMMENT:\\n";
    $opts[] = "COMMENT:" .date ($date_format, $start) . " -- " . date ($date_format, $end);
    $opts[] = "COMMENT:\\n";
    $opts[] = "COMMENT:Most recent update:  " . date ($date_format, $last) . "            " . "(Created " . date ($date_format, time ()) . ")";

    $ret = rrd_graph("graphs/$graphname.png", $opts, count($opts));
    if ( !is_array($ret) ){
        $err = rrd_error();
        $str .= '<span class="error">'.$err.'</span><br />'."\n";
    }
    $dclass = "oncolor";
    if ( $currentgraph % 2 ){
        $dclass = "offcolor";
    }
    $imgsize = GetImageSize('graphs/'.$graphname.'.png');
    $str .= '<div class="'.$dclass.'" id="containerfor-'.$graphname.'" style="position: relative;">';
        $str .= '<span style="float:left"><a href="#" onClick="removeGraph(\''.$graphname.'\',\'containerfor-'.$graphname.'\'); return false;">Remove Graph</a></span> ';
        $str .= '<span class="graphtitle" id="titlefor-'.$graphname.'" style="background:transparent;" onmousedown="dragStart(event, \'containerfor-'.$graphname.'\')">Graph #';
        $str .= preg_replace('`[^-]*-(.*)`','$1',$currentgraph)."</span>\n<br style=\"clear:both\" />";
    $str .= '<img onClick="setCurrentGraph(\''.$currentgraph.'\');" src="graphs/'.$graphname.'.png?stupidcache='.mktime().'" id="'.$graphname.'" '.$imgsize[3].'/>';
    $str .= '<div id="uifor-'.$graphname.'">';
        $str .= '</div>'."\n";
        $str .= '<span style="float:left"><a href="#" onClick="makevis(\'graphelements-'.$graphname.'\'); return false;">Show GraphElements</a></span> ';
        $str .= "<br />\n";
        $str .= '<div id="graphelements-'.$graphname.'" class="graphui" style="display:none">';
        $str .= '<div id="timeui-'.$graphname.'" class="graphui">'."\n";
            $str .= '<form name="timeui" onSubmit="changeGraphTime(\''.$graphname.'\'); return false;" style="margin:0;" action="">';
            //FIX add make the right current graph thingy
            $str .= '<label for="start-'.$graphname.'">Start:</label>';
            $str .= '<input type="text" onSubmit="setCurrentGraph(\''.$currentgraph.'\'); changeGraphTime(\''.$graphname.'\'); return false;" id="start-'.$graphname.'" onBlur="setCurrentGraph(\''.$currentgraph.'\'); changeGraphTime(\''.$graphname.'\'); return false;" id="start-'.$graphname.'" name="start-'.$graphname.'" size="35" value="'.date('r',$start).'" />';
            $str .= "<br />\n";
            $str .= '<label for="end-'.$graphname.'">End:</label>';
            $str .= '<input type="text" onSubmit="setCurrentGraph(\''.$currentgraph.'\'); changeGraphTime(\''.$graphname.'\'); return false;"  onBlur="setCurrentGraph(\''.$currentgraph.'\'); changeGraphTime(\''.$graphname.'\'); return false;" id="end-'.$graphname.'" name="end-'.$graphname.'" size="35" value="'.date('r',$end).'" />';
            $str .= "</form>\n";
            $str .= "<br style=\"clear:both\" />\n";
            $str .= $graphelementsstr;
        $str .= "</div>\n";
    $str .= '</div>'."\n";
    $str .= '</div>'."\n";
    return $str;
}

function recurse($dir,$nodes){
    $files    = array();
    if ( is_dir($dir) && is_readable($dir) && is_executable($dir) ){
        $dh = opendir($dir) or die('can not open dir, pal');
        while (($file = readdir($dh)) != false) {
            if ( $file != '.' && $file != '..' ){
                if ( is_dir($dir.'/'.$file) ){
                    $nodes['dirs'][] = $dir.'/'.$file;
                    $nodes = recurse($dir.'/'.$file,$nodes,$rra,$start,$end);
                }else{
                    if ( $file{0} != '.'){
                        $files[] = $dir.'/'.$file;
                    }
                }
            }
        }
        $files = cleanempty($files);
        if ( sizeof($files) == 0 ){
            return $nodes;
        }
        natsort($files);
        $files = array_values($files);
        reset($files);
        $nodes['files'][] = $files;

        //var_dump ($files);
        //graphem ($files, $rra, $start, $end);
        //$out = join($files,"\n");
        //echo $out;

        return $nodes;
    }else{
        return "something really bad happened.";
        exit;
    }
}

function recurse4massAdd($dir,$nodes){
    global $rrddir, $webdir;
    if ( ! preg_match("`^$rrddir.*`", $dir) && ! preg_match("`^$webdir`", $dir)){
        return "Nope.\n";
    }
    if ( preg_match('`\.\.`', $dir) ){
        return "Uh, no.\n";
    }
    $files    = array();
    if ( is_dir($dir) && is_readable($dir) && is_executable($dir) ){
        $dh = opendir($dir) or die('can not open dir, pal');
        while (($file = readdir($dh)) != false) {
            if ( $file != '.' && $file != '..' ){
                if ( is_dir($dir.'/'.$file) ){
                    $nodes = recurse4massAdd($dir.'/'.$file,$nodes);
                }else{
                    if ( $file{0} != '.'){
                        $files[] = $dir.'/'.$file;
                    }
                }
            }
        }
        closedir($dh);
        $files = cleanempty($files);
        if ( empty($files) ){
            return $nodes;
        }
        natsort($files);
        while( count($files) > 15 ){
            $nf = array_splice($files,0,15);
            $pre .= '~~~|~~~'.'^^~~|~~'.join('^^~~|~~',$nf).'~~~|~~~';
        }
        $filestr = join('^^~~|~~',$files);
        $filestr = '^^~~|~~'.$filestr;
        $nodes['list'][$dir] = $pre.$filestr;

        return $nodes;
    }else{
        return "something really bad happened.";
        exit;
    }
}

function defile($file,$array){

    $file=$file;

    if ( empty($array) ){
        $array=array();
    }

    reset($array);

    if ( file_exists($file) && ! is_writable($file) ){
        printf("Unable to write to file [%s]. Check permissions.\n",$file);
        exit;
    }

    $fp=@fopen("$file","w");

    if ($fp==0){
        printf("Unable to write to file [%s]. Check permissions.\n",$file);
        exit;
    }

    if ( flock($fp,LOCK_EX) ){

        while( list(,$value)=each($array) ){
            if ( ! empty($value) && ! strpos($value,"\n") ){
                $value.="\n";
            }
            fwrite($fp,"$value");
        }
        flock($fp,LOCK_UN);

    } else {
        printf("Unable to lock %s. Aborting.<br>\n",$file);
        exit;
    }

    fclose($fp);
}

function showTreeChild($path,$oid,$initial,$plm){
    global $rrddir, $webdir;
    $nodes  = ls_dir($path);
    if ( ! is_array($nodes) ){
        return "An error occured trying to deal with that path.~~|~~$oid";
    }
    $out    = '';
    $prefix = '';

    //foreach ($nodes['dirs'] as $v) {
        //$host = preg_replace('#.*/([^/]*).*#',"\$1",$v);
        //$nodes['names'][] = $host;
    //}
        //act differently for the initial load of the page

    if ( $initial ){
        $prefix = "\t";
        $letter = 'A';
        $preout .= "<div id=\"alphanav\">\n";
        $preout .= "<a href=\"#\" onClick=\"showTreeChild('$webdir/playlists/pl','playlistspl',0,1); return false;\">[PL]</a> \n";
        $preout .= "<a href=\"#\" onClick=\"navVis('nav$letter'); return false;\" accesskey=\"a\">[$letter]</a> \n";
        $out .= "\n".'<div id="playlistspl"></div>'."\n";
        $out .= "\n".'<div id="nav'.$letter.'" style="display:none" class="closeme">'."\n";
    }
    foreach ($nodes['dirs'] as $v) {
        $host = preg_replace('#.*/([^/]*).*#',"\$1",$v);
        if ( $initial ){
            $nl = strtoupper($host{0});
            if ( $letter != $nl ){
                $ll = strtolower($nl);
                $preout .= "<a href=\"#\" onClick=\"navVis('nav$nl'); return false;\" accesskey=\"$ll\">[$nl]</a> \n";
                $letter = $nl;
                $out .= '</div>'."\n";
                $out .= "\n".'<div id="nav'.$letter.'" style="display:none" class="closeme">'."\n";
            }
        }
        $id   = preg_replace('`/|\.`','',$path.$host);
        $host{0} = strtoupper($host{0});
        $out .= $prefix."<div id=\"$id\">\n\t$prefix<a href=\"#\" onClick=\"showTreeChild('".$v."','".$id."',0,$plm) ; return false; \">".$host."</a>\n";
        if ( ! $plm ){
            $out .= $prefix."\t<a href=\"#\" onClick=\"massAdd('".$v."'); return false;\">[All]</a>\n$prefix</div>\n";
        }
    }
    if ( $initial ){
        $out .= '</div>'."\n";
    }
    if (sizeof($nodes['files']) > 0 ){
        $out .= "<div>\n";
        foreach ($nodes['files'] as $v) {
            $lab  = preg_replace('`.*/`','',$v);
            $olab = $lab;
            $lab{0} = strtoupper($lab{0});
            if ( $plm ){
                $lab  = preg_replace('`\.pspl$`','',$lab);
                $olab  = preg_replace('`\.pspl$`','',$olab);
                $out .= "<a href=\"#\" onClick=\"loadPlaylist('".$olab."',1) ; return false; \">".$lab."</a>\n";
                $out .= "<a href=\"".$_SERVER['PHP_SELF'].'?pl='.$olab."\" class=\"smalllink\">[URL]</a><br />\n";
            }else{
                $out .= "<a href=\"#\" onClick=\"addRrdToGraph('".$v."',1) ; return false; \">".$lab."</a><br />\n";
            }
        }
        $out .= "</div>\n";
    }
    if ( $initial ){
        $preout .= ' <a href="#" onClick="makevis(\'containerfor-helptext\'); return false;">[Help!]</a>'."\n";
        $preout .= " <a href=\"oldstats.php\">[Old]</a>\n";
        $out = $preout."\n</div>\n".$out;
    }
    $out .= '~~|~~'.$oid;
    return $out;
}

function drawGraph($path,$graphname,$start,$end,$currentgraph){
    //FIX
    $rra   = "MAX";
    $files = explode('~~|~~',$path);
    $files = cleanempty($files);
    //FIX error checking
    $start = strtotime($start);
    $end   = strtotime($end);
    $str   = graphone ($files, $rra, $start, $end, $graphname,$currentgraph);
    return $str;
}

function massAdd($path){
    //recurse path and find rrds
    $nodes = array();
    $a = recurse4massAdd($path,$nodes);
    if ( ! is_array($a) ){
        return "~~~|~~~^^~~|~~An error occured trying to deal with that path.";
    }
    //group them
    foreach ($a['list'] as $k => $v) {
        $str .= '~~~|~~~'.$v;
    }
    //return them
    return $str;
}

function serializeAndSave($name,$str){
    global $webdir;
    $name = preg_replace('`\W`','',$name);
    $name = trim($name);
    $name = $_SERVER['PHP_AUTH_USER'].'-'.$name;
    if ( empty($name) ){
        return "Your name sucked so I threw it out. I also didn't save your data.<br />What are you going to do about it?";
    }
    $file = $webdir.'/playlists/pl/'.$name.'.pspl';
    $a    = explode("\n",$str);
    defile($file,$a);
    $out  = "Yea, maybe I saved your playlist. Who knows really? Not me.<br />\n";
    $out .= "The URL for this playlist is <a href=\"".$_SERVER['PHP_SELF'].'?pl='.$name."\">".$_SERVER['PHP_SELF'].'?pl='.$name."</a><br />\n";
    return $out;
}

function loadPlaylist($path){
    global $webdir;
    if ( preg_match('`\.\.`', $path) ){
        return "alert('What\'s the big idea?!');";
    }
    $path = $webdir.'/playlists/pl/'.$path.'.pspl';
    $a  = file($path);
    $s  = join('', $a);
    //$s .= "</script>\n";
    return $s;
}

sajax_init();
// $sajax_debug_mode = 1;
sajax_export("drawGraph",'loadPlaylist',"showTreeChild",'massAdd','serializeAndSave');
sajax_handle_client_request();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <title>Stat Pajax</title>
    <script>
<?
    sajax_show_javascript();
?>
    var currentgraph;
    var gn = 0;
    var ar2glock      = 0;
    var graphnames    = new Array();
    var overridestart = new Array();
    var savestart     = new Array();
    var overrideend   = new Array();
    var saveend       = new Array();
    var paths         = new Array();

    function removeAllGraphs(){
        currentgraph  = null;
        gn            = 0;
        graphnames    = new Array();
        overridestart = new Array();
        overrideend   = new Array();
        paths         = new Array();
        gs = document.getElementById('graphspace');
        gs.innerHTML='';
    }
    function setCurrentGraph(n){
        currentgraph = n;
        hilightGraphTitle(n);
        var myRe = /[^-]*-(.*)/;
        var txt = n.replace(myRe,'$1');
        var sp = document.getElementById('currentgraphindicator');
        sp.innerHTML = txt;
    }
    function hilightGraphTitle(n){
        for (var key in graphnames){
        //for ( var f = 0; f<graphnames.length; f++){
                var graphname = graphnames[key];
                if ( document.getElementById('titlefor-' + graphname) ){
                    var hi = document.getElementById('titlefor-' + graphname);
                    hi.style.backgroundColor = "transparent";
                }
        }
        var graphname = graphnames[currentgraph];
        if ( document.getElementById('titlefor-' + graphname) ){
            var hi = document.getElementById('titlefor-' + graphname);
            hi.style.backgroundColor = "rgb(255,255,0)";
        }
    }
    function makevis( targetId ){
        if (document.getElementById){
            target = document.getElementById( targetId );
            if (target.style.display == "none"){
                target.style.display = "";
            } else {
                target.style.display = "none";
            }
        }
        return false;
    }
    function navVis( targetId ){
        var old = document.getElementById( targetId);
        var oldvis = old.style.display;
        var divies = document.getElementsByTagName('div');
        for(var i=0;i<divies.length;i++){
            //alert(divies[i].className);
            if ( (divies[i].className.indexOf("closeme")) != -1 ){
                divies[i].style.display="none";
            }
        }
        if (oldvis == "none"){
            old.style.display = "";
        } else {
            old.style.display = "none";
        }
    }
    function massAddCB(z){
        //document.getElementById("tex").value = z;
        var result_array=z.split("~~~|~~~");
        //alert(result_array.length);
        for (var i=0;i<result_array.length;i++){
            if ( result_array[i] != '' ){
                newGraphName();
                var newa = result_array[i].split('^^');
                if ( newa.length != 0 ){
                    paths[graphnames[currentgraph]] = newa;
                    drawGraph();
                }
            }
        }
        //document.getElementById("tex").value = paths[graphnames].join("\n");
    }
    function massAdd(path){
        x_massAdd(path,massAddCB);
    }
    function newGraphName(){
        previousgraph = currentgraph;
        if ( currentgraph === undefined || currentgraph === null ){
            x = '<?= uniqid('a') ?>';
            i = '<?= preg_replace('`\.`','',$_SERVER['REMOTE_ADDR']) ?>';
            z = i + x + '-0';
            setCurrentGraph(z);
            graphnames[currentgraph] = z;
        }else{
                if ( paths[currentgraph] !== undefined ){
                    gn++;
                    var myRe = /([^-]*)-/;
                    previousgraph = previousgraph.replace(myRe,'$1');
                    z = previousgraph + '-' + gn;
                    setCurrentGraph(z);
                    //currentgraph = setCurrentGraph(z);
                    graphnames[currentgraph] = z;
                }
        }

        //document.getElementById("tex").value = graphnames.join("\n");
        //alert('graphnames in newgraphname ' + graphnames.join("\n"));
    }
    function drawGraph(){
        //alert(currentgraph);
        //alert(graphnames[currentgraph]);
        //alert(paths[graphnames[currentgraph]]);
        var files = paths[graphnames[currentgraph]];
        //alert(files.length);
        if ( files.length > 1 ){
            files = files.join('');
        }
        start = '4 days ago';
        end   = 'now';
        if ( overridestart[currentgraph] ){
            start = overridestart[currentgraph];
        }
        if ( overrideend[currentgraph] ){
            end = overrideend[currentgraph];
        }
        var graphname = graphnames[currentgraph];
        if (  document.getElementById('containerfor-' + graphname) ){
            var ct = document.getElementById('containerfor-' + graphname);
            ct.style.display = "";
        }
        x_drawGraph(files,graphnames[currentgraph],start,end,currentgraph,drawGraphCB);
    }
    function drawGraphCB(z) {
        /* document.getElementById("tex").value = paths.join(); */
        var newa = z.split('^~~|~~^');
        graphname = newa[0];
        content   = newa[1];
        if ( document.getElementById('containerfor-' + graphname) ){
            ss = document.getElementById('containerfor-' + graphname );
            ss.parentNode.innerHTML = content;
        }else{
            ss = document.createElement('div');
            ss.innerHTML = content;
            document.getElementById('graphspace').appendChild(ss);
        }
        hilightGraphTitle(currentgraph);
        ar2glock = 0;
    }
    function rmRrdFromGraph(path){
        var dummy = paths[graphnames[currentgraph]];
        var smart = new Array();
        graphname = graphnames[currentgraph];
        for ( var i=0;i<dummy.length;i++){
            if ( path != dummy[i] ){
                smart.push(dummy[i]);
            }
        }
        paths[graphnames[currentgraph]] = smart;
        if ( smart.length == 0 ){
            makevis('containerfor-' + graphname);
        }else{
            drawGraph();
        }
    }
    function addRrdToGraph(path,draw){
        if ( ar2glock == 1 ){
            alert("We have a race condition, so you have to slow down.\nPath not added.\n");
            return false;
        }
        ar2glock = 1;
        var gl = 0;
        for (var key in graphnames){
            gl++;
        }
        if ( gl == 0){
            newGraphName();
        }
        var mungedpath = '~~|~~' + path
        if ( paths[currentgraph] ){
            if ( paths[currentgraph].length == 15 ){
                ar2glock = 0;
                alert("That graph is full, punk!");
                return false;
            }
            for (var r=0;r<paths[currentgraph].length;r++){
                if ( paths[currentgraph][r] == mungedpath ){
                    ar2glock = 0;
                    alert("Uh, you already have that stat, SIR!");
                    return false;
                }
            }
        }else{
            paths[currentgraph] = new Array();
        }
        paths[currentgraph].push(mungedpath);
        if ( draw == 1 ){
            drawGraph();
        }
    }
    function removeGraph(gid,id){
        paths.splice(gid,1);
        var i = 0;
        var j = 0;
        var k = 0;
        delete graphnames[gid];
        for (var key in graphnames){
            if ( i == 0 ){
                var javascriptisstupid = graphnames[key];
            }
            i++;
        }
        graphnames.length = i;
        delete overridestart[gid];
        for (var key in overridestart){
            j++;
        }
        overridestart.length = j;
        delete overrideend[gid];
        for (var key in overrideend){
            k++;
        }
        overrideend.length = k;
        jj = document.getElementById(id).parentNode;
        jj.innerHTML = null;
        if ( graphnames[javascriptisstupid] !== undefined ){
            setCurrentGraph(graphnames[javascriptisstupid]);
        }
    }
    function populateTimeOverrideArrays(){
        for (var key in graphnames){
            var start = document.getElementById('start-' + key).value;
            var end   = document.getElementById('end-' + key).value;
            savestart[key] = start;
            saveend[key]   = end;
        }
    }
    function changeGraphTime(gid){
        var start = document.getElementById('start-' + gid).value;
        var end   = document.getElementById('end-' + gid).value;
        overridestart[currentgraph] = start;
        overrideend[currentgraph]   = end;
        drawGraph();
    }
    function showTreeChild(path,id,a,b){
        var v = document.getElementById(id + 'hide');
        if ( v !== undefined && v !== null ){
            makevis(id + 'hide');
            if ( v.style.display == '' ){
                x_showTreeChild(path,id,a,b,showTreeChildCB);
            }
        }else{
            x_showTreeChild(path,id,a,b,showTreeChildCB);
        }
    }
    function showTreeChildCB(result){
        var result_array=result.split("~~|~~");
        //document.getElementById("tex").value = result;
        if ( document.getElementById( result_array[1] + 'hide') ){
            var jojo = document.getElementById( result_array[1] + 'hide');
            jojo.innerHTML = result_array[0];
        }else{
            nathanjr = document.createElement("div");
            nathanjr.setAttribute('id',result_array[1] + 'hide');
            nathanjr.setAttribute('style','padding-left:15px');
            nathanjr.innerHTML = result_array[0];
            document.getElementById(result_array[1]).appendChild(nathanjr);
        }
    }
    function serializeAndSave(){
        //var prefix = '<?= $_SERVER['PHP_AUTH_USER'] ?>';
        var name = document.getElementById('playlistname').value;
        //FIX need a playlist name checker. gah.
        if ( name == '' ){
            alert("Hey, you need a playlist name, sucker.");
            return false;
        }
        //name = prefix + '-' + name;
        if ( graphnames === undefined || paths === undefined || currentgraph === undefined || currentgraph === null ){
            alert("Nothing to save. Stop wasting time.");
            return false;
        }
        var out = "graphnames = new Array();\npaths = new Array();\nvar gn = " + gn + ";\n";
        //for(var a=0;a<graphnames.length;a++){
        var bsi = 0;
        for (var key in graphnames){
            if ( bsi == 0){
                var stupidkey = key;
            }
            bsi++;
            if ( paths[graphnames[key]] !== undefined ){
                out = out + 'graphnames[\'' + key + '\'] = \'' + graphnames[key] + '\';' + "\n";
                out = out + 'paths[\'' + graphnames[key] + '\'] = new Array();' + "\n";
                for(var b=0;b<paths[graphnames[key]].length;b++){
                    out = out + 'paths[\'' + graphnames[key] + '\'][' + b + '] = \'' + paths[graphnames[key]][b] + '\';' + "\n";
                }
            }
        }
        var sat = document.getElementById('satcb');
        if ( sat.checked ){
            populateTimeOverrideArrays();
            out = out + "overridestart = new Array()\noverrideend = new Array();\n";
            for (var key in savestart){
                out = out + 'overridestart[\'' + key + '\'] = \'' + savestart[key] + '\';' + "\n";
            }
            for (var key in saveend){
                out = out + 'overrideend[\'' + key + '\'] = \'' + saveend[key] + '\';' + "\n";
            }
            savestart = new Array();
            saveend   = new Array();
        }else{
            if ( overridestart !== undefined ){
                out = out + "overridestart = new Array()\noverrideend = new Array();\n";
                for (var key in overridestart){
                //for(var c=0;c<overridestart.length;c++){
                    out = out + 'overridestart[\'' + key + '\'] = \'' + overridestart[key] + '\';' + "\n";
                }
                for (var key in overrideend){
                //for(var d=0;d<overrideend.length;d++){
                    out = out + 'overrideend[\'' + key + '\'] = \'' + overrideend[key] + '\';' + "\n";
                }
            }
        }
        if ( paths[currentgraph] !== undefined ){
            cg = currentgraph;
        }else{
            cg = stupidkey;
        }
        out = out + 'currentgraph = \'' + cg + "\';\n";
        //alert(out);
        x_serializeAndSave(name,out,serializeAndSaveCB);
    }
    function serializeAndSaveCB(z){
        //document.getElementById("tex").value = z;
        d = document.getElementById('messagespace');
        d.innerHTML = z;
    }
    function loadPlaylist(path){
        x_loadPlaylist(path,loadPlaylistCB);
    }
    function loadPlaylistCB(z){
        //document.getElementById("tex").value = z;
        //d = document.getElementById('messagespace');
        //d.innerHTML = z;
        removeAllGraphs();
        eval(z);
        ocg = currentgraph;
        for (var key in graphnames){
            currentgraph = key;
            drawGraph();
        }
        currentgraph = ocg;
        //alert(currentgraph);
    }

//start drag code
function Browser() {

  var ua, s, i;

  this.isIE    = false;
  this.isNS    = false;
  this.version = null;

  ua = navigator.userAgent;

  s = "MSIE";
  if ((i = ua.indexOf(s)) >= 0) {
    this.isIE = true;
    this.version = parseFloat(ua.substr(i + s.length));
    return;
  }

  s = "Netscape6/";
  if ((i = ua.indexOf(s)) >= 0) {
    this.isNS = true;
    this.version = parseFloat(ua.substr(i + s.length));
    return;
  }

  // Treat any other "Gecko" browser as NS 6.1.

  s = "Gecko";
  if ((i = ua.indexOf(s)) >= 0) {
    this.isNS = true;
    this.version = 6.1;
    return;
  }
}

var browser = new Browser();

// Global object to hold drag information.

var dragObj = new Object();
dragObj.zIndex = 0;

function dragStart(event, id) {

  var el;
  var x, y;

  // If an element id was given, find it. Otherwise use the element being
  // clicked on.

  if (id)
    dragObj.elNode = document.getElementById(id);
  else {
    if (browser.isIE)
      dragObj.elNode = window.event.srcElement;
    if (browser.isNS)
      dragObj.elNode = event.target;

    // If this is a text node, use its parent element.

    if (dragObj.elNode.nodeType == 3)
      dragObj.elNode = dragObj.elNode.parentNode;
  }

  // Get cursor position with respect to the page.

  if (browser.isIE) {
    x = window.event.clientX + document.documentElement.scrollLeft
      + document.body.scrollLeft;
    y = window.event.clientY + document.documentElement.scrollTop
      + document.body.scrollTop;
  }
  if (browser.isNS) {
    x = event.clientX + window.scrollX;
    y = event.clientY + window.scrollY;
  }

  // Save starting positions of cursor and element.

  dragObj.cursorStartX = x;
  dragObj.cursorStartY = y;
  dragObj.elStartLeft  = parseInt(dragObj.elNode.style.left, 10);
  dragObj.elStartTop   = parseInt(dragObj.elNode.style.top,  10);

  if (isNaN(dragObj.elStartLeft)) dragObj.elStartLeft = 0;
  if (isNaN(dragObj.elStartTop))  dragObj.elStartTop  = 0;

  // Update element's z-index.

  dragObj.elNode.style.zIndex = ++dragObj.zIndex;

  // Capture mousemove and mouseup events on the page.

  if (browser.isIE) {
    document.attachEvent("onmousemove", dragGo);
    document.attachEvent("onmouseup",   dragStop);
    window.event.cancelBubble = true;
    window.event.returnValue = false;
  }
  if (browser.isNS) {
    document.addEventListener("mousemove", dragGo,   true);
    document.addEventListener("mouseup",   dragStop, true);
    event.preventDefault();
  }
}

function dragGo(event) {

  var x, y;

  // Get cursor position with respect to the page.

  if (browser.isIE) {
    x = window.event.clientX + document.documentElement.scrollLeft
      + document.body.scrollLeft;
    y = window.event.clientY + document.documentElement.scrollTop
      + document.body.scrollTop;
  }
  if (browser.isNS) {
    x = event.clientX + window.scrollX;
    y = event.clientY + window.scrollY;
  }

  // Move drag element by the same amount the cursor has moved.

  dragObj.elNode.style.left = (dragObj.elStartLeft + x - dragObj.cursorStartX) + "px";
  dragObj.elNode.style.top  = (dragObj.elStartTop  + y - dragObj.cursorStartY) + "px";

  if (browser.isIE) {
    window.event.cancelBubble = true;
    window.event.returnValue = false;
  }
  if (browser.isNS)
    event.preventDefault();
}

function dragStop(event) {

  // Stop capturing mousemove and mouseup events.

  if (browser.isIE) {
    document.detachEvent("onmousemove", dragGo);
    document.detachEvent("onmouseup",   dragStop);
  }
  if (browser.isNS) {
    document.removeEventListener("mousemove", dragGo,   true);
    document.removeEventListener("mouseup",   dragStop, true);
  }
}

    </script>

    <style type="text/css">
    <!--
    .graphui input,select{
        float: right;
        margin-right: 5.5em;
        margin-bottom: 4px;
    }
    label {
        margin-top: 4px;
        width: 9em;
        float: left;
        text-align: right;
    }
    #satcb{
        margin-right: 24.3em;
    }
    .graphtitle {
        float: right;
        font-size: xx-small;
        border-left: 1px solid black;
        border-bottom: 1px solid black;
        cursor: move;
    }
    .graphtitlec {
        float: right;
        font-size: xx-small;
        border-left: 1px solid black;
        border-bottom: 1px solid black;
        cursor: pointer;
    }
    .graphtitlel {
        float: left;
        font-size: xx-small;
        border-right: 1px solid black;
        border-bottom: 1px solid black;
        cursor: e-resize;
    }
    .oncolor {
        background: #ddd;
        border: 1px solid black;
        margin-bottom: 5px;
        width: 518px;
        float: left;
    }
    .offcolor {
        background: #aaa;
        border: 1px solid black;
        margin-bottom: 5px;
        width: 518px;
        float: left;
    }
    #alphanav{
        font-size: x-small;
        margin-bottom: 1em;
    }
    #graphspace{
        margin-top: 2em;
    }
    a {
        text-decoration: none;
    }
    .smalllink{
        font-size: x-small;
    }
    #containerfor-helptext{
        position: relative;
        width: 550px;
        border: 1px solid black;
        -moz-border-radius: 9px;
        background: #fff;
        z-index: 1;
    }
    #helptext {
        height: 300px;
        width: 518px;
        overflow: auto;
        padding: 1em;
        z-index: 2;
    }
    #currentgraphdisplay{
        font-size: x-small;
        border: 1px solid black;
        width: 12em;
        text-align: center;
        margin-left: auto;
        margin-right: auto;
        background: #fff;
    }
    #containerfor-graphspace{
        background:#999
    }
    .clear{
        clear: both;
    }
    -->
    </style>
</head>
<? if (isset($_GET['pl']) ){ ?>
<body onLoad="loadPlaylist('<?= $_GET['pl'] ?>');">
<? }else{ ?>
<body>
<? } ?>

<!--
<textarea id="tex" cols="90" rows="30">
</textarea>
-->
<br />
<div id="messagespace"></div>
<div id="hostlist" style="max-height:400px; overflow: auto; width: 600px;">
<?
$out=showTreeChild($rrddir,$oid,1,0);
$out=preg_replace('`~~\|~~.*`','',$out);
print $out;
?>
</div>
<div id="containerfor-graphspace">
    <div id="currentgraphdisplay">Current graph #<span id="currentgraphindicator"></span></div>
    <div class="offcolor" style="padding: 4px;">
        <div class="graphui">
            <form onSubmit="return false" action="">
            <label for="playlistname"> <a href="#" onClick="serializeAndSave(); return false;">Save</a> Playlist:</label>
            <input type="text" size="35" id="playlistname" />
            <label for="satcb">SaveAllTimes:</label>
            <input type="checkbox" id="satcb" />
            </form>
        <br style="clear: both" />
        <span style="float: right;"><a href="#" onClick="removeAllGraphs(); return false;" class="smalllink">Remove ALL Graphs</a></span><br />
        <span style="margin-left: .5em"><a href="#" onClick="newGraphName(); return false;">New Graph</a></span><br />
        </div>
    </div>
    <br style="clear: both" />
    <div id="graphspace"></div>
    <br style="clear: both" />
</div>
<div id="containerfor-helptext" style="display:none">
    <div style="border-bottom: 1px solid black; background: #ddd;">
        <div class="graphtitle" onMouseDown="dragStart(event,'containerfor-helptext')">Drag</div>
        <div class="graphtitlec" onClick="makevis('containerfor-helptext'); return false">Close</div>
        <div class="graphtitlel" onMouseDown="makevis('helptext')">Shade</div>
        <div style="text-align: center; font-size: x-small; cursor: move;" onMouseDown="dragStart(event,'containerfor-helptext')">Help</div>
    </div>
    <div id="helptext">
    <h1>Stat Pajax <sup>with Sajax!</sup></h1>
    <p>
    Welcome to PatStats.
    </p>
    <p>
    The basics:
    <ul>
        <li>Click on a letter to see the hosts/dirs that start with that letter.
            <ul>
                <li>The <em>[PL]</em> link opens the list of playlists.</li>
            </ul>
        </li>
        <li>Click on a host/dir to expand/contract it.</li>
        <li>Click on an rrd file to add it to the current graph.</li>
        <li>You can add any stat from any host to a graph. Obviously, you'll want to use stats that have similar scales/units.</li>
        <li>Graphs are drawn with the default start time of <em>4 days ago</em> and the default end time of <em>now</em>.</li>
        <li>Click on a graph image to make that graph the current graph.
            <ul>
                <li>This allows you to add additional elements to a graph.</li>
                <li>The current graph is indicated by the graph title having a yellow background.</li>
            </ul>
        </li>
        <li>Clicking on [All] will create new graphs, grouped by subdirectory, of all of the rrd files in that directory and it's subdirectories.</li>
        <li>Click and drag a graph name to move the graph to whatever location you want!</li>
    </ul>
    Advanced:
    <ul>
        <li>Click on <em>Show GraphElements</em> to remove lines from or change the time of a graph.</li>
        <li>To change the time of a graph, simply modify the time below the graph image and then de-focus the text-box. The graph will automagically redraw with your assigned time.</li>
        <li>Time formatting can be loose. Strings such as these are acceptable:
            <ul>
                <li><em>now</em></li>
                <li><em>5 minutes ago</em></li>
                <li><em>yesterday</em></li>
                <li><em>two days ago 4pm</em></li>
                <li><em>one month ago</em></li>
            </ul>
            When in doubt, try it out! If PatStats is unable to figure out what you meant, you'll get the default time.</li>
        <li>If you want to reset the times to the default values, simply empty each form.</li>
    </ul>
    </p>
    <p>
    Playlists allow you to save the graphs you've made, for later viewing.
    </p>
    <p>
    Things to know about playlists:
    <ul>
        <li>Loading a playlist removes all currently loaded graphs.</li>
        <li>Playlist names can only have A-Z a-z and 0-9 characters in their names.</li>
        <li>Playlists only store <strong>changed</strong> times, unless you click the <em>Save All Times</em> checkbox.
            <ul>
                <li>This means that if you load a playlist (that was saved w/o <em>Save All Times</em> at a later date, the graphs it draws will have current times, rather than the times when you saved the playlist.</li>
            </ul>
        </li>
        <li>Your username and a dash will be prepended to the name of your playlist.</li>
    </ul>
    Creating playlists:
    <ol>
        <li>Simply create one or more graphs.</li>
        <li>Once you've created all of the graphs you want to save in the playlist, enter a name for the playlist in the <em>Playlist</em> text box.</li>
        <li>If you want the times for the graphs to be saved, check the <em>Save All Times</em> checkbox.</li>
        <li>Click <em>Save</em> next to the playlist name.</li>
    </ol>
    Playlists will have your username prepended to them to prevent you from overwriting the playlists of others.
    </p>
    <p>
    Loading playlists:
    <ul>
        <li>Click on <em>[PL]</em> to see what playlists are available.</li>
        <li>Clicking on a playlist name will remove all other graphs.</li>
        <li>The <em>[URL]</em> link beside each playlist is a link that can be used to go immediately to a playlist.</em>
    </ul>
    </p>
    </div>
</div>
</body>
</html>
