<?php

# Copyright (C) 2008 Board of Regents of the University System of Georgia
#
# This file is part of YaketyStats (see http://yaketystats.org/).
# YaketyStats is free software: you can redistribute it and/or modify
#
# vim: set sw=4 sts=4 et tw=0 :

require("Sajax.php");
require("JSON.php");
require("conf.php");
require_once('common.php');

if ( ! is_file($rrdtool) ){
    print "Unable to find your rrdtool. Please update conf.php\n";
    exit;
}
if ( ! is_executable($rrdtool) ){
    print "Unable to execute $rrdtool. Please check permissions or update conf.php\n";
    exit;
}

if ( ! is_writable($webdir) ){
    print "Unable to write to $webdir. Please check permissions.\n";
    exit;
}

if ( ! is_dir("playlists") ){
    $bool = mkdir("$webdir/playlists");
    if ( ! $bool ){
        print "Unable to create missing $webdir/playlists directory. Please check permissions.\n";
        exit;
    }
}

if ( ! is_writable("playlists") ){
    print "Unable to write to $webdir/playlists directory. Please check permissions.\n";
    exit;
}

if ( ! is_dir("graphs") ){
    $bool = mkdir("$webdir/graphs");
    if ( ! $bool ){
        print "Unable to create missing $webdir/graphs directory. Please check permissions.\n";
        exit;
    }
}

if ( ! is_writable("graphs") ){
    print "Unable to find or write to the graphs directory. Please check permissions.\n";
    exit;
}

if ( ! file_exists("$webdir/events.db") ){
    $sql = "CREATE TABLE events ( id INTEGER PRIMARY KEY, edate INTEGER, title TEXT, comment TEXT, user TEXT, color TEXT , shortname TEXT UNIQUE);";
    $sql2 = "CREATE TABLE t2e ( tagid INTEGER, eventid INTEGER);";
    $sql3 = "CREATE TABLE tags ( id INTEGER PRIMARY KEY, tag TEXT NOT NULL UNIQUE );";
    $dbcs = 'sqlite:'.$webdir.'/events.db';
    try {
        $db = new PDO($dbcs);
    }catch (PDOException $e){
        print "Couldn't create your events DB\n";
        exit;
    }
    $sth = $db->prepare($sql);
    $sth->execute();
    $sth = $db->prepare($sql2);
    $sth->execute();
    $sth = $db->prepare($sql3);
    $sth->execute();
}

error_reporting(0);

class Graph {
    public $args      = array("-i -P -W '<big>YaketyStats</big>' -E --rigid ");
    public $comments  = array();
    public $cmd       = array();
    //public $defs      = array();
    public $defs      = array();
    public $debug     = '';
    public $events    = array();
    public $json      = '';
    public $last      = '';
    public $lines     = array();
    public $minusb    = '';
    public $naniszero = 0;
    public $number    = 0;
    public $opaths    = array();
    public $paths     = array();
    public $slidesize = 0;
    public $targs     = array();

    public function __construct($number,$paths,$dooverlay){
        $this->json  = new Services_JSON();
        $this->number = $number;
        $this->opaths = $paths;
        $this->paths = $this->json->decode($paths);
        $this->redrawoverlay = $dooverlay;
        $this->debugLog($paths);
    }

    public function commentArgs(){
        global $dateformat;
        /*
        if ( $this->slidesize ){
            $this->comments[] = "'COMMENT:\\n' ";
            $this->comments[] = "'COMMENT:Sliding window size\\: ";
            $this->comments[] = $this->secToEng($this->slidesize) . "' ";
        }
         */
        $this->comments[] = "'COMMENT: \\n' ";
        $this->comments[] = "'COMMENT:                 Start\\: ";
        $this->comments[] = $this->dateEscape($dateformat, $this->paths->start);
        $this->comments[] = "' ";

        $this->comments[] = "'COMMENT:\\n' ";
        $this->comments[] = "'COMMENT:                   End\\: ";
        $this->comments[] = $this->dateEscape( $dateformat, $this->paths->end);
        $this->comments[] = "' ";

        $this->comments[] = "'COMMENT:\\n' ";
        $this->comments[] = "'COMMENT:      Aprox Time shown\\: ";
        $this->comments[] = $this->secToEng($this->paths->end - $this->paths->start) . "' ";

        $this->comments[] = "'COMMENT:\\n' ";
        $this->comments[] = "'COMMENT:         Graph created\\: ";
        $this->comments[] =  $this->dateEscape( $dateformat, time() ). "' ";

        $this->comments[] = "'COMMENT:\\n' ";
        $this->comments[] = "'COMMENT:Most recent RRD update\\: ";
        $this->comments[] = $this->dateEscape( $dateformat, $this->rrdlast)."' ";

        $this->comments[] = "'COMMENT: \\n' ";
        $this->comments[] = "'COMMENT: \\n' ";
    }

    public function createCommandLine(){
        $this->paths->end = $this->stt($this->paths->end,'end');
        $this->paths->start = $this->stt($this->paths->start,'start');
        $this->timeArgs();
        $this->fontArgs();
        $this->sizeArgs();
        $this->labelArgs();
        $this->pathArgs();
        $this->eventArgs();
        $this->commentArgs();
        $tmp  = array_merge($this->args,$this->defs,$this->lines,$this->events,$this->comments);
        $tmpa = join('',$tmp);
        $out  = "graphv ". $this->nameGraph();
        $out .= " ". $this->minusb . $tmpa;
        $this->cmd = $out;
        $this->debugLog('command:',$out,"\n\n");
    }

    public function dateEscape($f,$t){
        return trim(addcslashes(date($f,$t),":"));
    }

    public function debugLog(){
        ob_start();
        foreach(func_get_args() as $v){
            var_dump( $v );
        }
        $this->debug .= ob_get_contents();
        ob_end_clean();
    }

    public function debugWriteLog(){
        $file = $this->nameGraph() . "-class.log";
        $fp = fopen($file,'w');
        $blah = fwrite($fp,$this->debug);
        fclose($fp);
    }

    public function draw(){
        $this->createCommandLine();
        global $rrdtool;
        //$str = exec( $this->cmd . " 2>&1",$output,$retval);
        $descriptors = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );
        $exec = proc_open("$rrdtool -",$descriptors,$pipes);
        if ( is_resource($exec) ){
            fwrite($pipes[0],$this->cmd);
            fclose($pipes[0]);
            $output  = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $retval = proc_close($exec);
        }
        $output = split("\n",$output);

        if ( $retval != 0 ){
            //FIX
            $out[] = 'ERROR';
            $out[] = "$stderr  " . implode(" ", $output). " $retval ".$this->cmd;
            $out[] = $this->number;
        }else{
            $out[] = 'image'; // [0]
            $out[] = $this->number; // [1]

            $h = $this->grep('/^image_height =\s+/',$output);
            $w = $this->grep('/^image_width =\s+/',$output);

            $out[] = $this->nameGraph() . "?" . mktime(); // [2]

            if ( empty($h) ){
                $h = $this->paths->ysize;
            }
            if (empty($w) ){
                $w = $this->paths->xsize;
            }

            $out[] = $h; // [3]
            $out[] = $w; // [4]
            $min    = $this->grep('/^value_min =\s+/',$output);
            $max    = $this->grep('/^value_max =\s+/',$output);
            $out[]  = $min; // [5]
            $out[]  = $max; // [6]

            $xoff   = $this->grep('/^graph_left =\s+/',$output);
            $yoff   = $this->grep('/^graph_top =\s+/',$output);
            $xsize  = $this->grep('/^graph_width =\s+/',$output);
            $ysize  = $this->grep('/^graph_height =\s+/',$output);
            $out[]  = $xoff; // [7]
            $out[]  = $yoff; // [8]
            $out[]  = $xsize; // [9]
            $out[]  = $ysize; // [10]
            //$out[]  = $this->redrawoverlay;
            if ( $this->redrawoverlay ){
                $np = preg_replace('/"min": "[^"]*"/','"min": "'.$min.'"',$this->opaths);
                $np = preg_replace('/"max": "[^"]*"/','"max": "'.$max.'"',$np);
                $this->debugLog('np',$np);
                $this->debugLog($min,$max,$this->opaths,$np);
                $ol = createGraphDragOverlay($this->number,$np,1);
                $out[]  = $ol; // [11]
            }
        }
        $this->debugLog($output,$stderr,$out,$this->args,$this->defs,$this->events);
        $this->debugWriteLog();
        //return $this->json->encode($out);
        return $out;
    }

    public function error($msg){
        $out[] = 'ERROR';
        $out[] = $msg;
        return $out;
    }

    public function eventArgs(){
        global $dateformat,$webdir,$colors;
        $user   = $_SERVER['PHP_AUTH_USER'];
        $dbfile = $webdir.'/events.db';
        $dbcs   = 'sqlite:'.$dbfile;
        $join   = '';
        $a      = array();
        if ( file_exists($dbfile) ){
            try {
                $db = new PDO($dbcs);
            }catch (PDOException $e){
                return;
            }
            switch ( $this->paths->events ){
                case 'none':
                case '':
                    return;
                case "all":
                    $where = '';
                    break;
                case "my":
                    $where = " AND user='$user'";
                    break;
                default:
                    $in    = split(' ',$this->paths->events);
                    foreach ($in as $i){
                        $x[] = '"'.$i.'"';
                    }
                    $in    = join(',',$x);
                    $join  = ' JOIN t2e ON events.id = t2e.eventid join tags ON tags.id = t2e.tagid ';
                    $where = ' AND tags.tag in ('.$in.') ';
            }
            $s   = $this->paths->start;
            $e   = $this->paths->end;
            $sql = 'SELECT events.id,edate,title,comment,color,shortname FROM events ';
            $sql.= $join . ' WHERE edate > ' . $s . ' AND edate < ' . $e  . $where . ' ORDER BY edate';
            $this->debugLog('sql',$sql);
            $l   = 0;
            $stupid = array();
            foreach ( $db->query($sql) as $q ){
                if ( in_array($q['id'],$stupid) ){
                    continue;
                }else{
                    $stupid[] = $q['id'];
                    $sn = strlen($q['title']);
                    if ( $sn > $l ){
                        $l = $sn;
                    }
                    $a[] = $q;
                }
            }
            if ( count($a) > 0 ){
                $this->events[] = " 'COMMENT: \\n' ";
                $this->events[] = " 'COMMENT:<b>Events</b>\\: \\n' ";
            }
            foreach ( $a as $q ){
                $sn = '';
                if ( ! empty($q['shortname']) ){
                    $sn = ' <small>[' . addcslashes($q['shortname'],':') . ']</small>';
                }
                //$this->debugLog('sn',$sn, $q);
                //$c = $q['color'];
                $c = array_shift($colors);
                if ( ! $c ){
                    $c = '#ff0000';
                }
                $es = ' VRULE:' . $q['edate'] . "$c:'". str_pad(addcslashes($q['title'],':'),$l) ." $sn\\n':dashes ";
                $this->events[] = $es;
                $this->events[] = " 'COMMENT:  " . $this->dateEscape($dateformat, $q['edate']) ." \\n' ";
                if ( ! empty($q['comment']) ){
                    $x = addcslashes($q['comment'],"\n\t:");
                    $x = preg_replace("/['\"]/",'',$x);
                    $this->events[] = " 'COMMENT:  " . $x ."' ";
                    $this->events[] = " 'COMMENT: \\n' ";
                }
            }
        }
    }

    public function fontArgs(){
        global $font;
        $fs = 6;
        if ($this->paths->size > 49){
            $fs = 8;
        }
        $this->args[] = "--font DEFAULT:$fs:$font ";
    }

    public function grep($regex,$s){
        $r = preg_grep($regex,$s);
        $r = array_values($r);
        if ( ! empty($r[0]) ){
            $r = $r[0];
            $r = preg_replace($regex,'',$r);
            $r = urlencode($r);
        }
        return $r;
    }

    public function labelArgs(){
        if ( isset($this->paths->graphlabel) && !empty($this->paths->graphlabel) ){
            $tmp = escapeshellcmd(preg_replace('/[:\'"&;]/','',$this->paths->graphlabel));
            $this->args[] = "-t '$tmp' ";
        }
        if ( isset($this->paths->vertlabel) && !empty($this->paths->vertlabel) ){
            $tmp = escapeshellcmd(preg_replace('/[:\'"&;]/','',$this->paths->vertlabel));
            $this->args[] = "-v '$tmp' ";
        }
        if ( isset($this->paths->canvas) && !empty($this->paths->canvas) ){
            $this->args[] = "--color CANVAS#".escapeshellcmd($this->paths->canvas).' ';
        }
    }

    public function nameGraph(){
        global $graphpath;
        $user  = $_SERVER['PHP_AUTH_USER'];
        return "$graphpath/$user-". $this->number . '.png';
    }

    private function pathArgs(){
        // the default Ystats DS
        $a         = 0;  // the average counter
        $ds        = 'yabba';
        $fakerrds  = array('total','avg');
        $i         = 0;
        $rra       = 'MAX';
        $defables  = array();
        //$trendtotaldefs  = array();
        $predctdefs = array();
        if ($this->paths->total == 1 ){
            $this->naniszero = 1;
        }
        //if ( $this->paths->predicting == 1 ){
            //$slide = floor(($this->paths->end - $this->paths->start)/2);
            //$this->slidesize = $slide;
            //$slideStart = $this->paths->start - $slide;
            //$slideEnd = $this->paths->end + $slide; 
        //}
        foreach ($this->paths->paths as $v) {
            $negative  = 0;
            $stack = '';
            if ( ! isset($v->display) ){
                $this->debugLog('display was not set for ',$v);
                $v->display = 1;
            }
            $color = escapeshellcmd(substr(trim($v->color),1));
            $drawt = escapeshellcmd(trim($v->drawtype));
            if ( $drawt{0} == '-' ){
                $drawt = substr($drawt, 1);
                $negative = 1;
            }
            if ( isset($v->opacity) ){
                $color .= $v->opacity;
            }
            if ( $drawt == 'STACK' ){
                $drawt = 'AREA';
                $stack = ':STACK';
            }
            if ( preg_match('/[:][:]/',$v->path) ){
                $path = preg_replace('/(.*)[:][:].*/','$1',$v->path);
                $ds   = preg_replace('/.*[:][:](.*)/','$1',$v->path);
                $path = escapeshellcmd($path);
                $ds   = escapeshellcmd($ds);
            }else{
                $path  = escapeshellcmd($v->path);
            }
            if ( ! file_exists($path) && ! in_array($path,$fakerrds) ){
                $this->debugLog("BAD PATH:",$path);
                continue;
            }
            if ( filesize($path) === 0 ){
                die( $this->json->encode(array('ERROR',"$path is zero-length.")));
            }
            if ( empty($this->minusb) ){
                if ( preg_match('#/(memory|disk)/#',$path) ){
                    $this->minusb = '-b 1024 ';
                }
            }
            $name = escapeshellcmd($v->name);
            $name = preg_replace('/[:]/','\\:',$name);
            if ( $v->isPredict == 1 ){
                $this->debugLog('vname',$v->name);
                $otherdefid = $defables[$path];
                if (empty($otherdefid) ){
                    continue;
                }
                $name = addcslashes($v->name,':');
                $name = '<i>' . $name . '</i>';
                $dashes = '';
                if ( $drawt != 'AREA' ){
                    $dashes = ':dashes';
                }
                // This didn't work for making Happy's vista*predict_8 playlist
                // work. :(
                /*
                if ( $otherdefid == 'total'){
                    $muppy = preg_replace('/WUB([^,]*),UN,0,WUB[^,]*,IF/','TWUB$1',$totalargs);
                    $muppypl = substr($plusses, 0, -3);
                    $this->defs[] = 'CDEF:supertotal='.$muppy.$muppypl.' ';
                    $otherdefid = 'supertotal';
                }
                 */
                    //$this->defs[] = "DEF:${otherdefid}slide=$path:$ds:$rra:start=${slideStart}:end=${slideEnd} ";
                    //$this->defs[] = "CDEF:${otherdefid}trend=${otherdefid}slide,$slide,TRENDNAN ";
                    $predictdefs[] = "VDEF:${otherdefid}slope=${otherdefid},LSLSLOPE ";
                    $predictdefs[] = "VDEF:${otherdefid}int=${otherdefid},LSLINT ";
                    $predictdefs[] = "VDEF:${otherdefid}corr=${otherdefid},LSLCORREL ";
                    $predictdefs[] = "CDEF:${otherdefid}predict=${otherdefid},POP,${otherdefid}slope,COUNT,*,${otherdefid}int,+ ";
                if ( $v->display != 0 && $this->iAmOverlay == 0 ){
                    $this->lines[] = " $drawt:${otherdefid}predict#$color:'$name\\n'$stack$dashes ";
                }
            }elseif ( $path == 'total' ){
                $defid = 'total';
                if ( $v->display != 0 ){
                    $this->lines[] = " $drawt:$defid#$color:Total$stack ";
                }
                $defables["$path"] = $defid;
            }elseif ( $path == 'avg' ){
                $defid = 'average';
                if ( $v->display != 0 ){
                    $this->lines[] = " $drawt:$defid#$color:Average$stack ";
                }
            }else{
                if ( ! $this->rrdlast ){
                    $this->rrdlast = my_rrd_last($path);
                }
                // $i adds some uniqueness in case someone dupes a color
                $defid  = "WUB$i";
                //if ( $this->paths->predictTotal == 1 ){
                    //$trendtotaldefs[] = "DEF:TWUB$i=$path:$ds:$rra:start=${slideStart}:end=${slideEnd} ";
                //}
                $defables["$path"] = $defid;
                // 0's pull the prediction down, so don't do it if we're predicting the total which often has nans
                if ( $this->naniszero == 1 && $this->paths->predictTotal == 0) {
                  # CDEF:result=value,UN,0,value,IF
                  $rpn = "${defid},UN,0,${defid},IF";
                } else {
                  $rpn = $defid;
                }
                $this->defs[] = "DEF:$defid=$path:$ds:$rra ";
                //if ( $this->paths->justtotal == 0 ){
                    if ( $negative ){
                        $rpn = "0,$rpn,-";
                        //$defs .= "CDEF:neg$defid=0,$defid,- ";
                        $this->defs[] = "CDEF:neg$defid=$rpn ";
                        if ( $v->display != 0 && $this->paths->justtotal == 0){
                            $this->lines[] = "$drawt:neg$defid#$color:'$name'$stack ";
                        }
                    }else{
                        if ( $v->display != 0 && $this->paths->justtotal == 0){
                            $this->lines[] = "$drawt:$defid#$color:'$name'$stack ";
                        }
                    }
                //}
                if ( $this->paths->avg ){
                    $avgargs .= "$rpn,";
                    $a++;
                }
                if ( $this->paths->total ){
                    $totalargs .= "$rpn,";
                    $plusses   .= '+,';
                }
                if ( $v->display == 1 ){
                    $this->defs[] = "VDEF:jg$defid=$defid,MAXIMUM ";
                    $this->defs[] = "VDEF:tg$defid=$defid,MINIMUM ";
                }
            }
            if ( ($this->paths->justtotal == 0 || $path == 'total' || $path == 'avg') && $v->isPredict != 1 && $v->display == 1 ){
                $this->lines[] = "'COMMENT:\\n' ";
                $this->lines[] = "VDEF:AVG$i=$defid,AVERAGE ";
                $this->lines[] = "'GPRINT:AVG$i:   Avg%7.2lf%s' ";
                $this->lines[] = "VDEF:MIN$i=$defid,MINIMUM ";
                $this->lines[] = "'GPRINT:MIN$i:Min%7.2lf%s' ";
                $this->lines[] = "VDEF:MAX$i=$defid,MAXIMUM ";
                $this->lines[] = "'GPRINT:MAX$i:Max%7.2lf%s' ";
                $this->lines[] = "VDEF:LAST$i=$defid,LAST ";
                $this->lines[] = "'GPRINT:LAST$i:Last%7.2lf%s\\n' ";
            }
            $i++;
        }
        if ( $this->paths->total ){
            $plusses = substr($plusses, 0, -3);
            $this->defs[] = 'CDEF:total='.$totalargs.$plusses.' ';
            //$this->defs[] = 'VDEF:supertotal=total,MAXIMUM ';
        }
        if ( $this->paths->avg ){
            $this->defs[] = 'CDEF:average='.$avgargs.$a.',AVG ';
        }
        /*
        if ( isset($totalt) ){
            $this->defs[] = join('',$trendtotaldefs);
            $muppy = preg_replace('/WUB([^,]*),UN,0,WUB[^,]*,IF/','TWUB$1',$totalargs);
            $this->defs[] = "CDEF:totalslide=${muppy}${plusses} ";
            $this->defs[] = $totalt;
        }
         */
        if ( $this->iAmOverlay == 0 ){
            foreach ($predictdefs as $t){
                $this->defs[] = $t;
            }
        }
    }

    public function secToEng($d_sec){
$odsec = $d_sec;
        $d['years'] = floor($d_sec/31536000);
        $d_sec     -= $d['years'] * 31536000;
        $d['weeks'] = floor($d_sec/604800);
        $d_sec     -= $d['weeks'] * 604800;
        $d['days']  = floor($d_sec/86400);
        $d_sec     -= $d['days'] * 86400;
        $d['hours'] = floor($d_sec/3600);
        $d_sec     -= $d['hours'] * 3600;
        $d['mins']  = floor($d_sec/60);
        $d_sec     -= $d['mins'] * 60;
        $d['secs']  = $d_sec;

        $str = '';
        foreach( $d as $key => $value ){
            if ( $value  != '0' ){
                $str .= "$value $key ";
            }
        }
        return $str;
    }

    public function sizeArgs(){
        global $font;
        switch ($this->paths->size){
            case 0:
                $this->args[] = '-w 200 -h 50 ';
                break;
            case 100:
                $this->args[] = '-w 500 -h 150 ';
                break;
            case 150:
                $this->args[] = '-w 575 -h 250 ';
                break;
            case 200:
                $this->args[] = '-w 650 -h 350 ';
                break;
        }
    }

    public function timeArgs(){
        $this->args[] = '-s ' . $this->paths->start;
        $this->args[] = ' -e ' . $this->paths->end . ' ';
    }

    public function stt($t,$l){
        $r = mystrtotime($t,$l);
        if ( ! $r ){
            // FIX
            die( $this->json->encode(array('ERROR',"Invalid $l time")));
        }
        return $r;
    }

}

class Overlay extends Graph {

    public $iAmOverlay = 1;
    public function __construct($number,$paths){
        parent::__construct($number,$paths);
    }

    public function nameGraph(){
        global $graphpath;
        $user  = $_SERVER['PHP_AUTH_USER'];
        return "$graphpath/$user-". $this->number . '-overlay.png';
    }

    public function sizeArgs(){
        $ul = urldecode($this->paths->max);
        $ll = urldecode($this->paths->min);
        $w  = $this->paths->xsize * 3;
        $h  = $this->paths->ysize;
        $this->args[] = "--only-graph -h $h -w $w ";
        $this->args[] = "--lower $ll --upper $ul ";
    }

    public function timeArgs(){
        $diff = $this->paths->end - $this->paths->start;
        $this->paths->start = $this->paths->start - $diff;
        $this->paths->end = $this->paths->end + $diff;
        $this->args[] = '-s ' . $this->paths->start;
        $this->args[] = ' -e ' . $this->paths->end . ' ';
    }

}

function cleanempty($a){
    if ( ! is_array($a) ){
        return FALSE;
    }
    $a=array_filter($a,'notempty');
    $b=array_values($a);
    return $b;
}

function clickToCenterTime($start,$end,$graph,$xsize,$xoff){
    global $dateformat;
    $json  = new Services_JSON();
    $start = strtotime($start);
    $end   = strtotime($end);
    if ( ! $start || ! $end ){
        $out[] = 'ERROR';
        $out[] = "Bad start or end time: ";
        $out   = $json->encode($out);
        return $out;
    }
    $xsize      = intval($xsize);
    $xoff       = intval($xoff);
    $seconds    = $end - $start;
    $fob        = $xsize / 2;
    $spp        = round($seconds / $xsize);
    $coff       = $xoff - $fob;
    $tdiff      = $coff * $spp;
    $ns         = $start + $tdiff;
    $ne         = $end   + $tdiff;
    $end   = date($dateformat,$ne);
    $start = date($dateformat,$ns);
    $out   = array($graph,$start,$end);
    $out  = $json->encode($out);
    return $out;
}

function convertAllTimes($str){
    global $dateformat;
    $json = new Services_JSON();
    $arr  = $json->decode($str);
    $a    = array();
    foreach ($arr as $v) {
        $tmp   = array();
        $tmp[] = $v[0];
        $time  = mystrtotime($v[1]);
        if ( ! $time ){
            $time = mktime();
        }
        $time = date($dateformat,$time);
        $tmp[] = $time;
        $time  = mystrtotime($v[2]);
        if ( ! $time ){
            $time = mktime();
        }
        $time = date($dateformat,$time);
        $tmp[] = $time;
        $a[]   = $tmp;
    }
    $out  = $json->encode($a);
    return $out;
}

function convertTime($id,$str){
    global $dateformat;
    $json = new Services_JSON();
    $time = mystrtotime($str);
    if ( $time ){
        $time = date($dateformat,$time);
    }
    $a[]  = $id;
    $a[]  = $time;
    $out  = $json->encode($a);
    return $out;
}

function createGraphDragOverlay($graphnumber,$paths,$fromphp=0){
    $o = new Overlay($graphnumber,$paths,0);
    $ov = $o->draw();
    if ( $fromphp ){
        return $ov;
    }else{
        $json  = new Services_JSON();
        return $json->encode($ov);
    }
}

function createGraphImage($graphnumber,$paths,$dooverlay){
    $g = new Graph($graphnumber,$paths,$dooverlay);
    $graph = $g->draw();
    $json  = new Services_JSON();
    return $json->encode($graph);
    //return $graph;
}

function debugLoadLog($file,$n){
    global $webdir;
    $json = new Services_JSON();
    $user = $_SERVER['PHP_AUTH_USER'];
    $file = "$webdir/graphs/$user-$file";
    $fs   = filesize($file);
    if ( $fs > 200000 ){
        $f = "File too large at $fs.";
    }elseif ( $fs == 0 ){
        $f = "File is empty!";
    }else{
        $f    = file_get_contents($file);
    }
    $out  = array($n,$f);
    $out  = $json->encode($out);
    return $out;
}

function debugLogfiles(){
    global $webdir;
    $json = new Services_JSON();
    $ls   = ls_dir("$webdir/graphs");
    $out  = array();
    $ls   = $ls['files'];
    $user = $_SERVER['PHP_AUTH_USER'];
    foreach($ls as $f){
        if ( preg_match("=^$webdir/graphs/$user.*[.]log=",$f) ){
            $out[] = preg_replace("=^$webdir/graphs/$user-(.*)=",'$1',$f);
        }
    }
    $out = stripslashes($json->encode($out));
    $out .= "\n";
    return $out;
}

function debugZeroFile($file,$n){
    global $webdir;
    $json = new Services_JSON();
    $user = $_SERVER['PHP_AUTH_USER'];
    $file = "$webdir/graphs/$user-$file";
    $fp   = fopen($file,'w');
    fclose($fp);
    $f    = 'Gone now.';
    $out  = array($n,$f);
    $out  = $json->encode($out);
    return $out;
}

function deleteEvent($ids){
    global $webdir;
    if ( file_exists($webdir.'/events.db') ){
        $json = new Services_JSON();
        $ids = $json->decode($ids);
        $user = $_SERVER['PHP_AUTH_USER'];
        $stupid = 'sqlite:'.$webdir.'/events.db';
        try {
            $db = new PDO($stupid);
        }catch (PDOException $e){
            die( "Couldn't open your events DB\n");
        }
        $sql = 'DELETE FROM events WHERE id=?';
        $sth = $db->prepare($sql);
        foreach ($ids as $id){
            $sth->execute(array($id));
        }
        $sql = 'DELETE FROM t2e WHERE eventid=?';
        $sth = $db->prepare($sql);
        foreach ($ids as $id){
            $sth->execute(array($id));
        }
        $sql = 'SELECT tags.id FROM tags LEFT OUTER JOIN t2e ON t2e.tagid = tags.id WHERE eventid IS NULL';
        $res = $db->query($sql);
        $orphaned = join( ',' , $res->fetchAll(PDO::FETCH_COLUMN, 0));
        if ( ! empty($orphaned) ){
            $dql = 'DELETE FROM tags WHERE id IN ('. $orphaned .')';
            $db->query($dql);
        }

        $eventlist = eventList();
        $eventtags = eventTagList();
        return '{ "eventlist":'.$eventlist.', "eventtags":'.$eventtags.'}';
    }
}

function deletePlaylist($path,$divtoclear){
    global $webdir;
    $json = new Services_JSON();
    $user = $_SERVER['PHP_AUTH_USER'];
    $path = trim($path);
    $re   = '^'.$webdir.'/playlists/'.$user.'.*';
    if ( preg_match("`$re`",$path) ){
        if ( unlink($path.'.pspl') ){
            return $json->encode(array('SUCCESS',$divtoclear));
        }else{
            return $json->encode(array('ERROR',"Unable to remove playlist $path. See Admin."));
        }
    }else{
        return $json->encode(array('ERROR','Unable to remove stuff outside of your directory.'));
    }
    return $json->encode(array('ERROR','Impossible deletePlaylist fallthrough'));
}

function dragTime($cx,$graph,$start,$end,$xsize){
    global $dateformat;
    $json  = new Services_JSON();
    $cx    = intval($cx);
    $start = strtotime($start);
    $end   = strtotime($end);
    $w     = intval($xsize);
    if ( ! $start || ! $end ){
        $out[] = 'ERROR';
        $out[] = "Bad start or end time: ";
        $out   = $json->encode($out);
        return $out;
    }
    // if cx (change in x) is negative, we are dragging into the future
    // if cx is positive, we are dragging into the past
    // if cx is greater than xsize, the user has dragged beyond the left image width
    // if cx is less than -xsize, the user has dragged beyond the right image width
    // divide by xsize to get the percentage change
    if ( $cx > $w ){
        $cx = $w;
    }
    if ( $cx < (0 - $w) ){
        $cx = (0 - $w);
    }
    $cxp    = abs($cx) / $w;
    $span   = $end - $start;
    $change = $span * $cxp;
    if ( $cx < 0 ){
        $end   += $change;
        $start += $change;
    }else{
        $end   -= $change;
        $start -= $change;
    }
    $end   = date($dateformat,$end);
    $start = date($dateformat,$start);
    $out   = array($graph,$start,$end);
    $out  = $json->encode($out);
    return $out;
}

function eventList(){
    global $dateformat,$webdir;
    $json  = new Services_JSON();
    if ( file_exists($webdir.'/events.db') ){
        $user = $_SERVER['PHP_AUTH_USER'];
        $stupid = 'sqlite:'.$webdir.'/events.db';
        try {
            $db = new PDO($stupid);
        }catch (PDOException $e){
            echo "Couldn't open your events DB\n";
        }
        $sql = 'SELECT id,edate,title FROM events WHERE user = \'' . $user ."' ORDER BY edate";
        //$s = '';
        $out = array();
        foreach ( $db->query($sql) as $q ){
            $o = new stdClass();
            $o->id = $q['id'];
            $o->str = $q['title'] . ' ' . date($dateformat,$q['edate']);
            $out[] = $o;
            //$s .= '<input type="checkbox" id="myEvent'. $q['id'] . '">';
            //$s .= '<label for="myEvent'. $q['id'] .'">' . $q['title'] . ' ';
            //$s .= date($dateformat,$q['edate']) . "</label><br>\n";
        }
        return $json->encode($out);
        //print $s;
    }
}

function eventLookup($shortname){
    $json = new Services_JSON();
    global $webdir;
    $dbfile = $webdir.'/events.db';
    $dbcs = 'sqlite:'.$dbfile;
    try {
        $db = new PDO($dbcs);
    }catch (PDOException $e){
        return false;
    }
    $shorty = $db->quote($shortname);
    $sql    = "SELECT edate FROM events WHERE shortname like $shorty";
    $result = $db->query($sql);
    $o      = $result->fetch();
    if ( empty($o) || ! $o ){
        return false;
    }
    return $o['edate'];
}

function eventTaglist(){
    global $webdir;
    $json  = new Services_JSON();
    if ( file_exists($webdir.'/events.db') ){
        $stupid = 'sqlite:'.$webdir.'/events.db';
        try {
            $db = new PDO($stupid);
        }catch (PDOException $e){
            echo "Couldn't open your events DB\n";
        }
        $sql = 'SELECT * FROM tags ORDER BY tag';
        //$s = '';
        $out = array();
        foreach ( $db->query($sql) as $q ){
            $out[] = $q['tag'];
        }
        return $json->encode($out);
    }
}

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

function gradientArea($canvas,$color,$def){
    $steps  = 20;
    $oc     = $canvas;
    $canvas = hexrgb($canvas);
    $oco    = $color;
    $color  = hexrgb($color);
    $d['r'] = floor( ($color['r'] - $canvas['r']) );
    $d['g'] = floor( ($color['g'] - $canvas['g']) );
    $d['b'] = floor( ($color['b'] - $canvas['b']) );
    $stepper   = 1 / $steps;
    $j         = 1;
    for ( $i = 0; $i < 1 ; $i+=$stepper){
        $n    = floor(100 - (100 / $steps) * $j);
        $n    = sprintf('%02d',$n);
        if ( $n == 0 ){ continue; };
        $nr   = $color['r'] - floor(($d['r'] * sin($i)));
        $ng   = $color['g'] - floor(($d['g'] * sin($i)));
        $nb   = $color['b'] - floor(($d['b'] * sin($i)));
        if ( $nr > 255 ){ $nr = 255; }
        if ( $ng > 255 ){ $ng = 255; }
        if ( $nb > 255 ){ $nb = 255; }
        $nc   = '#' . sprintf('%02x%02x%02x',$nr,$ng,$nb);
        $str .= " CDEF:shade${def}s$j=$def,0.$n,* AREA:shade${def}s$j$nc ";
        $j++;
    }
    return $str;
}

function hexrgb($hexstr) {
    $int = hexdec($hexstr);
    return array('r' => 0xFF & ($int >> 0x10), 'g' => 0xFF & ($int >> 0x8), 'b' => 0xFF & $int);
}

function my_rrd_last($rrd){
    global $rrdtool;
    $run = "$rrdtool info $rrd";
    $str    = exec("$run 2>&1",$output,$retval);
    foreach ($output as $v) {
        if ( preg_match('/last_update/', $v) ){
            return preg_replace('/.*=\s*/','',$v);
        }
    }
}

function initPaths(){
    global $rrddir, $webdir;
    $out    = '';

    $play   = ls_dir("$webdir/playlists");
    $i      = 0;
    foreach ($play['dirs'] as $v) {
        if ( !empty($v) ){
            $out .= "'$v',";
        }
        $i++;
        if ( $i % 10 == 0){
            $out .= "\n";
        }
    }

    $nodes  = ls_dir($rrddir);
    foreach ($nodes['dirs'] as $v) {
        if ( !empty($v) ){
            $out .= "'$v',";
        }
        $i++;
        if ( $i % 10 == 0){
            $out .= "\n";
        }
    }
    // get rid of the last comma
    $out = trim($out);
    $out = substr($out, 0, -1);
    print($out);
}

function lineargradientArea($canvas,$color,$def){
    $steps  = 20;
    $oc     = $canvas;
    $canvas = hexrgb($canvas);
    $oco    = $color;
    $color  = hexrgb($color);
    $step['r'] = floor( ($color['r'] - $canvas['r']) / $steps );
    $step['g'] = floor( ($color['g'] - $canvas['g']) / $steps );
    $step['b'] = floor( ($color['b'] - $canvas['b']) / $steps );
    $sl        = sprintf('%02x%02x%02x',$color['r']*.98,$color['g']*.98,$color['b']*.98);
    $str       = "CDEF:shade${def}s98=$def,0.98,* AREA:shade${def}s98#$sl ";
    for ( $i = 1; $i < $steps ; $i++){
        $n    = floor(100 - (100 / $steps) * $i);
        $n    = sprintf('%02d',$n);
        $nr   = $color['r'] - ($step['r'] * $i);
        $ng   = $color['g'] - ($step['g'] * $i);
        $nb   = $color['b'] - ($step['b'] * $i);
        if ( $nr > 255 ){ $nr = 255; }
        if ( $ng > 255 ){ $ng = 255; }
        if ( $nb > 255 ){ $nb = 255; }
        $nc   = '#' . sprintf('%02x%02x%02x',$nr,$ng,$nb);
        $str .= " CDEF:shade${def}s$n=$def,0.$n,* AREA:shade${def}s$n$nc ";
    }
    return $str;
}

function loadPlaylist($path){
    global $webdir;
    $json = new Services_JSON();
    if ( preg_match('`\.\.`', $path) ){
        return $json->encode(array('ERROR',"What's the big idea?! $path"));
    }
    $path .= '.psp';
    if ( ! preg_match('/^\//', $path) ){
        $path = $webdir.'/playlists/'.$path;
    }
    if ( is_readable($path."l") ){
        $c    = file_get_contents($path."l");
    }elseif ( is_executable($path."x") ){
        $r    = $path."x";
        $c    = `$r`;
    }else{
        return $json->encode(array('ERROR',"Unable to read $path"));
    }
    return $c;
}

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
        if ( file_exists("$dir/.map") ){
            $fa = file("$dir/.map");
            $fa = array_map('trim',$fa);
            natsort($fa);
            $nodes['files'] = $fa;
        }
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

function massAdd($path,$limit,$target){
    $out  = array($target);
    $nout = recurseForMassAdd($path,$limit,null);
    $out  = array_merge($out , $nout);
    $json = new Services_JSON();
    $out  = $json->encode($out);
    $out  = stripslashes($out);

    return $out;
}

function mergePlaylists($paths,$name){
    global $webdir;
    $json = new Services_JSON();
    $paths = $json->decode($paths);
    if ( empty($paths) ){
        return $json->encode(array('ERROR',"No playlists."));
    }
    $a = array();
    foreach($paths as $path){
        if ( ! file_exists($path) ){
            return $json->encode(array('ERROR',"Couldn't find $path."));
        }
        $x = file_get_contents($path);
        $y = $json->decode($x);
        if ( $y[0] == 'ERROR' ){
            return $y[1];
        }
        foreach ($y as $graph){
            if ( isset($graph->description) ){
                $graph->description = '';
            }
            if ( isset($graph->regex) ){
                $graph->regex = '';
            }
            $a[] = $graph;
        }
    }
    $a = $json->encode($a);
    $name = preg_replace('`\W`','',$name);
    $name = trim($name);
    $name  = "$webdir/playlists/".$_SERVER['PHP_AUTH_USER'].'/'.$name.'.pspl';
    $fp   = @fopen($name,'w');
    if ($fp==0){
        return $json->encode(array('ERROR',"Unable to write to file [$file]. Check permissions."));
    }
    $blah = fwrite($fp,"$a");
    fclose($fp);
    return $json->encode(array('SUCCESS',$name));
}

function mystrtotime($str,$label="start or end"){
    $json = new Services_JSON();
    $str  = trim($str);
    $o    = strtotime($str);
    if ( $o ){
        return $o;
    }
    $o = eventLookup($str);
    if ( $o ){
        if ( $label == 'start' ){
            return $o - 60;
        }
        if ( $label == 'end' ){
            return $o + 60;
        }
        return $o;
    }
    $o = $json->encode(array('ERROR',"Invalid $label time"));
    die($o);
}

function newPlSub($name){
    global $webdir;
    $user = $_SERVER['PHP_AUTH_USER'];
    $name = preg_replace('`\W`','',$name);
    $json = new Services_JSON();
    if ( !empty($name) ){
        if ( ! file_exists("$webdir/playlists/$user") ){
            $b = mkdir("$webdir/playlists/$user",0755);
            if ( ! $b ){
                return $json->encode(array('ERROR',"I couldn't make/find your user playlist directory, so I'm crapping out. NOT SAVED!"));
            }
        }
        $b = mkdir("$webdir/playlists/$user/$name",0755);
        if ( $b ){
            $a = array('SUCCESS',$name);
            $out  = $json->encode($a);
            return $out;
        }else{
            $a = array('ERROR','Error creating subdirectory. See admin.');
            $out  = $json->encode($a);
            return $out;
        }
    }else{
        $a = array('ERROR','Empty directory name.');
        $out  = $json->encode($a);
        return $out;
    }
    $a    = array('ERROR','Something really bad happened when trying to create your subdirectory.');
    $out  = $json->encode($a);
    return $out;
}

function notempty($s){
    return ( !empty($s) );
}

function playlistDirs(){
    global $webdir;
    $user = $_SERVER['PHP_AUTH_USER'];
    $dirs = ls_dir("$webdir/playlists/$user");
    $dirs = $dirs['dirs'];
    $out  = '<select id="playlistsubs" name="playlistsubs">';
    $out .= '<option value="/">/</option>';
    foreach($dirs as $d){
        $d    = preg_replace("|$webdir/playlists/$user|",'',$d);
        $out .= '<option value="'.$d.'">'.$d.'/</option>';
    }
    $out .= '<option value="~">New Directory</option>';
    $out .= '</select>';
    return $out;
}

function recurseForMassAdd($path,$limit,$out){
    global $rrddir, $webdir;
    if ( ! preg_match("`^$rrddir.*`", $path) && ! preg_match("`^$webdir`", $path)){
        return "Nope.\n";
    }
    if ( preg_match('`\.\.`', $path) ){
        return "Uh, no.\n";
    }
    if ( $out == null ){
        $out = array();
    }
    // ls_dir the path
    $nodes = ls_dir($path);
    // for each dir in the results, ls_dir
    foreach ($nodes['dirs'] as $v) {
        $out = recurseForMassAdd($v,$limit,$out);
    }
    // add each array of files to a master array that gets returned
    if ( isset($nodes['files']) ){
        if ( sizeof($nodes['files']) > $limit ){
            while ( count($nodes['files']) > $limit ){
                $out[] = array_splice($nodes['files'],0,$limit);
            }
        }
        $out[] = $nodes['files'];
    }
    return $out;
}

function saveEvent($time,$title,$comment,$color='',$shortname='',$tags){
    global $webdir;
    $json = new Services_JSON();
    $time = mystrtotime($time,'event');
    if ( ! $time ){
        return $json->encode(array('ERROR','Your time sucked.'));
    }
    $user = $_SERVER['PHP_AUTH_USER'];
    $dbcs = 'sqlite:'.$webdir.'/events.db';
    try {
        $db = new PDO($dbcs);
    }catch (PDOException $e){
        return;
    }
    // save the event
    //sqlite> insert into events values (NULL, 1239717600, 'EMERGENCY LOAD TEST2','a 2nd comment','sam','#ffffff','elt2');
    $sql = 'INSERT INTO events VALUES (NULL,?,?,?,?,?,?)';
    $sth = $db->prepare($sql);
    $x   = array($time,$title,$comment,$user,$color,$shortname);
    $ret = $sth->execute($x);
    if ( !$ret ){
        return $json->encode(array('ERROR','EMERGENCY LOAD TEST (something bad happened... possibly a duplicate shortname?)'));
    }
    $eventid = $db->lastInsertId();
    // maybe save each tag
    $tags = split(' ',$tags);
    foreach ($tags as $tag){
        $tag = trim($tag);
        if ( empty($tag) ){
            continue;
        }
        $sql = 'INSERT INTO tags VALUES (NULL,?)';
        $sth = $db->prepare($sql);
        $x   = array($tag);
        $ret = $sth->execute($x);
        if ( $ret ){
            $ids[] = $db->lastInsertId();
        }else{
            $sql = 'SELECT id FROM tags WHERE tag = ?';
            $sth = $db->prepare($sql);
            $ret = $sth->execute($x);
            if ( $ret ){
                $zz = $sth->fetch();
                $ids[] = $zz[0];
            }
        }
    }
    $sql = 'INSERT INTO t2e VALUES(?,?)';
    foreach ($ids as $id){
        $sth = $db->prepare($sql);
        $x = array($id,$eventid);
        $ret = $sth->execute($x);
    }
    $eventlist = eventList();
    $eventtags = eventTagList();
    return '{ "eventlist":'.$eventlist.', "eventtags":'.$eventtags.'}';
}

function saveUserPrefs($str){
    global $webdir;
    $user = $_SERVER['PHP_AUTH_USER'];
    $dir  = $webdir.'/playlists/';
    if ( is_writable($dir) ){
        $dir .= $user;
        if ( ! file_exists($dir) ){
            $md = mkdir($dir,0755);
            if ( ! $md ){
                return "can't make playlists dir, check permissions";
            }
        }
        $dir .= '/';
    }else{
        return "playlists dir isn't writable";
    }
    $bd = "$webdir/playlists/$user/";
    if ( ! is_writable($bd)){
        return "can't write to your playlist dir";
    }
    $file = $bd.".prefs";
    $str  = stripslashes($str);
    // I really should verify this input
    if ( empty($str) ){
        return "empty prefs";
    }
    $fp   = @fopen($file,'w');
    if ($fp==0){
        return "can't open prefs file for writing";
    }
    $blah = fwrite($fp,"$str");
    fclose($fp);
    return "saved";
}

function savePlaylist($name,$pldir,$verify,$str){
    global $webdir;
    $name = preg_replace('`\W`','',$name);
    $name = trim($name);
    $dir  = $webdir.'/playlists/';
    $json = new Services_JSON();
    $user = $_SERVER['PHP_AUTH_USER'];
    if ( $pldir == '//' ){
        $pldir = '/';
    }
    if ( $user == 'guest' ){
        return $json->encode(array('ERROR',"Guest users cannot create playlists. Get a real account!"));
    }
    if ( is_writable($dir) ){
        $dir .= $user;
        if ( ! file_exists($dir) ){
            $md = mkdir($dir,0755);
            if ( ! $md ){
                return $json->encode(array('ERROR',"I couldn't make/find your user playlist directory, so I'm crapping out. NOT SAVED!"));
            }
        }
        $dir .= '/';
    }else{
        return $json->encode(array('ERROR','General playlist directory not writable. NOT SAVED!'));
    }
    $bd = "$webdir/playlists/$user/$pldir";
    if ( ! is_writable($bd)){
        $a = array('ERROR',"Error creating subdirectory [$pldir]. See admin.");
        $out  = $json->encode($a);
        return $out;
    }
    if ( empty($name) ){
        return $json->encode(array('ERROR',"Your name sucked so I threw it out. I also didn't save your data. Life is hard."));
    }
    $file = $dir.$pldir.$name.'.pspl';
    if ( empty($str) ){
        return $json->encode(array('ERROR','Empty playlist!'));
    }
    $tmped = 0;
    if ( file_exists($file) && $verify == 1 ){
        $file .= ".tmp";
        $tmped = 1;
    }
    $fp   = @fopen($file,'w');
    if ($fp==0){
        return $json->encode(array('ERROR',"Unable to write to file [$file]. Check permissions."));
    }
    $blah = fwrite($fp,"$str");
    fclose($fp);
    if ( $tmped == 1 ){
        return $json->encode(array('VERIFY',$pldir.$name.'.pspl.tmp'));
    }
    $tname = $pldir.$name;
    $out  = $json->encode(array('SUCCESS',"Yea, $pldir maybe I saved your playlist. Who knows really? Not me. The URL for this playlist is ",$_SERVER['PHP_SELF'].'?pl='.$user.$tname,$dir.$tname));
    return $out;
}

function rmTmpPlaylist($file){
    global $webdir;
    $json = new Services_JSON();
    if ( preg_match('`\.\.`', $file) ){
        return $json->encode(array('ERROR',"Uh, No."));
    }
    $dir  = $webdir.'/playlists/';
    $user = $_SERVER['PHP_AUTH_USER'];
    $r = unlink("$dir/$user/$file");
    if ( $r ){
        return $json->encode(array('SUCCESS',"Ok then."));
    }else{
        return $json->encode(array('ERROR',"Some problem unlinking the tempfile for this playlist. See your admin, perhaps yourself. Yea see yourself as soon as possible."));
    }
}

function selTime($graph,$s1,$s2p,$e1,$e2p){
    global $dateformat;
    $json  = new Services_JSON();
    $start = strtotime($s1);
    $end   = strtotime($e1);
    if ( ! $start || ! $end ){
        $out[] = 'ERROR';
        $out[] = "Bad start or end time: ";
        $out   = $json->encode($out);
        return $out;
    }
    $whole = $end - $start;
    $s2    = floor($start + ($whole * $s2p));
    $e2    = floor($start + ($whole * $e2p));
    $start = date($dateformat,$s2);
    $end   = date($dateformat,$e2);
    $a     = array($graph,$start,$end);
    return $json->encode($a);
}

function showTreeChild($id,$path){
    global $rrddir, $webdir;
    $nodes  = ls_dir($path);
    $nodes['id'] = $id;
    $json  = new Services_JSON();
    $nodes = $json->encode($nodes);
    $nodes = stripslashes($nodes);

    return $nodes;
}

function unTmpPlaylist($file){
    global $webdir;
    $json = new Services_JSON();
    if ( preg_match('`\.\.`', $file) ){
        return $json->encode(array('ERROR',"Uh, No."));
    }
    $dir  = $webdir.'/playlists/';
    $user = $_SERVER['PHP_AUTH_USER'];
    $newf = preg_replace('/.tmp$/','',$file);
    $outy = preg_replace('/.pspl$/','',$newf);
    $r = rename("$dir/$user/$file","$dir/$user/$newf");
    return $json->encode(array('SUCCESS',"Yea, $user maybe I saved your playlist. Who knows really? Not me. The URL for this playlist is ",$_SERVER['PHP_SELF'].'?pl='.$user.$outy,$dir.$user.$outy));
}

function zoomTimes($start,$end,$amt,$action,$graph){
    global $dateformat;
    $os    = $start;
    $oe    = $end;
    $start = strtotime($start);
    $end   = strtotime($end);
    $dur   = $end - $start;
    $amt   = floatval($amt);
    $mv    = round($dur * $amt);
    $json  = new Services_JSON();
    switch ($action){
        case 'left':
            $start -= $mv;
            $end   -= $mv;
            break;
        case 'right':
            $start += $mv;
            $end   += $mv;
            break;
        case 'out':
            $start -= $mv;
            $end   += $mv;
            break;
        case 'in':
            if ( $amt >= .5 ){
                $amt = .25;
            }
            $mv     = round($dur * $amt);
            $start += $mv;
            $end   -= $mv;
            if ( $end <= $start ){
                $end = (2 + $start);
            }
            break;
        default:
            $a     = array($os,$oe,$graph);
            return $json->encode($a);

    }
    $start = date($dateformat,$start);
    $end   = date($dateformat,$end);
    $a     = array($start,$end,$graph,$amt);
    return $json->encode($a);
}

sajax_init();
//$sajax_debug_mode = 1;
$exports = array('clickToCenterTime','convertAllTimes','convertTime','createGraphDragOverlay','createGraphImage','deleteEvent','deletePlaylist','dragTime','findMatches','loadPlaylist','massAdd','mergePlaylists','newPlSub','rmTmpPlaylist','saveEvent','savePlaylist','saveUserPrefs','selTime','showTreeChild','unTmpPlaylist','zoomTimes');
if ( in_array($_SERVER['PHP_AUTH_USER'],$admins) ){
    array_push($exports,'debugLogfiles','debugLoadLog','debugZeroFile');
}
//Stupid sajax!
call_user_func_array('sajax_export',$exports);
//sajax_export(&$exports);
sajax_handle_client_request();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
    <title>Jart <?php echo $version; ?></title>
    <link rel="stylesheet" type="text/css" href="css/reset-min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="css/fonts-min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="css/grids-min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="colorPicker.css" media="screen">
    <script type="text/javascript" src="js.php"></script>
<?php 
/*
    <script type="text/javascript" src="js/prototype.js"></script>
    <script type="text/javascript" src="js/prototype-plus.js"></script>
 */
?>
    <script type="text/javascript" src="js/scriptaculous.js"></script>
<?php 
/*
    <script type="text/javascript" src="js/yahoo.color.js"></script>
    <script type="text/javascript" src="js/colorPicker.js"></script>
 */
?>
    <script type="text/javascript" src="js/graph.js.php"></script>
    <script type="text/javascript">
<?php
    sajax_show_javascript();
?>
    var debuglog = 0;
    var saveplct = 0;
    var alls     = 0;
    var eventTags=<?php print eventTaglist(); ?>;

    function debugLogTog(){
        if ( $F('debuglog') == 'on' ){
            debuglog = 1;
        }else{
            debuglog = 0;
        }
    }
    var dsPicker = (function(){

        var user   = '<?php echo $_SERVER['PHP_AUTH_USER']?>';
        var webdir = '<?php echo $webdir?>';
        var initialPaths    = '';
<?php
$myEvents = eventList();
print "        var myEvents=$myEvents;\n";
?>
<?php if (isset($_GET['pl']) ){ ?>
        var initialPlaylist = '<?php echo $_GET['pl'] ?>';
<?php }else{ ?>
        var initialPlaylist = '';
<?php } ?>

        function navVis(e){
            var me = $('nav' + e.target.id);
            var mevis = me.style.display;
            var divies = $$('div.closeme');
            divies.each(function(d){
                d.style.display="none";
            })
            if ( mevis == "none" ){
                me.style.display = "";
            }else{
                me.style.display = "none";
            }
        }

        function massAdd(e,path){
            if ( e.target.id ){
                e.target.innerHTML = '<img src="img/scanner-transparent-back.gif" class="allthrobber">';
            }
            x_massAdd(path,G.defaultpathlimit,e.target.id,massAddCB);
        }

        function massAddCB(s){
            var mypaths = s.evalJSON();
            var target  = '';
            //console.log(mypaths);
            var i=0;
            mypaths.each(function(paths){
                if ( i == 0 ){
                    target  = paths;
                    i++;
                }else{
                    G.addGraph();
                    var label = paths[0].replace(/.*\/([^/]+\/[^/]+)\/[^/]+.rrd/,'$1');
                    G.graphs[G.cg].graphlabel = label;
                    paths.each(function(path){
                        if ( path != '' ){
                            G.addRrdToGraph(path,0);
                        }
                    })
                }
            })
            G.drawAllGraphs();
            $(target).innerHTML = '[All]';
        }

        function showTreeChild(e,p){
            var me = $(e.target.parentNode.id + 'hide')
            if ( me ){
                Element.toggle(me);
            }else{
                Element.show('pickerspinner');
                x_showTreeChild(e.target.parentNode.id,p,showTreeChildCB);
            }
        }

        function showTreeChildCB(s){
            var nodes = s.evalJSON();
            var par   = $(nodes.id);
            //create a div called id+hide
            var hider = document.createElement('div');
            hider.id  = nodes.id + 'hide';
            if ( nodes.dirs ){

                nodes.dirs.each(function(path){
                    //create divs for each path
                    var pdiv = document.createElement('div');
                    pdiv.id  = path.replace(/\W/g,'');
                    Element.setStyle(pdiv, { paddingLeft:'15px' } );
                    //create the span
                    var span = document.createElement('span');
                    span.className = 'clickable';
                    var me = path.replace(/.*\/([^/]*).*/,'$1');
                    //mefirst = me.replace(/(.).*/,'$1').toUpperCase();
                    //me      = me.replace(/.(.*)/,mefirst + '$1');
                    var txt  = document.createTextNode( me );
                    span.appendChild(txt);
                    //attach the event listener
                    Event.observe(span,'click',function(e){ showTreeChild(e,path) }.bindAsEventListener());
                    pdiv.appendChild(span);
                    if ( ! path.match(/.*\/playlists\/.*/) ){
                        var span = document.createElement('span');
                        span.className = 'clickable smalltxt';
                        span.id = 'alls-' + alls;
                        alls++;
                        var txt  = document.createTextNode( ' [All]' );
                        span.appendChild(txt);
                        //attach the event listener
                        Event.observe(span,'click',function(e){ massAdd(e,path) }.bindAsEventListener());
                        pdiv.appendChild(span);
                    }else{
                        txt = document.createTextNode('/');
                        span.appendChild(txt);
                    }
                    hider.appendChild(pdiv);
                })
            }
            if ( nodes.files ){
                var par = $(nodes.id);
                var i   = 0;
                nodes.files.each(function(path){
                    var div  = document.createElement('div');
                    if ( path.match(/.*\/playlists\/.*/) ){
                        nameAttachPlaylist(path,div);
                    }else{
                        div.className = 'roll';
                        div.style.height = "20px";
                        div.id = nodes.id + '-' + i;
                        i++;
                        var span = document.createElement('span');
                        span.className = 'clickable';
                        var txt  = document.createTextNode( path.replace(/.*\/([^/]*)(?:[.]rrd|[.]psp.)/,'$1') );
                        span.appendChild(txt);
                        Element.setStyle(span, { paddingLeft:'15px' } );
                        div.appendChild(span);
                        Event.observe(span,'click',function(e){ G.addRrdToGraph(path,1) }.bindAsEventListener());
                    }
                    hider.appendChild(div);
                })
            }
            par.appendChild(hider);
            Element.hide('pickerspinner');
        }

        function nameAttachPlaylist(path,div){
            var opath = path;
            var path  = path.replace(/[.]psp.$/,'');
            var id    = path.replace(/[/.]/g,'');
            var ex    = $(id);
            if ( ex !== null ){
                Element.show(ex);
                return;
            }
            div.className = 'roll';
            div.style.height = "20px";
            div.id = id;
            var p  = '^' + webdir + '/playlists/' + user + '/';
            var re = new RegExp(p);
            if ( path.match(re) ){
                var sp  = document.createElement('span');
                sp.className = 'closebutton';
                var img = document.createElement('img');
                img.src = 'img/gtk-delete.png';
                img.title = 'Delete this playlist';
                sp.appendChild(img);
                Event.observe(img,'click',function(e){ if (dsPicker.verify('deleteplaylist') ){deletePlaylist(path,div.id);} }.bindAsEventListener());
                div.appendChild(sp);
                var ck = document.createElement('input');
                ck.type = 'checkbox';
                ck.value = opath;
                ck.title = 'Select for Merging';
                ck.className = 'mergecheckbox';
                if ( G.showMergeCheckboxes == 0 ){
                    ck.style.display = 'none';
                }
                Event.observe(ck,'click',showMerge.bindAsEventListener());
                div.appendChild(ck);
            }
            var span = document.createElement('span');
            span.className = 'clickable';
            var txt  = document.createTextNode( path.replace(/.*\/([^/]*)$/,'$1') );
            span.appendChild(txt);
            Element.setStyle(span, { paddingLeft:'15px' } );
            div.appendChild(span);
            Event.observe(span,'click',function(e){ loadPlaylist(path) }.bindAsEventListener());
            var txt   = document.createTextNode(' ');
            div.appendChild(txt);
            var urla  = document.createElement('a');
            urla.className = 'smalltxt';
            urla.href = '<?php echo $_SERVER['PHP_SELF'] ?>?pl=' + path.replace(/.*playlists\/(.*)/,'$1');
            var txt   = document.createTextNode('[URL]');
            urla.appendChild(txt);
            div.appendChild(urla);
        }

        function showMerge(){
            var pls = $$('input.mergecheckbox');
            var i = 0;
            pls.each(function(p){
                if ( p.checked ){
                    i++;
                }
            })
            if ( i > 1 ){ // 1, not 0 because you don't want to merge 1 playlist.
                Element.show('mergedplaylistname');
            }else{
                var inp = $('mergedplaylistname');
                inp.value = 'Input merged playlist name and hit Enter';
                Element.hide(inp);
            }
        }

        function mergePlaylists(){
            name = $('mergedplaylistname').value;
            if ( name == '' ){
                handleError('You must supply a name for the merged playlist.');
                return;
            }
            var pls = $$('input.mergecheckbox');
            var i = [];
            pls.each(function(p){
                if ( p.checked ){
                    i.push(p.value);
                }
            })
            i = Object.toJSON(i);
            x_mergePlaylists(i,name,mergePlaylistsCB);
        }

        function mergePlaylistsCB(s){
            a = s.evalJSON();
            if ( a[0] != 'ERROR' ){
                var par = a[1].replace(/(.*\/).*/,'$1');
                par     = par.replace(/\W/g,'');
                par    += 'hide';
                par     = $(par);
                if ( par ){
                    var div   = document.createElement('div');
                    nameAttachPlaylist(a[1],div);
                    par.appendChild(div);

                }
                handleError('Success!');
                var pls = $$('input.mergecheckbox');
                pls.each(function(p){
                    p.checked = false;
                })
                var inp = $('mergedplaylistname');
                inp.value = 'Input merged playlist name and hit Enter';
                Element.hide(inp);
            }else{
                handleError(a[1]);
            }
        }

        function findAndLoad(a){
            re = a[0].regexlive;
            if ( a[0].pathbreaksgraph == 'on'){
                pb = 1;
            }else{
                pb = 0;
            }
            if ( a[0].unlimited == 'on' ){
                G.defaultpathlimit = 2000;
            }else{
                G.defaultpathlimit = 15;
            }
            x_findMatches(re,pb,G.defaultpathlimit,findAndLoadCB);
        }
        function findAndLoadCB(s){
            var list = s.evalJSON();
            if ( G.graphs[0].regexavg == 'on' ){
                var doavg = 1;
            }else{
                var doavg = 0;
            }
            if ( G.graphs[0].regextotals == 'on' ){
                var dototals = 1;
            }else{
                var dototals = 0;
            }
            if ( G.graphs[0].regexjusttotals == 'on' ){
                var dojusttotals = 1;
            }else{
                var dojusttotals = 0;
            }
            G.cg = 0;
            G.graphs = [];
            G.addGraph();
            list.graphs.each(function(g){
                //console.log(g);
                G.addGraph();
                g.each(function(path){
                    //console.log(path);
                    G.addRrdToGraph(path,0);
                });
            });
            if ( doavg == 1 ){
                regexAddAvg();
            }
            if ( dototals == 1 ){
                regexAddTotal();
            }
            if ( dojusttotals == 1 ){
                regexJustTotal();
            }
            G.drawAllGraphs();
        }
        function findMatches(){
            //$('total').checked = false;
            //$('justtotal').checked = false;
            var re = $F('regextext');
            re     = re + '[.]rrd$';
            //G.graphs.regex = re;
            var pb = $F('regexgraphpathbreaks');
            if ( pb == 'on' ){
                pb = 1;
            }else{
                pb = 0;
            }
            //G.graphs.gpb = pb;
            G.closeAllGraphs(0);
            G.cg = 0;
            G.graphs = [];
            G.addGraph();
            x_findMatches(re,pb,G.defaultpathlimit,findMatchesCB);
        }
        function findMatchesCB(s){
            var list = s.evalJSON();
            var rl = $('regexerlist');
            rl.value = '';
            rl.value = list.string;
            //console.log(list);
            list.graphs.each(function(g){
                //console.log(g);
                G.addGraph();
                var label = g[0].replace(/.*\/([^/]+\/[^/]+)\/[^/]+.rrd/,'$1');
                G.graphs[G.cg].graphlabel = label;
                g.each(function(path){
                    //console.log(path);
                    G.addRrdToGraph(path,0);
                });
            });
            G.graphs[0].regex = $F('regextext');
            Element.show('regexersaver');
            //console.log(G.graphs);
        }
        function unlimited(){
            var u = $('regexunlimited');
            //console.log(u.checked);
            if ( u.checked ){
                G.defaultpathlimit = 2000;
            }else{
                G.defaultpathlimit = 15;
            }
            //console.log(G.defaultpathlimit);
        }
        function regexAddAvg(){
            G.graphs.each(function(graph,key){
                if ( G.graphs[key].total == 0 ){
                    G.graphs[key].avg = 1;
                    G.cg = key;
                    G.addRrdToGraph('avg',0);
                }
            })
        }
        function regexAddTotal(){
            G.graphs.each(function(graph,key){
                if ( G.graphs[key].total == 0 ){
                    G.graphs[key].total = 1;
                    G.cg = key;
                    G.addRrdToGraph('total',0);
                }
            })
        }
        function regexJustTotal(){
            G.graphs.each(function(graph,key){
                G.graphs[key].justtotal = 1;
            })
        }
        function help(e){
            var f = e.target.id;
            var d = $(f+'d');
            if ( d ){
                var o = document.viewport.getScrollOffsets();
                d.style.top = o[1] + 5 + 'px';
                d.style.left = o[0] + 5 + 'px';
                Element.show(d);
            }
        }
        function hidehelp(e){
            var f = e.target.id;
            var d = $(f+'d');
            if ( d ){
                Element.hide(d);
            }
        }

        function init(){
            var pickerButton    = $('pickerbutton');
            var regexerButton   = $('regexerbutton');
            Event.observe(pickerButton,'click',function(){showPicker(0)}.bindAsEventListener());
            Event.observe(regexerButton,'click',showRegexer.bindAsEventListener());
            // regexer
            var go = $('regexgo');
            Event.observe(go,'click',findMatches.bindAsEventListener());
            var save = $('regexsaveit');
            Event.observe(save,'click',regexSavePlaylist.bindAsEventListener());
            var regdraw = $('regexdraw');
            Event.observe(regdraw,'click',regexDraw.bindAsEventListener());
            var u  = $('regexunlimited');
            u.checked = false;
            $('regexgraphpathbreaks').checked = false;
            Event.observe(u,'change',unlimited.bindAsEventListener());
            $$("img.helpbutton").each(function(hele){
                Event.observe(hele,'mouseover',dsPicker.help.bindAsEventListener());
                Event.observe(hele,'mouseout',dsPicker.hidehelp.bindAsEventListener());
            });
            var regtot = $('regexjusttotal');
            Event.observe(regtot,'change',toggleRegexTotals.bindAsEventListener());

            // picker
            var letter          = '';
            var letterDiv       = $('alphanav');
            var plLetter        = document.createElement('span');
            plLetter.className  = "clickable";
            plLetter.id         = "PL";
            var plLetterinsides = document.createTextNode('[PL]');
            plLetter.appendChild(plLetterinsides);
            //var plLetter        = $('PL');
            letterDiv.appendChild(plLetter);
            Event.observe(plLetter,'click',navVis.bindAsEventListener());
            var hostList        = $('hostlist');
            var navPL           = document.createElement('div');
            navPL.id            = 'navPL';
            navPL.className     = 'closeme';
            navPL.style.display = 'none';
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.size = 40;
            inp.value = 'Input merged playlist name and hit Enter';
            inp.id = 'mergedplaylistname';
            Event.observe(inp,'click',function(){ inp.value = '' }.bindAsEventListener());
            inp.style.display = 'none';
            navPL.appendChild(inp);
            var br = document.createElement('br');
            navPL.appendChild(br);
            var navDiv;  // eg navC
            var hostDiv; // eg wwwrrdbeansborusgedu
            dsPicker.initialPaths.each(function(path){
                var me  = path.replace(/.*\/([^/]*).*/,'$1');
                mefirst = me.replace(/(.).*/,'$1').toUpperCase();
                me      = me.replace(/.(.*)/,mefirst + '$1');
                var nl = me.charAt(0);
                if ( letter != nl ){
                    if ( path.match(/.*\/playlists\/.*/) ){
                        var hostDiv = document.createElement('div');
                        meid    = path.replace(/\W/g,'');
                        hostDiv.id = meid;
                        var span = document.createElement('span');
                        var txt  = document.createTextNode(me);
                        span.appendChild(txt);
                        span.className = 'clickable';
                        Event.observe(span,'click',function(e){ showTreeChild(e,path) }.bindAsEventListener());
                        hostDiv.appendChild(span);
                        navPL.appendChild(hostDiv);
                        return;
                    }
                    letter = nl;
                    if ( navDiv != null ){
                        hostList.appendChild(navDiv);
                    }
                    var tmp = document.createElement('span');
                    var txt = document.createTextNode('[' + letter + ']');
                    tmp.appendChild(txt);
                    tmp.id  = letter;
                    tmp.className = 'clickable';
                    Event.observe(tmp,'click',navVis.bindAsEventListener());
                    letterDiv.appendChild(tmp);
                    if ( letter == 'L' ){
                        br = document.createElement('br');
                        letterDiv.appendChild(br);
                    }
                    navDiv = document.createElement('div');
                    navDiv.id  = 'nav' + letter;
                    navDiv.style.display = 'none';
                    navDiv.className     = 'closeme';
                }
                var hostDiv = document.createElement('div');
                meid    = path.replace(/\W/g,'');
                hostDiv.id = meid;
                var span = document.createElement('span');
                var txt  = document.createTextNode(me);
                span.appendChild(txt);
                span.className = 'clickable';
                Event.observe(span,'click',function(e){ showTreeChild(e,path) }.bindAsEventListener());
                hostDiv.appendChild(span);
                var span = document.createElement('span');
                span.className = 'clickable smalltxt';
                span.id        = 'alls-' + alls;
                alls++;
                var txt  = document.createTextNode( ' [All]' );
                span.appendChild(txt);
                //attach the event listener
                Event.observe(span,'click',function(e){ massAdd(e,path) }.bindAsEventListener());
                hostDiv.appendChild(span);
                navDiv.appendChild(hostDiv);
            })
            if ( navDiv != null ){
                hostList.appendChild(navDiv);
            }
            hostList.appendChild(navPL);
            var button = $('saveplaylistbutton');
            Event.observe(button,'click',function(e){ toggleControl(e,'containerforplaylistdialog'); $('playlistname').focus() }.bindAsEventListener() );
            if ( initialPlaylist != undefined && initialPlaylist != '' ){
                loadPlaylist(initialPlaylist);
            }
            Event.observe('playlistsubs','change',playlistSubChange.bindAsEventListener() );
            Event.observe('newsubname','keyup',newPlSubMon.bindAsEventListener() );

            $('eventSaveButton').onclick = function(){saveEvent()};
            $('eventsIcon').onclick = function(){Element.toggle('eventControls')};
            $('eventAddTab').onclick = function(){genericTabber(['addEvent','delEvent'],'addEvent')};
            $('eventDeleteTab').onclick = function(){genericTabber(['addEvent','delEvent'],'delEvent')};
            $('eventDeleteButton').onclick = function(){ deleteEvent(); };
            $('eventClearButton').onclick = function(){ x=['eventTime','eventTitle','eventShortName','eventComment','eventTags']; x.each(function(e){$(e).value=''});};
            $('eventCancelButton').onclick = function(){ Element.toggle('eventControls')};
            $('eventCancelButtonTwo').onclick = function(){ Element.toggle('eventControls')};
            populateEventDeletion();
            populateEventTags();
            var s = myEvents.size();
            var c = G.getColor(s);
            //var ec = $('eventColor');
            //ec.value = c.sub(/#/,'');
            //var eo = $('eventColoropacity');
            //eo.value = 'ff';
            //var ece = $('eventColorExample');
            //new Control.ColorPicker( ec, { 'swatch':ece, 'opacityField':eo });
        }

        function populateEventDeletion(){
            var s = myEvents.size();
            var c = G.getColor(s);
            //var ec = $('eventColor');
            //ec.value = c.sub(/#/,'');
            //var eo = $('eventColoropacity');
            //eo.value = 'ff';
            //var ece = $('eventColorExample');
            //ece.style.backgroundColor = c;

            var c = $('delEventList');
            c.innerHTML = '';
            myEvents.each(function(e){
                var i = document.createElement('input');
                i.type = 'checkbox';
                i.value = 'myEvent' + e.id;
                c.appendChild(i);
                var l = document.createElement('label');
                var t = document.createTextNode(e.str);
                l.appendChild(t);
                c.appendChild(l);
                var b = document.createElement('br');
                c.appendChild(b);
            });
        }

        function populateEventTags(div,input){
            if ( div === undefined ){
                var div   = $('existingEventTags');
                var input = $('eventTags');
            }
            div.innerHTML = '';
            var z = eventTags;
            if ( div.id != 'existingEventTags' ){
                z = ['all','none','my'].concat(eventTags);
            }
            var x = z.size();
            var y = 0;
            z.each(function(tag){
                y++;
                var span = document.createElement('span');
                span.className = 'eventTagWords';
                var txt  = document.createTextNode(tag);
                span.appendChild(txt);
                div.appendChild(span);
                if ( y < x ){
                    var txt  = document.createTextNode(', ');
                    div.appendChild(txt);
                }
            Event.observe(span,'click',function(){toggleEventTag(tag,input)});
            });
        }
        function toggleEventTag(tag,ele){
            var solos = ['all','none','my']
            if ( ele.value == '' || ele.value == 'all' || solos.include(tag) ){
                ele.value = tag;
            }else{
                // look to see if it's already there and deal w/ special cases
                var x = ele.value.split(' ');
                if ( x.include(tag) ){
                    var v = 0;
                }else{
                    ele.value = ele.value + ' ' + tag;
                    var v = 1;
                    var x = ele.value.split(' ');
                }
                var y = x.reject(function(e){
                    if ( v == 0 ){
                        return ( e == tag || solos.include(e) )
                    }else{
                        return ( solos.include(e) )
                    }
                });
                ele.value = y.sort().join(' ');
            }
            ele.fire('dingus:change');
            ele.focus();
        }
        function saveEvent(){
            var time = $('eventTime').value;
            var comment = $('eventComment').value;
            var title = $('eventTitle').value;
            var shorty = $('eventShortName').value;
            var tags = $('eventTags').value;
            //var color = $('eventColor').value;
            var color = '';
            var x = $('eventTags').value;
            var changed = 0;
            x.split(' ').each(function(tag){
                if ( ! eventTags.include(tag) ){
                    eventTags.push(tag);
                    changed = 1;
                }
            });
            if ( changed == 1 ){
                populateEventTags();
            }
            if ( time == '' ){
                alert("no timey, no savey");
                return;
            }
            if ( title == '' ){
                alert("Title is required.");
                return;
            }
            if ( shorty == '' ){
                alert("You have to have a short-name, sorry.");
                return;
            }
            Element.hide('eventControls');
            x_saveEvent(time,title,comment,'#'+ color,shorty,tags,saveEventCB);
        }
        function saveEventCB(s){
            x = s.evalJSON();
            if ( x[0] == 'ERROR' ){
                handleError(x[1]);
            }else{
                handleError('The event OF A LIFETIME.');
                myEvents = x.eventlist;
                eventTags = x.eventtags;
                populateEventDeletion();
                populateEventTags();
                $$('div.eventcontainer').each(function(div){
                    me = div.id.sub(/eventcontrolcontainerfor-/,'');
                    var zz = $('eventselectorfor-' + me);
                    var aa = $('eventtagsfor-' + me );
                    populateEventTags(aa,zz);
                });
            }
        }
        function deleteEvent(){
            var ids = $$('div#delEventList input');
            var a   = [];
            ids.each(function(id){
                if ( id.checked ){
                    a.push(id.value.sub(/myEvent/,''));
                }
            });
            a = Object.toJSON(a);
            x_deleteEvent(a,deleteEventCB);
        }
        function deleteEventCB(s){
            x = s.evalJSON();
            myEvents = x.eventlist;
            eventTags = x.eventtags;
            populateEventDeletion();
            populateEventTags();
            $$('div.eventcontainer').each(function(div){
                me = div.id.sub(/eventcontrolcontainerfor-/,'');
                var zz = $('eventselectorfor-' + me);
                var aa = $('eventtagsfor-' + me );
                populateEventTags(aa,zz);
            });
        }
        function genericTabber(a,which){
            a.each(function(tab){
                if ( tab == which ){
                    Element.show(tab);
                }else{
                    Element.hide(tab);
                }
            });
        }

        function savePlaylist(){
            var x = 0;
            G.graphs.each(function(graph){
                if ( graph && graph.paths && graph.paths[0] ){
                    x++;
                }
            })
            Element.hide('containerforplaylistdialog');
            if ( x == 0 ){
                handleError('Nothing to save');
                return;
            }
            var pltimes = $F('playlisttimes');
            if ( pltimes == 'on' ){
                convertAllTimes();
            }else{
                realSavePlaylist();
            }
        }
        function regexSavePlaylist(){
            var plname = $F('regexsavename');
            var pldir  = '/';
            G.graphs[0].regex = $F('regextext');
            var ro = $('regexonly');
            if ( ro.checked ){
                G.graphs = [];
                G.addGraph();
                G.graphs[0].regexlive = $F('regextext') + '[.]rrd$';
                G.graphs[0].pathbreaksgraph = $F('regexgraphpathbreaks');
                G.graphs[0].unlimited = $F('regexunlimited');
                G.graphs[0].regextotals = $F('regextotal');
                G.graphs[0].regexjusttotals = $F('regexjusttotal');
                G.graphs[0].regexavg = $F('regexavg');
            }
            var avg = $('regexavg');
            if ( avg.checked ){
                regexAddAvg();
            }
            var ts = $('regextotal');
            if ( ts.checked ){
                regexAddTotal();
            }
            var jt = $('regexjusttotal');
            if ( jt.checked ){
                regexJustTotal();
            }
            var playlist = Object.toJSON(G.graphs);
            x_savePlaylist(plname,pldir,G.confirmoverwriteplaylist,playlist,savePlaylistCB);
        }
        function realSavePlaylist(){
            var plname = $F('playlistname');
            var pldir  = $F('playlistsubs') + '/';
            // Wow, this code is hacked to death.
            // if you delete the first graph, you might not be able to save your description
            // unless we re-create the G.graphs array sans nils
            var ted = [];
            G.graphs.each(function(graph){
                if ( graph && graph.paths && graph.paths[0] ){
                    ted.push(graph);
                }
            })
            ted[0].description = $F('playlistdescription');
            //console.log(pldir+plname);
            var playlist = Object.toJSON(ted);
            x_savePlaylist(plname,pldir,G.confirmoverwriteplaylist,playlist,savePlaylistCB);
        }
        function toggleRegexTotals(){
            var ts = $('regextotal');
            var jt = $('regexjusttotal');
            if ( jt.checked ){
                ts.checked = true;
            }
        }
        function regexDraw(){
            showPicker(1);
            var avg = $('regexavg');
            if ( avg.checked ){
                regexAddAvg();
            }
            var ts = $('regextotal');
            if ( ts.checked ){
                regexAddTotal();
            }
            var jt = $('regexjusttotal');
            if ( jt.checked ){
                regexJustTotal();
            }
            G.drawAllGraphs();
        }
        function verifyPlaylistOverwrite(s){
                var pl = s.evalJSON();
                pl = pl[1];
                var yn = confirm('File ' + pl.replace(/.tmp/,'') + ' exists. Overwite?');
                if ( yn ){
                    x_unTmpPlaylist(pl,savePlaylistCB);
                }else{
                    x_rmTmpPlaylist(pl,rmTmpPlaylistCB);
                }
        }
        function rmTmpPlaylistCB(s){
            var msg = s.evalJSON();
            if ( msg[0] == 'ERROR' ){
                handleError(msg[1]);
            }else{
                removeErrors();
                var out = document.createTextNode(msg[1]);
                $('errorspace').appendChild(out);
                rmErrorButton();
                $('playlistname').value = '';
                $('playlistsubs').value = '/';
            }
        }
        function savePlaylistCB(s){
            //console.log(s);
            if ( s.match(/\[\"ERROR\",/) ){
                var error = s.evalJSON();
                handleError(error[1]);
            }else if ( s.match(/\[\"VERIFY\",/) ){
                verifyPlaylistOverwrite(s);
                return;
            }else{
                removeErrors();
                var msg = s.evalJSON();
                var out = document.createTextNode(msg[1]);
                var a   = document.createElement('a');
                var o   = document.createTextNode(msg[2]);
                a.href  = msg[2];
                a.appendChild(o);
                $('errorspace').appendChild(out);
                $('errorspace').appendChild(a);
                // magically add the playlist to the div if it exists
                var par = msg[3].replace(/(.*\/).*/,'$1');
                par     = par.replace(/\W/g,'');
                par    += 'hide';
                par     = $(par);
                if ( par ){
                    var div   = document.createElement('div');
                    nameAttachPlaylist(msg[3] + '.pspl',div);
                    par.appendChild(div);

                }
                rmErrorButton();
            }
            $('playlistname').value = '';
            $('playlistsubs').value = '/';
        }
        function deletePlaylist(path,n){
            x_deletePlaylist(path,n,deletePlaylistCB);
        }
        function deletePlaylistCB(s){
            var a = s.evalJSON();
            if ( a[0] == 'ERROR' ){
                handleError(error[1]);
            }else{
                if ( a[1] ){
                    Element.hide(a[1]);
                    return;
                }
            }
            handleError(s);
        }
        function playlistSubChange(e){
            var n = e.target.value;
            if ( n == '~' ){
                Element.show('newsubcontainer');
                $('newsubname').focus();
                e.target.value = '/';
            }
        }
        function newPlSub(){
                Element.hide('newsubcontainer');
                var name = $('newsubname').value;
                x_newPlSub(name,newPlSubCB);
        }
        function newPlSubCB(s){
            //console.log(s);
            var a = s.evalJSON();
            if ( a[0] == 'ERROR' ){
                handleError(error[1]);
            }else{
                if ( a[1] ){
                    var o = document.createElement('option');
                    o.value = '/' + a[1];
                    o.text  = '/' + a[1] + '/';
                    var p = $('playlistsubs')
                    p.appendChild(o);
                    p.value = '/' + a[1];
                }
            }

        }
        function newPlSubMon(e){
            var v = e.target.value;
            // there should be something in here to disable the go button
            // but I do not want to do the lookup every time... //FIX
            //if ( v.match(/[.~!@#\$\%\^\&\*\(\)\s|,<>\\`]/) ){
            if ( v.match(/\W/) ){
                e.target.className = 'redbg';
            }else{
                e.target.className = 'whitebg';
            }
        }
        function loadPlaylist(path){
            var op = path.replace(/.*playlists\/(.*)/,'$1');
            document.title = op + " -- Jart <?php echo $version ?>";
            $('playlistlink').innerHTML = 'Playlist: <a href="<?php $_SERVER['PHP_SELF'] ?>?pl=' + op + '">' + op + '</a>';
            Element.show('playlistdisplay');
            x_loadPlaylist(path,loadPlaylistCB);
        }
        function loadPlaylistCB(s){
            //console.log(s);
            if ( s.match(/\[\"ERROR\",/) ){
                var error = s.evalJSON();
                handleError(error[1]);
            }else{
                G.closeAllGraphs(1);
                G.graphs = s.evalJSON();
                if ( G.graphs[0].regexlive != undefined ){
                    findAndLoad(G.graphs);
                    $('playlistregexdisplay').value = G.graphs[0].regexlive;
                    Element.show('playlistregexdisplayhide');
                    // currently there's no way to add a desc to regexlive
                    //if ( G.graphs[0].description != undefined ){
                        //$('playlistdescriptiondisplay').value = G.graphs[0].description;
                    //}else{
                        //$('playlistdescriptiondisplay').value = '';
                    //}
                }else if ( G.graphs ){
                    G.drawAllGraphs();
                    if ( G.graphs[0].regex != undefined ){
                        $('playlistregexdisplay').value = G.graphs[0].regex;
                        Element.show('playlistregexdisplayhide');
                    }else{
                        Element.hide('playlistregexdisplayhide');
                        $('playlistregexdisplay').value = '';
                    }
                    if ( G.graphs[0].description != undefined ){
                        $('playlistdescriptiondisplay').value = G.graphs[0].description;
                    }else{
                        $('playlistdescriptiondisplay').value = '';
                    }
                }else{
                    G.graphs = [];
                    G.addGraph();
                    Element.hide('playlistdisplay');
                }
            }
        }
        function handleError(str){
            removeErrors();
            rmErrorButton();
            var t = document.createElement('textarea');
            var e = document.createTextNode(str);
            t.appendChild(e);
            t.rows     = 12;
            t.cols     = 80;
            $('errorspace').appendChild(t);
            rmErrorButton();
        }
        function rmErrorButton(){
            var br = document.createElement('br');
            var b = document.createElement('span');
            b.className = 'clickable clearerrorsbutton';
            var t = document.createTextNode('Clear Messages');
            b.appendChild(t);
            Event.observe(b,'click',removeErrors.bindAsEventListener());
            $('errorspace').appendChild(br);
            $('errorspace').appendChild(b);
            var br = document.createElement('br');
            $('errorspace').appendChild(br);
        }
        function removeErrors(){
            $A($('errorspace').childNodes).each(function(child){
                $('errorspace').removeChild(child);
            })
        }
        function verify(s,arg){
            switch ( s ){
                case "closeallgraphs":
                    if ( G.confirmcloseallgraphs ){
                        return confirm('You sure?');
                    }else{
                        return true;
                    }
                    break;
                case "deleteplaylist":
                    if ( G.confirmdeleteplaylist ){
                        return confirm('You sure?');
                    }else{
                        return true;
                    }
                    break;
                default:
                    return;
            }
        }
        function convertAllTimes(){
            var a = [];
            G.graphs.each(function(graph,key){
                var tmp = [];
                tmp.push(key);
                tmp.push(graph.start);
                tmp.push(graph.end);
                a.push(tmp);
            })
            a = a.toJSON();
            //console.log(a);
            x_convertAllTimes(a,convertAllTimesCB);
        }
        function convertAllTimesCB(s){
            //console.log(s);
            //FIX this prolly needs some error checking
            var a = s.evalJSON();
            a.each(function(times){
                G.graphs[times[0]].start = times[1];
                G.graphs[times[0]].end   = times[2];
            })
            realSavePlaylist();
        }

        function toggleControl(e,id){
            var me = $(id);
            //var x  = Event.pointerX(e) + 5;
            //var y  = Event.pointerY(e) + 5;
            //me.style.left = x + 'px';
            //me.style.top  = y + 'px';
            Element.toggle(me);
        }
        function hidePicker(){
            Element.hide('allgraphcontrols');
            Element.hide('alphanav');
            Element.hide('hostlist');
            Element.hide('timepresetscontainer');
            Element.hide('toolmenu');
        }
        function showPicker(r){
            hideRegexer(r);
            Element.show('allgraphcontrols');
            Element.show('alphanav');
            Element.show('hostlist');
            Element.show('toolmenu');
        }
        function hideRegexer(r){
            Element.hide('regexernav');
            Element.hide('regexerout');
            Element.hide('regexersaver');
            $('regexerlist').value = '';
            if ( r == 0 ){
                G.graphs = [];
            }
        }
        function showRegexer(){
            hidePicker();
            Element.show('regexernav');
            Element.show('regexerout');
            $('regextext').focus();
            G.closeAllGraphs(0);
        }

        return{ 'navVis': navVis, 'initialPaths': initialPaths, 'init': init, 'toggleControl':toggleControl, 'savePlaylist':savePlaylist, 'loadPlaylist':loadPlaylist, 'handleError':handleError, 'newPlSub':newPlSub, 'help':help, 'hidehelp':hidehelp ,'findMatches':findMatches,'regexSavePlaylist':regexSavePlaylist, verify:verify, removeErrors:removeErrors, mergePlaylists:mergePlaylists, saveEvent:saveEvent, myEvents:myEvents, populateEventTags:populateEventTags}
    })();


    dsPicker.initialPaths = [ <?php initPaths() ?> ];

    document.observe('dom:loaded',function(){ G.init() ; dsPicker.init(); });

<?php 
    if ( in_array($_SERVER['PHP_AUTH_USER'],$admins) ){
        include('debug.php');
    }
?>

</script>

<?php include('css/yakety.css'); ?>

    <link rel="stylesheet" type="text/css" href="css/print.css" media="print">

</head>
<body>

<div id="doc3" class="yui-t6">
    <div id="bd">
        <div id="picker" class="yui-b">
            <div id="pickertop">
                <img src="img/stock_unknown-24.png" id="pickerregexerhelp" class="helpbutton">
                <img src="img/gtk-preferences.png" id="userprefsbutton" class="prefsbutton" title="Preferences">
                <img src="img/stock_calendar.png" id="eventsIcon" title="Events" class="prefsbutton">
                <br><span id="pickerbutton" class="clickable"><img src="img/stock_form-file-selection.png" title="Picker"></span> <span id="regexerbutton" class="clickable"><img src="img/stock_macro-stop-after-procedure.png" title="Regexer"></span>
                <br>
                <a href="feed.php"><img src="img/feed-icon_orange-16px.png" id="userprefsbutton" class="feedicon" title="ATOM Feed for recent playlists"></a>
                <br>
            </div>
            <div id="timepresetscontainer" style="display:none">
            <span class="clickable button" id="daybutton">Day</span>&nbsp;<span class="clickable button" id="twodaysbutton">2 Days</span>&nbsp;<span class="clickable button" id="weekbutton">Week</span>&nbsp;<span class="clickable button" id="monthbutton">Month</span>
            </div>
            <br>
            <div style="display:none" id="containerforplaylistdialog">
                <div id="playlistdialog">
                    <label for="playlistsubs">Dir:</label>
                    <?php 
                        $o = playlistDirs();
                        print($o);
                    ?>
                    <br>
                    <label for="playlistname">Name:</label>
                    <input id="playlistname" type="text" width="20" value="">
                    <br>
                    <label for="playlisttimes">Make All Times Absolute:</label>
                    <input id="playlisttimes" type="checkbox">
                    <div id="saveplaylistmorehide">
                    <label for="playlistdescription">Description:</label><br>
                    <textarea id="playlistdescription"></textarea>
                    </div>
                    <br>
                    <input type="button" onClick="dsPicker.savePlaylist(); return false;" value="Go!">
                    <input type="button" onClick="Element.toggle($('containerforplaylistdialog')); return false;" value="Cancel">

                </div>
            </div>
            <div style="display:none" id="newsubcontainer">
                <label for="newsubname">Directory Name:</label>
                <input id="newsubname" width="20" value="">
                <br>
                <input type="button" onClick="dsPicker.newPlSub(); return false" value="Go!">
                <br>
                <br>
                <input type="button" onClick="Element.toggle($('newsubcontainer')); return false;" value="Cancel">
                <br>
            </div>
            <div style="display:none" id="containerforallgraphtimes">
                <div id="allgraphtimesdialog">
                    <label for="allgraphstart">Start:</label>
                    <input id="allgraphstart" type="text" width="20" value="">
                    <br>
                    <label for="allgraphend">End:</label>
                    <input id="allgraphend" type="text" width="20" value="">
                    <br>
                    <label for="newgraphsnstime">New Graphs Too:</label>
                    <input type="checkbox" id="newgraphsnstime">
                    <br>
                    <input type="button" onClick="G.setAllGraphTimes(0,0); return false;" value="Go!">
                    <input type="button" onClick="G.setAllGraphTimes(1,0); return false;" value="Set All to Defaults">
                    <br>
                    <input type="button" onClick="Element.toggle($('containerforallgraphtimes')); return false;" value="Cancel">
                </div>
            </div>
            <div style="display:none" id="containerforallgraphsizes">
                <div id="allgraphsizesdialog">
                    Size:
                    <div class="slidediv roomy" id="slidedivforall" style="width :200px"><div class="slidehandle" id="slidehandleforall"><img src="img/stock_up.png"></div></div>
                    <div id="sizeindicatorforall">50</div>
                    <input type="button" onClick="Element.toggle($('containerforallgraphsizes')); return false;" value="Cancel">
                </div>
            </div>
            <div id="allgraphcontrols" class="yui-g">
            <div class="yui-u first">
                <span class="clickable" id="newgraphbutton"><img src="img/stock_file-with-objects.png" title="New Graph"></span>
                <span class="clickable" id="showmergecheckboxesbutton"><img src="img/stock-tool-button-scale.png" title="Show Playlist Merge UI"></span>
                <span class="clickable" id="saveplaylistbutton"><img src="img/stock_data-save.png" title="Save Playlist"></span>
                <br>
                <span class="clickable" id="setalltimesbutton"><img src="img/stock_timer.png" title="Set Times for All Graphs"></span>
                <span class="clickable" id="setallsizesbutton"><img src="img/stock_handles-simple.png" title="Set Sizes for All Graphs"></span>
                <span class="clickable" id="redrawallgraphsbutton"><img src="img/lc_formatpaintbrush.png" title="Redraw All Graphs"></span>
                <br>
            <?php 
            /*
            <br class="clear"><br>
            <label for="autorefresh" id="arl">Auto-Refresh</label>
            <select name="autorefresh" id="autorefresh">
                <option value="0">Never</option>
                <option value="900000">15 Minutes</option>
                <option value="1800000">30 Minutes</option>
                <option value="3600000">60 Minutes</option>
            </select>
             * if ( in_array($_SERVER['PHP_AUTH_USER'],$admins) ){
            <br>
            <label for="debuglog">DebugLog</label>
            <input type="checkbox" onClick="debugLogTog()" id="debuglog">
            <a href="drag-debug.php">drag-debug</a>
            }
            */
            ?>
<?php if (isset($_GET['pl']) ){ ?>
            <br>
            <a href="<?php echo $_SERVER['PHP_SELF'] ?>"><img src="img/stock_repeat-16.png" title="Reset Page"></a>
<?php } ?>
<?php if ( in_array($_SERVER['PHP_AUTH_USER'],$admins) ){ ?>
            <input type="button" onClick="debugShow(debugcount); debugcount++; return false;" value="more!">
<?php } ?>
            </div>
            <div class="yui-u">
                <span class="clickable floatright" onClick="if ( dsPicker.verify('closeallgraphs')){ G.closeAllGraphs(0); document.title='Jart <?php echo $version ?>'};"><img src="img/stock_delete.png" title="Close All Graphs"></span>
            </div>
            <img src="img/stock_help-chat.png" height="24" width="24" id="smiley">
        </div>
<br style="clear: all">
            <div id="playlistdisplay" style="display:none">
                <span id="playlistmoreinfobutton" class="clickable">More</span>
                <span id="playlistlink"></span>
                <div  id="playlistmoreinfo" style="display:none">
                    <div id="playlistregexdisplayhide" style="display:none">
                        <label for="playlistregexdisplay">Regex:</label><br>
                        <input type="text" id="playlistregexdisplay"><br>
                    </div>
                    <label for="playlistdescriptiondisplay">Description</label><br>
                    <textarea id="playlistdescriptiondisplay"></textarea>
                </div>
            </div>

            <?php 
            if ( file_exists('local.html') ){
                include('local.html'); 
            }
            ?>
            <br>
            <span class="clickable floatright"><img id="pickerspinner" style="display: none" src="img/scanner-transparent-back.gif"></span>
            <div id="alphanav">
            </div>
            <div id="hostlist">
            </div>
            <div id="regexernav" style="display:none">
                <form onSubmit="return false;">
                    <span class="red">&quot;[.]rrd$&quot; will be appended to your regex!!</span><br>
                    <label for="regextext">Regex:</label>
                    <input id="regextext" type="text" size="40" value="">
                    <br>
                    <img src="img/stock_unknown-24.png" id="pathbreaksgraphhelp" class="helpbutton">
                    <label for="regexgraphpathbreaks">Path Breaks Graph:</label>
                    <input id="regexgraphpathbreaks" type="checkbox">
                    <br>
                    <label for="regexunlimited">Unlimited graphs</label>
                    <input id="regexunlimited" type="checkbox">
                    <br>
                    <input id="regexgo"   type="button" value="Go">
                </form>
            </div>
        </div>
        <div id="yui-main">
            <div id="containerforgraphspace" class="yui-b">
                <div id="toolmenu">
                    <span class="clickable" id="dragtool"><img src="img/lc_arrowshapes.quad-arrow.png" id="dragtoolicon" title="Drag/CtC"></span>
                    <span class="clickable" id="seltool"><img src="img/lc_flowchartshapes.png" id="seltoolicon" title="Highlight"></span>
                    <div id="seltools">
                        <form name="seltools">
                            <input type="radio" name="seltools" value="time" id="seltoolstime"><label for="seltoolstime"><img src="img/sc10937.png" title="Selection Sets Time"></label>
                            <br>
                            <input type="radio" name="seltools" value="select" id="seltoolssel" checked><label for="seltoolssel"><img src="img/stock_draw-rounded-rectangle.png" title="Selection for Display Only"></label>
                        </form>
                        <input id="selcolorinp" value="#000000" style="display:none"><input id="selopacityinp" value="" style="display:none"><label class="colorexample" id="colorexampleCANVAS-sel" style="background-color:#000000" title="Hightlight Color"></label><img src="img/stock_3d-color-picker-16.png" style="margin-left: 1em;" title="Highlight Color">
                        <br>
                    </div>
                </div>
                <div id="errorspace"></div>
                <div id="regexerout" style="display:none">
                    <textarea id="regexerlist" cols="80" rows="30" disabled="true"></textarea>
                </div>
                <div id="regexersaver" style="display:none">
                <img src="img/stock_unknown-24.png" id="regexsaver" class="helpbutton">
                    <form onSubmit="return false;">
                        <label for="regexavg">Avg(<strong>s</strong>)</label>
                        <input id="regexavg" type="checkbox">
                        <label for="regextotal">Total(<strong>s</strong>)</label>
                        <input id="regextotal" type="checkbox">
                        <br>
                        <label for="regexjusttotal">JUST Total(<strong>s</strong>)</label>
                        <input id="regexjusttotal" type="checkbox">
                        <br>
                        <label for="regexonly">Regex ONLY</label>
                        <input id="regexonly" type="checkbox">
                        <br>
                        <label for="regexsavename">Playlist Name</label>
                        <input id="regexsavename" type="text" size="20" value="">
                        <input id="regexsaveit"   type="button" value="Save">
                        <input id="regexdraw"     type="button" value="Draw">
                    </form>
                </div>
                <div id="graphspace">
                </div>
            </div>
        </div>
    </div>
</div>
<div id="userprefsdiv" class="help" style="display: none">
<p>Set Defaults:</p>
<label for="userpstart">Start:</label><input type="text" id="userpstart"><br>
<label for="userpend">End:</label><input type="text" id="userpend"><br>

<label for="userpsize">Size:</label><select name="userpsize" id="userpsize">
    <option value="0">0</option>
    <option value="50">50</option>
    <option value="100">100</option>
    <option value="150">150</option>
    <option value="200">200</option>
</select><br>

<label for="userptool">Tool:</label><select name="userptool" id="userptool">
    <option value="0">Drag/CtC</option>
    <option value="1">Highlight</option>
</select><br>

<label for="userpzoompct">Zoom Percentage:</label><select name="userpzoompct" id="userpzoompct">
    <option value=".05">10%</option>
    <option value=".125">25%</option>
    <option value=".25">50%</option>
    <option value=".375">75%</option>
    <option value=".50">100%</option>
    <option value=".75">150%</option>
    <option value="1">200%</option>
</select><br>

<strong>Canvas Color</strong>
<label for="userpcanvas" class="colorexample" id="userpcanvaslab"> </label>
<input type="text" id="upserpcanvasinp" style="display:none;"><br>
<input type="text" id="upserpcanvasopacityinp" style="display:none;"><br>

<strong>Highlight Color</strong>
<label for="userphigh" class="colorexample" id="userphighlab"> </label>
<input type="text" id="upserphighinp" style="display:none;"><br>
<input type="text" id="upserphighopacityinp" style="display:none;"><br>

<label for="userpconfirmcloseall">Confirm Close All Graphs</label>
<input type="checkbox" id="userpconfirmcloseall"><br>

<label for="userpconfirmdeletepl">Confirm Delete Playlist</label>
<input type="checkbox" id="userpconfirmdeletepl"><br>

<label for="userpconfirmdeletepl">Confirm Overwrite Playlist</label>
<input type="checkbox" id="userpconfirmoverwritepl"><br>

<label for="userpshowmergeck">Show Merge-playlist Checkboxes</label>
<input type="checkbox" id="userpshowmergeck"><br>

<input type="button" value="Save" id="userpsave">
<input type="button" value="Cancel" id="userpcancel">
</div>

<div id="eventControls" class="help" style="display: none">
    <h1>Event-o-matic</h1>
    <div id="eventTabsContainer">
        <ul id="eventTabs">
            <li id="eventAddTab"><span>Add</span></li>
            <li id="eventDeleteTab"><span>Delete</span></li>
        </ul>
        <br class="clear">
    </div>
    <div id="addEvent">
        <label for="eventTime">Event Time</label><br>
        <input type="text" class="eventInputs" id="eventTime" value=""><span class="red">*</span><br>
        <label for="eventTitle">Event Title</label><br>
        <input type="text" class="eventInputs" id="eventTitle" value=""><span class="red">*</span><br>
        <span   style="display:none">Color:</span><br   style="display:none">
        <input type="text" style="display:none" id="eventColor">
        <input type="text" style="display:none" id="eventColoropacity"   style="display:none">
        <label for="eventColor" class="colorexample" id="eventColorExample"   style="display:none"></label><br   style="display:none">
        <label for="eventShortName" title="Use this label in time inputs!">Event Short Name</label><br>
        <input type="text" class="eventInputs" id="eventShortName" value="" title="Use this label in time inputs!"><span class="red">*</span><br>
        <label for="eventComment">Event Comment</label><br>
        <input type="text" class="eventInputs" id="eventComment" value=""><br>
        <label for="eventTags">Event Tags</label><br>
        <input type="text" class="eventInputs" id="eventTags" value=""><br>
        <div id="existingEventTags"></div>
        <input type="button" value="Save" id="eventSaveButton">
        <input type="button" id="eventCancelButton" value="Cancel" class="floatright">
        <input type="button" id="eventClearButton" value="Clear" class="floatright">
    </div>
    <div id="delEvent" style="display:none">
        <br>
        <div id="delEventList"></div>
        <input type="button" id="eventDeleteButton" value="Delete!">
        <input type="button" id="eventCancelButtonTwo" value="Cancel" class="floatright">
    </div>
</div>

<div id="pathbreaksgraphhelpd" style="display: none" class="help smaller">
<dl>
    <dt>Regex</dt>
    <dd>Enter your regular expression into this field. After optionally selecting either of the checkboxes below, click Go to see the results of your regular expression in the textarea to the left. Note that this textarea is for display purposes and not editing. Also note that the output will be divided by graph and the number of lines and graphs are displayed. If the output doesn't match what you expect, try refining your regex or using different options.</dd>
    <dt>Path Breaks Graph</dt>
    <dd>This option creates new graphs based on the path to the RRD files the way [All] does in the picker. It's useful, for example, when you want to see all of the CPU (or whatever) stats for a set of hosts, grouped by host.</dd>
    <dt>Unlimited Graphs</dt>
    <dd>Graphs can have a much larger (99 default) amount of lines than the default of 15. This is useful, for example, when creating aggregate bandwidth graphs.</dd>
</dl>
</div>

<div id="regexsaverd" style="display: none" class="help smaller">
<dl>
    <dt>Total(s)</dt>
    <dd>Enabling this option will add a total line to each graph.</dd>
    <dt>Just Total(s)</dt>
    <dd>Enabling this option displays only the total line on the graph(s). Enabling this option has no effect unless Totals is also checked.</dd>
    <dt>Regex ONLY</dt>
    <dd>Enabling this option saves the playlist in a format such that the playlist is always dynamically generated from the provided regular expression. If this option is not checked, the graphs described in the output window will be saved. If the option is checked, matches that come into existance in the future could create new graphs or modify existing ones.</dd>
</dl>
</div>

<div id="pickerregexerhelpd" style="display: none" class="help smaller">
<dl>
    <dt><img src="img/stock_form-file-selection.png" title="Picker"></dt>
    <dd>Selects the Picker controls. These controls allow interactive building of graphs and playlists.</dd>
    <dt><img src="img/stock_macro-stop-after-procedure.png" title="Regexer"></dt>
    <dd>Selects the Regexer. The Regexer allows you to build a playlist of graphs via regular expression. Knowledge of the regular expressions and the RRD layout is required for this option.</dd>
    <dt><img src="img/stock_calendar.png" title="Events"></dt>
    <dd>Opens the Event-o-matic which allows you to create events that show up on ALL graphs.</dd>
    <dt><img src="img/gtk-preferences.png" title="Preferences"></dt>
    <dd>Opens a dialog where various preferences can be set and saved.</dd>
    <dt><img src="img/stock_file-with-objects.png" title="New Graph"></dt>
    <dd>New Graph. This button creates a new graph. This is useful when creating graphs a line at a time.</dd>
    <dt><img src="img/stock-tool-button-scale.png" title="Show Playlist Merge UI"></dt>
    <dd>Show Playlist Merge UI. Clicking this icon toggles checkboxes next to your playlist names that can be used to merge them. If you check more than one checkbox, a text-field will appear for entering the name of the new &quot;merged&quot; playlist. Hit &quot;Enter&quot; in that field to save.</dd>
    <dt><img src="img/stock_data-save.png" title="Save Playlist"></dt>
    <dd>Save Playlist. This button brings up the Save Playlist dialog. A playlist saved from this button is made up of the currently displayed graphs.</dd>
    <dt><img src="img/stock_timer.png" title="Set Times for All Graphs"></dt>
    <dd>Set Times for All Graphs. This button brings up a dialog that allows you to change the start and end times of all displayed graphs.</dd>
    <dt><img src="img/stock_handles-simple.png" title="Set Sizes for All Graphs"></dt>
    <dd>Set Sizes for All Graphs. This button brings up a dialog that allows you to change the sizing for all displayed graphs.</dd>
    <dt><img src="img/lc_formatpaintbrush.png" title="Redraw All Graphs"></dt>
    <dd>Redraw All Graphs. This button redraws all graphs with their current times.</dd>
    <dt><img src="img/stock_delete.png" title="Delete All Graphs"></dt>
    <dd>Delete All Graphs. This button removes all graphs from view and adjusts the graph counter to 0.</dd>
    <dt><img src="img/lc_arrowshapes.quad-arrow.png" title="Drag/CtC"></dt>
    <dd>Drag Tool. When this tool is selected, graphs can be dragged back and forth in time by mousing over the graph and dragging.</dd>
    <dt><img src="img/lc_flowchartshapes.png" title="Highlight"></dt>
    <dd>Selection Tool. This tool operates in one of two modes. The first mode uses the selection to narrow the displayed times. The second mode makes no use of the selection -- it's there for display/demonstration only. Selection color can also be controlled in the options for this tool.</dd>
</dl>
</div>

</body>
</html>
