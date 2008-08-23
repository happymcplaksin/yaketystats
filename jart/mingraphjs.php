<?php

require 'jsmin-1.1.1.php';

// Output a minified version of example.js.
// echo JSMin::minify(file_get_contents('example.js'));

clearstatcache();
if ( filemtime("js/mingraph.js") < filemtime("js/graph.js") ){
    $jsmin =  JSMin::minify(file_get_contents('js/graph.js'));
    $fp = fopen("js/mingraph.js","w");
    fwrite($fp,$jsmin);
    fclose($fp);
}else{
    $f=filemtime("js/mingraph.js");
    $g=filemtime("js/graph.js");
    echo "min: $f\nfull:$g\n";
}

?>
