const SB_URL='https://aviglyievilmbqxtjzsr.supabase.co';
const SB_KEY='sb_publishable_2qfJSsd5jInntnakB01B5Q_3kudbcAa';
const SESSION_KEY='pulse.web.session.v2';
const LOCAL_KEY='pulse.web.local.alarms.v2';
const HISTORY_KEY='pulse.web.history.v1';
let session=null,context=null,alarms=[],assignments=[],ringing=null,timer=null;
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function loadSession(){try{session=JSON.parse(localStorage.getItem(SESSION_KEY)||'null')}catch{session=null}}
function saveSession(v){session=v;if(v)localStorage.setItem(SESSION_KEY,JSON.stringify(v));else localStorage.removeItem(SESSION_KEY)}
function loadLocal(){try{alarms=JSON.parse(localStorage.getItem(LOCAL_KEY)||'[]')}catch{alarms=[]}}
function saveLocal(){localStorage.setItem(LOCAL_KEY,JSON.stringify(alarms))}
function getHistory(){try{return JSON.parse(localStorage.getItem(HISTORY_KEY)||'[]')}catch{return[]}}
function addHistory(text){const h=getHistory();h.unshift({text,at:new Date().toISOString()});localStorage.setItem(HISTORY_KEY,JSON.stringify(h.slice(0,100)));if(window.renderHistory)renderHistory()}
class HttpError extends Error{constructor(message,status,body){super(message);this.name='HttpError';this.status=status;this.body=body}}
async function jf(url,o={}){const r=await fetch(url,o);let b={};try{b=await r.json()}catch{}if(!r.ok)throw new HttpError(b.msg||b.message||b.error_description||b.error||('Request failed ('+r.status+')'),r.status,b);return b}
function tokenExpiryMs(token){try{const p=JSON.parse(atob(token.split('.')[1].replace(/-/g,'+').replace(/_/g,'/')));return Number(p.exp||0)*1000}catch{return 0}}
function isAuthError(e){if(e?.status===401)return true;const m=String(e?.message||'').toLowerCase();return m.includes('jwt')||m.includes('token')&&['expired','invalid','revoked','refresh'].some(x=>m.includes(x))}
async function refreshSession(){if(!session?.refresh_token)throw new HttpError('Session expired',401,{});const old=session;const n=await jf(SB_URL+'/auth/v1/token?grant_type=refresh_token',{method:'POST',headers:{apikey:SB_KEY,'Content-Type':'application/json'},body:JSON.stringify({refresh_token:old.refresh_token})});const merged={...old,...n,refresh_token:n.refresh_token||old.refresh_token};saveSession(merged);return merged}
async function ensureFreshSession(){if(!session?.access_token)return false;const exp=tokenExpiryMs(session.access_token);if(session.refresh_token&&(!exp||exp-Date.now()<120000)){await refreshSession()}return true}
async function af(path,body={}){if(!session?.access_token)throw new HttpError('Please sign in again',401,{});await ensureFreshSession();try{return await jf(SB_URL+path,{method:'POST',headers:{apikey:SB_KEY,Authorization:'Bearer '+session.access_token,'Content-Type':'application/json'},body:JSON.stringify(body)})}catch(e){if(isAuthError(e)&&session?.refresh_token){await refreshSession();return jf(SB_URL+path,{method:'POST',headers:{apikey:SB_KEY,Authorization:'Bearer '+session.access_token,'Content-Type':'application/json'},body:JSON.stringify(body)})}throw e}}
async function signIn(email,password){const s=await jf(SB_URL+'/auth/v1/token?grant_type=password',{method:'POST',headers:{apikey:SB_KEY,'Content-Type':'application/json'},body:JSON.stringify({email:email.trim(),password})});saveSession(s);await loadApp()}
async function signUp(email,password,displayName,handle){
  const meta={display_name:displayName.trim(),mention_handle:handle.trim().toLowerCase().replace(/^@/,'')};
  const r=await jf(SB_URL+'/auth/v1/signup',{method:'POST',headers:{apikey:SB_KEY,'Content-Type':'application/json'},body:JSON.stringify({email:email.trim(),password,data:meta})});
  if(r.access_token){saveSession(r);await loadApp();return{signedIn:true,message:'Pulse Account created and signed in.'}}
  if(r.id||r.user)return{signedIn:false,message:'Pulse Account created. Check your email if verification is required, then sign in.'};
  return{signedIn:false,message:'Account created. Please sign in.'}
}
function showSignedOut(){context=null;assignments=[];if(timer)clearInterval(timer);$('appView').classList.add('hidden');$('loginView').classList.remove('hidden');$('bottomNav').style.display='none';$('addAlarmBtn').style.display='none'}
function signOut(){saveSession(null);showSignedOut();$('password').value=''}
async function restoreSession(){loadSession();if(!session?.access_token&&!session?.refresh_token)return false;try{await ensureFreshSession();return !!session?.access_token}catch(e){if(isAuthError(e)){saveSession(null);return false}throw e}}
function pill(t,c=''){return `<span class="pill ${c}">${esc(t)}</span>`}
function repeats(a){return Array.isArray(a.days)&&a.days.some(Boolean)}
function nextOccurrence(a,from=Date.now()){const now=new Date(from);if(!repeats(a)){const t=a.oneShotAt||a.nextAt;return t&&t>from?t:null}for(let add=0;add<8;add++){const d=new Date(now);d.setDate(now.getDate()+add);d.setHours(a.hour,a.minute,0,0);const idx=(d.getDay()+6)%7;if(a.days[idx]&&d.getTime()>from)return d.getTime()}return null}
function fmtTime(a){return String(a.hour).padStart(2,'0')+':'+String(a.minute).padStart(2,'0')}
function repeatText(a){if(!repeats(a))return 'One time';const n=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];if(a.days.every(Boolean))return 'Every day';return a.days.map((v,i)=>v?n[i]:null).filter(Boolean).join(' • ')}
function blankAlarm(){const d=new Date();d.setMinutes(d.getMinutes()+5);return{id:'ios:'+Date.now(),title:'',details:'',hour:d.getHours(),minute:d.getMinutes(),days:[false,false,false,false,false,false,false],category:'General',important:false,enabled:true,strongVibration:true,bypassSilent:false,soundUri:'Default',nextAt:d.getTime(),oneShotAt:d.getTime(),lastAction:'edit',snoozeMinutes:0,cloudVersion:0}}
