'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
async function run(textMode=false,hiddenStart=false,failSprite=false) {
 let now=1000000, next=0, images=0, swapped=false;
 const statuses=[],parent={postMessage:data=>statuses.push(data)};
 const timers=new Map(),events={},ctx={fillRect(){},drawImage(){},fillText(value){assert.equal(typeof value,"string");},setTransform(...v){assert.ok(v.every(Number.isFinite));}};
 const canvas={dataset:{},hidden:true,getContext:()=>ctx,getAttribute:()=> 'Countdown'};
 const button={hidden:true,dataset:{pause:'Pause',resume:'Resume'},addEventListener:(e,f)=>events['button-'+e]=f};
 const fallback={hidden:false,replaceWith:()=>{swapped=true;}};
 const sprite=()=>({uri:'data:image/png;base64,synthetic',w:10,h:12});
 const data={text:textMode,dc:"#111",lc:"#222",deadline:2000,remaining:999,w:600,h:80,logicalW:600,logicalH:80,scale:1,gap:10,dw:10,dh:12,bg:'#fff',hideDays:true,cols:[20,20,20,20],digits:Object.fromEntries('0123456789:'.split('').map(c=>[c,sprite()])),labels:textMode?['Days','Hours','','Seconds']:Array.from({length:4},sprite),paused:hiddenStart,end:''};
 const doc={visibilityState:hiddenStart?'hidden':'visible',getElementById:id=>({'ect-frame-data':{textContent:JSON.stringify(data)},'ect-frame-canvas':canvas,'ect-frame-pause':button,'ect-frame-fallback':fallback})[id],addEventListener:(e,f)=>events[e]=f};
 class Image { constructor(){images++;this.naturalWidth=96;}set src(s){this.source=s;queueMicrotask(()=>failSprite?this.onerror():this.onload());} }
 vm.runInNewContext(fs.readFileSync('assets/frame.js','utf8'),{document:doc,window:{parent,addEventListener:(e,f)=>events[e]=f},Image,Date:{now:()=>now},setTimeout:(fn,delay)=>{timers.set(++next,fn);return next;},clearTimeout:id=>timers.delete(id)});
 await new Promise(resolve=>setImmediate(resolve));
 if(failSprite) { assert.equal(statuses.at(-1).ready,false);assert.equal(fallback.hidden,false);console.log('FRAME CLOCK PASS: failed sprite retains snapshot and signals failure');return; }
 assert.equal(statuses.at(-1).ready,true,'loaded assets signal readiness even before a background first paint');
 if(hiddenStart) { events.message({source:parent,data:{type:'ecd-frame-ping'}});assert.equal(statuses.at(-1).ready,true,'background ping can recover a missed ready signal'); assert.equal(fallback.hidden,false,'hidden paused startup retains snapshot'); doc.visibilityState='visible';events.visibilitychange();assert.equal(canvas.hidden,false,'foreground paints paused initial snapshot');events['button-click'](); }
 assert.equal(canvas.dataset.remaining,'1000','absolute deadline, not cached remaining value');assert.equal(images,textMode?0:15);
 const tick=()=>{for(const [id,fn] of [...timers]){timers.delete(id);fn();}};
 now+=65000;tick();assert.equal(canvas.dataset.remaining,'935','clock remains active beyond the GIF minute');assert.equal(images,textMode?0:15,'no repeated image loading');
 events['button-click']();const held=canvas.dataset.remaining;now+=3000;tick();assert.equal(canvas.dataset.remaining,held,'paused snapshot stays unchanged');
 events['button-click']();assert.equal(canvas.dataset.remaining,'932','resume catches up');
 doc.visibilityState='hidden';events.visibilitychange();assert.equal(timers.size,0);
 now+=500000;doc.visibilityState='visible';events.visibilitychange();assert.equal(canvas.dataset.remaining,'432','device-sleep elapsed time is included');
 events.pagehide();assert.equal(timers.size,0);events.pageshow({persisted:true});assert.equal(timers.size,1);
 now=2100000;tick();assert.equal(canvas.dataset.remaining,'0');assert.equal(button.disabled,true);assert.equal(timers.size,0);
 assert.equal(swapped,false);console.log('FRAME CLOCK PASS: absolute deadline, 65s, no polling, pause/resume, visibility/sleep, bfcache and expiry');
}
run().then(()=>run(true)).then(()=>run(false,true)).then(()=>run(false,false,true)).catch(e=>{console.error(e);process.exitCode=1;});
