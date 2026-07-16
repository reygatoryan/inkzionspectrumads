<?php
$pageTitle = 'Messages';
$pageSubtitle = 'Chat with customers about their orders and requests';
require 'includes/admin-header.php';
?>
<style>
  .admin-chat-layout { display: grid; grid-template-columns: 300px 1fr; grid-template-rows: 1fr; gap: 1.5rem; height: calc(100vh - 180px); min-height: 500px; overflow: hidden; }
  .admin-chat-sidebar { background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.04); display: flex; flex-direction: column; }
  .admin-chat-sidebar h3 { margin: 0; padding: 1rem 1.25rem; font-size: 0.95rem; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e2e8f0; }
  .conv-list { flex: 1; overflow-y: auto; }
  .conv-item { padding: 0.85rem 1.25rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: all 0.15s; display: flex; gap: 0.65rem; align-items: center; }
  .conv-item:hover { background: #f8fafc; }
  .conv-item.active { background: rgba(43, 76, 82,0.05); border-left: 3px solid #2B4C52; }
  .conv-avatar { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg,#2B4C52,#4A7C84); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
  .conv-info { flex: 1; min-width: 0; }
  .conv-name { font-weight: 600; color: #0f172a; font-size: 0.85rem; }
  .conv-preview { font-size: 0.78rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .conv-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 0.25rem; }
  .conv-time { font-size: 0.7rem; color: #94a3b8; }
  .conv-unread { background: #2B4C52; color: white; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 999px; min-width: 18px; text-align: center; }
  .conv-product { font-size: 0.65rem; color: #2B4C52; font-weight: 500; }
  .delete-conv-btn { font-size:0.75rem;color:#94a3b8;cursor:pointer;padding:0.15rem 0.3rem;border-radius:4px;transition:all 0.15s ease;opacity:0; }
  .conv-item:hover .delete-conv-btn { opacity:1; }
  .delete-conv-btn:hover { color:#dc2626;background:#fef2f2; }
  .admin-chat-main { background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.04); display: flex; flex-direction: column; }
  .chat-product-bar { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 1.25rem; background: #E8F1ED; border-bottom: 1px solid rgba(43, 76, 82,0.12); }
  .chat-product-bar img { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; }
  .chat-product-bar .info { font-size: 0.82rem; font-weight: 600; color: #0f172a; }
  .chat-product-bar .info small { font-weight: 400; color: #64748b; }
  .chat-header { padding: 0.85rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
  .chat-header h4 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; }
  .chat-header-actions { display: flex; gap: 0.5rem; }
  .chat-header-actions .btn-action { padding: 0.4rem 0.85rem; border-radius: 8px; border: none; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.15s; display: inline-flex; align-items: center; gap: 0.35rem; }
  .btn-send-request { background: rgba(43, 76, 82,0.1); color: #2B4C52; }
  .btn-send-request:hover { background: #2B4C52; color: white; }
  .btn-send-order { background: rgba(16,185,129,0.1); color: #047857; }
  .btn-send-order:hover { background: #10b981; color: white; }
  .btn-action-danger { background: rgba(220,38,38,0.1); color: #dc2626; }
  .btn-action-danger:hover { background: #dc2626; color: white; }
  .chat-messages { flex: 1; overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; }
  .msg { display: flex; gap: 0.65rem; max-width: 75%; }
  .msg.sent { align-self: flex-end; flex-direction: row-reverse; }
  .msg.received { align-self: flex-start; }
  .msg-avatar { width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg,#2B4C52,#4A7C84); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; flex-shrink: 0; }
  .msg-bubble { padding: 0.7rem 1rem; border-radius: 14px; font-size: 0.85rem; line-height: 1.45; }
  .msg.sent .msg-bubble { background: linear-gradient(135deg,#2B4C52,#4A7C84); color: white; border-bottom-right-radius: 4px; }
  .msg.received .msg-bubble { background: #f1f5f9; color: #0f172a; border-bottom-left-radius: 4px; }
  .msg-time { font-size: 0.65rem; color: #94a3b8; margin-top: 0.2rem; }
  .msg.sent .msg-time { text-align: right; }
  .no-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; }
  .no-chat i { font-size: 3.5rem; opacity: 0.2; margin-bottom: 0.75rem; }
  .chat-input-area { padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0; }
  .chat-input-wrap { display: flex; gap: 0.5rem; }
  .chat-input-wrap input { flex: 1; padding: 0.7rem 1rem; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; outline: none; font-family: inherit; }
  .chat-input-wrap input:focus { border-color: #2B4C52; }
  .chat-input-wrap .btn-send-msg { padding: 0.7rem 1.2rem; border-radius: 10px; border: none; background: linear-gradient(135deg,#2B4C52,#4A7C84); color: white; font-weight: 600; cursor: pointer; font-size: 0.85rem; }
  .chat-input-wrap .btn-send-msg:hover { transform: translateY(-1px); }
  .request-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.85rem; margin-top: 0.4rem; }
  .request-card .title { font-weight: 600; font-size: 0.85rem; color: #0f172a; margin-bottom: 0.3rem; }
  .request-card .title i { color: #2B4C52; margin-right: 0.35rem; }
  .request-card .btn-fill { display: inline-block; padding: 0.35rem 0.75rem; border-radius: 8px; background: #2B4C52; color: white; font-size: 0.75rem; font-weight: 600; text-decoration: none; margin-top: 0.4rem; }
  @media (max-width: 768px) { .admin-chat-layout { grid-template-columns: 1fr; } .admin-chat-sidebar { display: none; } }
  .img-modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:10000;display:none;align-items:center;justify-content:center;cursor:pointer; }
  .img-modal-overlay.active { display:flex; }
  .img-modal-overlay img { max-width:90%;max-height:90%;border-radius:8px;cursor:default;box-shadow:0 8px 40px rgba(0,0,0,0.5); }
  .img-modal-close { position:fixed;top:1rem;right:1.5rem;color:#fff;font-size:2.5rem;cursor:pointer;z-index:10001;background:none;border:none;opacity:0.7;transition:opacity 0.2s;line-height:1; }
  .img-modal-close:hover { opacity:1; }
  .admin-file-preview { display:none;margin-bottom:0.5rem;padding:0.5rem;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;position:relative; }
  .admin-file-preview-content { display:flex;align-items:center;gap:0.5rem; }
  .admin-file-preview-content img { width:40px;height:40px;border-radius:6px;object-fit:cover; }
  .admin-file-preview-content i { color:#2B4C52;font-size:1.2rem; }
  .admin-file-preview-name { font-size:0.82rem;color:#0f172a; }
  .admin-file-preview .btn-clear-file { position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;border:none;background:#ef4444;color:white;cursor:pointer;font-size:0.65rem;display:flex;align-items:center;justify-content:center; }
  .btn-attach { width:40px;height:40px;border-radius:10px;border:2px solid #e2e8f0;background:white;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:all 0.15s;flex-shrink:0; }
  .btn-attach:hover { border-color:#2B4C52;color:#2B4C52; }
</style>

<div class="admin-chat-layout">
  <div class="admin-chat-sidebar">
    <h3><i class="fas fa-comments" style="color:#2B4C52;margin-right:0.4rem;"></i>Conversations</h3>
    <div style="padding:0.5rem 0.85rem;border-bottom:1px solid #e2e8f0;">
      <div style="position:relative;">
        <input type="text" id="admin-search-input" placeholder="Search customers or products..." style="width:100%;padding:0.45rem 0.65rem;border:2px solid #e2e8f0;border-radius:8px;font-size:0.78rem;outline:none;font-family:inherit;background:#f8fafc;" oninput="adminSearchConversations(this.value)">
        <i class="fas fa-search" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.75rem;"></i>
      </div>
    </div>
    <div class="conv-list" id="convList"><p style="text-align:center;color:#94a3b8;padding:1.5rem;">Loading...</p></div>
  </div>
  <div class="admin-chat-main" id="chatMain">
    <div class="no-chat">
      <i class="fas fa-comments"></i>
      <p>Select a conversation to start messaging</p>
    </div>
  </div>
</div>

<script>
let currentConvId = null;
let pollTimer = null;
let lastMsgLen = 0;
let allAdminConvs = [];
let msgOffset = 0;
let hasMoreMsgs = false;
let isLoadingMore = false;
let autoScrollAdmin = true;
let lastMsgId = 0;
let emptyPollsAdmin = 0;
let pollIntervalAdmin = 1000;
let isTabVisibleAdmin = true;
let adminSending = false;
let adminLoadedMsgIds = new Set();
let isLoadingAdminConv = false;
let adminSelectedFile = null;

function escapeHtml(t) { if(!t)return''; var d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

function getInitials(n) { if(!n)return'?'; var p=n.split(' '); return(p[0][0].toUpperCase())+(p[1]?p[1][0].toUpperCase():''); }

function getDateLabel(ds) {
  var d=new Date(ds), t=new Date(), y=new Date(t); y.setDate(y.getDate()-1);
  if(d.toDateString()===t.toDateString())return'Today';
  if(d.toDateString()===y.toDateString())return'Yesterday';
  return d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:d.getFullYear()!==t.getFullYear()?'numeric':undefined});
}

function fmtTime(ds) { return new Date(ds).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }

function showAdminNotification(msg, type) {
  var existing=document.getElementById('admin-toast');
  if(existing)existing.remove();
  var t=document.createElement('div'); t.id='admin-toast';
  t.style.cssText='position:fixed;bottom:2rem;right:2rem;z-index:99999;background:#1e293b;color:white;padding:0.75rem 1.25rem;border-radius:12px;font-size:0.82rem;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.2);display:flex;align-items:center;gap:0.5rem;cursor:pointer;max-width:360px;';
  var icon=type==='error'?'exclamation-circle':'info-circle';
  t.innerHTML='<i class="fas fa-'+icon+'" style="color:#2B4C52;"></i> '+escapeHtml(msg);
  t.onclick=function(){this.remove();};
  document.body.appendChild(t);
  setTimeout(function(){t.style.opacity='0';t.style.transition='all 0.3s';setTimeout(function(){t.remove();},300);},4000);
}

function adminSearchConversations(query) {
  var list=document.getElementById('convList');
  var filtered=query.trim()?allAdminConvs.filter(function(c){
    var n=(c.user_name||c.seller_name||'').toLowerCase();
    var p=(c.product_name||'').toLowerCase();
    return n.includes(query.toLowerCase())||p.includes(query.toLowerCase());
  }):allAdminConvs;
  if(!filtered.length){list.innerHTML='<p style="text-align:center;color:#94a3b8;padding:1.5rem;">No conversations found.</p>';return;}
  list.innerHTML=filtered.map(renderAdminConv).join('');
}

function renderAdminConv(c) {
  var name=c.user_name||c.seller_name||'Customer';
  var unread=c.unread_count||0;
  var last=c.last_message||'No messages';
  var time=c.last_message_at?new Date(c.last_message_at).toLocaleDateString():'';
  var prod=c.product_name?'<div class="conv-product"><i class="fas fa-tag"></i> '+escapeHtml(c.product_name)+'</div>':'';
  return '<div class="conv-item'+(currentConvId==c.id?' active':'')+'" data-id="'+c.id+'" onclick="adminSelectConversation('+c.id+')">'+
    '<div class="conv-avatar">'+getInitials(name)+'</div>'+
    '<div class="conv-info"><div class="conv-name">'+escapeHtml(name)+'</div><div class="conv-preview">'+escapeHtml(last)+'</div>'+prod+'</div>'+
    '<div class="conv-meta"><span class="conv-time">'+time+'</span>'+(unread>0?'<span class="conv-unread">'+unread+'</span>':'')+'<span class="delete-conv-btn" data-id="'+c.id+'" title="Delete conversation">&times;</span></div>'+
    '</div>';
}

async function adminLoadConversations() {
  var list=document.getElementById('convList');
  try {
    var r=await fetch('../api/chat.php?action=conversations',{credentials:'include'});
    var d=await r.json();
    if(!d.success)throw new Error(d.error);
    allAdminConvs=d.conversations||[];
    if(!allAdminConvs.length){list.innerHTML='<p style="text-align:center;color:#94a3b8;padding:1.5rem;">No conversations.</p>';return;}
    var si=document.getElementById('admin-search-input');
    if(si&&si.value.trim()){adminSearchConversations(si.value);}
    else{list.innerHTML=allAdminConvs.map(renderAdminConv).join('');}
  }catch(e){list.innerHTML='<p style="text-align:center;color:#dc2626;padding:1.5rem;">Failed to load.</p>';}
}

function adminUpdateConvPreview(newMsgs) {
  if(!newMsgs.length||!currentConvId)return;
  var last=newMsgs[newMsgs.length-1];
  var item=document.querySelector('.conv-item[data-id="'+currentConvId+'"]');
  if(!item)return;
  var preview=item.querySelector('.conv-preview');
  if(preview){
    var text=last.content||'';
    if(last.message_type==='image')text='Sent an image';
    else if(last.message_type==='file')text='Sent a file';
    else if(last.message_type==='custom_request')text='Sent a custom request';
    else if(last.message_type==='order_form')text='Sent an order form';
    preview.textContent=text;
  }
}

function adminStartPolling() {
  if(pollTimer){clearTimeout(pollTimer);pollTimer=null;}
  emptyPollsAdmin=0; pollIntervalAdmin=1000; adminDoPoll();
}

async function adminDoPoll() {
  if(!currentConvId||!isTabVisibleAdmin){pollTimer=setTimeout(adminDoPoll,isTabVisibleAdmin?pollIntervalAdmin:10000);return;}
  try{
    var url='../api/chat-poll.php?conversation_id='+currentConvId+'&last_message_id='+lastMsgId+'&check_typing=1';
    var r=await fetch(url,{credentials:'include'});
    var d=await r.json();if(!d.success)throw new Error(d.error);
    var newMsgs=d.messages||[];
    if(newMsgs.length>0){
      lastMsgId=d.last_message_id||lastMsgId; emptyPollsAdmin=0; pollIntervalAdmin=1000;
      adminAppendMessages(newMsgs);
    }else{emptyPollsAdmin++;if(emptyPollsAdmin>3)pollIntervalAdmin=Math.min(pollIntervalAdmin+1000,10000);}
    var ti=document.getElementById('admin-typing-indicator');
    if(ti){
      if(d.typing){ti.style.display='block';ti.textContent=d.typing.user_name+' is typing...';}
      else{ti.style.display='none';}
    }
    if(newMsgs.length>0)adminUpdateConvPreview(newMsgs);
  }catch(e){}
  var ni=isTabVisibleAdmin?pollIntervalAdmin:Math.max(pollIntervalAdmin,8000);
  pollTimer=setTimeout(adminDoPoll,ni);
}

function adminAppendMessages(msgs) {
  var div=document.getElementById('chatMsgs');if(!div)return;
  var lastDt=null;var html='';
  var dateEls=div.querySelectorAll('[data-date-label]');
  if(dateEls.length>0)lastDt=dateEls[dateEls.length-1].getAttribute('data-date-label');
  msgs.forEach(function(m){
    if(adminLoadedMsgIds.has(m.id))return;
    var dt=getDateLabel(m.created_at);
    if(dt!==lastDt){html+='<div style="text-align:center;padding:0.35rem 0;font-size:0.7rem;color:#94a3b8;font-weight:500;" data-date-label="'+dt+'">'+dt+'</div>';lastDt=dt;}
    html+=adminRenderMsg(m);
    adminLoadedMsgIds.add(m.id);
  });
  if(!html)return;
  div.insertAdjacentHTML('beforeend',html);
  lastMsgLen+=msgs.length;
  if(autoScrollAdmin)div.scrollTop=div.scrollHeight;
  msgs.forEach(function(m){
    if(m.sender_id!=<?= $userId ?>){
      var body=m.message_type==='text'?(m.content||'Sent a message'):m.message_type==='image'?'Sent an image':m.message_type==='file'?'Sent a file':'Sent a message';
      if(document.hidden&&'Notification'in window&&Notification.permission==='granted'){new Notification('New Message from '+(m.sender_name||'Customer'),{body:body,icon:'../assets/logo.png'});}
    }
  });
}

async function adminSelectConversation(convId) {
  if(isLoadingAdminConv)return;
  isLoadingAdminConv=true;
  if(pollTimer){clearTimeout(pollTimer);pollTimer=null;}
  try{
    currentConvId=convId; msgOffset=0; hasMoreMsgs=false; isLoadingMore=false; lastMsgLen=0; autoScrollAdmin=true; lastMsgId=0; adminLoadedMsgIds=new Set();
    document.querySelectorAll('.conv-item').forEach(function(el){el.classList.toggle('active',parseInt(el.dataset.id)===convId);});
    await adminLoadChat(convId);
    adminStartPolling();
  }catch(e){console.error('Failed to load conversation:',e);}
  finally{isLoadingAdminConv=false;}
}

function parseMsgContent(str) { try{return JSON.parse(str);}catch(e){return null;} }

function adminRenderMsg(m) {
  var isSent=m.sender_id==<?= $userId ?>;
  var time=fmtTime(m.created_at);
  var content='';
  if(m.message_type==='text'){content='<div class="msg-bubble">'+escapeHtml(m.content)+'</div>';}
  else if(m.message_type==='custom_request'){var rd=parseMsgContent(m.content);var rt=rd?(rd.title||'Custom Request'):'Custom Request';var ri=rd?rd.request_id:null;var lk=ri?'../customer/request-form.php?id='+ri:'#';content='<div class="msg-bubble"><div class="request-card"><div class="title"><i class="fas fa-paint-brush"></i> '+escapeHtml(rt)+'</div><a href="'+lk+'" class="btn-fill" target="_blank"><i class="fas fa-external-link-alt"></i> View Request</a></div></div>';}
  else if(m.message_type==='order_form'){var rd=parseMsgContent(m.content);var pt=rd?(rd.title||'Order Form'):'Order Form';var pi=rd?rd.proposal_id:null;var lk=pi?'../customer/order-form.php?id='+pi:'#';content='<div class="msg-bubble"><div class="request-card"><div class="title"><i class="fas fa-file-invoice"></i> '+escapeHtml(pt)+'</div><a href="'+lk+'" class="btn-fill" target="_blank"><i class="fas fa-external-link-alt"></i> View Order Form</a></div></div>';}
  else if(m.message_type==='image'){var url=(m.file_url||'').startsWith('http')?m.file_url:'../'+(m.file_url||'');content='<div class="msg-bubble"><img src="'+escapeHtml(url)+'" style="max-width:200px;border-radius:8px;cursor:pointer;" onclick="openImageModal(\''+escapeHtml(url)+'\')"></div>';}
  else if(m.message_type==='file'){content='<div class="msg-bubble"><i class="fas fa-file"></i> '+escapeHtml(m.file_name||'File')+'</div>';}
  return '<div class="msg '+(isSent?'sent':'received')+'"><div class="msg-avatar">'+getInitials(m.sender_name)+'</div><div>'+content+'<div class="msg-time">'+time+'</div></div></div>';
}

async function adminLoadMoreMsgs() {
  if(isLoadingMore||!hasMoreMsgs||!currentConvId)return;
  isLoadingMore=true;
  var div=document.getElementById('chatMsgs');
  var prevH=div.scrollHeight;
  var el=document.createElement('div');el.id='admin-load-more';el.style.cssText='text-align:center;padding:0.5rem;color:#94a3b8;font-size:0.78rem;';el.innerHTML='<i class="fas fa-spinner fa-spin"></i> Loading...';
  div.prepend(el);
  try{
    msgOffset+=50;
    var r=await fetch('../api/chat.php?action=messages&conversation_id='+currentConvId+'&limit=50&offset='+msgOffset,{credentials:'include'});
    var d=await r.json();if(!d.success)throw new Error(d.error);
    hasMoreMsgs=d.has_more||false;
    document.getElementById('admin-load-more').remove();
    var older=d.messages||[];
    if(older.length){var html='',lastDt=null;older.forEach(function(m){var dt=getDateLabel(m.created_at);if(dt!==lastDt){html+='<div style="text-align:center;padding:0.35rem 0;font-size:0.7rem;color:#94a3b8;font-weight:500;">'+dt+'</div>';lastDt=dt;}html+=adminRenderMsg(m);});div.insertAdjacentHTML('afterbegin',html);var newH=div.scrollHeight;div.scrollTop=newH-prevH;}
  }catch(e){document.getElementById('admin-load-more').remove();showAdminNotification('Failed to load older messages','error');}
  isLoadingMore=false;
}

async function adminLoadChat(convId, silent) {
  var main=document.getElementById('chatMain');
  var prevLen=lastMsgLen;
  if(!silent){
    main.innerHTML='<div class="chat-header" id="chatHdr"><h4>Loading...</h4></div><div class="chat-messages" id="chatMsgs"><p style="text-align:center;color:#94a3b8;padding:1.5rem;">Loading...</p></div><div class="chat-input-area" id="chatInputArea"></div>';
  }
  try{
    var convRes=await fetch('../api/chat.php?action=conversations',{credentials:'include'});
    var convData=await convRes.json();
    var conv=(convData.conversations||[]).find(function(c){return c.id==convId;});
    
    var r=await fetch('../api/chat.php?action=messages&conversation_id='+convId+'&limit=50&offset=0',{credentials:'include'});
    var d=await r.json();if(!d.success)throw new Error(d.error);
    var msgs=d.messages||[]; hasMoreMsgs=d.has_more||false;
    lastMsgLen=msgs.length;
    adminLoadedMsgIds=new Set(msgs.map(function(m){return m.id;}));
    if(msgs.length>0)lastMsgId=msgs[msgs.length-1].id;
    
    var prodBar='';
    if(conv&&conv.product_name){var img=conv.product_image?'<img src="'+escapeHtml(conv.product_image)+'" alt="" onerror="this.style.display=\'none\'">':'<div style="width:36px;height:36px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#2B4C52;"><i class="fas fa-box"></i></div>';prodBar='<div class="chat-product-bar">'+img+'<div class="info">'+escapeHtml(conv.product_name)+'<small> &middot; Product</small></div></div>';}
    var requestId=conv?conv.request_id:null, requestStatus=conv?conv.request_status:null;
    var hdrActions='';
    if(requestId){
      if(requestStatus==='pending'){hdrActions='<button class="btn-action btn-send-request" onclick="adminSendCustomRequest()"><i class="fas fa-paper-plane"></i> Make Request</button>';}
      else if(requestStatus==='in_review'||requestStatus==='approved'){hdrActions='<button class="btn-action btn-send-request" onclick="adminSendCustomRequest()"><i class="fas fa-paper-plane"></i> Make Request</button>';}
      hdrActions+='<button class="btn-action btn-action-danger" onclick="adminDeleteCustomRequest('+requestId+')"><i class="fas fa-trash"></i> Delete</button>';
    }else{
      hdrActions='<button class="btn-action btn-send-request" onclick="adminSendCustomRequest()"><i class="fas fa-paper-plane"></i> Make Request</button>';
    }
    var customerName=conv?(conv.user_name||'Customer'):'Customer';
    
    var msgsHtml='';
    if(msgs.length){msgsHtml='';var lastDt=null;msgs.forEach(function(m){var dt=getDateLabel(m.created_at);if(dt!==lastDt){msgsHtml+='<div style="text-align:center;padding:0.35rem 0;font-size:0.7rem;color:#94a3b8;font-weight:500;">'+dt+'</div>';lastDt=dt;}msgsHtml+=adminRenderMsg(m);});if(hasMoreMsgs){msgsHtml='<div style="text-align:center;padding:0.4rem;"><button onclick="adminLoadMoreMsgs()" style="background:none;border:1px solid #e2e8f0;border-radius:6px;padding:0.3rem 0.8rem;color:#2B4C52;font-size:0.72rem;font-weight:500;cursor:pointer;"><i class="fas fa-chevron-up"></i> Load older</button></div>'+msgsHtml;}}
    else{msgsHtml='<p style="text-align:center;color:#94a3b8;padding:1.5rem;">No messages yet.</p>';adminLoadedMsgIds=new Set();}
    
    main.innerHTML=prodBar+'<div class="chat-header" id="chatHdr"><h4><i class="fas fa-user" style="color:#2B4C52;margin-right:0.35rem;"></i>'+escapeHtml(customerName)+'</h4><div class="chat-header-actions">'+hdrActions+'</div></div>'+
      '<div class="typing-indicator" id="admin-typing-indicator" style="display:none;padding:0.3rem 1.25rem;font-size:0.78rem;color:#94a3b8;font-style:italic;"></div>'+
      '<div class="chat-messages" id="chatMsgs">'+msgsHtml+'</div>'+
      '<div class="chat-input-area" id="chatInputArea">'+
      '<div class="admin-file-preview" id="admin-file-preview">'+
        '<button class="btn-clear-file" onclick="adminClearFilePreview()"><i class="fas fa-times"></i></button>'+
        '<div class="admin-file-preview-content" id="admin-file-preview-content"></div>'+
      '</div>'+
      '<div class="chat-input-wrap"><input type="text" id="msgInput" placeholder="Type a message...">'+
      '<button class="btn-attach" onclick="document.getElementById(\'admin-file-input\').click()" title="Attach file"><i class="fas fa-paperclip"></i></button>'+
      '<button class="btn-send-msg" id="admin-btn-send"><i class="fas fa-paper-plane"></i></button></div></div>';
    
    var msgsDiv=document.getElementById('chatMsgs');
    if(msgs.length)msgsDiv.scrollTop=msgsDiv.scrollHeight;
    
    // Scroll handler for auto-scroll
    msgsDiv.onscroll=function(){var th=30;autoScrollAdmin=(msgsDiv.scrollHeight-msgsDiv.scrollTop-msgsDiv.clientHeight)<th;if(msgsDiv.scrollTop<80&&hasMoreMsgs&&!isLoadingMore)adminLoadMoreMsgs();};
    
    // Typing check
    try{var tr=await fetch('../api/chat.php?action=typing_status&conversation_id='+convId,{credentials:'include'});var td=await tr.json();var ti=document.getElementById('admin-typing-indicator');if(ti){if(td.success&&td.is_typing){ti.style.display='block';ti.textContent=td.user_name+' is typing...';}else{ti.style.display='none';}}}catch(e){}
    
    if(silent&&prevLen>0&&msgs.length>prevLen&&autoScrollAdmin&&msgsDiv){msgsDiv.scrollTop=msgsDiv.scrollHeight;}
    
  }catch(e){if(!silent)main.innerHTML='<div class="no-chat"><i class="fas fa-exclamation-circle"></i><p>Failed to load conversation.</p></div>';}
}

function adminClearFilePreview() {
  adminSelectedFile = null;
  var pre = document.getElementById('admin-file-preview');
  if (pre) pre.style.display = 'none';
  document.getElementById('admin-file-input').value = '';
}

function adminShowFilePreview(file) {
  var pre = document.getElementById('admin-file-preview');
  var cont = document.getElementById('admin-file-preview-content');
  if (!pre || !cont) return;
  if (file.type.startsWith('image/')) {
    var reader = new FileReader();
    reader.onload = function(e) {
      cont.innerHTML = '<img src="'+e.target.result+'" alt=""><span class="admin-file-preview-name">'+escapeHtml(file.name)+'</span>';
      pre.style.display = 'block';
    };
    reader.readAsDataURL(file);
  } else {
    cont.innerHTML = '<i class="fas fa-file"></i><span class="admin-file-preview-name">'+escapeHtml(file.name)+' ('+(file.size/1024/1024).toFixed(1)+' MB)</span>';
    pre.style.display = 'block';
  }
}

document.addEventListener('change', function(e) {
  if (e.target.id === 'admin-file-input') {
    var file = e.target.files[0];
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) {
      showAdminNotification('File too large. Maximum size is 10MB.', 'error');
      e.target.value = '';
      return;
    }
    if (currentConvId) {
      adminSelectedFile = file;
      adminShowFilePreview(file);
    } else {
      showAdminNotification('Select a conversation first', 'error');
      e.target.value = '';
    }
  }
});

async function adminSendTextMsg() {
  if(adminSending)return;
  var input=document.getElementById('msgInput');
  var content=input?input.value.trim():'';
  if(!content&&!adminSelectedFile)return;
  if(!currentConvId)return;
  adminSending=true;if(input)input.value='';
  try{
    if(adminSelectedFile){
      var fd=new FormData();
      fd.append('action','send_message');
      fd.append('conversation_id',currentConvId);
      fd.append('file',adminSelectedFile);
      if(content)fd.append('content',content);
      var r=await fetch('../api/chat.php',{method:'POST',credentials:'include',body:fd});
      var d=await r.json();
      if(d.success){
        adminClearFilePreview();
        if(d.message){lastMsgId=Math.max(lastMsgId,d.message.id);adminAppendMessages([d.message]);}
        adminLoadConversations();
      }
      else showAdminNotification(d.error||'Failed to send','error');
    }else{
      var r=await fetch('../api/chat.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({action:'send_message',conversation_id:currentConvId,message_type:'text',content})});
      var d=await r.json();
      if(d.success){
        if(d.message){lastMsgId=Math.max(lastMsgId,d.message.id);adminAppendMessages([d.message]);}
        adminLoadConversations();
      }
      else showAdminNotification(d.error||'Failed to send','error');
    }
  }catch(e){showAdminNotification('Failed to send message','error');}
  finally{adminSending=false;}
}

async function adminSendCustomRequest() {
  if(!currentConvId)return;
  try{
    var r=await fetch('../api/custom-printing.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({action:'admin_create_from_chat',conversation_id:currentConvId})});
    var d=await r.json();
    if(d.success){
      showAdminNotification('Custom request #'+d.request_id+' created','success');
      if(currentConvId)adminLoadChat(currentConvId);
      adminLoadConversations();
    }
    else showAdminNotification(d.error||'Failed','error');
  }catch(e){showAdminNotification('Failed to create request','error');}
}

async function adminSendOrderForm() {
  if(!currentConvId)return;
  try{
    var convRes=await fetch('../api/chat.php?action=conversations',{credentials:'include'});
    var convData=await convRes.json();
    var conv=(convData.conversations||[]).find(function(c){return c.id==currentConvId;});
    var requestId=conv?conv.request_id:null;if(!requestId){showAdminNotification('No custom request found','error');return;}
    var title=prompt('Order form title:','Order for '+(conv.product_name||'Custom Printing'));if(!title)return;
    var r=await fetch('../api/chat.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({action:'send_message',conversation_id:currentConvId,message_type:'order_form',content:JSON.stringify({request_id:requestId,title})})});
    var d=await r.json();
    if(d.success){
      if(d.message){lastMsgId=Math.max(lastMsgId,d.message.id);adminAppendMessages([d.message]);}
      adminLoadConversations();
    }
    else showAdminNotification(d.error||'Failed','error');
  }catch(e){showAdminNotification('Failed to send order form','error');}
}

async function adminDeleteCustomRequest(requestId) {
  if(!confirm('Delete this custom request? This cannot be undone.'))return;
  try{
    var r=await fetch('../api/custom-printing.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({action:'delete',request_id:requestId})});
    var d=await r.json();
    if(d.success){
      showAdminNotification('Custom request deleted','success');
      if(currentConvId)adminLoadChat(currentConvId);
      adminLoadConversations();
    }else showAdminNotification(d.error||'Failed to delete request','error');
  }catch(e){showAdminNotification('Failed to delete request','error');}
}

// Admin delegated send handlers
document.addEventListener('click',function(e){
  if(e.target.closest('#admin-btn-send'))adminSendTextMsg();
});
document.addEventListener('click',function(e){
  var btn=e.target.closest('.delete-conv-btn');
  if(btn){
    e.stopPropagation();
    var convId=parseInt(btn.dataset.id);
    if(!convId)return;
    if(!confirm('Delete this conversation? All messages will be permanently removed.'))return;
    fetch('../api/chat.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({action:'delete_conversation',conversation_id:convId})})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.success){
        if(currentConvId===convId){currentConvId=null;document.getElementById('chatMain').innerHTML='<div class="no-chat"><i class="fas fa-comments"></i><p>Select a conversation to start messaging</p></div>';}
        adminLoadConversations();
      }else adminShowNotification(d.error||'Failed to delete','error');
    })
    .catch(function(){adminShowNotification('Failed to delete','error');});
  }
});
document.addEventListener('keydown',function(e){
  if(e.target.id==='msgInput'&&e.key==='Enter'){e.preventDefault();adminSendTextMsg();}
});

// Tab visibility for admin
document.addEventListener('visibilitychange',function(){
  isTabVisibleAdmin=!document.hidden;
  if(!document.hidden){
    emptyPollsAdmin=0;pollIntervalAdmin=1000;
    if(currentConvId){
      if(pollTimer){clearTimeout(pollTimer);pollTimer=null;}
      adminLoadChat(currentConvId,true).then(function(){adminStartPolling();}).catch(function(){});
    }
  }
});

// Notification permission
if('Notification'in window&&Notification.permission==='default')Notification.requestPermission();

adminLoadConversations();
setInterval(adminLoadConversations, 30000);

(function(){
  var p=new URLSearchParams(window.location.search);
  var c=p.get('conversation');
  if(c)setTimeout(function(){adminSelectConversation(parseInt(c));},300);
})();

function openImageModal(url){
  document.getElementById('adminImgModalContent').src=url;
  document.getElementById('adminImgModalOverlay').classList.add('active');
  document.body.style.overflow='hidden';
}
function closeImageModal(){
  document.getElementById('adminImgModalOverlay').classList.remove('active');
  document.body.style.overflow='';
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeImageModal();});
</script>

<div class="img-modal-overlay" id="adminImgModalOverlay" onclick="closeImageModal()">
  <button class="img-modal-close" onclick="closeImageModal()">&times;</button>
  <img id="adminImgModalContent" onclick="event.stopPropagation()" alt="Preview">
</div>

<input type="file" id="admin-file-input" style="display:none;" accept="image/*,.pdf,.ai,.psd,.zip">

<?php require 'includes/admin-footer.php'; ?>
