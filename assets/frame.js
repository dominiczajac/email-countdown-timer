/* Local canvas clock. Raster sprites preserve server fonts without font downloads. */
(function () {
    'use strict';
    var data = JSON.parse(document.getElementById('ect-frame-data').textContent);
    var canvas = document.getElementById('ect-frame-canvas'), button = document.getElementById('ect-frame-pause');
    var ctx = canvas.getContext('2d');
    function status(ready) { window.parent.postMessage({type:'ecd-frame-status',ready:ready},'*'); }
    if (!ctx) { status(false); return; }
    var paused = data.paused, timer = null, ended = false, drawn = false, ready = false;
    function load(sprite) {
        return new Promise(function (resolve, reject) {
            var image = new Image(); image.onload = function () { sprite.image = image; resolve(); }; image.onerror = reject; image.src = sprite.uri;
        });
    }
    function remaining() { return Math.max(0, data.deadline - Math.floor(Date.now() / 1000)); }
    function draw() {
        clearTimeout(timer); timer = null;
        if (document.visibilityState === 'hidden') return;
        var firstDraw = !drawn;
        var left = remaining(), values = [Math.floor(left / 86400), Math.floor(left % 86400 / 3600), Math.floor(left % 3600 / 60), left % 60];
        canvas.dataset.remaining = String(left);
        if (left === 0 && data.end && !ended) {
            ended = true;
            var end = new Image(); end.referrerPolicy = 'no-referrer'; end.alt = canvas.getAttribute('aria-label') || '';
            end.onload = function () { if(end.naturalWidth > 1) { canvas.hidden = true; button.hidden = true; document.getElementById('ect-frame-fallback').replaceWith(end); } };
            end.src = data.end;
        }
        ctx.setTransform(1,0,0,1,0,0);
        ctx.fillStyle = data.bg; ctx.fillRect(0, 0, data.w, data.h);
        ctx.setTransform(data.scale,0,0,data.scale,(data.w-data.logicalW*data.scale)/2,(data.h-data.logicalH*data.scale)/2);
        var first = data.hideDays && values[0] === 0 ? 1 : 0;
        var total = data.cols.slice(first).reduce(function(a,b){return a+b;},0) + (3-first)*(20+data.digits[':'].w);
        var x = (data.logicalW-total)/2;
        for(var i=first;i<4;i++) {
            var text = String(values[i]).padStart(2,'0'), vx = x+(data.cols[i]-text.length*data.dw)/2;
            if(data.text) {
                ctx.textAlign='center';ctx.font=data.dh+'px monospace';ctx.fillStyle=data.dc;ctx.fillText(text,x+data.cols[i]/2,10+data.dh,data.cols[i]);
                ctx.font='12px sans-serif';ctx.fillStyle=data.lc;ctx.fillText(data.labels[i],x+data.cols[i]/2,data.h-5,data.cols[i]);
            } else for(var j=0;j<text.length;j++) { var s=data.digits[text[j]]; ctx.drawImage(s.image,vx+(data.dw-s.w)/2,10+data.dh-s.h); vx+=data.dw; }
            if(!data.text) { var label=data.labels[i]; ctx.drawImage(label.image,x+(data.cols[i]-label.w)/2,10+data.dh+data.gap); }
            x+=data.cols[i];
            if(i<3) { var colon=data.digits[':']; if(data.text) { ctx.font=data.dh+'px monospace';ctx.fillStyle=data.dc;ctx.fillText(':',x+15,10+data.dh,10); } else ctx.drawImage(colon.image,x+10,10+data.dh-colon.h); x+=20+colon.w; }
        }
        if(firstDraw) { document.getElementById('ect-frame-fallback').hidden=true;canvas.hidden=false;button.hidden=false;drawn=true; }
        button.textContent = button.dataset[paused ? 'resume' : 'pause']; button.disabled = left === 0;
        if(!paused && left > 0) timer = setTimeout(draw,1000-Date.now()%1000);
    }
    Promise.all(data.text?[]:Object.values(data.digits).concat(data.labels).map(load)).then(function () {
        ready=true;draw();status(true);
        button.addEventListener('click',function(){paused=!paused;draw();});
        document.addEventListener('visibilitychange',function(){if(!paused || !drawn)draw();});
        window.addEventListener('message',function(e){if(e.source===window.parent && e.data && e.data.type==='ecd-frame-ping') { if(ready) status(true); }});
        window.addEventListener('pagehide',function(){clearTimeout(timer);});
        window.addEventListener('pageshow',function(e){if(e.persisted && (!paused || !drawn))draw();});
    }).catch(function(){ status(false); /* The saved static PNG remains available. */ });
}());
