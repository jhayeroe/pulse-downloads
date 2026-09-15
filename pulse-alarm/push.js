const PUSH_CONFIG_URL=SB_URL+'/functions/v1/pulse-push?config=1';
function urlB64ToUint8Array(base64String){const padding='='.repeat((4-base64String.length%4)%4);const base64=(base64String+padding).replace(/-/g,'+').replace(/_/g,'/');const raw=atob(base64);return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)))}
async function enablePushNotifications(){
  if(!session?.access_token)throw new Error('Sign in first');
  if(!('serviceWorker'in navigator)||!('PushManager'in window)||!('Notification'in window))throw new Error('Push notifications are not supported on this browser');
  const standalone=matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
  const isiOS=/iPad|iPhone|iPod/.test(navigator.userAgent);
  if(isiOS&&!standalone)throw new Error('On iPhone, install Pulse Alarm to Home Screen first, then open the installed app and enable notifications.');
  const permission=await Notification.requestPermission();
  if(permission!=='granted')throw new Error('Notifications were not allowed');
  const reg=await navigator.serviceWorker.ready;
  let sub=await reg.pushManager.getSubscription();
  if(!sub){const cfg=await jf(PUSH_CONFIG_URL);sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:urlB64ToUint8Array(cfg.publicKey)})}
  const json=sub.toJSON();
  await af('/rest/v1/rpc/pulse_register_push_subscription',{p_endpoint:sub.endpoint,p_p256dh:json.keys?.p256dh||'',p_auth:json.keys?.auth||'',p_user_agent:navigator.userAgent,p_platform:isiOS?'ios_pwa':'web_pwa'});
  return sub;
}
async function refreshPushRegistration(){
  if(!session?.access_token||!('serviceWorker'in navigator)||!('PushManager'in window))return false;
  try{const reg=await navigator.serviceWorker.ready;const sub=await reg.pushManager.getSubscription();if(!sub)return false;const json=sub.toJSON();await af('/rest/v1/rpc/pulse_register_push_subscription',{p_endpoint:sub.endpoint,p_p256dh:json.keys?.p256dh||'',p_auth:json.keys?.auth||'',p_user_agent:navigator.userAgent,p_platform:/iPad|iPhone|iPod/.test(navigator.userAgent)?'ios_pwa':'web_pwa'});return true}catch{return false}
}
async function disablePushNotifications(){
  if(!('serviceWorker'in navigator))return;const reg=await navigator.serviceWorker.ready;const sub=await reg.pushManager.getSubscription();if(!sub)return;try{if(session?.access_token)await af('/rest/v1/rpc/pulse_unregister_push_subscription',{p_endpoint:sub.endpoint})}catch{}await sub.unsubscribe();
}
function applyLaunchIntent(){const p=new URLSearchParams(location.search);const tab=p.get('tab');const normalized=tab==='account'?'settings':tab;if(normalized&&['alarms','workspace','history','settings'].includes(normalized)&&typeof setTab==='function')setTab(normalized);const action=p.get('action'),id=p.get('id');if(action&&id&&session?.access_token){setTimeout(async()=>{try{if(action==='managed-snooze10')await af('/rest/v1/rpc/pulse_managed_snooze',{p_assignment_id:id,p_minutes:10});if(action==='managed-accomplish')await af('/rest/v1/rpc/pulse_mark_assignment_accomplished',{p_assignment_id:id});if(action==='personal-snooze10'){const a=alarms.find(x=>x.id===id);if(a){a.nextAt=Date.now()+10*60000;a.snoozeMinutes=10;a.lastAction='snooze';saveLocal();await syncPersonal(true);renderAlarms()}}await loadAssignments();history.replaceState({},'',location.pathname)}catch(e){console.warn(e)}},800)}}
