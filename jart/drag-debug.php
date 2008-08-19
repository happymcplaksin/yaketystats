<?php

if ( !isset($_GET['id']) ){
    $id = 'sam';
}else{
    $id = $_GET['id'];
}

if ( !isset($_GET['gn']) ){
    $gn = '0';
}else{
    $gn = $_GET['gn'];
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<title>debugit</title>
    <style type="text/css">
    <!--
    #themimages {
        width: 1200px;
        position: relative;
    }
    -->
    </style>
</head>
<body>

<div id="themimages"><img src="graphs/<?=$id?>-<?=$gn?>-l.png" style="border-bottom: 1px solid black" /><img src="graphs/<?=$id?>-<?=$gn?>-c.png" /><img src="graphs/<?=$id?>-<?=$gn?>-r.png" style="border-bottom: 1px solid black" /></div>

<h2>bg</h2>
<textarea cols="150" rows="18">
<? include("graphs/$id-$gn--args.log") ?>
</textarea>

<h2>left</h2>
<textarea cols="150" rows="18">
<? include("graphs/$id-$gn-l-args.log") ?>
</textarea>

<h2>center</h2>
<textarea cols="150" rows="18">
<? include("graphs/$id-$gn-c-args.log") ?>
</textarea>

<h2>right</h2>
<textarea cols="150" rows="18">
<? include("graphs/$id-$gn-r-args.log") ?>
</textarea>
</body>
</html>
