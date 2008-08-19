<?php

require("Sajax.php");
require("JSON.php");
require("conf.php");


function findMatches($s,$graphpathbreaks,$gll){
    global $webdir;
    $graphlinelimit = $gll;
    $json = new Services_JSON();
    $ob = new stdClass();
    $pa = array();
    $os = 'Regex: '.$s."\n";
    $os .= "----------------\n";
    if ( ($f = @fopen($webdir.'/fscache','r')) === FALSE ){
        $ob->string = 'unable to open fscache file.';
        $ob->graphs = $pa;
        $ob->gpb    = $graphpathbreaks;
        $ob = $json->encode($ob);
    }
    $i = 0;
    $dir = '';
    $tmp = array();
    while ( $line = fscanf($f,"%s\n")){
        $line = $line[0];
        if ( preg_match("`$s`", $line) ){
            $i++;
            if ( $graphpathbreaks ){
                $odir = $dir;
                $dir = dirname($line);
                if ( $dir == $odir ){
                    if ( $i % $graphlinelimit == 0 ){
                        $os .= "----------------\n";
                        $pa[] = $tmp;
                        $tmp = array();
                    }
                }elseif ( !empty($odir) ){
                    $i = 0;
                    $os .= "----------------\n";
                    $pa[] = $tmp;
                    $tmp = array();
                }
            }else{
                if ( $i % $graphlinelimit == 0 ){
                    $os .= "----------------\n";
                    $pa[] = $tmp;
                    $tmp = array();
                }
            }
            $os .= $line."\n";
            $tmp[] = $line;
        }
    }
    $pa[] = $tmp;
    $pas  = sizeof($pa);
    $os   = "Happy this is display only!\n$pas graphs\n$i lines\n".$os;
    fclose($f);
    $ob->string = $os;
    $ob->graphs = $pa;
    $ob->gpb    = $graphpathbreaks;
    $ob = $json->encode($ob);
    return $ob;
}

function savePlaylist($name,$str){
    global $webdir;
    $name = preg_replace('`\W`','',$name);
    $name = trim($name);
    $dir  = $webdir.'/playlists/';
    $json = new Services_JSON();
    $user = $_SERVER['PHP_AUTH_USER'];
    if ( $user == 'guest' ){
        return $json->encode(array('ERROR',"Guest users cannot create playlists. Get a real account!"));
    }
    if ( is_writable($dir) ){
        $dir .= $user;
        if ( ! file_exists($dir) ){
            $md = mkdir($dir);
            if ( ! $md ){
                return $json->encode(array('ERROR',"I couldn't make/find your playlist directory, so I'm crapping out. NOT SAVED!"));
            }
        }
        $dir .= '/';
    }
    if ( empty($name) ){
        return $json->encode(array('ERROR',"Your name sucked so I threw it out. I also didn't save your data. Life is hard."));
    }
    $file = $dir.$name.'.pspl';
    $str  = stripslashes($str);
    if ( empty($str) ){
        return $json->encode(array('ERROR','Empty playlist!'));
    }
    $fp   = @fopen($file,'w');
    if ($fp==0){
        return $json->encode(array('ERROR',"Unable to write to file [$file]. Check permissions."));
    }
    $blah = fwrite($fp,"$str");
    fclose($fp);
    $out  = $json->encode(array('SUCCESS',"Yea, maybe I saved your playlist. Who knows really? Not me. The URL for this playlist is ",$_SERVER['PHP_SELF'].'?pl='.$user.'/'.$name));
    return $out;
}

sajax_init();
//$sajax_debug_mode = 1;
sajax_export('findMatches','savePlaylist');
sajax_handle_client_request();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <title>Jart Regexer</title>
    <link rel="stylesheet" type="text/css" href="css/reset-min.css">
    <link rel="stylesheet" type="text/css" href="css/fonts-min.css">
    <link rel="stylesheet" type="text/css" href="css/grids-min.css">
    <link rel="stylesheet" type="text/css" href="colorPicker.css">
    <script type="text/javascript" src="js/json.js"></script>
    <script type="text/javascript" src="js/prototype.js"></script>
    <script type="text/javascript" src="js/prototype-plus.js"></script>
    <script type="text/javascript" src="js/scriptaculous.js"></script>
    <script type="text/javascript" src="js/graph.js"></script>
    <script>
<?php
    sajax_show_javascript();
?>
    function addLoadEvent(func) {
        var oldonload = window.onload;
        if (typeof window.onload != 'function') {
            window.onload = func;
        } else {
            window.onload = function() {
                if (oldonload){
                    oldonload();
                }
                func();
            }
        }
    }
    var R = (function(){
        function init(){
            var go = $('regexgo');
            Event.observe(go,'click',findMatches.bindAsEventListener());
            var save = $('saveit');
            Event.observe(save,'click',savePlaylist.bindAsEventListener());
            var u  = $('unlimited');
            u.checked = false;
            $('graphpathbreaks').checked = false;
            Event.observe(u,'change',unlimited.bindAsEventListener());
            var t  = $('total');
            Event.observe(t,'change',addTotal.bindAsEventListener());
            var jt = $('justtotal');
            Event.observe(jt,'change',justTotal.bindAsEventListener());
        }
        function findMatches(){
            $('total').checked = false;
            $('justtotal').checked = false;
            var re = $F('regextext');
            re     = re + '[.]rrd$';
            //G.graphs.regex = re;
            var pb = $F('graphpathbreaks');
            if ( pb == 'on' ){
                pb = 1;
            }else{
                pb = 0;
            }
            //G.graphs.gpb = pb;
            G.cg = 0;
            G.graphs = new Array();
            G.addGraph();
            x_findMatches(re,pb,G.defaultpathlimit,findMatchesCB);
        }
        function findMatchesCB(s){
            var list = s.parseJSON();
            $('list').innerHTML = list.string;
            //console.log(list);
            list.graphs.each(function(g){
                //console.log(g);
                G.addGraph();
                g.each(function(path){
                    //console.log(path);
                    G.addRrdToGraph(path,0);
                });
            });
            //console.log(G.graphs);
        }
        function savePlaylist(){
            var plname = $F('savename');
            G.graphs[0].regex = $F('regextext');
            var playlist = G.graphs.toJSONString();
            x_savePlaylist(plname,playlist,savePlaylistCB);
        }
        function savePlaylistCB(s){
            var o = s.parseJSON();
            //console.log(o);
            alert(o[0]);
        }
        function unlimited(){
            var u = $('unlimited');
            //console.log(u.checked);
            if ( u.checked ){
                G.defaultpathlimit = 2000;
            }else{
                G.defaultpathlimit = 15;
            }
            //console.log(G.defaultpathlimit);
        }
        function addTotal(){
            var t = $('total');
            if ( t.checked ){
                G.graphs.each(function(graph,key){
                    if ( G.graphs[key].total == 0 ){
                        G.graphs[key].total = 1;
                        G.cg = key;
                        G.addRrdToGraph('total',0);
                    }else{
                        alert('this graph seems to already have a total');
                    }
                })
            }else{
                alert('unchecking this has no effect. re-do your regex and the total will be gone');
            }
        }
        function justTotal(){
            var t = $('total');
            if ( t.checked ){
                G.graphs.each(function(graph,key){
                    G.graphs[key].justtotal = 1;
                })
            }else{
                alert('unchecking this has no effect. re-do your regex and the total will be gone');
            }
        }
        return{ 'init': init}
    })();

    addLoadEvent(R.init);
</script>
    <style type="text/css">
    <!--
    .roomy {
        margin: 20px 20px 20px 0px;
    }
    .red {
        color: red;
    }
    -->
    </style>
</head>
<body>

<div id="doc3" class="yui-t3">
<div id="hd" class="roomy">
Fill in the Regex and check the checkboxes before hitting Go. <br />
Then decide if you want totals or just totals and then name your playlist.
</div>
    <div id="bd">
        <div id="yui-main">
            <div class="yui-b">
                <textarea id="list" cols="80" rows="30" disabled="true"></textarea>
            </div>
        </div>

        <div class="yui-b">
            <div class="yui-u first roomy">
                <form onSubmit="return false;">
                    <span class="red">&quot;[.]rrd$&quot; will be appended to your regex!!</span><br />
                    <label for="regextext">Regex:</label>
                    <input id="regextext" type="text" size="40" value="" />
                    <br />
                    <label for="graphpathbreaks">Path Breaks Graph:</label>
                    <input id="graphpathbreaks" type="checkbox" />
                    <input id="newgraphsnstime" type="checkbox" style="display: none" />
                    <br />
                    <label for="unlimited">Unlimited graphs</label>
                    <input id="unlimited" type="checkbox" />
                    <br />
                    <input id="regexgo"   type="button" value="Go" />
                </form>
            </div>
            <div id="below" class="roomy">
                <form onSubmit="return false;">
                    <label for="total">total<em>s</em></label>
                    <input id="total" type="checkbox" />
                    <label for="justtotal">just total<em>s</em></label>
                    <input id="justtotal" type="checkbox" />
                    <br />
                    <label for="savename">Playlist Name</label>
                    <input id="savename" type="text" size="20" value="" />
                    <input id="saveit"   type="button" value="Save" />
                </form>
            </div>
        </div>
        <div id="ft">The end.</div>
    </div>
</div>

</body>
</html>
