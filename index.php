<?php
// index.php
session_start();

// Path DB file
define('DB_FILE','db.json');

// Load DB
if(file_exists(DB_FILE)){
    $DB=json_decode(file_get_contents(DB_FILE),true);
}else{
    $DB=['users'=>[],'otps'=>[]];
}

// Save DB function
function saveDB($DB){ file_put_contents(DB_FILE,json_encode($DB,JSON_PRETTY_PRINT)); }

// Helper: generate OTP
function genOTP(){ return rand(100000,999999); }

// Handle AJAX actions
$action=$_POST['action']??'';
$response=['ok'=>false];

if($action=='send_otp'){
    $phone=$_POST['phone']??'';
    if(!$phone){ $response['error']='No phone'; echo json_encode($response); exit;}
    $otp=genOTP();
    $DB['otps'][$phone]=$otp;
    saveDB($DB);
    // Twilio optional: kirim SMS. Untuk demo, OTP dicetak
    $response=['ok'=>true,'otp'=>$otp];
    echo json_encode($response);
    exit;
}

if($action=='verify_otp'){
    $phone=$_POST['phone']??''; $otp=$_POST['otp']??'';
    if(!$phone||!$otp){ $response['error']='Missing'; echo json_encode($response); exit;}
    if(!isset($DB['otps'][$phone])||$DB['otps'][$phone]!=$otp){ $response['error']='Invalid OTP'; echo json_encode($response); exit;}
    unset($DB['otps'][$phone]);
    if(!isset($DB['users'][$phone])) $DB['users'][$phone]=['phone'=>$phone,'name'=>'','contacts'=>[],'messages'=>[]];
    saveDB($DB);
    $_SESSION['me']=$phone;
    $response=['ok'=>true,'user'=>$DB['users'][$phone]];
    echo json_encode($response); exit;
}

if($action=='add_contact'){
    $me=$_SESSION['me']??''; if(!$me){$response['error']='Not logged in';echo json_encode($response);exit;}
    $phone=$_POST['phone']??''; $name=$_POST['name']??'';
    if(!$phone){ $response['error']='No contact phone'; echo json_encode($response); exit;}
    if(!isset($DB['users'][$phone])) $DB['users'][$phone]=['phone'=>$phone,'name'=>$name,'contacts'=>[],'messages'=>[]];
    if(!in_array($phone,$DB['users'][$me]['contacts'])) $DB['users'][$me]['contacts'][]=$phone;
    saveDB($DB);
    $response=['ok'=>true,'contacts'=>$DB['users'][$me]['contacts']];
    echo json_encode($response); exit;
}

if($action=='send_msg'){
    $me=$_SESSION['me']??''; if(!$me){$response['error']='Not logged in';echo json_encode($response);exit;}
    $to=$_POST['to']??''; $text=$_POST['text']??''; if(!$to||!$text){$response['error']='Missing';echo json_encode($response);exit;}
    if(!isset($DB['users'][$to])) $DB['users'][$to]=['phone'=>$to,'name'=>'','contacts'=>[],'messages'=>[]];
    $msg=['from'=>$me,'text'=>$text,'time'=>time()];
    $DB['users'][$me]['messages'][$to][]=$msg;
    $DB['users'][$to]['messages'][$me][]=$msg;
    saveDB($DB);
    $response=['ok'=>true,'messages'=>$DB['users'][$me]['messages'][$to]];
    echo json_encode($response); exit;
}

if($action=='get_msgs'){
    $me=$_SESSION['me']??''; if(!$me){$response['error']='Not logged in';echo json_encode($response);exit;}
    $with=$_POST['with']??''; if(!$with){$response['error']='Missing';echo json_encode($response);exit;}
    $msgs=$DB['users'][$me]['messages'][$with]??[];
    $response=['ok'=>true,'messages'=>$msgs];
    echo json_encode($response); exit;
}

// If not AJAX, render HTML
$me=$_SESSION['me']??'';
$contacts=[];
if($me){ $contacts=$DB['users'][$me]['contacts']??[]; }
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>LoHChat PHP Demo</title>
<style>
body{font-family:sans-serif;background:#0b1220;color:#e6eef8;margin:0;padding:0;}
.sidebar{width:300px;background:#0f1724;float:left;height:100vh;padding:10px;box-sizing:border-box;}
.main{margin-left:300px;padding:10px;}
input,button{padding:6px;margin:2px 0;width:100%;}
.contact{padding:6px;background:rgba(255,255,255,0.05);margin-bottom:4px;cursor:pointer;}
.msg{margin:4px;padding:6px;border-radius:6px;}
.msg.me{background:#3b82f6;color:white;text-align:right;}
</style>
<script>
function ajax(action,data,callback){
    data.action=action;
    var xhr=new XMLHttpRequest();
    xhr.open('POST','',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    var params=Object.keys(data).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(data[k])).join('&');
    xhr.onreadystatechange=function(){if(xhr.readyState==4)callback(JSON.parse(xhr.responseText));};
    xhr.send(params);
}

function sendOTP(){ var phone=document.getElementById('phone').value; ajax('send_otp',{phone:phone},r=>alert('OTP: '+r.otp)) }
function verifyOTP(){ var phone=document.getElementById('phone').value; var otp=document.getElementById('otp').value; ajax('verify_otp',{phone:phone,otp:otp},r=>{if(r.ok)location.reload();else alert(r.error)}) }
function addContact(){ var name=document.getElementById('contact-name').value; var phone=document.getElementById('contact-phone').value; ajax('add_contact',{name:name,phone:phone},r=>{if(r.ok)location.reload();else alert(r.error)}) }
function sendMsg(){ var to=document.getElementById('chat-with').dataset.to; var text=document.getElementById('msg-input').value; ajax('send_msg',{to:to,text:text},r=>{if(r.ok)showMsgs(to,r.messages);else alert(r.error)}) }
function getMsgs(to){ ajax('get_msgs',{with:to},r=>{if(r.ok)showMsgs(to,r.messages);else alert(r.error)}) }
function showMsgs(to,msgs){ var box=document.getElementById('messages'); box.innerHTML=''; msgs.forEach(m=>{var div=document.createElement('div');div.className='msg'+(m.from=='<?php echo $me;?>'?' me':'');div.textContent=m.text;box.appendChild(div);}); document.getElementById('chat-with').dataset.to=to; }
</script>
</head>
<body>
<div class="sidebar">
<h2>LoHChat PHP Demo</h2>
<?php if(!$me): ?>
<input id="phone" placeholder="Nomor HP +62"><button onclick="sendOTP()">Kirim OTP</button>
<input id="otp" placeholder="Masukkan OTP"><button onclick="verifyOTP()">Login</button>
<?php else: ?>
<h4>Kontak</h4>
<?php foreach($contacts as $c): ?><div class="contact" onclick="getMsgs('<?php echo $c;?>')"><?php echo $c;?></div><?php endforeach;?>
<h4>Tambah kontak</h4>
<input id="contact-name" placeholder="Nama kontak">
<input id="contact-phone" placeholder="Nomor +62">
<button onclick="addContact()">Tambah</button>
<?php endif;?>
</div>
<div class="main">
<?php if($me): ?>
<h3 id="chat-with" data-to="">Chat Area</h3>
<div id="messages" style="height:400px;overflow:auto;background:#111;padding:6px;"></div>
<input id="msg-input" placeholder="Tulis pesan...">
<button onclick="sendMsg()">Kirim</button>
<?php else: ?>
<p>Login untuk mulai chat</p>
<?php endif;?>
</div>
</body>
</html></head>
<body>
<div class="app">
<div class="sidebar">
  <div class="brand">LoHChat</div>
  <div id="auth-area">
    <div class="login">
      <input id="phone" placeholder="Nomor HP +62..." />
      <button id="send-otp">Kirim OTP</button>
      <input id="otp" placeholder="Masukkan kode OTP" />
      <button id="verify-otp">Login</button>
      <div class="small">OTP dikirim via SMS (atau console jika Twilio tidak aktif)</div>
    </div>
  </div>
  <div id="user-area" style="display:none">
    <div><strong id="me-name"></strong><div class="small" id="me-phone"></div></div>
    <div style="display:flex;gap:6px;margin-top:6px">
      <input id="add-name" placeholder="Nama kontak" /><input id="add-phone" placeholder="Nomor +62..." />
      <button id="add-contact">Tambah</button>
    </div>
    <h4 class="small">Kontak</h4>
    <div class="contacts" id="contacts"></div>
    <button id="logout" style="margin-top:6px">Logout</button>
  </div>
</div>
<div class="main">
  <div class="chat-header"><div id="chat-with">Pilih kontak</div></div>
  <div class="messages" id="messages"></div>
  <div class="composer" style="display:none" id="composer">
    <input id="msg-input" placeholder="Tulis pesan..." style="flex:1"/>
    <button id="send-msg">Kirim</button>
  </div>
</div>
</div>
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
const socket=io();let me=null,currentChat=null;
function el(id){return document.getElementById(id)}
function renderContacts(list){const c=el('contacts');c.innerHTML='';(list||[]).forEach(contact=>{const d=document.createElement('div');d.className='contact';d.textContent=(contact.name||contact.phone)+' — '+contact.phone;d.onclick=()=>openChat(contact.phone,contact.name);c.appendChild(d);})}
function openChat(phone,name){currentChat=phone;el('chat-with').textContent=name||phone;el('composer').style.display='flex';fetch('/messages/'+encodeURIComponent(me.phone)+'/'+encodeURIComponent(phone)).then(r=>r.json()).then(d=>renderMessages(d.messages||[]))}
function renderMessages(msgs){const box=el('messages');box.innerHTML='';msgs.forEach(m=>{const d=document.createElement('div');d.className='msg '+(m.from===me.phone?'me':'');d.textContent=m.text+'\\n'+new Date(m.time).toLocaleString();box.appendChild(d)});box.scrollTop=box.scrollHeight}
el('send-otp').onclick=()=>{const phone=el('phone').value.trim();if(!phone)return alert('Nomor kosong');fetch('/send-otp',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone})}).then(r=>r.json()).then(resp=>{if(resp.ok)alert('OTP dikirim');else alert('Gagal:'+resp.error)})}
el('verify-otp').onclick=()=>{const phone=el('phone').value.trim(),otp=el('otp').value.trim();if(!phone||!otp)return alert('Lengkapi phone & otp');fetch('/verify-otp',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone,otp})}).then(r=>r.json()).then(resp=>{if(resp.ok){me=resp.user;onLogin()}else alert('Verifikasi gagal:'+resp.error)})}
function onLogin(){el('auth-area').style.display='none';el('user-area').style.display='block';el('me-name').textContent=me.name||'(Tanpa nama)';el('me-phone').textContent=me.phone;renderContacts(me.contacts||[]);socket.emit('auth',me.phone)}
el('add-contact').onclick=()=>{const name=el('add-name').value.trim(),phone=el('add-phone').value.trim();if(!phone)return alert('Masukkan nomor');fetch('/add-contact',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({me:me.phone,contact:{phone,name}})}).then(r=>r.json()).then(resp=>{if(resp.ok){me=resp.user;renderContacts(me.contacts);el('add-name').value='';el('add-phone').value=''}else alert('Gagal:'+resp.error)})}
el('logout').onclick=()=>{location.reload()}
el('send-msg').onclick=()=>{const text=el('msg-input').value.trim();if(!text||!currentChat)return;fetch('/send-message',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({from:me.phone,to:currentChat,text})}).then(r=>r.json()).then(resp=>{if(resp.ok){renderMessages(resp.messages);el('msg-input').value=''}else alert('Gagal kirim')})}
socket.on('message',data=>{if(me&&data.to===me.phone){if(currentChat===data.from)fetch('/messages/'+encodeURIComponent(me.phone)+'/'+encodeURIComponent(data.from)).then(r=>r.json()).then(d=>renderMessages(d.messages));if(Notification&&Notification.permission!=='granted')Notification.requestPermission();if(Notification&&Notification.permission==='granted')new Notification('Pesan baru dari '+data.from,{body:data.text});}});
</script>
</body>
</html>`);
});

// Send OTP
app.post('/send-otp',(req,res)=>{
  const phone=req.body.phone; if(!phone) return res.json({ok:false,error:'No phone'});
  const otp=genOTP(); DB.otps[phone]={otp,expires:Date.now()+5*60*1000}; saveDB();
  if(twilioClient&&TWILIO_PHONE){
    twilioClient.messages.create({to:phone,from:TWILIO_PHONE,body:`Kode OTP LoHChat: ${otp}`}).then(()=>res.json({ok:true})).catch(err=>res.json({ok:false,error:'Twilio fail'}));
  } else { console.log('OTP for',phone,'=>',otp); res.json({ok:true,note:'otp-console'}); }
});

// Verify OTP
app.post('/verify-otp',(req,res)=>{
  const {phone,otp}=req.body;
  if(!phone||!otp)return res.json({ok:false,error:'Missing'});
  const record=DB.otps[phone];
  if(!record||record.expires<Date.now()||record.otp!==otp)return res.json({ok:false,error:'Invalid/expired'});
  delete DB.otps[phone]; if(!DB.users[phone])DB.users[phone]={phone,name:null,contacts:[],messages:{}};
  saveDB(); res.json({ok:true,user:DB.users[phone]});
});

// Get user
app.get('/user/:phone',(req,res)=>{const u=DB.users[req.params.phone];if(!u)return res.json({ok:false,error:'No user'});res.json({ok:true,user:u});});

// Add contact
app.post('/add-contact',(req,res)=>{const {me,contact}=req.body;if(!me||!contact||!contact.phone)return res.json({ok:false,error:'Missing'});if(!DB.users[me])DB.users[me]={phone:me,name:null,contacts:[],messages:{}};if(!DB.users[contact.phone])DB.users[contact.phone]={phone:contact.phone,name:contact.name||null,contacts:[],messages:{}};if(!DB.users[me].contacts.find(c=>c.phone===contact.phone))DB.users[me].contacts.push({phone:contact.phone,name:contact.name||null});saveDB();res.json({ok:true,user:DB.users[me]});});

// Send message
app.post('/send-message',(req,res)=>{const {from,to,text}=req.body;if(!from||!to||!text)return res.json({ok:false,error:'Missing'});if(!DB.users[from])DB.users[from]={phone:from,name:null,contacts:[],messages:{}};if(!DB.users[to])DB.users[to]={phone:to,name:null,contacts:[],messages:{}};const msg={from,to,text,time:Date.now()};DB.users[from].messages[to]=DB.users[from].messages[to]||[];DB.users[to].messages[from]=DB.users[to].messages[from]||[];DB.users[from].messages[to].push(msg);DB.users[to].messages[from].push(msg);saveDB();io.to(to).emit('message',msg);res.json({ok:true,messages:DB.users[from].messages[to]});});

// Fetch messages
app.get('/messages/:me/:other',(req,res)=>{const me=req.params.me,other=req.params.other;if(!DB.users[me])DB.users[me]={phone:me,name:null,contacts:[],messages:{}};res.json({ok:true,messages:DB.users[me].messages[other]||[]});});

// Socket auth
io.on('connection',socket=>{socket.on('auth',phone=>{if(phone)socket.join(phone);})});

const PORT=process.env.PORT||3000;
server.listen(PORT,()=>console.log('Server running on port',PORT));
app.use(bodyParser.json());

// Serve single-page app HTML
app.get('/', (req, res) => {
  res.set('Content-Type', 'text/html');
  res.send(`<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Chatter - Web Chat Demo</title>
<style>
  /* Tema gelap custom — modifikasi sesukamu */
  :root{--bg:#0b1220;--card:#0f1724;--accent:#7dd3fc;--muted:#94a3b8;--me:#60a5fa}
  body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial;color:#e6eef8;background:linear-gradient(180deg,var(--bg),#071023);}
  .app{display:flex;height:100vh}
  .sidebar{width:320px;background:var(--card);padding:16px;box-sizing:border-box;display:flex;flex-direction:column;gap:12px}
  .brand{font-weight:700;font-size:20px;color:var(--accent)}
  input,button{padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}
  .login{display:flex;flex-direction:column;gap:8px}
  .contacts{overflow:auto;flex:1}
  .contact{padding:8px;border-radius:8px;margin-bottom:8px;background:rgba(255,255,255,0.02);cursor:pointer}
  .main{flex:1;display:flex;flex-direction:column}
  .chat-header{padding:12px;border-bottom:1px solid rgba(255,255,255,0.03);display:flex;align-items:center;gap:12px}
  .messages{flex:1;padding:12px;overflow:auto}
  .msg{max-width:60%;padding:10px;border-radius:10px;margin-bottom:8px;background:rgba(255,255,255,0.03)}
  .msg.me{margin-left:auto;background:linear-gradient(90deg,var(--me),#3b82f6);color:white}
  .composer{display:flex;gap:8px;padding:12px;border-top:1px solid rgba(255,255,255,0.03)}
  .small{font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="app">
  <div class="sidebar">
    <div class="brand">Chatter (demo)</div>
    <div id="auth-area">
      <div class="login">
        <input id="phone" placeholder="Masukkan nomor HP (contoh: +628123...)" />
        <button id="send-otp">Kirim OTP via SMS</button>
        <input id="otp" placeholder="Masukkan kode OTP" />
        <button id="verify-otp">Verifikasi / Login</button>
        <div class="small">Nomor digunakan sebagai identitas. SMS dikirim jika dikonfigurasi.</div>
      </div>
    </div>
    <div id="user-area" style="display:none">
      <div><strong id="me-name"></strong><div class="small" id="me-phone"></div></div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <input id="add-name" placeholder="Nama kontak" />
        <input id="add-phone" placeholder="Nomor +62..." />
        <button id="add-contact">Tambah</button>
      </div>
      <h4 class="small">Kontak</h4>
      <div class="contacts" id="contacts"></div>
      <button id="logout" style="margin-top:8px">Logout</button>
    </div>
  </div>
  <div class="main">
    <div class="chat-header"><div id="chat-with">Pilih kontak untuk mulai chat</div></div>
    <div class="messages" id="messages"></div>
    <div class="composer" style="display:none" id="composer">
      <input id="msg-input" placeholder="Tulis pesan..." style="flex:1" />
      <button id="send-msg">Kirim</button>
    </div>
  </div>
</div>

<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
(function(){
  const socket = io();
  let me = null; // {phone,name}
  let currentChat = null; // phone

  function el(id){return document.getElementById(id)}

  function renderContacts(list){
    const c = el('contacts'); c.innerHTML='';
    (list||[]).forEach(contact=>{
      const d = document.createElement('div'); d.className='contact'; d.textContent=(contact.name||contact.phone)+' — '+contact.phone;
      d.onclick = ()=>openChat(contact.phone, contact.name);
      c.appendChild(d);
    });
  }

  function openChat(phone, name){
    currentChat = phone; el('chat-with').textContent = (name||phone);
    el('composer').style.display='flex';
    fetch('/messages/'+encodeURIComponent(me.phone)+'/'+encodeURIComponent(phone)).then(r=>r.json()).then(data=>{
      renderMessages(data.messages||[]);
    });
  }

  function renderMessages(msgs){
    const box = el('messages'); box.innerHTML='';
    msgs.forEach(m=>{
      const d = document.createElement('div'); d.className='msg '+(m.from===me.phone? 'me':'' ); d.textContent = m.text+"\\n"+new Date(m.time).toLocaleString();
      box.appendChild(d);
    });
    box.scrollTop = box.scrollHeight;
  }

  el('send-otp').onclick = ()=>{
    const phone = el('phone').value.trim();
    if(!phone){alert('Masukkan nomor!');return}
    fetch('/send-otp',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone})}).then(r=>r.json()).then(resp=>{
      if(resp.ok){alert('OTP dikirim (atau dicetak ke console jika SMS tidak dikonfigurasi).')}
      else alert('Gagal: '+(resp.error||'unknown'))
    })
  }

  el('verify-otp').onclick = ()=>{
    const phone = el('phone').value.trim(); const otp = el('otp').value.trim();
    if(!phone||!otp){alert('Lengkapi phone & otp');return}
    fetch('/verify-otp',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone,otp})}).then(r=>r.json()).then(resp=>{
      if(resp.ok){ me = resp.user; onLogin(); }
      else alert('Verifikasi gagal: '+(resp.error||''))
    })
  }

  function onLogin(){
    el('auth-area').style.display='none'; el('user-area').style.display='block';
    el('me-name').textContent = me.name||'(Tanpa nama)'; el('me-phone').textContent = me.phone;
    renderContacts(me.contacts||[]);
    socket.emit('auth', me.phone);
    try{ localStorage.setItem('chatter_session', JSON.stringify(me)); }catch(e){}
  }

  el('add-contact').onclick = ()=>{
    const name = el('add-name').value.trim(); const phone = el('add-phone').value.trim();
    if(!phone){alert('Masukkan nomor kontak');return}
    fetch('/add-contact',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({me:me.phone, contact:{phone,name}})}).then(r=>r.json()).then(resp=>{
      if(resp.ok){ me = resp.user; renderContacts(me.contacts); el('add-name').value=''; el('add-phone').value=''; }
      else alert('Gagal: '+(resp.error||'')); 
    })
  }

  el('logout').onclick = ()=>{ localStorage.removeItem('chatter_session'); location.reload(); }

  el('send-msg').onclick = ()=>{
    const text = el('msg-input').value.trim(); if(!text||!currentChat) return; 
    fetch('/send-message',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({from:me.phone,to:currentChat,text})}).then(r=>r.json()).then(resp=>{
      if(resp.ok){ renderMessages(resp.messages); el('msg-input').value=''; }
      else alert('Gagal kirim');
    })
  }

  // socket events
  socket.on('connect',()=>console.log('socket connected'));
  socket.on('message', data=>{
    // jika pesan untuk kita dan sedang terbuka chat yang sama, fetch messages
    if(me && data.to===me.phone){
      if(currentChat===data.from) fetch('/messages/'+encodeURIComponent(me.phone)+'/'+encodeURIComponent(data.from)).then(r=>r.json()).then(d=>renderMessages(d.messages));
      // optionally show browser notification
      if(Notification && Notification.permission!=='granted') Notification.requestPermission();
      if(Notification && Notification.permission==='granted') new Notification('Pesan baru dari '+data.from, {body:data.text});
    }
  });

  // initial load: try session restore (localStorage)
  try{
    const sess = localStorage.getItem('chatter_session');
    if(sess){ me = JSON.parse(sess); fetch('/user/'+encodeURIComponent(me.phone)).then(r=>r.json()).then(resp=>{ if(resp.ok){ me=resp.user; localStorage.setItem('chatter_session', JSON.stringify(me)); onLogin(); } else { localStorage.removeItem('chatter_session') } }); }
  }catch(e){}

  // store session when login happens via verify-otp response handler

})();
</script>
</body>
</html>`);
});

// API: send OTP
app.post('/send-otp', (req, res) => {
  const phone = req.body.phone;
  if (!phone) return res.json({ ok: false, error: 'No phone' });
  const otp = genOTP();
  DB.otps[phone] = { otp, expires: Date.now() + 5 * 60 * 1000 }; // 5 menit
  saveDB();
  if (twilioClient && TWILIO_PHONE) {
    twilioClient.messages.create({ to: phone, from: TWILIO_PHONE, body: `KODE OTP Chatter: ${otp}` }).then(msg => {
      console.log('SMS sent', msg.sid);
      res.json({ ok: true });
    }).catch(err => {
      console.error('Twilio send error', err);
      res.json({ ok: false, error: 'Failed to send SMS' });
    });
  } else {
    console.log('OTP for', phone, '=>', otp);
    res.json({ ok: true, note: 'otp-printed' });
  }
});

// API: verify otp => login or create user
app.post('/verify-otp', (req, res) => {
  const { phone, otp } = req.body;
  if (!phone || !otp) return res.json({ ok: false, error: 'Missing' });
  const record = DB.otps[phone];
  if (!record || record.expires < Date.now() || record.otp !== otp) return res.json({ ok: false, error: 'Invalid or expired OTP' });
  delete DB.otps[phone];
  if (!DB.users[phone]) {
    DB.users[phone] = { phone, name: null, contacts: [], messages: {} };
  }
  saveDB();
  res.json({ ok: true, user: DB.users[phone] });
});

// Get user
app.get('/user/:phone', (req, res) => {
  const phone = req.params.phone;
  const u = DB.users[phone];
  if (!u) return res.json({ ok: false, error: 'No user' });
  res.json({ ok: true, user: u });
});

// Add contact
app.post('/add-contact', (req, res) => {
  const { me, contact } = req.body; // contact: {phone,name}
  if (!me || !contact || !contact.phone) return res.json({ ok: false, error: 'Missing' });
  if (!DB.users[me]) DB.users[me] = { phone: me, name: null, contacts: [], messages: {} };
  // ensure contact user exists (create stub)
  if (!DB.users[contact.phone]) DB.users[contact.phone] = { phone: contact.phone, name: contact.name || null, contacts: [], messages: {} };
  // add to contacts if not exists
  const exists = DB.users[me].contacts.find(c => c.phone === contact.phone);
  if (!exists) DB.users[me].contacts.push({ phone: contact.phone, name: contact.name || null });
  saveDB();
  res.json({ ok: true, user: DB.users[me] });
});

// Send message (store and emit)
app.post('/send-message', (req, res) => {
  const { from, to, text } = req.body;
  if (!from || !to || !text) return res.json({ ok: false, error: 'Missing' });
  if (!DB.users[from]) DB.users[from] = { phone: from, name: null, contacts: [], messages: {} };
  if (!DB.users[to]) DB.users[to] = { phone: to, name: null, contacts: [], messages: {} };
  const msg = { from, to, text, time: Date.now() };
  // store both sides
  DB.users[from].messages[to] = DB.users[from].messages[to] || [];
  DB.users[to].messages[from] = DB.users[to].messages[from] || [];
  DB.users[from].messages[to].push(msg);
  DB.users[to].messages[from].push(msg);
  saveDB();
  // emit to recipient if connected
  io.to(to).emit('message', msg);
  // return conversation for sender-view
  res.json({ ok: true, messages: DB.users[from].messages[to] });
});

// fetch messages between two phones (as seen by user)
app.get('/messages/:me/:other', (req, res) => {
  const me = req.params.me; const other = req.params.other;
  if (!DB.users[me]) DB.users[me] = { phone: me, name: null, contacts: [], messages: {} };
  const msgs = DB.users[me].messages[other] || [];
  res.json({ ok: true, messages: msgs });
});

// socket auth mapping: when client auth emits phone, join room phone
io.on('connection', socket => {
  socket.on('auth', phone => {
    if (!phone) return;
    socket.join(phone);
    console.log('socket joined', phone);
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => console.log('Server running on http://localhost:'+PORT));
