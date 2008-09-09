<?php

// Allow group-write
umask (0022);

// Read paths from stats.conf
//
// Maybe a Makefile should fill in this path?
$server_conf = file ("/usr/local/yaketystats/etc/server.conf");
// It's OK if the maintenance file isn't defined but you'll never be able to have maintenance
foreach ($server_conf as $line) {
  if ( preg_match ('/^\s*maint .*$/', $line, $matches) > 0 ) {
    $maint = preg_split ('/[\s]+/', $matches[0]);
    $maint = $maint[1];
  }
  if ( preg_match ('/^\s*inbound_dir .*$/', $line, $matches) > 0 ) {
    $dir = preg_split ('/[\s]+/', $matches[0]);
    $dir = $dir[1];
  }
}

$now     = mktime();

define("ERROR_BAD_REQUEST",400);
define("ERROR_FORBIDDEN",403);
define("ERROR_CONFLICT",409);
define("ERROR_SERVICE_UNAVAILABLE",503);
define("ERROR_HAPPY_UNAVAILABLE",588);

// silence errors and warnings so the header call in unavail works.
// TODO: This is awesome for debugging!
//error_reporting(0);

// Bail out if maintenance is happening
if ( file_exists ($maint) ) {
  unavail(ERROR_SERVICE_UNAVAILABLE);
}

if ( isset($_REQUEST['dataversion']) ){
    if ( $_REQUEST['dataversion'] == '1.3' ){
        // Change the directory to this version of the stats
        $dir .= '/'.$_REQUEST['dataversion'].'/';
        // test to make sure that host,dataversion,datafile are set if not, 400
        if ( isset($_REQUEST['host'],$_REQUEST['dataversion'],$_FILES['datafile']) ){
	  // FUTURE TODO MAYBE: verify that host matches uuid if not, 403
	  // Make sure there are no bad characters in the host
            $host = preg_replace('[^a-zA-Z0-9-.]','',$_REQUEST['host']);
            if ( empty($host) ){
                unavail(ERROR_BAD_REQUEST);
            }

	    // if there's no directory, make one!
	    if ( ! mkdir_p($dir) ){
	      unavail(ERROR_CONFLICT);
	    }

            /*
            $uuid = lookupUUID($host);
            if ( $_REQUEST['uuid'] != $uuid ){
                unavail(403);
            }
            */

            // check to see if the location is a directory or doesn't exist. if not, 503
            // The directory name might be occupied by the old-version stats file, so let's
            // be sure. This could also be used to do maintence for one host.
            $type = filetype($dir.$host);

            if ( ! $type ){
                // if there's no directory, make one!
                if ( ! mkdir_p($dir.$host) ){
                    unavail(ERROR_CONFLICT);
                }
                $type = 'dir';
            }

            if ( $type == 'dir' ){
                // loop over the uploaded files
                $files = array_keys($_FILES);
                $str   = "BEGIN\n";
                foreach ($files as $file) {
                    // then write the file to the directory with the php uniq name
                    $newfn="$dir/$host/".$_FILES[$file]['name'].'.'.time ().".".$_SERVER['REMOTE_ADDR'];
                    if ( $_FILES[$file]['error'] != 'UPLOAD_ERR_OK' ){
		        $str .= "ERROR " . $_FILES[$file]['name'] . "(uploading:" . $_FILES[$file]['error'] . ")\n";
                        print($str);
                        exit(0);
                    }else{
                        if ( is_file($_FILES[$file]['tmp_name']) ){
                            if ( !move_uploaded_file($_FILES[$file]['tmp_name'],$newfn) ){
                                $str .= "ERROR " . $_FILES[$file]['name'] . "(moving)\n";
                                print($str);
                                exit(0);
                            }else{
                                $str .= "OK " . $_FILES[$file]['name'] . "\n";
                            }
                        }
                    }
                }
                print($str);
                exit(0);
            }else{
                unavail(ERROR_SERVICE_UNAVAILABLE);
            }
        }else{
            unavail(ERROR_BAD_REQUEST);
        }
    }
    exit (0);
}

$host = preg_replace('#/([^/]*)/.*#','$1',$_REQUEST['path']);

if ( empty($host) ){
    unavail(ERROR_USER);
}

$log  = $dir.$host;

if ( ! ($handle = fopen("$log", "a")) ) {
    unavail(ERROR_HAPPY_UNAVAILABLE);
}

$date_format = "m/d/y:H:i:s";
$string = sprintf ("%s %s %s\n", $_SERVER['REMOTE_ADDR'],
                                 date ($date_format, time()),
                                 $_SERVER['QUERY_STRING']);

if ( ! fwrite ($handle, $string) ) {
    unavail(ERROR_HAPPY_UNAVAILABLE);
}

if ( fclose ($handle) ) {
    exit(0);
} else {
    unavail(ERROR_HAPPY_UNAVAILABLE);
}

function unavail ($code) {
    //print("HTTP/1.0 503 Service Unavailable because $code");
    header("HTTP/1.0 $code Service Unavailable");
    exit(0);
}

function lookupUUID($host){
    global $log;
    //$horrible = dba_open($log."host.uuid","c");
    // see if we already know this host
    // if not, spit out a new UUID and exit
    // if so, return the UUID
}

function mkdir_p ($dir) {
  if ( ! file_exists($dir) ) {
    if ( file_exists (dirname ($dir)) ) {
      if ( ! mkdir ($dir) ) {
	unavail(ERROR_FORBIDDEN);
	return (FALSE);
      }
      chmod ($dir, 0775);
    } else {
      mkdir_p(dirname ($dir));
    }
    return (TRUE);
  }
  return (TRUE);
}
?>
