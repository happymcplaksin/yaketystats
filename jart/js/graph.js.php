<?php
# Copyright (C) 2008 Board of Regents of the University System of Georgia
#
# This file is part of YaketyStats (see http://yaketystats.org/).
# YaketyStats is free software: you can redistribute it and/or modify
#
# vim: set sw=4 sts=4 et tw=0 :
header("content-type: application/x-javascript");
ob_start ("ob_gzhandler");
?>
// Copyright (C) 2008 Board of Regents of the University System of Georgia
//
// This file is part of YaketyStats (see http://yaketystats.org/).
// YaketyStats is free software: you can redistribute it and/or modify

var G = (function() {

    var graphs       = new Array();
    var cg           = 0;
    var defaultdrawtype    = 'LINE1';
    var defaultpathlimit   = 25;

<?php
require('../conf.php');
require('../JSON.php');
require_once('../common.php');
$user  = $_SERVER['PHP_AUTH_USER'];
$json = new Services_JSON();
$file = "$webdir/playlists/$user/.prefs";
$out  = '';
$defaults = array(
                    'confirmcloseallgraphs'    => 1,
                    'confirmdeleteplaylist'    => 1,
                    'confirmoverwriteplaylist' => 1,
                    'defaultCanvasColor'       => "ffffff",
                    'defaultstarttime' => "4 days ago",
                    'defaultendtime'   => "now",
                    'defaultsize'      => 50,
                    'selColor'         => "#ff0000",
                    'selOpacity'       => 56,
                    'showMergeCheckboxes' => 0,
                    'tool'             => 1,
                    'zoompct'          => .125
                );
$arr = array();
if ( file_exists($file) && is_readable($file) ){
    $arr  = $json->decode( file_get_contents($file) );
}
foreach( $defaults as $key => $value ){
    $q = '';
    if ( isset($arr->$key) ){
        $defaults[$key] = $arr->$key;
    }
    if ( ! is_numeric($defaults[$key]) || preg_match('/olor/',$key) ){
        $q = "'";
    }
    $out .= "    var $key = $q".$defaults[$key] . "$q;\n";
}
print $out;
?>
    // 0 == drag / CtC
    // 1 == highlight
    var slider;
    var gsliders           = new Array();
    var overlayoffset      = new Array();
    var selectoffset       = new Array();

    var mydrag;

    function Path(){
        this.path      = '';
        this.color     = '';
        this.isPredict = 0;
        this.name      = '';
        this.drawtype  = defaultdrawtype;
        this.display   = 1;
        this.opacity   = 'ff';
    }
    function Graph(){
        this.avg         = 0;
        this.bglastdrawn = 0;
        this.canvas      = G.defaultCanvasColor;
        this.description = '';
        this.end         = G.defaultendtime;
        this.graphlabel  = '';
        this.max         = 'nan';
        this.min         = 'nan';
        this.mo          = 0;
        this.nextcolor   = 0;
        this.ollastdrawn = 0;
        this.paths       = new Array();
        this.pathlimit   = '';
        this.size        = parseInt(G.defaultsize);
        this.start       = G.defaultstarttime;
        this.total       = 0;
        this.predicting  = 0;
        this.predictTotal = 0;
        this.vertlabel   = '';
        this.xsize       = 'nan';
        this.ysize       = 'nan';
        this.xoff        = 'nan';
        this.yoff        = 'nan';
        this.events      = 'all';
    }

    function init(){
        $('userpsave').onclick = function(){saveUserPrefs()};
        $('userpcancel').onclick = function(){Element.hide('userprefsdiv'); userPrefsInit()};
        $('userprefsbutton').onclick = function(){Element.toggle('userprefsdiv')};
        userPrefsInit();

        addGraph();
        document.onkeypress = function(e){handleKeys(e)}.bindAsEventListener();

        var ngb = $('newgraphbutton');
        Event.observe(ngb,'click',addGraph.bindAsEventListener());

        var mtb = $('showmergecheckboxesbutton');
        Event.observe(mtb,'click',toggleMergeCheckboxes.bindAsEventListener());

        var sat = $('setalltimesbutton');
        Event.observe(sat,'click',function(e){dsPicker.toggleControl(e,'containerforallgraphtimes'); $('allgraphstart').focus() }.bindAsEventListener());

        var sas = $('setallsizesbutton');
        Event.observe(sas,'click',function(e){dsPicker.toggleControl(e,'containerforallgraphsizes')}.bindAsEventListener());

        var rag = $('redrawallgraphsbutton');
        Event.observe(rag,'click',createAllGraphImages.bindAsEventListener());

        var smile = $('smiley');
        Event.observe(smile,'click',showTimePresets.bindAsEventListener());

        var db    = $('daybutton');
        Event.observe(db,'click',function(e){setAllGraphTimes('1 day ago','now')}.bindAsEventListener());

        var tdb   = $('twodaysbutton');
        Event.observe(tdb,'click',function(e){setAllGraphTimes('2 days ago','now')}.bindAsEventListener());

        var wb    = $('weekbutton');
        Event.observe(wb,'click',function(e){setAllGraphTimes('1 Week ago','now')}.bindAsEventListener());

        var mb    = $('monthbutton');
        Event.observe(mb,'click',function(e){setAllGraphTimes('1 month ago','now')}.bindAsEventListener());

        //var ts    = $('autorefresh');
        //Event.observe(ts,'change',autoRefresh.bindAsEventListener());

        $('playlistmoreinfobutton').onclick = function(){ Element.toggle('playlistmoreinfo'); lessMore('playlistmoreinfobutton');  };

        var hti   = $('seltoolicon');
        Event.observe(hti,'click',setTool.bindAsEventListener());

        var dti   = $('dragtoolicon');
        Event.observe(dti,'click',setTool.bindAsEventListener());
        setOpacity(dti,3);

        var sci = $('selcolorinp');
        sci.value = selColor.replace(/^#/,'');
        var soi = $('selopacityinp');
        soi.value = G.selOpacity;
        var ces = $('colorexampleCANVAS-sel');
        ces.style.backgroundColor = selColor;
        ces.style.opacity         = parseInt(G.selOpacity,16) /255;
        new Control.ColorPicker( sci, { 'swatch':ces, 'onUpdate':updateSelColor, 'opacityField':soi });

        var ctc = $('dragtoolicon');
        var hil = $('seltoolicon');
        if ( tool == 1 ){
            setOpacity(hil,10);
            setOpacity(ctc,4);
        }else{
            Element.hide('seltools');
            setOpacity(ctc,10);
            setOpacity(hil,4);
        }
        slider = new Control.Slider('slidehandleforall','slidedivforall', {sliderValue: 50,range:$R(0,200),values:[0,50,100,150,200], onSlide: function(v){$('sizeindicatorforall').innerHTML = v}, onChange:function(v){G.setAllGraphSizes(v); Element.hide('containerforallgraphsizes');}});
    }

    function lessMore(ele){
        var ele = $(ele);
        if ( ele.innerHTML == 'More' ){
            ele.innerHTML = 'Less';
        }else{
            ele.innerHTML = 'More';
        }
    }

    function handleKeys(e){
        //console.log(e.target);
        //console.log(e);
        if ( e.keyCode != 13 ){
            return;
        }
        switch(e.target.id){
            case "allgraphstart":
            case "allgraphend":
                G.setAllGraphTimes(0,0);
                break;
            case "eventTime":
            case "eventTitle":
            case "eventShortName":
            case "eventComment":
            case "eventTags":
                dsPicker.saveEvent();
                break;
            case "mergedplaylistname":
                dsPicker.mergePlaylists();
                break;
            case "playlistname":
                dsPicker.savePlaylist();
                break;
            case "regextext":
                dsPicker.findMatches();
                break;
            case "regexsavename":
                dsPicker.regexSavePlaylist();
                break;
            case "userpstart":
            case "userpend":
                saveUserPrefs();
                break;
            case "eventTime":
            case "eventTitle":
            case "eventComment":
                dsPicker.saveEvent();
                break;
            default:
                return;
        }
    }
    function updateSelColor(color,opacity,me){
        G.selColor = color;
        G.selOpacity = opacity;
        var opacity = parseInt(opacity,16) / 25.5;
        var l = $('colorexampleCANVAS-sel');
        l.style.backgroundColor = color;
        for (key in G.graphs){
            if ( typeof(G.graphs[key]) != 'function' ){
                // Potential pinch point... these lookups are slow
                if (  $('graph-' + key) ){
                    var sd;
                    if ( sd = $('seldiv-' + key) ){
                        sd.style.backgroundColor = color;
                        setOpacity(sd,opacity);
                    }
                }
            }
        }
    }

    function setTool(e){
        if ( e.target !== undefined ){
            var c = e.target.id;
        }else{
            var c = e
        }
        ['dragtoolicon','seltoolicon'].each(function(tool,key){
            var me = $(tool);
            if ( me.id == c ){
                setOpacity(me,10);
                G.tool = key;
                if ( key == 1 ){
                    new Effect.SlideDown('seltools');
                }else{
                    Element.hide('seltools');
                }
            }else{
                setOpacity(me,4);
            }
        });
        for (key in G.graphs){
            if ( typeof(G.graphs[key]) != 'function' ){
                // Potential pinch point... these lookups are slow
                if (  $('graph-' + key) ){
                    var odivc = $('overlaycontainerdiv-' + key);
                    odivc.innerHTML = '';
                }
            }
        }
        return;
    }
    function setOpacity(o,v){
        o.style.opacity = v/10;
        o.style.filter = 'alpha(opacity=' + v*10 + ')';
    }
    function addGraph(){
        if ( G.graphs[G.cg] ){
            if ( ! G.graphs[G.cg].paths[0] ){
                return;
            }
        }
        G.graphs.push(new Graph());
        G.cg = G.graphs.length -1;
        //FIX ME use prototype?
        if ( $('newgraphsnstime').checked ){
            var s = $('allgraphstart').value;
            var e = $('allgraphend').value;
            if ( e != '' && s != '' ){
                G.graphs[G.cg].start = s;
                G.graphs[G.cg].end   = e;
            }
        }
    }
    function addGraphTotal(me){
        setCurrentGraph(me);
        Element.show('justtotalfor-' + me);
        G.graphs[me].total = 1;
        addRrdToGraph('total',0);
    }
    function addGraphAvg(me){
        setCurrentGraph(me);
        G.graphs[me].avg = 1;
        addRrdToGraph('avg',0);
    }
    function setJustTotal(me,e){
        setCurrentGraph(me);
        if ( e.currentTarget.checked ){
            G.graphs[me].justtotal = 1;
        }else{
            G.graphs[me].justtotal = 0;
        }
        createGraphImage(me,0);
    }
    function createDraggable(e){
        //console.log('create',e.currentTarget);
        Event.stopObserving(e.currentTarget,'mouseover',createDraggable);
        Sortable.create(e.currentTarget.id,{onUpdate:function(el){reorderPaths(Sortable.serialize(el))}} );
    }
    function destroyDraggable(n){
        //console.log('destroy',n);
        var list = $('pathul-' + n);
        //console.log(list);
        Event.observe(list,'mouseover',createDraggable);
        Sortable.destroy(list);
    }
    function addRrdToGraph(rrd,draw){
        var stupid = 0;
        var mygraph = G.cg;
        if ( G.graphs[mygraph].paths.length == G.defaultpathlimit ){
            alert('Graph is full, Move on.');
            return;
        }
        var xrrd = rrd.replace(/^predict:/,'');
        G.graphs[mygraph].paths.each(function(path){
            if ( path.path == rrd || ( path.path == xrrd && path.isPredict == 1) ){
                alert('You already have that path, buster!');
                stupid = 1;
            }
        })
        if ( stupid == 1 ){
            return;
        }
        var tmp = new Path();
        var prefix = '';
        if ( rrd.match(/^predict:/) ){
            prefix = "Predict:";
            rrd = rrd.replace(/^predict:/,'');
            tmp.isPredict = 1;
        }
        tmp.path = rrd;
        tmp.color = getColor();
        if ( rrd != 'total' && rrd != 'avg' ){
            tmp.name  = rrd.replace(/.*\/rrd\/([^.]*)[^/]*(.*)\.rrd$/,'$1$2');
            tmp.name  = tmp.name.replace(/\//g,' ');
        }else{
            if ( tmp.isPredict == 1 ){
                G.graphs[mygraph].predictTotal = 1;
            }
            tmp.name  = rrd;
        }
        tmp.name = prefix + tmp.name;
        G.graphs[mygraph].paths.push(tmp);
        if ( $('graph-' + mygraph) ){
            var d = $('controlsfor-' + mygraph);
            if ( d ){
                if ( G.graphs[mygraph].paths.length > 3 && G.graphs[mygraph].paths.length < 10 ){
                    Element.show('blendiconfor-' + mygraph);
                }else{
                    Element.hide('blendiconfor-' + mygraph);
                }
                var mylist = $('pathul-' + mygraph);
                //var mynum  = mylist.childNodes.length;
                var mynum  = G.graphs[mygraph].paths.length -1;
                destroyDraggable(mygraph);
                // add a new li to the ul and number it properly
                var li = pathLi(tmp,mygraph,mynum);
                mylist.appendChild(li);
            }
            createGraphImage(mygraph,0);
        }else if ( draw == 1 ){
            drawGraph();
        }
    }
    function reorderPaths(str){
        var myul = str.replace(/([^[])\[.*/,'$1');
        var mygraph = myul.replace(/\D+-(\d+)/,'$1');
        destroyDraggable(mygraph);
        var s = str.split('&');
        var tmp = new Array();
        s.each(function(j){
            var a = j.replace(/[^=]+=(\d+)/,'$1');
            tmp.push(G.graphs[mygraph].paths[a]);
        });
        G.graphs[mygraph].paths=tmp;
        deIdList(myul,mygraph);
        createGraphImage(mygraph,0);
    }
    function updateColor(color,opacity,me){
        var mygraph = me.replace(/pathli-(\d+).*/,'$1');
        var mypath  = me.replace(/pathli-\d+_(\d+)/,'$1');
        G.graphs[mygraph].paths[mypath].color = color;
        G.graphs[mygraph].paths[mypath].opacity = opacity;
        createGraphImage(mygraph,0);
    }
    function updateCanvas(color,opacity,me){
        var mygraph = me.replace(/linecontrolcontainerfor-(\d+)/,'$1');
        G.graphs[mygraph].canvas = color.replace(/#/,'');
        createGraphImage(mygraph,0);
    }
    function getColor(noglobal){
        if ( noglobal === undefined ){
            var nextcolor = G.graphs[G.cg].nextcolor;
        }else{
            var nextcolor = noglobal;
        }
        if ( nextcolor < 100 ){
            var mycolor   = colors[nextcolor];
        }else if ( nextcolor < 355 ){
            nextcolor -= 100;
            var pi     = nextcolor.toString(16);
            if ( pi.length == 1 ){
                pi = '0' + pi;
            }
            var mycolor = '#' + pi + pi + pi;
        }else if ( nextcolor < 610 ){
            nextcolor -= 355;
            var pi     = nextcolor.toString(16);
            if ( pi.length == 1 ){
                pi = '0' + pi;
            }
            var mycolor = '#' + pi + '00' + '00';
        }else if ( nextcolor < 865 ){
            nextcolor -= 610;
            var pi     = nextcolor.toString(16);
            if ( pi.length == 1 ){
                pi = '0' + pi;
            }
            var mycolor = '#' + '00' + pi + '00';
        }else if ( nextcolor < 1120 ){
            nextcolor -= 865;
            var pi     = nextcolor.toString(16);
            if ( pi.length == 1 ){
                pi = '0' + pi;
            }
            var mycolor = '#' + '00' + '00' + pi;
        }else if ( nextcolor > 1120 ){
            var mycolor = '#dddddd';
        }
        if ( noglobal === undefined ){
            G.graphs[G.cg].nextcolor++;
        }
        return mycolor;
    }
    function closeGraph(me){
        var list    = 'pathul-' + me;
        var le      = $(list);
        if ( le ){
            Sortable.destroy(list);
        }
        delete G.graphs[me];
        $('graphspace').removeChild( $('graph-' + me) );
        var i = 0;
        for (key in G.graphs){
            if ( typeof(G.graphs[key]) != 'function' ){
                G.cg = key;
                i++;
            }
        }
        if ( i == 0 ){
            resetSizeForAll();
            G.graphs = new Array();
            addGraph();
            $('playlistlink').innerHTML = '';
            Element.hide('playlistdisplay');
        }
    }
    function closeAllGraphs(flc){
        $('graphspace').innerHTML = '';
        dsPicker.removeErrors();
        G.graphs = new Array();
        resetSizeForAll();
        addGraph();
        G.cg = 0;
        if ( ! flc ){
            $('playlistlink').innerHTML = '';
            Element.hide('playlistdisplay');
        }
    }
    function resetSizeForAll(){
        slider.setValue(50);
        $('sizeindicatorforall').innerHTML = '50';
    }
    function drawAllGraphs(){
        for (key in G.graphs){
            if ( typeof(G.graphs[key]) != 'function' ){
                // Potential pinch point... these lookups are slow
                if ( ! $('graph-' + key) ){
                    G.cg=key;
                    drawGraph();
                }
            }
        }
    }
    function updateTimes(graph,start,end){
        G.graphs[graph].start       = start;
        G.graphs[graph].end         = end;
        if ( $('controlsfor-' + graph) ){
            $('start-' + graph).value = start;
            $('end-' + graph).value   = end;
        }
    }
    function resetGraphTime(me){
        updateTimes(me,defaultstarttime,defaultendtime);
        createGraphImage(me,0);
    }
    function showTimePresets(e){
        var me = $('timepresetscontainer');
        if ( Element.visible(me) ){
            new Effect.SlideUp(me);
        }else{
            new Effect.SlideDown(me);
        }
    }
    function showGraphUI(me){
        var d  = $('controlsfor-' + me);
        if ( d ){
            Element.toggle( d );
        }else{
            createGraphUI(me);
            var d  = $('controlsfor-' + me);
            Element.show( d );
        }
    }
    function changeLineDrawType(me,e){
        // this is this way because the id can change with a re-order so
        // passing it in is not an option
        var line = e.target.parentNode.id.replace(/[^-]+-\d+_(\d+)/,'$1');
        var val  = e.target.value;
        G.graphs[me].paths[line].drawtype = val;
        createGraphImage(me,0);
    }
    function setGraphStart(me,e){
        G.graphs[me].start = e.target.value;
        createGraphImage(me,0);
    }
    function setGraphEnd(me,e){
        G.graphs[me].end = e.target.value;
        createGraphImage(me,0);
    }
    function setAllGraphTimes(s,e){
        //FIX what if there are no graphs?
        if ( e ){
            var start = s;
            var end   = e;
        }else if( s ){
            var start = defaultstarttime;
            var end   = defaultendtime;
            Element.toggle($('containerforallgraphtimes'));
        }else{
            var start = $F('allgraphstart');
            //console.log(start);
            var end   = $F('allgraphend');
            //console.log(end);
            Element.toggle($('containerforallgraphtimes'));
            if ( end == '' || start == '' ){
                dsPicker.handleError('Either start or end time is empty.');
                return;
            }
        }
        // thanks Prototype!
        for (key in G.graphs){
            if ( typeof(G.graphs[key]) != 'function' ){
                updateTimes(key,start,end);
            }
        }
        createAllGraphImages();
    }
    function setAllGraphSizes(size){
        for (key in G.graphs){
            if ( typeof(G.graphs[key]) != 'function' ){
                G.graphs[key].size = size;
                var i = $('sizeindicatorfor-' + key);
                if ( i ){
                    i.innerHTML = size;
                    gsliders[key].setValue(size);
                }
            }
        }
        createAllGraphImages();
    }
    function removeGraphPath(me,e){
        var list    = e.target.parentNode.parentNode;
        var ele     = e.target.parentNode;
        var pathno  = e.target.parentNode.id.replace(/[^_]+_(\d+)/,'$1');
        var i       = 0;
        var tmp     = new Array();
        //first turn off the Sortable
        //Sortable.destroy(list);
        destroyDraggable(me);
        //see if we have any predictions for that line
        var gp = G.graphs[me].paths[pathno].path;
        var amIpredict = G.graphs[me].paths[pathno].isPredict;
        var predicting = 0;
        var predictTotal = 0;
        //then rebuild the array
        G.graphs[me].paths.each(function(path){
            if ( i != pathno ){
                if ( path.path == gp && ! amIpredict){
                    predictele=$('pathli-' + me + '_' + i);
                    list.removeChild(predictele);
                }else{
                    tmp.push(path);
                    if ( path.isPredict ){
                        predicting = 1;
                        if (path.path == 'total'){
                            predictTotal=1;
                        }
                    }
                }
            }else{
                if ( path.path == 'total' && path.isPredict == 0){
                    noJustTotal(me);
                }
                if ( path.path == 'avg' ){
                    G.graphs[me].avg = 0;
                }
            }
            i++;
        })
        if ( tmp.length == 0 ){
            // I was the last path, so get rid of the graph
            closeGraph(me);
            return;
        }
        //make the tmp array the real deal
        G.graphs[me].paths = tmp;
        G.graphs[me].predicting = predicting;
        G.graphs[me].predictTotal = predictTotal;
        //remove the li
        list.removeChild(ele);
        var i=0;
        //rebuild the DOM IDs
        deIdList(list,me);
        if ( G.graphs[me].paths.length < 4 || G.graphs[me].paths.length < 10 ){
            Element.hide('blendiconfor-' + me);
        }else{
            Element.show('blendiconfor-' + me);
        }
        createGraphImage(me,0);
    }
    function deIdList(list,mygraph){
        $A($(list).childNodes).each(function(e){
            if(e.tagName.toLowerCase() == 'li'){
                e.removeAttribute('id');
                $A($(e).childNodes).each(function(f){
                    if ( f.tagName ){
                        if(f.tagName.toLowerCase() == 'input'){
                            f.removeAttribute('id');
                        }
                        if(f.tagName.toLowerCase() == 'label'){
                            f.removeAttribute('id');
                        }
                    }
                })
            }
        })
        var i=0;
        $A($(list).childNodes).each(function(e){
            if(e.tagName.toLowerCase() == 'li'){
                e.id = 'pathli-' + mygraph + '_' + i;
                $A($(e).childNodes).each(function(f){
                    if ( f.tagName ){
                        if(f.tagName.toLowerCase() == 'input'){
                            if ( f.className.match(/colorfor/) ){
                                f.id = 'colorfor-' + mygraph + '_' + i;
                            }
                            if ( f.className.match(/display/) ){
                                f.id = 'display-' + mygraph + '_' + i;
                            }
                        }
                        if(f.tagName.toLowerCase() == 'label'){
                            f.id = 'colorexample' + mygraph + i;
                        }
                    }
                })
                i++;
            }
        })
    }
    function convertTime(e){
        var myid = e.target.previousSibling.id;
        var str  = e.target.previousSibling.value
        x_convertTime(myid,str,convertTimeCB);
    }
    function convertTimeCB(s){
        var timea = s.evalJSON();
        var me    = $(timea[0]);
        me.value  = timea[1];
        var mygraph = timea[0].replace(/[^-]*-(\d+)/,'$1');
        if ( timea[0].match(/start-\d+/) ){
            G.graphs[mygraph].start = timea[1];
        }else{
            G.graphs[mygraph].end = timea[1];
        }
    }
    function zoom(action,graph){
        amt=$('userpzoompct').value;
        x_zoomTimes(G.graphs[graph].start,G.graphs[graph].end,amt,action,graph,zoomCB);
    }
    function zoomCB(s){
        var a = s.evalJSON();
        //just assume everything is great!
        var start   = $('start-' + a[2]);
        if ( start ){
            start.value = a[0];
        }
        var end     = $('end-' + a[2]);
        if ( end ){
            end.value   = a[1];
        }
        G.graphs[a[2]].start = a[0];
        G.graphs[a[2]].end   = a[1];
        createGraphImage(a[2],0);
    }
    function setCurrentGraph(me){
        var old     = $$('div.currentgraph');
        if ( old !== undefined && old[0] !== undefined ){
            Element.removeClassName(old[0],'currentgraph');
            old[0].parentNode.style.zIndex = 0;
        }
        //for the Draggable
        G.cg        = me;
        Element.addClassName('titlefor-' + me,'currentgraph');
        $('graph-' + me).style.zIndex = 1;
    }
    function createGraphImage(me,dooverlay){
        var spin = $('spinnerfor-' + me);
        if ( ! spin ){ return; }
        Element.show(spin);
        var paths = Object.toJSON(G.graphs[me]);
        x_createGraphImage(me,paths,dooverlay,createGraphImageCB);
    }
    function createGraphImageCB(s){
        //console.log(s);
        //return;
        var result = s.evalJSON();
        if ( result[0] == 'ERROR' ){
            //console.log(result[1]);
            dsPicker.handleError(result[1]);
            Element.hide('spinnerfor-' + result[2]);
            return;
        }
        if ( result[0] == 'image' ){
            var graph      = result[1];
            var image      = result[2];
            var height     = parseInt(result[3]);
            var width      = parseInt(result[4]);
            var it         = $('graphimage-' + graph);
            it.src         = image;
            it.height      = height;
            it.width       = width;
            it.title       = G.graphs[graph].graphlabel;
            var gc         = $('graph-' + graph);
            var gcw        = width + 37;
            gc.style.width = gcw + "px";
            var lastdrawn  = image.replace(/.*\?(\d+)/,'$1');
            //console.log('bg last drawn ',lastdrawn);
            G.graphs[graph].bglastdrawn = lastdrawn;

            G.graphs[graph].min   = result[5];
            G.graphs[graph].max   = result[6];
            var ol = $('overlaycontainerdiv-' + graph);
            result[7] = parseInt(result[7]) + 10;
            ol.style.left = result[7] + 'px';
            G.graphs[graph].xoff  = result[7];

            result[8] = parseInt(result[8]) + 37;
            ol.style.top = result[8] + 'px';
            G.graphs[graph].yoff  = result[8];

            G.graphs[graph].xsize = parseInt(result[9]);
            ol.style.width = result[9] + 'px';
            G.graphs[graph].ysize = parseInt(result[10]);
            ol.style.height = result[10] + 'px';
            Element.hide('spinnerfor-' + graph);

            var ole = $('seldiv-' + graph);
            if ( ole ){
                if ( ole.style.left == 0 || ole.style.height === undefined ){
                    return;
                }
                var neww = parseInt(result[9]);
                var newh = parseInt(result[10]) + 'px';
                var pme = $('overlaydragdiv-' + graph);
                pme.style.width = neww + 'px';
                pme.style.height = newh;
                var sizes = [];
                    sizes[50]  = 200;
                    sizes[100] = 400;
                    sizes[150] = 500;
                    sizes[250] = 575;
                    sizes[350] = 650;
                var oldh = parseInt(ole.style.height);
                if ( sizes[oldh] === undefined ){
                    return;
                }
                var oldw = sizes[oldh];
                if ( oldh != newh ){
                    ole.style.height = newh;
                }
                var oldsw  = parseInt(ole.style.width);
                var swperc = oldsw /oldw;
                var newsw  = Math.floor(neww * swperc)
                var oldoff = parseInt(ole.style.left);
                var perc   = oldoff / oldw;
                var newoff = Math.floor(neww * perc);
                ole.style.width = newsw + 'px';
                ole.style.left = newoff + 'px';
            }
            // Use the overlay if there is one
            if ( result[11] ){
                createGraphDragOverlayCB(result[11]);
            }
        }
    }
    function drawGraph(){
        var me             = G.cg;
        if ( ! G.graphs[me].graphlabel ){
            G.graphs[me].graphlabel = '';
        }
        if ( ! G.graphs[me].vertlabel ){
            G.graphs[me].vertlabel = '';
        }
        if ( G.graphs[me].size === undefined ){
            G.graphs[me].size = 50;
        }
        var fw=0; //fakewidth
        var fh=0;
        // having these hardcoded sizes is stupid, but it's better than
        // nothing at this point
        switch(G.graphs[me].size){
            case 0:
                fw = 287;
                fh = 300;
                break;
            case 50:
                fw = 487;
                fh = 500;
                break;
            case 100:
                fw = 588;
                fh = 600;
                break;
            case 150:
                fw = 662;
                fh = 650;
                break;
            case 200:
                fw = 737;
                fh = 725;
                break;
            default:
        }
        var contain         = document.createElement('div');
        contain.id          = 'graph-' + me;
        contain.className   = 'offcolor'; //FIX
        var fwe             = fw + 37;
        contain.style.width = fwe + "px";

        var titlebar       = document.createElement('div');
        titlebar.className = 'graphtitle';
        titlebar.id        = 'titlefor-' + me;
        var span           = document.createElement('span');
        span.className     = 'closebutton';
        //spinner
        var spinner        = document.createElement('img');
        spinner.src        = 'img/scanner-transparent-back.gif';
        spinner.id         = 'spinnerfor-' + me;
        spinner.border     = '0';
        spinner.style.display = 'none';
        span.appendChild(spinner);
        //closebutton
        var img            = document.createElement('img');
        img.src            = 'img/stock-none-16.png';
        img.border         = '0';
        span.appendChild(img);
        titlebar.appendChild(span);
        var span           = document.createElement('span');
        span.className     = 'dragbutton';
        span.title         = 'Drag Me'; //FIX
        var dimg           = document.createElement('img');
        dimg.src           = 'img/stock_fullscreen.png';
        dimg.border        = '0';
        span.appendChild(dimg);
        titlebar.appendChild(span);
        var span           = document.createElement('span');
        span.className     = "graphtitletext";
        span.id            = 'titlebartextfor-' + me;
        var txt            = document.createTextNode('Graph #' + me );
        span.appendChild(txt);
        titlebar.appendChild(span);
        Event.observe(span,'click',function(){setCurrentGraph(me)}.bindAsEventListener());
        Event.observe(img,'click',function(){closeGraph(me)}.bindAsEventListener());
        contain.appendChild(titlebar);

        var br             = document.createElement('br');
        br.className       = 'clearboth';
        contain.appendChild(br);

        //Overlay
        var odivc             = document.createElement('div');
        odivc.style.width     = '400px';
        odivc.style.height    = '100px';
        odivc.style.position  = 'absolute';
        odivc.style.overflow  = 'hidden';
        odivc.style.top       = 58 + 'px';
        odivc.style.left      = 61 + 'px';
        odivc.style.cursor    = 'move';
        odivc.style.backgroundColor = "transparent";
        odivc.id              = 'overlaycontainerdiv-' + me;
        contain.appendChild(odivc);
        Event.observe(odivc,'mouseover',graphMouseOverHandler);
        Event.observe(odivc,'mouseout',graphMouseOutHandler);

        //GRAPH
        var img            = document.createElement('img');
        //img.src            = 'img/scanner-transparent-back.gif';
        img.src            = 'img/14-1.gif';
        //img.src            = 'fortune.php';
        img.border         = '0';
        //img.height         = 223;
        img.height         = fh;
        //img.width          = 487;
        img.width          = fw;
        img.className      = 'graphimage'
        img.id             = 'graphimage-' + me;
        contain.appendChild(img);

        var div            = document.createElement('div');
        div.id             = 'uifor-' + me;

        //Redraw
        var span           = document.createElement('span');
        span.className     = 'redrawgraphbutton clickable';
        var img            = document.createElement('img');
        img.src            = 'img/sc_formatpaintbrush.png';
        img.title          = 'Redraw Graph';
        Event.observe(img,'click',function(){createGraphImage(me,0)}.bindAsEventListener());
        span.appendChild(img);
        div.appendChild(span);

        //GEAR
        var span           = document.createElement('span');
        span.className     = 'showgraphuibutton';
        var img            = document.createElement('img');
        img.src            = 'img/stock_exec-16.png';
        img.title          = 'Show Graph UI';
        Event.observe(img,'click',function(){showGraphUI(me)}.bindAsEventListener());
        span.appendChild(img);
        div.appendChild(span);

        //ZOOM
        var span           = document.createElement('span');
        span.className     = 'zoomui';
        var img            = document.createElement('img');
        img.src            = 'img/stock_data-first-16.png';
        img.border         = '0';
        img.title          = 'Move Left';
        Event.observe(img,'click',function(){ zoom('left',me)}.bindAsEventListener() );
        span.appendChild(img);
        var img            = document.createElement('img');
        img.src            = 'img/stock_zoom-in-16.png';
        img.border         = '0';
        img.title          = 'Zoom In';
        Event.observe(img,'click',function(){ zoom('in',me)}.bindAsEventListener() );
        span.appendChild(img);
        var img            = document.createElement('img');
        img.src            = 'img/stock_zoom-out-16.png';
        img.border         = '0';
        img.title          = 'Zoom Out';
        Event.observe(img,'click',function(){ zoom('out',me)}.bindAsEventListener() );
        span.appendChild(img);
        var img            = document.createElement('img');
        img.src            = 'img/stock_data-last-16.png';
        img.border         = '0';
        img.title          = 'Move Right';
        Event.observe(img,'click',function(){ zoom('right',me)}.bindAsEventListener() );
        span.appendChild(img);
        div.appendChild(span);

        var br             = document.createElement('br');
        br.className       = 'clearboth';
        div.appendChild(br);
        contain.appendChild(div);
        if ( $('graph-' + me ) ){
            $('graphspace').removeChild($('graph-' + me));
        }
        $('graphspace').appendChild(contain);
        new Draggable('graph-' + me,{handle:'dragbutton',zindex:false,revert:function(){setCurrentGraph(me)}});
        createGraphImage(me,0); //FIX prolly move this below the final addition to the DOM
    }
    function createGraphUI(me){

        var contain         = $('graph-' + me);
        //SUBGEAR
        var div            = document.createElement('div');
        div.style.display  = 'none';
        div.id             = 'controlsfor-' + me;
        div.className      = 'controlcontainer';

        //TABS
        var tabsdiv        = document.createElement('div');
        tabsdiv.id         = 'controlstabsdivfor-' + me;
        tabsdiv.className  = 'controlstabsdiv';

        //times
        var timesTabSpan   = document.createElement('span');
        timesTabSpan.id    = 'timestabfor-' + me;
        timesTabSpan.className = 'controlstab';
        var timesTabTxt    = document.createTextNode('Times');
        timesTabSpan.appendChild(timesTabTxt);
        Event.observe(timesTabSpan,'click',function(){ tabControlDisplay('time',me)}.bindAsEventListener() );
        tabsdiv.appendChild(timesTabSpan);

        //lines
        var linesTabSpan   = document.createElement('span');
        linesTabSpan.id    = 'linestabfor-' + me;
        linesTabSpan.className = 'controlstab';
        var linesTabTxt    = document.createTextNode('Lines');
        linesTabSpan.appendChild(linesTabTxt);
        Event.observe(linesTabSpan,'click',function(){ tabControlDisplay('line',me)}.bindAsEventListener() );
        tabsdiv.appendChild(linesTabSpan);

        //labels
        var labelTabSpan   = document.createElement('span');
        labelTabSpan.id    = 'labelstabfor-' + me;
        labelTabSpan.className = 'controlstab';
        var labelTabTxt    = document.createTextNode('Labels');
        labelTabSpan.appendChild(labelTabTxt);
        Event.observe(labelTabSpan,'click',function(){ tabControlDisplay('label',me)}.bindAsEventListener() );
        tabsdiv.appendChild(labelTabSpan);

        //size
        var labelTabSpan   = document.createElement('span');
        labelTabSpan.id    = 'sizetabfor-' + me;
        labelTabSpan.className = 'controlstab';
        var labelTabTxt    = document.createTextNode('Size');
        labelTabSpan.appendChild(labelTabTxt);
        Event.observe(labelTabSpan,'click',function(){ tabControlDisplay('size',me)}.bindAsEventListener() );
        tabsdiv.appendChild(labelTabSpan);

        //Events
        var labelTabSpan   = document.createElement('span');
        labelTabSpan.id    = 'eventtabfor-' + me;
        labelTabSpan.className = 'controlstab';
        var labelTabTxt    = document.createTextNode('Events');
        labelTabSpan.appendChild(labelTabTxt);
        Event.observe(labelTabSpan,'click',function(){ tabControlDisplay('event',me)}.bindAsEventListener() );
        tabsdiv.appendChild(labelTabSpan);

        //ENDTABS
        div.appendChild(tabsdiv);

        //TIMES
        var timecontainer  = document.createElement('div');
        //timecontainer.style.display = 'none';
        timecontainer.id   = 'timecontrolcontainerfor-' + me;

        //RESET TIME
        var span           = document.createElement('span');
        span.className     = 'timeresetbutton';
        var img            = document.createElement('img');
        img.src            = 'img/stock_undo-16.png';
        img.border         = '0';
        img.title          = 'Reset to Default Times';
        Event.observe(img,'click',function(){resetGraphTime(me)}.bindAsEventListener());
        span.appendChild(img);
        timecontainer.appendChild(span);

        //SET ALL TIMES TO ME
        var span           = document.createElement('span');
        span.className     = 'timeresetbutton';
        var img            = document.createElement('img');
        img.src            = 'img/timezone3.png';
        img.border         = '0';
        img.title          = 'Set All Graph Times To This'
        Event.observe(img,'click',function(){setAllTimesToMe(me)}.bindAsEventListener());
        span.appendChild(img);
        timecontainer.appendChild(span);

        //START
        var tdiv           = document.createElement('div');
        tdiv.className     = 'startandendtimes';
        tdiv.id            = 'startandendtimesfor-' + me;
        var label          = document.createElement('label');
        label.htmlFor      = 'start-' + me;
        var txt            = document.createTextNode('Start: ');//FIX
        label.appendChild(txt);
        tdiv.appendChild(label);
        var inp            = document.createElement('input');
        inp.id             = 'start-' + me;
        inp.value          = G.graphs[me].start;
        inp.size           = '30';
        Event.observe(inp,'change',function(e){setGraphStart(me,e)}.bindAsEventListener());
        tdiv.appendChild(inp);
        var img            = document.createElement('img');
        img.src            = 'img/stock_form-time-field.png';
        img.title          = 'Convert to Absolute Time';
        img.className      = 'updatetime';
        Event.observe(img,'click',convertTime.bindAsEventListener());
        tdiv.appendChild(img);
        var br             = document.createElement('br');
        tdiv.appendChild(br);

        //END
        var label          = document.createElement('label');
        label.htmlFor      = 'end-' + me;
        var txt            = document.createTextNode('End: ');//FIX
        label.appendChild(txt);
        tdiv.appendChild(label);
        var inp            = document.createElement('input');
        inp.id             = 'end-' + me;
        inp.value          = G.graphs[me].end;
        inp.size           = '30';
        Event.observe(inp,'change',function(e){setGraphEnd(me,e)}.bindAsEventListener());
        tdiv.appendChild(inp);
        var img            = document.createElement('img');
        img.src            = 'img/stock_form-time-field.png';
        img.title          = 'Convert to Absolute Time';
        img.className      = 'updatetime';
        Event.observe(img,'click',convertTime.bindAsEventListener());
        tdiv.appendChild(img);
        var br             = document.createElement('br');
        tdiv.appendChild(br);
        //END TIMES
        timecontainer.appendChild(tdiv);
        div.appendChild(timecontainer);

        //LINES
        var linecontainer  = document.createElement('div');
        linecontainer.style.display = 'none';
        linecontainer.id   = 'linecontrolcontainerfor-' + me;
        linecontainer.className = 'linecontainer';

        //TOTAL
        var button         = document.createElement('img');
        button.src         = 'img/stock_sum-16.png';
        button.title       = 'Add a Total line';//FIX
        button.id          = 'total-' + me;
        button.className   = 'clickable';
        Event.observe(button,'click',function(){addGraphTotal(me)}.bindAsEventListener());
        linecontainer.appendChild(button);
        var justtotal      = document.createElement('input');
        justtotal.id       = 'justtotalfor-' + me;
        justtotal.type     = 'checkbox';
        if ( G.graphs[me].justtotal ){
            justtotal.checked = true;
        }
        if ( ! G.graphs[me].total ){
            justtotal.style.display = 'none';
        }
        Event.observe(justtotal,'change',function(e){setJustTotal(me,e)}.bindAsEventListener());
        linecontainer.appendChild(justtotal);

        //AVG (needs better icon)
        var button         = document.createElement('img');
        button.src         = 'img/stock_format-scientific-16.png';
        button.title       = 'Add an Average line';//FIX
        button.id          = 'avg-' + me;
        button.className   = 'clickable someleftmargin';
        Event.observe(button,'click',function(){addGraphAvg(me)}.bindAsEventListener());
        linecontainer.appendChild(button);

        //COLOR SWATCH
        var canvastxt  = document.createTextNode('Canvas:');
        var span       = document.createElement('span');
        span.appendChild(canvastxt);
        var inp        = document.createElement('input');
        inp.style.display = 'none';
        inp.id         = 'canvascolorfor-' + me;
        if ( G.graphs[me].canvas ){
            inp.value      = G.graphs[me].canvas.replace(/#/,'');
        }else{
            inp.value      = 'FFFFFF';
        }
        var oinp       = document.createElement('input');
        oinp.style.display = 'none';
        oinp.id        = 'canvasopacityfor-' + me;
        var lab        = document.createElement('label');
        lab.className  = 'colorexample';
        lab.id         = 'colorexampleCANVAS-' + me;
        if ( G.graphs[me].canvas ){
            lab.style.backgroundColor = '#' + G.graphs[me].canvas;
        }else{
            G.graphs[me].canvas       = 'FFFFFF';
            lab.style.backgroundColor = '#FFFFFF';
        }
        new Control.ColorPicker( inp, { 'swatch':lab, 'opacityField':oinp, 'onUpdate':updateCanvas });
        var br             = document.createElement('br');
        linecontainer.appendChild(br);
        linecontainer.appendChild(span);
        linecontainer.appendChild(inp);
        linecontainer.appendChild(oinp);
        linecontainer.appendChild(lab);
        //BLEND
        var blend = document.createElement('img');
        blend.src = 'img/stock_filters-pop-art-16.png';
        if ( G.graphs[me].paths.length < 4 || G.graphs[me].paths.length > 10 ){
            blend.style.display = 'none';
        }
        blend.id    = 'blendiconfor-' + me;
        blend.title = 'Blend Colors';
        blend.className = 'colorblendicon';
        Event.observe(blend,'click',function(e){blendColors(me,e)}.bindAsEventListener());
        //var br    = document.createElement('br');
        //linecontainer.appendChild(br);
        linecontainer.appendChild(blend);


        //PATHS
        var ul             = document.createElement('ul');
        ul.className       = 'pathlist';
        ul.id              = 'pathul-' + me;
        var i              = 0;
        G.graphs[me].paths.each(function(path){
            var li = pathLi(path,me,i);
            ul.appendChild(li);
            i++;
        });
        linecontainer.appendChild(ul);
        Event.observe(ul,'mouseover',createDraggable);

        var br             = document.createElement('br');
        br.className       = 'clearboth';
        linecontainer.appendChild(br);
        div.appendChild(linecontainer);

        //LABLES
        var labelcontainer = document.createElement('div');
        labelcontainer.style.display = 'none';
        labelcontainer.id   = 'labelcontrolcontainerfor-' + me;
        labelcontainer.className = 'labelcontainer';
        var label          = document.createElement('label');
        label.htmlFor      = 'graphlabel-' + me;
        var txt            = document.createTextNode('Graph Label: ');
        label.appendChild(txt);
        labelcontainer.appendChild(label);
        var inp            = document.createElement('input');
        inp.id             = 'graphlabel-' + me;
        inp.value          = G.graphs[me].graphlabel;
        inp.size           = '30';
        Event.observe(inp,'change',function(e){setGraphLabel(me,e)}.bindAsEventListener());
        labelcontainer.appendChild(inp);
        var br             = document.createElement('br');
        labelcontainer.appendChild(br);

        var label          = document.createElement('label');
        label.htmlFor      = 'vertlabel-' + me;
        var txt            = document.createTextNode('Vertical Label: ');
        label.appendChild(txt);
        labelcontainer.appendChild(label);
        var inp            = document.createElement('input');
        inp.id             = 'vertlabel-' + me;
        inp.value          = G.graphs[me].vertlabel;
        inp.size           = '30';
        Event.observe(inp,'change',function(e){setVertLabel(me,e)}.bindAsEventListener());
        labelcontainer.appendChild(inp);
        div.appendChild(labelcontainer);

        //Size
        var sizecontainer  = document.createElement('div');
        var br             = document.createElement('br');
        sizecontainer.appendChild(br);
        sizecontainer.style.display = 'none';
        sizecontainer.id   = 'sizecontrolcontainerfor-' + me;
        sizecontainer.className = 'sizecontainer';
        var br             = document.createElement('br');
        sizecontainer.appendChild(br);
        var sizeindicator  = document.createElement('div');
        sizeindicator.id   = 'sizeindicatorfor-' + me;
        sizeindicator.innerHTML = G.graphs[me].size;
        sizecontainer.appendChild(sizeindicator);
        //Size Slider
        var slidediv = document.createElement('div');
        slidediv.className = 'slidediv';
        slidediv.id        = 'slidedivfor-' + me;
        slidediv.style.width = '200px';
        var slidehandle    = document.createElement('div');
        slidehandle.className = 'slidehandle';
        slidehandle.id     = 'slidehandlefor-' + me;
        var meimg = document.createElement('img');
        meimg.src = "img/stock_up.png";
        slidehandle.appendChild(meimg);
        slidediv.appendChild(slidehandle);
        sizecontainer.appendChild(slidediv);
        div.appendChild(sizecontainer);

        //Events
        var eventcontainer  = document.createElement('div');
        var br             = document.createElement('br');
        eventcontainer.appendChild(br);
        eventcontainer.style.display = 'none';
        eventcontainer.id   = 'eventcontrolcontainerfor-' + me;
        eventcontainer.className = 'eventcontainer';
        var br             = document.createElement('br');
        eventcontainer.appendChild(br);
        var label          = document.createElement('label');
        label.htmlFor      = 'eventselectorfor-' + me;
        var txt            = document.createTextNode('Show:');
        label.appendChild(txt);
        eventcontainer.appendChild(label);
        var inp            = document.createElement('input');
        inp.id             = 'eventselectorfor-' + me;
        inp.type           = 'text';
        if ( G.graphs[me].events === undefined ){
            inp.value          = '';
        }else{
            inp.value          = G.graphs[me].events;
        }
        inp.size           = '30';
        Event.observe(inp,'change',function(e){limitEvents(me,e)}.bindAsEventListener());
        Event.observe(inp,'dingus:change',function(e){limitEvents(me,e)}.bindAsEventListener());
        eventcontainer.appendChild(inp);
        var etags          = document.createElement('div');
        etags.id           = 'eventtagsfor-' + me;
        etags.className    = 'graphEventTags';
        eventcontainer.appendChild(etags);
        dsPicker.populateEventTags(etags,inp);
        div.appendChild(eventcontainer);

        contain.appendChild(div);

        gsliders[me] = new Control.Slider('slidehandlefor-' + me,'slidedivfor-' + me, {sliderValue: G.graphs[me].size,range:$R(0,200),values:[0,50,100,150,200], onSlide: function(v){$('sizeindicatorfor-' + me).innerHTML = v}, onChange:function(v){G.graphs[me].size = v;createGraphImage(me,0)}});
    }

    function pathLi(path,me,i){
        var li         = document.createElement('li');
        li.className   = 'pathlist';
        li.id          = 'pathli-' + me + '_' + i;
        li.style.whiteSpace = 'nowrap';
        var img        = document.createElement('img');
        img.src        = 'img/gtk-delete.png';
        img.border     = '0';
        img.title      = 'Remove This Path'; //FIX
        img.className  = 'removepath';
        li.appendChild(img);
        Event.observe(img,'click',function(e){removeGraphPath(me,e)}.bindAsEventListener());
        //LINE TYPE
        // what has the user defined the drawtype to be?
        var thisDrawType = G.graphs[me].paths[i].drawtype;
        var select = document.createElement('select');
        Event.observe(select,'change',function(e){changeLineDrawType(me,e)}.bindAsEventListener());
        select.className = 'linetype';

        [ 'LINE1','LINE2','LINE3','AREA','STACK',
          '-LINE1','-LINE2','-LINE3', '-AREA', '-STACK' ].each(function(drawt){
            var option = document.createElement('option');
            if ( thisDrawType == drawt ){
                option.selected = true;
            }
            var txt    = document.createTextNode(drawt);
            option.appendChild(txt);
            option.value = drawt;
            select.appendChild(option);
        });
        li.appendChild(select);

        //COLOR SWATCH
        var inp        = document.createElement('input');
        inp.style.display = 'none';
        inp.id         = 'colorfor-' + me + '_' + i;
        inp.className  = 'colorfor';
        inp.value      = path.color.replace(/#/,'');
        var oinp       = document.createElement('input');
        oinp.style.display = 'none';
        oinp.id        = 'opacityfor-' + me + '_' + i;
        if ( path.opacity !== undefined ){
            oinp.value     = path.opacity;
        }else{
            oinp.value     = 'ff';
            path.opacity   = 'ff';
        }
        var lab        = document.createElement('label');
        lab.className  = 'colorexample';
        lab.id         = 'colorexample' + me + i;
        lab.style.backgroundColor = path.color;
        lab.style.opacity         = parseInt(path.opacity,16) /255;
        new Control.ColorPicker( inp, { 'swatch':lab, 'opacityField':oinp, 'onUpdate':updateColor });
        li.appendChild(inp);
        li.appendChild(oinp);
        li.appendChild(lab);

        //DISPLAY CHECKBOX
        //var txt = document.createTextNode(' | ');
        //li.appendChild(txt);
        var dcb       = document.createElement('input');
        dcb.type      = 'checkbox';
        dcb.title     = 'Display this DS';
        dcb.id        = 'display-' + me + '_' + i;
        dcb.className = 'display';
        // the or is for old playlists that didn't have .display
        if ( path.display == 1 || path.display === undefined ){
            dcb.checked = true;
        }
        li.appendChild(dcb);
        Event.observe(dcb,'change',function(e){toggleDsDisplay(e)}.bindAsEventListener());
        //PREDICT ICON
        if ( (path.isPredict === undefined || path.isPredict == 0) && path.name != 'avg' ){
            //var txt = document.createTextNode(' | ');
            //li.appendChild(txt);
            var img = document.createElement('img');
            img.src = 'img/sc27059.png';
            img.className = 'clickable predicticon';
            img.title     = 'Predict this line';
            li.appendChild(img);
            var p = path.path;
            Event.observe(img,'click',function(){addPredictLine(p,me)}.bindAsEventListener());
        }
        if ( path.name != 'avg' && path.name != 'total' ){
            var img        = document.createElement('img');
            // not the best image... get a better one
            img.src        = 'img/stock_text_underlined-16.png';
            img.style.verticalAlign = 'middle';
            img.title      = "Edit this line label";
            li.appendChild(img);
        }
        var span       = document.createElement('span');
        var txt        = document.createTextNode(' ' + path.name + ' ');
        span.appendChild(txt);
        li.appendChild(span);
        // this span must be the previous sibling to the input for this
        // to keep working.
        if ( path.name != 'avg' && path.name != 'total' ){
            var einp       = document.createElement('input');
            einp.value     = path.name;
            einp.style.display = "none";
            einp.size      = '30';
            li.appendChild(einp);
            Event.observe(img,'click',function(){Element.toggle(span);Element.toggle(einp);}.bindAsEventListener());
            Event.observe(einp,'change',function(e){updatePathLabel(e)}.bindAsEventListener());
            //var txt        = document.createTextNode(' ');
            //li.appendChild(txt);
        }

        return li;
    }
    function limitEvents(me,e){
        G.graphs[me].events = e.target.value;
        G.cg = me;
        createGraphImage(me,0);
    }
    function addPredictLine(path,graph){
        setCurrentGraph(graph);
        G.graphs[graph].predicting = 1;
        addRrdToGraph('predict:'+path,0);
    }
    function toggleDsDisplay(e){
        var me = e.currentTarget.id.replace(/display-(\d+).*/,'$1');
        var i  = e.currentTarget.id.replace(/display-\d+_(\d+)/,'$1');
        if ( e.currentTarget.checked == true ){
            G.graphs[me].paths[i].display = 1;
            if ( G.graphs[me].paths[i].path == 'total' ){
                G.graphs[me].total = 1;
                var jtf = $('justtotalfor-' + me);
                Element.show(jtf);
            }
        }else{
            G.graphs[me].paths[i].display = 0;
            if ( G.graphs[me].paths[i].path == 'total' ){
                noJustTotal(me);
            }
        }
        setCurrentGraph(me);
        createGraphImage(me,0);
    }
    function noJustTotal(graph){
        G.graphs[graph].total = 0;
        G.graphs[graph].justtotal = 0;
        var jtf = $('justtotalfor-' + graph);
        jtf.checked = false;
        Element.hide(jtf);
    }
    function updatePathLabel(e){
        var ele  = e.currentTarget;
        // get rid of nasty chars
        var s    = e.currentTarget.value.replace(/[!@#$%^&*(){}\[\]]/g,'');
        var x    = e.currentTarget.parentNode.id;
        var me   = x.replace(/pathli-(\d+).*/,'$1');
        var path = x.replace(/pathli-\d+_(\d+).*/,'$1');
        e.currentTarget.value = s;
        G.graphs[me].paths[path].name = s;
        var span = e.currentTarget.previousSibling;
        // the ringer cannot look empty
        if ( s === '' || s.match(/^\s+$/) ){
            s = span.textContent;
            e.currentTarget.value = span.textContent;
        }
        span.innerHTML = ' ' + s + ' ';
        Element.toggle(ele);
        Element.toggle(span);
        createGraphImage(me,0);
    }
    function tabControlDisplay(which,graph){
        [ 'time','line','label','size','event' ].each(function(tab){
            if ( tab == which ){
                Element.show(tab + 'controlcontainerfor-' + graph);
            }else{
                Element.hide(tab + 'controlcontainerfor-' + graph);
            }
        });
    }
    function setGraphLabel(me,e){
        var v = e.target.value.replace(/[!@#$%^&*(){}\[\]]/g,'');
        G.graphs[me].graphlabel = v;
        e.target.value = v;
        //var t = document.createTextNode(G.graphs[me].graphlabel);
        //$('graphlabelfor-' + me).appendChild(t);
        G.createGraphImage(me,0);
    }
    function setVertLabel(me,e){
        G.graphs[me].vertlabel = e.target.value;
        G.createGraphImage(me,0);
    }
    function autoRefresh(e){
        var n = e.target.value;
        if ( n != 0 ){
            autoRefreshSetup(n);
        }
    }
    function autoRefreshSetup(n){
        if ( n != 0 ){
            refreshd = setTimeout('G.autoRefreshReal(' + n + ')',n);
        }
    }
    function setAllTimesToMe(me){
        var s = $('start-' + me ).value;
        var e = $('end-' + me ).value;
        setAllGraphTimes(s,e);
    }
    function createAllGraphImages(){
        for (key in G.graphs){
            if ( typeof(G.graphs[key]) != 'function' ){
                    G.createGraphImage(key,0);
            }
        }
    }
    function autoRefreshReal(n){
        var s = $('autorefresh');
        if ( n != 0 && s.value != 0 ){
            G.createAllGraphImages();
            G.autoRefreshSetup(n);
        }
    }
    function graphMouseOverHandler(e){
        if ( ! isMouseLeaveOrEnter(e, this) ){
            //console.log('isMouseLOE is causing OVER to return on ' + me);
            return;
        }
        var me = e.currentTarget.id.replace(/[^-]*-(\d+)/,'$1');
        if ( G.graphs[me].bglastdrawn === undefined ){
            return;
        }
        //console.log('seems like an actual event on ' + me);
        //console.log(e);
        //console.log(e.currentTarget);
        Event.stopObserving(e.currentTarget,'mouseover',graphMouseOverHandler);
        G.graphs[me].mo = 1;
        //console.log(me);
        if ( G.tool == 0 ){
            createDraggableGraph(me);
            createGraphDragOverlay(me);
        }else{
            //console.log('building a select');
            createGraphSelOverlay(me);
            return;
        }
    }
    function graphMouseOutHandler(e){
        //console.log('graphMouseOutHandler called for '+me);
        if ( ! isMouseLeaveOrEnter(e, this) ){
            //console.log('isMouseLOE is causing OUT to return on ' + me);
            return;
        }
        //console.log('seems like an actual mouseOUT event on ' + me);
        //console.log(e);
        var me = e.currentTarget.id.replace(/[^-]*-(\d+)/,'$1');
        if ( G.graphs[me].bglastdrawn === undefined ){
            return;
        }
        //console.log(me);
        G.graphs[me].mo = 0;
        var odivc = $('overlaycontainerdiv-' + me);
        if ( G.tool == 0 ){
            var i = $('odim-' + me);
            if ( i ){
                i.src = 'img/blank.gif';
                odivc.style.backgroundColor = 'transparent';
                //console.log("about to destroy mydrag");
                mydrag.destroy();
                mydrag = '';
                //console.log("about to re-apply the graph mouseover handler to " + me);
                Event.observe(odivc,'mouseover',graphMouseOverHandler);
            }
        }else{
            var pme = $('overlaydragdiv-' + me);
            Event.stopObserving(pme,'mousedown',selDragStart.bindAsEventListener());
            mydrag.destroy();
            mydrag = '';
            Event.observe(odivc,'mouseover',graphMouseOverHandler);
        }
    }

    // http://www.dynamic-tools.net/toolbox/isMouseLeaveOrEnter/
    function isMouseLeaveOrEnter(e, handler) { 
        //console.log('IMLOE called');
        if (e.type != 'mouseout' && e.type != 'mouseover') return false;
        var reltg = e.relatedTarget ? e.relatedTarget: e.type == 'mouseout' ? e.toElement: e.fromElement; while (reltg && reltg != handler) reltg = reltg.parentNode;
        return (reltg != handler);
    }
    function createDraggableGraph(me){
        //console.log("createDraggableGraph called");
        //alert("createDraggableGraph called");
        // there is a container div in the main graph that this gets appended to
        // the container has overflow set to hidden, so we only see the main image,
        // not the left or right images

        var pme                     = $('overlaydragdiv-' + me);
        if ( ! pme ){
            createGraphOverlayDiv(me);
            var contain             = $('overlaycontainerdiv-' + me);
            contain.style.cursor    = 'move';
            contain.style.backgroundColor = '#' + G.graphs[me].canvas;
            var pme                 = $('overlaydragdiv-' + me);
            pme.style.width         = w + 'px';
            pme.style.left          = '-' + G.graphs[me].xsize + 'px';
            var odiml               = document.createElement('img');
            var w                   = G.graphs[me].xsize * 3;
            odiml.src               = 'img/blank.gif';
            odiml.id                = 'odim-' + me;
            odiml.width             = w;
            odiml.height            = G.graphs[me].ysize;
            odiml.border            = 0;
            odiml.style.cssFloat    = 'left';
            odiml.style.styleFloat  = 'left';
            pme.appendChild(odiml);

            Event.observe(pme,'mouseup',function(e){clickToCenterGraph(me,e)}.bindAsEventListener());
        }
        mydrag = new Draggable('overlaydragdiv-' + me,{constraint:'horizontal',revert:false,ghosting:false,onEnd:function(el,e){graphDragHandler(el,e)},starteffect:function(){return;}});

    }
    function createGraphOverlayDiv(me){
        var pme                     = $('overlaydragdiv-' + me);
        if ( ! pme ){
            var contain             = $('overlaycontainerdiv-' + me);
            //contain.style.backgroundColor = '#' + G.graphs[me].canvas;

            var odiv                = document.createElement('div');
            odiv.id                 = 'overlaydragdiv-' + me;
            odiv.style.width        = G.graphs[me].xsize + 'px';
            odiv.style.height       = G.graphs[me].ysize + 'px';
            odiv.style.position     = 'relative';
            contain.appendChild(odiv);
        }

    }
    function createGraphSelOverlay(me){
        var pme                          = $('overlaydragdiv-' + me);
        if ( ! pme ){
            var contain                  = $('overlaycontainerdiv-' + me);
            contain.style.cursor         = 'text';
            var seldiv                   = document.createElement('div');
            seldiv.id                    = 'seldiv-' + me;
            seldiv.style.position        = 'absolute';
            seldiv.style.top             = '0px';
            seldiv.style.width           = '0px';
            seldiv.style.height          = G.graphs[me].ysize + 'px';
            seldiv.style.backgroundColor = G.selColor;
            seldiv.style.opacity         = parseInt(G.selOpacity,16) /255;
            seldiv.className             = 'seldiv';
            var contain                  = $('overlaycontainerdiv-' + me);
            contain.appendChild(seldiv);
            createGraphOverlayDiv(me);
            var pme = $('overlaydragdiv-' + me);
        }
        overlayoffset[me] = Position.cumulativeOffset(pme);
        Event.observe(pme,'mousedown',selDragStart.bindAsEventListener());
        mydrag = new Draggable(pme,{constraint:'horizontal',revert:true,reverteffect:function(){pme.style.left=0;},ghosting:false,snap:[10,10],onEnd:function(el,e,me){selEnd(el,e,me)},change:function(e){selUpdate(mydrag.currentDelta(),overlayoffset[me],e,me)}});
    }
    function selDragStart(e){
        var el            = Event.element(e);
        var me            = el.id.replace(/\D+-(\d+)/,'$1');
        var x             = Event.pointerX(e);
        var contain       = $('overlaycontainerdiv-' + me);
        var co            = Position.cumulativeOffset(contain);
        overlayoffset[me] = co;
        var sd            = $('seldiv-' + me);
        sd.style.left     = x - co[0] + 'px';
        sd.oleft          = x -co[0];
        sd.style.width    = '0px';
    }
    function selUpdate(s,co,e,me){
        var sd = $('seldiv-' + me);
        var posi = s[0] * -1;
        if ( s[0] < 0 ){
            sd.style.left     = sd.oleft + s[0] + 'px';
            sd.style.width = posi + 'px';
        }else{
            sd.style.width = s[0] + 'px';
        }
    }
    function selEnd(el,e,me){
        var time = $('seltoolstime').checked;
        var sel  = $('seltoolssel').checked;
        if ( time ){
            //var sd            = $('seldiv-' + me);
            var me = el.element.id.replace(/\D+-(\d+)/,'$1');
            //console.log(el,e,me);
            var sl = Element.getStyle('seldiv-' + me,'left');
            s2     = sl.replace(/(\d+)px/,'$1');
            var sw = Element.getStyle('seldiv-' + me,'width');
            sw     = sw.replace(/(\d+)px/,'$1');
            e2     = parseInt(s2) + parseInt(sw);
            var s2p = s2 / G.graphs[me].xsize;
            var e2p = e2 / G.graphs[me].xsize;
            //console.log(s2,e2);
            //console.log(s2p,e2p,G.graphs[me].xsize);
            x_selTime(me,G.graphs[me].start,s2p,G.graphs[me].end,e2p,selTimeCB);
        }
    }
    function selTimeCB(s){
        //console.log(s);
        var a = s.evalJSON();
        updateTimes(a[0],a[1],a[2]);
        var sd         = $('seldiv-' + a[0]);
        sd.style.width = '0px';
        createGraphImage(a[0],0);
    }
    function createGraphDragOverlay(graph){
        //console.log(graph);
        if ( G.graphs[graph].ollastdrawn >= G.graphs[graph].bglastdrawn ){
            //console.log('using cached image');
            $('odim-' + graph).src = G.graphs[graph].overlayimage;
            return;
        }
        $('overlaycontainerdiv-' + graph).style.backgroundColor = '#' + G.graphs[graph].canvas;
        var oi = $('odim' + '-' + graph)
        oi.src = 'img/blank.gif';
        //oi.src = 'img/scanner-transparent-back.gif';
        //oi.height = G.graphs[graph].ysize;
        //oi.width  = G.graphs[graph].xsize;
        Element.show('spinnerfor-' + graph);
        //console.log('NOT using cached image');
        var paths = Object.toJSON(G.graphs[graph]);
        //console.log(paths);
        x_createGraphDragOverlay(graph,paths,createGraphDragOverlayCB);
    }
    function createGraphDragOverlayCB(s){
        //console.log(s);
        //return;
        if ( typeof(s) == 'string' ){
            var result = s.evalJSON();
        }else{
            var result = s;
        }
        //console.log(result);
        if ( result[0] == 'ERROR' ){
            //console.log(result[1]);
            dsPicker.handleError(result[1]);
            return;
        }
        if ( result[0] == 'image' ){
            var graph     = result[1];
            var me        = $('overlaydragdiv-' + graph);
            var it        = $('odim-' + graph);
            Element.hide('spinnerfor-' + graph);
            var image  = result[2];
            var height = result[3];
            var width  = result[4];
            it.src     = image;
            // not perfect, but as close as I can come knowing less than nothing
            it.onload = function(){ me.style.left = '-' + G.graphs[graph].xsize + 'px'};
            it.height  = height;
            it.width   = width;
            var lastdrawn = image.replace(/.*\?(\d+)/,'$1');
            G.graphs[graph].ollastdrawn  = lastdrawn;
            G.graphs[graph].overlayimage = image;
            return;
        }
        dsPicker.handleError(s);
    }
    function clickToCenterGraph(me,e){
        var cx = mydrag.currentDelta();
        cx = parseInt(cx[0]);
        var sz = 0 - G.graphs[me].xsize;
        if ( cx != sz ){
            return;
        }
        //console.log('clickToCenterGraph called');
        var ge = $('graph-' + me);
        var xoff = Event.pointerX(e) - (ge.offsetLeft + G.graphs[me].xoff);
        Element.show('spinnerfor-' + me);
        x_clickToCenterTime(G.graphs[me].start,G.graphs[me].end,me,G.graphs[me].xsize,xoff,clickToCenterGraphCB);
    }
    function clickToCenterGraphCB(s){
        //console.log(s);
        var a = s.evalJSON();
        updateTimes(a[0],a[1],a[2]);
        createGraphImage(a[0],1);

    }
    function graphDragHandler(e){
        //console.log(e,e.handle.id);
        //return;
        var cx = mydrag.currentDelta();
        cx = parseInt(cx[0]);
        //console.log('cx is ' + cx);
        //var graph = el.element.id;
        var graph = e.handle.id;
        graph     = graph.replace(/[^-]*-(\d+)/,'$1');
        cx += G.graphs[graph].xsize;
        //console.log(G.graphs[graph].xsize);
        // if cx (change in x) is negative, we are dragging into the future
        // if cx is positive, we are dragging into the past
        // if cx is greater than xsize, the user has dragged beyond the left image width
        // if cx is less than -xsize, the user has dragged beyond the right image width
        // divide by xsize to get the percentage change
        //console.log('graph is ' + graph);
        //console.log('cx is ' + cx);
        var start = G.graphs[graph].start;
        var end   = G.graphs[graph].end;
        var xsize = G.graphs[graph].xsize;
        x_dragTime(cx,graph,start,end,xsize,dragTimeCB);
    }
    function dragTimeCB(s){
        // graph, start, end
        //console.log(s);
        var a = s.evalJSON();
        if ( a[0] == 'ERROR' ){
            dsPicker.handleError(a[1]);
            return;
        }
        //console.log(a);
        updateTimes(a[0],a[1],a[2]);
        createGraphImage(a[0],1);
    }
    function selectValue(select,newValue){
        a = $(select);
        if ( a ){
            for( var i=0; i < a.options.length; i++ ){
                if ( a.options[i].value == newValue ){
                    a.selectedIndex=i;
                }
            }
        }
    }
    function userPrefsInit(){
        var ups=$('userpstart');
        ups.value=defaultstarttime;

        var upe=$('userpend');
        upe.value=defaultendtime;

        selectValue('userpsize',defaultsize);

        selectValue('userptool',G.tool);

        selectValue('userpzoompct',G.zoompct);

        var upc=$('upserpcanvasinp');
        var upoc=$('upserpcanvasopacityinp');
        upc.value=defaultCanvasColor.replace(/^#/,'');
        var upcl=$('userpcanvaslab');
        upcl.style.backgroundColor = '#' + defaultCanvasColor;
        new Control.ColorPicker( upc, { 'swatch':upcl, 'opacityField':upoc });

        var uphc=$('upserphighinp');
        var uphoc=$('upserphighopacityinp');
        var upo=$('upserphighopacityinp');
        upo.value = G.selOpacity;
        uphc.value=G.selColor;
        var uphcl=$('userphighlab');
        uphcl.style.backgroundColor = G.selColor;
        uphcl.style.opacity         = parseInt(G.selOpacity,16) /255;
        new Control.ColorPicker( uphc, { 'swatch':uphcl, 'opacityField':uphoc });

        var upccg=$('userpconfirmcloseall');
        upccg.checked = confirmcloseallgraphs;
        var upcdp=$('userpconfirmdeletepl');
        upcdp.checked = confirmdeleteplaylist;
        var upcop=$('userpconfirmoverwritepl');
        upcop.checked = confirmoverwriteplaylist;
        var upsmc=$('userpshowmergeck');
        upsmc.checked = showMergeCheckboxes;
    }
    function saveUserPrefs(){
        Element.hide('userprefsdiv');
        var ups=$('userpstart').value;
        //I'll no doubt regret this.
        var hash = new Hash();
        G.defaultstarttime = ups;
        hash.set('defaultstarttime',ups);

        var upe=$('userpend').value;
        G.defaultendtime = upe;
        hash.set('defaultendtime',upe);

        var upsize=$('userpsize').value;
        G.defaultsize = upsize;
        hash.set('defaultsize',upsize);

        var upt=$('userptool').value;
        G.tool = upt;
        tools = ['dragtoolicon','seltoolicon'];
        setTool(tools[upt]);
        hash.set('tool',upt);

        var upz=$('userpzoompct').value;
        hash.set('zoompct',upz);

        var upc=$('upserpcanvasinp').value;
        G.defaultCanvasColor = upc;
        hash.set('defaultCanvasColor',upc);

        var uphc=$('upserphighinp').value;
        var upho=$('upserphighopacityinp').value;
        hash.set('selColor', uphc);
        hash.set('selOpacity',upho);
        G.selColor = "#" + uphc;
        G.selOpacity = upho;
        var soi = $('selopacityinp');
        soi.value = G.selOpacity;
        updateSelColor(uphc,upho,'x');
        $('selcolorinp').value = selColor.replace(/^#/,'');
        var ces = $('colorexampleCANVAS-sel');
        ces.style.backgroundColor = selColor;
        ces.style.opacity         = parseInt(G.selOpacity,16) /255;

        var uca = 0;
        var upccg=$('userpconfirmcloseall').checked;
        if ( upccg ){
            uca = 1;
        }
        hash.set('confirmcloseallgraphs',uca);
        G.confirmcloseallgraphs = uca;

        var ucd = 0;
        var upcdp=$('userpconfirmdeletepl').checked;
        if ( upcdp ){
            ucd = 1;
        }
        hash.set('confirmdeleteplaylist',ucd);
        G.confirmdeleteplaylist = ucd;
        //  what was this trying to accomplish?
        //if ( G.graphs[0].paths[0] === undefined ){
            //closeAllGraphs(0);
        //}
        var uco = 0;
        var upcop=$('userpconfirmoverwritepl').checked;
        if ( upcop ){
            uco = 1;
        }
        hash.set('confirmoverwriteplaylist',uco);
        G.confirmoverwriteplaylist = uco;

        var tacos = 0;
        var upsmc=$('userpshowmergeck').checked;
        if ( upsmc ){
            tacos = 1;
        }
        hash.set('showMergeCheckboxes',tacos);
        if ( G.showMergeCheckboxes != tacos ){
            toggleMergeCheckboxes();
        }

        x_saveUserPrefs(hash.toJSON(),saveUserPrefsCB);
    }
    function saveUserPrefsCB(s){
        dsPicker.handleError('Preferences:' + s + ', bro!');
    }
    function toggleMergeCheckboxes(){
        var v = G.showMergeCheckboxes;
        if ( v == 1 ){
            G.showMergeCheckboxes = 0;
            Element.hide('mergedplaylistname');
            var cbs = $$('input.mergecheckbox');
            cbs.each(function(c){
                c.style.display="none";
            })
        }else{
            G.showMergeCheckboxes = 1;
            var cbs = $$('input.mergecheckbox');
            var i = 0;
            cbs.each(function(c){
                c.style.display="";
                if ( c.checked ){
                    i++;
                }
            })
            if ( i > 1 ){ // 1, not 0 because you don't want to merge 1 playlist.
                Element.show('mergedplaylistname');
            }
        }
    }
    function blendColors(me,e){
        var c1 = G.graphs[me].paths[0].color.replace(/#/,'');
        //console.log(c1);
        var cn = G.graphs[me].paths.length;
        if ( cn > 10 ){
            alert("too many lines, sorry");
            return;
        }
        var c2 = G.graphs[me].paths[ cn -1 ].color.replace(/#/,'');
        //console.log(c2);
        var steps = cn - 1;
        c1 = fromHex(c1);
        c2 = fromHex(c2);
        //console.log(c1,c2);
        var step = new Array();
        step[0] = Math.floor( (c1[0] - c2[0]) / steps );
        step[1] = Math.floor( (c1[1] - c2[1]) / steps );
        step[2] = Math.floor( (c1[2] - c2[2]) / steps );
        //console.log(step);
        G.graphs[me].paths.each(function(path,key){
            if ( key != 0 && key != steps ){
                var nr = c1[0] - (step[0] * key);
                var ng = c1[1] - (step[1] * key);
                var nb = c1[2] - (step[2] * key);
                //console.log(nr,ng,nb);
                nr = toHex(nr);
                ng = toHex(ng);
                nb = toHex(nb);
                var nc = '#' + nr + ng + nb;
                //console.log(key,nc);
                G.graphs[me].paths[key].color = nc;
                //console.log(me,key);
                $('colorfor-' + me + '_' + key).value = nr + ng + nb;
                $('colorexample' + me + key).style.backgroundColor = nc;
            }
        });
        createGraphImage(me,0);
    }
    function fromHex(s){
        var num = new Array(s.substr(0,2),s.substr(2,2),s.substr(4,2));
        var ret = new Array(parseInt(num[0],16),parseInt(num[1],16),parseInt(num[2],16));
        return ret;
    }
    function toHex(n){
        var pi = n.toString(16);
        if ( pi.length == 1 ){
            pi = '0' + pi;
        }
        return pi;
    }
    var colors = <?php $json = new Services_JSON(); echo $json->encode($colors) ?>;
    return {
        'init': init, 'drawGraph': drawGraph, 'addRrdToGraph': addRrdToGraph, 'cg': cg, 'graphs': graphs, 'selColor': selColor, 'selOpacity': selOpacity, 'addGraph': addGraph, 'defaultpathlimit': defaultpathlimit, 'closeAllGraphs': closeAllGraphs, 'drawAllGraphs': drawAllGraphs, 'setAllGraphTimes':setAllGraphTimes, 'autoRefreshReal':autoRefreshReal, 'createAllGraphImages':createAllGraphImages, 'createGraphImage':createGraphImage, 'autoRefreshSetup':autoRefreshSetup, setAllGraphSizes:setAllGraphSizes, 'tool':tool, zoompct:zoompct, resetSizeForAll:resetSizeForAll, 'confirmcloseallgraphs':confirmcloseallgraphs, 'confirmdeleteplaylist':confirmdeleteplaylist, 'defaultstarttime':defaultstarttime, 'defaultendtime':defaultendtime, 'defaultsize':defaultsize, 'defaultCanvasColor':defaultCanvasColor, 'confirmoverwriteplaylist':confirmoverwriteplaylist, showMergeCheckboxes:showMergeCheckboxes, getColor:getColor
    }
})();
