<?
/*
img.php V1.0002 by Sam Rowe 2002 http://www.deadman.org/misc.html
*/
function inline($path){
    if(file_exists("$path")){
        $size=GetImageSize($path);
        printf("<img src=\"%s\" %s />",$path,$size[3]);
    }else{
    return;
    }
}

$image=$_REQUEST['image'];
$dir=".";

$dirh=opendir("$dir");
while (($file = readdir($dirh)) != false) {
    if (eregi("\.jpg$|\.png$|\.gif$",$file)){
        $images[]=$file;
    }
}
closedir($dirh);

natsort($images);
$imgs=array_values($images);
$soi=sizeof($imgs);
?>
<html>
<head>
<script language="javaScript1.2">
    function makevis( targetId ){
    if (document.getElementById){
       target = document.getElementById( targetId );
       if (target.style.display == "none"){
           target.style.display = "";
       } else {
           target.style.display = "none";
       }
    }
}
</script>
    <style type="text/css">
    <!--
    a { text-decoration: none; }
    -->
    </style>
</head>
<body>

<div style="float:left; width:23%;">

<?
if (isset($image)){
    for ($x=0;$x<$soi;$x++){
        if ( $imgs[$x] == $image ){
            $current=$x;
        }
    }
}else{
    $current=0;
}

if ( $current > 0 ){
    $previous = $current - 1;
}else{
    $previous = 0;
}
$forward=$current + 1;
$end=$soi-1;

$common=$_SERVER['PHP_SELF'].'?image=';

$first=$common.$imgs[0];
$prev=$common.$imgs[$previous];
$curr=$common.$imgs[$current];
if( $current == $end ){
    $next=$first;
}else{
    $next=$common.$imgs[$forward];
}
$last=$common.$imgs[$end];
?>
<a href="<?=$first?>" title="The first image">&lt;&lt;</a>
<a href="<?=$prev?>" title="The previous image">&lt;</a>
<a href="<?=$curr?>" title="The current image">O</a>
<a href="<?=$next?>" title="The next image">&gt;</a>
<a href="<?=$last?>" title="The last image">&gt;&gt;</a>
<br />
<a href="#" onclick="makevis('list');return false;">+</a>
<br /> <br />
    <div id='list'>

<?
while(list($x,$value)=each($imgs)){
    printf("<a href=\"%s\">%s</a>\n",$imgs[$x],$imgs[$x]);
    printf("<a href=\"%s?image=%s\">here</a><br>\n",$_SERVER['PHP_SELF'],$imgs[$x]);
}
?>

    </div>
</div>

<div style="padding-top:20px; margin-left: 27%; margin-right: 5px;">

<?
if ( isset($image) ){
    inline($image);
}
?>

</div>
</body>
</html>
