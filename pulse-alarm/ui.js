function renderHistory(){const el=$('historyList');if(!el)return;const h=getHistory();el.innerHTML=h.length?h.map(x=>`<div class="historyItem"><div>${esc(x.text)}</div><div class="meta">${new Date(x.at).toLocaleString()}</div></div>`).join(''):'<div class="empty">No alarm history yet.</div>'}
function setTab(tab){document.querySelectorAll('.tabPage').forEach(x=>x.classList.add('hidden'));$(tab+'Page').classList.remove('hidden');document.querySelectorAll('.nav button[data-tab]').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));if(tab==='workspace')loadAssignments().catch(()=>{});if(tab==='history')renderHistory()}
async function requestNotify(){if(!('Notification'in window)){alert('Notifications are not supported in this browser.');return}const p=await Notification.requestPermission();$('notifyStatus').textContent=p==='granted'?'Notifications allowed':'Notifications not allowed'}
function bindImportantHold(button,action,normalLabel){let started=0,clock=null;const reset=()=>{if(clock)clearInterval(clock);clock=null;button.textContent=normalLabel};button.addEventListener('pointerdown',()=>{if(!ringing)return;if(!ringing.important){action();return}started=Date.now();button.setPointerCapture?.(event?.pointerId);clock=setInterval(()=>{const pct=Math.min(100,Math.round((Date.now()-started)/20));button.textContent='HOLD '+pct+'%';if(pct>=100){reset();action()}},80)});button.addEventListener('pointerup',()=>{if(ringing?.important&&Date.now()-started<2000)reset()});button.addEventListener('pointercancel',reset)}
$('loginForm').addEventListener('submit',async e=>{e.preventDefault();const b=$('loginBtn');b.disabled=true;$('loginStatus').textContent='Signing in…';try{await signIn($('email').value,$('password').value);$('loginStatus').textContent=''}catch(err){$('loginStatus').textContent=err.message}finally{b.disabled=false}});
$('logoutBtn').onclick=signOut;
$('addAlarmBtn').onclick=()=>openEditor();
$('closeEditorBtn').onclick=closeEditor;
$('saveAlarmBtn').onclick=saveEditor;
$('deleteAlarmBtn').onclick=deleteEditor;
$('notifyBtn').onclick=requestNotify;
$('refreshBtn').onclick=async()=>{try{$('syncStatus').textContent='Refreshing…';context=await af('/rest/v1/rpc/pulse_account_context',{});renderIdentity();await Promise.all([syncPersonal(true),loadAssignments()]);$('syncStatus').textContent='Updated'}catch(e){$('syncStatus').textContent=e.message}};
$('joinBtn').onclick=joinWorkspace;
document.querySelectorAll('.day').forEach(b=>b.onclick=()=>b.classList.toggle('on'));
document.querySelectorAll('.nav button[data-tab]').forEach(b=>b.onclick=()=>setTab(b.dataset.tab));
$('s10').onclick=()=>snooze(10);
$('s30').onclick=()=>snooze(30);
$('sCustom').onclick=()=>{const v=Math.max(1,Math.min(180,Number(prompt('Snooze minutes (1-180)','60'))||60));snooze(v)};
bindImportantHold($('doneBtn'),doneOccurrence,'DONE');
bindImportantHold($('accomplishBtn'),accomplish,'ACCOMPLISH');
document.addEventListener('visibilitychange',()=>{if(!document.hidden){checkDue();if(session?.access_token){syncPersonal(true).catch(()=>{});loadAssignments().catch(()=>{})}}});
if('serviceWorker'in navigator)navigator.serviceWorker.register('./sw.js').catch(()=>{});
loadSession();loadLocal();renderAlarms();renderHistory();if(!session?.access_token){$('bottomNav').style.display='none';$('addAlarmBtn').style.display='none'}if(session?.access_token)loadApp().catch(e=>{signOut();$('loginStatus').textContent=e.message});
