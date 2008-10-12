var debugcount = 0;
var user = 'sam';
var debugLogfiles = <?php $o = debugLogfiles(); print($o); ?>
function debugShow(me){
    var cont    = document.createElement('div');
    cont.id     = "debugZone-" + me;
    cont.style.width = 690 + 'px';
    cont.className = "offcolor";

    var img   = document.createElement('img');
    img.src   = "img/stock-none-16.png";
    img.id    = "closedebugZone-" + me;
    img.title = "Close this Debug Zone";
    img.style.cssFloat = "right";
    cont.appendChild(img);

    var lab     = document.createElement('label');
    lab.htmlFor = 'debugfilelist-' + me;
    cont.appendChild(lab);

    var img   = document.createElement('img');
    img.src   = "img/stock_repeat-16.png";
    img.id    = "debugrefreshfilelist-" + me;
    img.title = "Refresh File List";
    cont.appendChild(img);

    var txt     = document.createTextNode('File:');
    lab.appendChild(txt);
    var fp      = document.createElement('select');
    fp.id       = 'debugfilepicker-' + me;
    var opt     = document.createElement('option');
    var txt     = document.createTextNode('---');
    opt.appendChild(txt);
    fp.appendChild(opt);
    debugLogfiles.each(function(file){
        var opt = document.createElement('option');
        var txt = document.createTextNode(file);
        opt.appendChild(txt);
        opt.value = file
        fp.appendChild(opt);
    });
    cont.appendChild(fp);

    var img   = document.createElement('img');
    img.src   = "img/stock_delete.png";
    img.id    = "debugzerofile-" + me;
    img.title = "Empty this file";
    cont.appendChild(img);

    var img   = document.createElement('img');
    img.src   = "img/scanner-transparent-back.gif";
    img.id    = "spinnerfor-" + me;
    img.style.display = 'none';
    cont.appendChild(img);

    var br  = document.createElement('br');
    cont.appendChild(br);
    ta          = document.createElement('textarea');
    ta.rows     = 12;
    ta.cols     = 80;
    //ta.disabled = true;
    ta.id       = 'debugTa-' + me;
    cont.appendChild(ta);
    var br  = document.createElement('br');
    cont.appendChild(br);

    var img   = document.createElement('img');
    img.src   = "img/stock_repeat-16.png";
    img.id    = "debugrefresh-" + me;
    img.title = "Refresh Contents";
    img.style.cssFloat = "right";
    cont.appendChild(img);

    $('errorspace').appendChild(cont);
    Event.observe('debugfilepicker-' + me,'change',function(e){ debugLoadLog(e,me) }.bindAsEventListener());
    Event.observe('debugrefresh-' + me,'click',function(e){ debugLoadLog(e,me) }.bindAsEventListener());
    Event.observe('closedebugZone-' + me,'click',function(e){ closeDebugZone(e,me) }.bindAsEventListener());
    Event.observe('debugrefreshfilelist-' + me,'click',function(e){ debugRefreshFileList(e,me) }.bindAsEventListener());
    Event.observe('debugzerofile-' + me,'click',function(e){ debugZeroFile(e,me) }.bindAsEventListener());
}
function debugRefreshFileList(){
    x_debugLogfiles(debugFileListCB);
}
function debugFileListCB(s){
    debugLogfiles = s.parseJSON();
}
function debugZeroFile(e,n){
    var lf = $('debugfilepicker-' + n).value;
    if ( lf != '---' ){
        //console.log(lf);
        Element.show('spinnerfor-' + n);
        x_debugZeroFile(lf,n,debugZeroFileCB);
    }
}
function debugZeroFileCB(s){
    debugLoadLogCB(s);
}
function closeDebugZone(e,n){
    $('debugZone-' + n).innerHTML = '';
    var e = $('errorspace');
    var dumb = $('debugZone-' + n);
    var stupid = e.removeChild(dumb);
}
function debugLoadLog(e,n){
    //console.log(e);
    //console.log(n);
    var lf = $('debugfilepicker-' + n).value;
    if ( lf != '---' ){
        //console.log(lf);
        Element.show('spinnerfor-' + n);
        x_debugLoadLog(lf,n,debugLoadLogCB);
    }
}
function debugLoadLogCB(s){
    //console.log(s);
    var a = s.parseJSON();
    $('debugTa-' + a[0]).value = a[1];
    Element.hide('spinnerfor-' + a[0]);
}

