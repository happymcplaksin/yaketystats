<?php

require("conf.php");

/*
 * use the fscache ( find /rrd -follow > fscache ) 
 * to find rrd files with dses other than yabba
 */

$stdin = fopen('php://stdin', 'r');
stream_set_blocking($stdin, false);

if ( ($f = @fopen($webdir.'/fscache','r')) === FALSE ){
    die("Unable to open fscache file.\n");
}

           // the @ is because redhat gives a warning w/o it. Debian is fine.
$tty     = @posix_isatty($stdin);
$exclude = array();

function createMap($dir){
    global $rrdtool;
    // find the files in the dir
    if (is_dir($dir)) {
        if ( ! is_writable($dir) ){
            die("$dir is not writable.\n");
        }
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ( is_file($dir . $file) ){
                    $files[] = $dir . $file;
                }
            }
        }else{
            die("Can't open $dir\n");
        }
    }else{
        die("$dir is not a dir!\n");
    }
    if ( ! $fp = fopen($dir.".map",'w') ){
        die("Can't open map file in $dir. Bailing.\n");
    }
    // we only care about rrd files
    $files = preg_grep('/.*[.]rrd$/',$files);
    foreach ($files as $file) {
        $dslines   = array();
        $dsnames   = array();
        $outa      = array();
        $ofile     = $file;
        $file      = escapeshellarg($file);
        $rout      = exec("$rrdtool info $file",$outa,$rval);
        // get the dses from the output
        $dslines   = preg_grep('/ds[[].*[.]type/',$outa);
        // lose the funky element numbering
        $dslines   = array_values($dslines);
        // lines look like :  ds[bingo].type = "COUNTER"
        $dsnames   = preg_replace('/ds[[](.*)[]].type .*/','$1',$dslines);
        foreach ($dsnames as $dsname){
            $out .= "$ofile::$dsname\n";
        }
    }
    fwrite($fp,$out);
    fclose($fp);
}

createMap('/rrd/sam.test.edu/');
exit;

while ( $line = fscanf($f,"%s\n")) {
    $line  = $line[0];
    $outa  = array();
    $raval = 1;
    // if the user hits return, tell them where we are
    if ( $tty ){
        if ( fgets($stdin) ){
            print("$line\n\n\n");
        }
    }
    // look at lines that are rrd files
    if ( preg_match('/.*[.]rrd$/',$line)) {
        // find out the directory it's in
        $dir   = preg_replace('|(.*/).*|','\1',$line);
        // if it's already had a map file created, skip it
        if ( count($exclude) > 0 ){
            if ( preg_grep("|^$dir$|",$exclude) ){
                continue;
            }
        }
        // thank you Michael for Lady&Tramp!
        $eline = escapeshellarg($line);
        // ask the file for its dses
        $out   = exec("$rrdtool info $eline",$outa,$rval);
        if ( $rval == 0 ){
            // get the dses from the output
            $dslines  = preg_grep('/ds[[].*[.]type/',$outa);
            // a different var so that rrd files with yabba+others won't get ignored
            $ndslines = preg_grep('/[[]yabba\]/',$dslines,PREG_GREP_INVERT);
            if ( count($ndslines) > 0 ){
                // add the dir to the excludes
                $exclude[] = $dir;
                // go create a map in that dir
                $nothing   = createMap($dir);
            }
        }else{
            print("Failed $line\n");
        }
    }
}

?>
