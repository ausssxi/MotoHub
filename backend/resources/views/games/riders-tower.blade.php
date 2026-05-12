<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<meta name="description" content="タップでライダーを召喚！バイクを強化して日本縦断ツーリングに挑む放置系RPG。無料・登録不要">
<meta name="robots" content="index, follow">
<link rel="canonical" href="{{ url('/games/riders-tower') }}">
<meta property="og:title" content="バイク日本縦断 - 放置系バイクRPG | MotoHub">
<meta property="og:description" content="タップでライダーを召喚！バイクを強化して日本縦断ツーリングに挑む放置系RPG。無料・登録不要">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url('/games/riders-tower') }}">
<title>バイク日本縦断 - 放置系バイクRPG | MotoHub</title>
<script type="application/ld+json">{"@@context":"https://schema.org","@@type":"WebApplication","name":"バイク日本縦断","description":"タップでライダーを召喚！バイクを強化して日本縦断ツーリングに挑む放置系RPG","url":"{{ url('/games/riders-tower') }}","applicationCategory":"Game","operatingSystem":"Web Browser"}</script>
<style>
@verbatim
@import url('https://fonts.googleapis.com/css2?family=DotGothic16&family=Press+Start+2P&display=swap');

:root{
  --sky-top:#5bb8f5;--sky-bottom:#a8d8f0;
  --panel-bg:#e8dcc8;--panel-card:#f5edd8;--panel-border:#c0b090;
  --tab-bg:#e0d4b8;--tab-active:#f5edd8;
  --text-dark:#3a3020;--text-mid:#6a5a40;--accent:#d4760a;--gold:#ffd700;
  --fuel-color:#3abef7;--hp-enemy:#ff69b4;--hp-ally:#4aff4a;--header-bg:#2c5f8a;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#111;display:flex;justify-content:center;font-family:'DotGothic16',monospace;overflow:hidden;height:100dvh;touch-action:manipulation}

#GC{width:100%;max-width:420px;height:100dvh;display:flex;flex-direction:column;position:relative;overflow:hidden}

/* ===== HEADER ===== */
.g-header{background:linear-gradient(180deg,var(--header-bg),#3a7cbd);padding:6px 8px;display:flex;align-items:center;gap:6px;border-bottom:3px solid #1e4468;flex-shrink:0;z-index:20}
.g-currency{display:flex;align-items:center;gap:4px;background:rgba(0,0,0,.3);padding:3px 10px;border-radius:10px;min-width:100px}
.g-currency-val{color:var(--gold);font-family:'Press Start 2P',monospace;font-size:12px;text-shadow:1px 1px 0 rgba(0,0,0,.5)}
.g-equip-slots{display:flex;gap:3px;flex:1;justify-content:flex-end}
.g-eslot{width:30px;height:30px;background:rgba(0,0,0,.4);border:2px solid #5a5a7a;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:14px}
.g-eslot.filled{border-color:#f0c040;background:rgba(240,192,64,.15)}
.g-settings{width:28px;height:28px;background:rgba(0,0,0,.3);border:2px solid rgba(255,255,255,.2);border-radius:5px;color:#fff;font-size:14px;display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}

/* ===== STATUS BAR ===== */
.g-status{background:linear-gradient(180deg,#2a5578,#1e4468);padding:4px 8px;display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #163550;flex-shrink:0;z-index:20}
.g-stat{display:flex;align-items:center;gap:3px;color:#b8d4e8;font-size:10px}
.g-stat-num{color:#fff;font-weight:bold;font-family:'Press Start 2P',monospace;font-size:11px}
.g-stat-label{font-size:8px;display:block}
.g-bonus{background:rgba(255,200,50,.15);border:1px solid rgba(240,192,64,.4);border-radius:4px;padding:2px 5px;font-size:8px;color:#f0c040;text-align:center}

/* ===== GAME AREA ===== */
.g-area{flex:1;position:relative;overflow:hidden;min-height:0}
.g-scroll{position:absolute;inset:0;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;background:#1a2a1a;cursor:pointer}
.g-scroll::-webkit-scrollbar{width:3px}
.g-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:2px}
.g-stage-label{position:absolute;top:6px;left:50%;transform:translateX(-50%);z-index:12;background:rgba(0,0,0,.7);color:var(--gold);padding:2px 10px;border-radius:8px;font-size:10px;font-family:'Press Start 2P',monospace;white-space:nowrap;border:1px solid rgba(255,215,0,.25);pointer-events:none}

/* ===== 縦道路セグメント ===== */
.g-road{width:50%;max-width:200px;margin:0 auto;position:relative;min-height:100%}
.g-seg{height:100px;background:#666;border-left:5px solid #888;border-right:5px solid #888;position:relative}
.g-seg+.g-seg{border-top:1px solid rgba(255,255,255,.06)}
.g-seg.active{background:#6a6a6a;border-left-color:#aaa;border-right-color:#aaa}
.g-seg.cleared{background:#555}
.g-seg::before{content:'';position:absolute;left:50%;top:0;bottom:0;width:3px;transform:translateX(-50%);background:repeating-linear-gradient(180deg,#fff 0,#fff 18px,transparent 18px,transparent 32px);opacity:.25}
.g-seg.active::before{opacity:.45}
.g-seg::after{content:attr(data-km);position:absolute;right:-36px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.1);font-size:18px;font-family:'Press Start 2P',monospace;pointer-events:none}
.g-seg.active::after{color:rgba(255,215,0,.25)}
.g-seg.active.flash{animation:roadFlash .4s ease-out}
.g-area.defeat-flash{animation:defeatFlash .35s ease-out}
@keyframes defeatFlash{0%{filter:brightness(2.5)}100%{filter:brightness(1)}}
@keyframes roadFlash{0%{filter:brightness(1.8)}100%{filter:brightness(1)}}

/* km display */
.g-km-disp{position:absolute;top:6px;right:8px;z-index:12;color:rgba(255,255,255,.8);font-size:12px;font-family:'Press Start 2P',monospace;pointer-events:none}

/* ===== ENTITIES ===== */
.g-ent{position:absolute;z-index:6;font-size:28px;pointer-events:none;will-change:transform}
.g-ent.rider{animation:riderBounce .5s ease-in-out infinite alternate}
.g-ent.rider.atk{animation:atkShakeUp .15s ease-in-out infinite}
.g-ent.enemy{font-size:30px;transform:rotate(90deg)}
.g-ent.boss{font-size:36px}
.g-ent.dying{animation:deathAnim .4s ease-out forwards}
@keyframes riderBounce{from{transform:rotate(-90deg)}to{transform:rotate(-90deg) translateX(-3px)}}
@keyframes atkShakeUp{0%{transform:rotate(-90deg)}25%{transform:rotate(-90deg) translateX(-3px)}50%{transform:rotate(-90deg) translateX(2px)}75%{transform:rotate(-90deg) translateX(-2px)}100%{transform:rotate(-90deg)}}
@keyframes deathAnim{0%{transform:scale(1);opacity:1;filter:brightness(3)}50%{transform:scale(1.5);opacity:.7}100%{transform:scale(0);opacity:0}}

.g-hpbar{position:absolute;z-index:7;width:36px;height:4px;background:rgba(0,0,0,.4);border-radius:3px;overflow:hidden;pointer-events:none}
.g-hpbar-fill{height:100%;border-radius:3px;transition:width .2s}
.g-hpbar-fill.enemy{background:var(--hp-enemy)}
.g-hpbar-fill.rider-bar{background:#4aff4a}
.g-hpbar-fill.ally-bar{background:#4a9fff}

/* ===== EFFECTS ===== */
.g-float{position:absolute;z-index:15;font-family:'Press Start 2P',monospace;font-weight:bold;pointer-events:none;animation:floatUp 1s ease-out forwards;text-shadow:1px 1px 0 rgba(0,0,0,.5)}
@keyframes floatUp{0%{opacity:1;transform:translateY(0) scale(1)}60%{opacity:1}100%{opacity:0;transform:translateY(-40px) scale(.8)}}
.g-float.dmg{color:#ff8c00;font-size:14px}
.g-float.fuel{color:var(--fuel-color);font-size:11px}
.g-float.fuel-bounce{animation:fuelBounce .8s ease-out forwards}
@keyframes fuelBounce{0%{opacity:1;transform:translateY(0)}30%{transform:translateY(-25px)}50%{transform:translateY(-5px)}70%{transform:translateY(-15px)}100%{opacity:0;transform:translateY(-30px)}}
.g-float.crit{color:#ff3333;font-size:18px}
.g-hit{position:absolute;z-index:15;font-size:18px;pointer-events:none;animation:hitPop .3s ease-out forwards}
@keyframes hitPop{0%{transform:scale(.5);opacity:1}50%{transform:scale(1.3);opacity:1}100%{transform:scale(.8);opacity:0}}
.g-tap-ring{position:absolute;z-index:14;width:40px;height:40px;border:3px solid rgba(255,255,255,.8);border-radius:50%;pointer-events:none;animation:tapRing .4s ease-out forwards;box-shadow:0 0 8px rgba(255,255,255,.4)}
@keyframes tapRing{0%{transform:translate(-50%,-50%) scale(.3);opacity:1}100%{transform:translate(-50%,-50%) scale(1.8);opacity:0}}
.g-area.tap-flash{animation:areaFlash .15s ease-out}
@keyframes areaFlash{0%{filter:brightness(1.15)}100%{filter:brightness(1)}}
.g-tap-prompt{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.55);font-size:11px;z-index:12;animation:promptPulse 1.5s ease-in-out infinite;pointer-events:none}
@keyframes promptPulse{0%,100%{opacity:.3}50%{opacity:.8}}
.g-warn{position:absolute;top:40%;left:50%;transform:translate(-50%,-50%);color:#ff3333;font-size:13px;font-family:'Press Start 2P',monospace;text-shadow:2px 2px 0 rgba(0,0,0,.4);z-index:15;pointer-events:none;animation:warnFade 1.5s ease-out forwards}
@keyframes warnFade{0%{opacity:1;transform:translate(-50%,-50%) scale(1.1)}70%{opacity:1}100%{opacity:0;transform:translate(-50%,-50%) scale(.9)}}
.g-km-pop{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:20;color:var(--gold);font-size:20px;font-family:'Press Start 2P',monospace;text-shadow:2px 2px 0 rgba(0,0,0,.5);pointer-events:none;animation:kmPop .6s ease-out forwards}
@keyframes kmPop{0%{opacity:1;transform:translate(-50%,-50%) scale(1.3)}70%{opacity:1}100%{opacity:0;transform:translate(-50%,-50%) scale(.8)}}
/* ===== BOTTOM PANEL ===== */
.g-panel{background:linear-gradient(180deg,var(--panel-bg),#d8ccb0);border-top:3px solid var(--panel-border);overflow-y:auto;flex-shrink:0;max-height:220px;min-height:160px;padding:8px;z-index:20;-webkit-overflow-scrolling:touch}
.g-card{background:var(--panel-card);border:2px solid var(--panel-border);border-radius:7px;padding:8px;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.g-card.locked{opacity:.5}
.g-card-icon{width:38px;height:38px;background:#e0d4b8;border:2px solid #b0a080;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.g-card-info{flex:1;min-width:0}
.g-card-name{font-size:13px;font-weight:bold;color:var(--text-dark);display:flex;align-items:center;gap:5px}
.g-card-lv{background:#5a7aaa;color:#fff;padding:1px 5px;border-radius:3px;font-size:9px}
.g-card-stats{font-size:10px;color:var(--text-mid);margin-top:2px;display:flex;gap:8px;flex-wrap:wrap}
.g-card-btn{background:#f0e0c0;border:2px solid #c0a870;border-radius:5px;padding:5px 10px;cursor:pointer;text-align:center;flex-shrink:0;transition:transform .1s}
.g-card-btn:active{transform:scale(.95)}
.g-card-btn.buy{border-color:var(--accent);background:#fff0d0}
.g-card-cost{color:var(--accent);font-weight:bold;font-size:11px;font-family:'Press Start 2P',monospace}
.g-card-action{font-size:9px;color:var(--text-mid);margin-top:1px}

/* ===== TAB BAR ===== */
.g-tabs{display:flex;background:var(--tab-bg);border-top:3px solid var(--panel-border);flex-shrink:0;z-index:20}
.g-tab{flex:1;padding:8px 2px;text-align:center;font-size:10px;color:var(--text-mid);cursor:pointer;border-right:1px solid var(--panel-border);display:flex;flex-direction:column;align-items:center;gap:2px;transition:background .15s;position:relative;background:transparent;border-top:none;border-bottom:none;border-left:none;font-family:inherit}
.g-tab:last-child{border-right:none}
.g-tab.active{background:var(--tab-active);color:var(--text-dark);font-weight:bold;border-top:3px solid var(--accent);margin-top:-3px}
.g-tab-icon{font-size:16px}
.g-tab-label{font-size:9px}
.g-tab-notif{position:absolute;top:4px;right:20%;width:12px;height:12px;background:#e44;color:#fff;font-size:7px;border-radius:50%;display:none;align-items:center;justify-content:center}

/* ===== SETTINGS MODAL ===== */
.g-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s}
.g-modal-overlay.show{opacity:1;pointer-events:auto}
.g-modal{background:var(--panel-card);border:3px solid var(--accent);border-radius:12px;padding:20px;max-width:300px;width:85%;text-align:center;font-family:'DotGothic16',monospace}
.g-modal h3{color:var(--text-dark);font-size:16px;margin-bottom:12px}
.g-modal-btn{display:block;width:100%;padding:10px;margin-top:8px;background:#f0e0c0;border:2px solid #c0a870;border-radius:6px;font-size:13px;font-family:inherit;cursor:pointer;color:var(--text-dark)}
.g-modal-btn:active{transform:scale(.97)}
.g-modal-btn.danger{border-color:#e44;color:#c33}
.g-modal-btn.close{background:var(--accent);color:#fff;border-color:var(--accent);margin-top:14px}
@endverbatim
</style>
</head>
<body>

<div id="GC">
  <div class="g-header">
    <div class="g-currency"><span>💧</span><span class="g-currency-val" id="hFuel">0</span></div>
    <div class="g-equip-slots" id="hSlots"></div>
    <a href="/games" class="g-settings" id="btnSettings">⚙</a>
  </div>

  <div class="g-status">
    <div class="g-stat">🏍<div><span class="g-stat-num" id="sRiders">0/3</span><span class="g-stat-label">ライダー</span></div></div>
    <div class="g-stat">👥<div><span class="g-stat-num" id="sNakama">0</span><span class="g-stat-label">仲間</span></div></div>
    <div class="g-stat">📍<div><span class="g-stat-num" id="sKm">1</span><span class="g-stat-label">km</span></div></div>
    <div class="g-bonus" id="sBonus">🌟 パーツ0</div>
  </div>

  <div class="g-area" id="gArea">
    <div class="g-stage-label" id="stgLabel">Stage 1</div>
    <div class="g-scroll" id="gScroll">
      <div class="g-road" id="road"></div>
    </div>
    <div class="g-km-disp">到達: <span id="kmNum">1</span>km</div>
    <div class="g-tap-prompt">👆 タップでライダー召喚</div>
  </div>

  <div class="g-panel" id="gPanel"></div>

  <div class="g-tabs" id="gTabs">
    <button class="g-tab active" data-t="rider"><span class="g-tab-icon">🏍</span><span class="g-tab-label">ライダー</span></button>
    <button class="g-tab" data-t="nakama"><span class="g-tab-icon">👥</span><span class="g-tab-label">仲間</span><div class="g-tab-notif" id="nNotif">!</div></button>
    <button class="g-tab" data-t="parts"><span class="g-tab-icon">🔧</span><span class="g-tab-label">パーツ</span></button>
    <button class="g-tab" data-t="shop"><span class="g-tab-icon">🛒</span><span class="g-tab-label">ショップ</span></button>
  </div>
</div>

<div class="g-modal-overlay" id="modal">
  <div class="g-modal" id="modalBody"></div>
</div>

<script>
@verbatim
(function(){'use strict';

// ===== DOM =====
const $=id=>document.getElementById(id);
const area=$('gArea');
const scrollEl=$('gScroll');
const roadEl=$('road');

// ===== DATA =====
const STAGES=[
  {name:'佐多岬→鹿児島',kmStart:1,kmEnd:30,bg:['🌴','🌺','🌴','🌿','🌴'],enemies:['🦌','🐗','🐒'],bossE:'🌀',bossN:'台風',skyTop:'#5bb8f5',skyBot:'#a8d8f0'},
  {name:'鹿児島→熊本',kmStart:31,kmEnd:60,bg:['🌋','🪨','🌾','🏔️','🌋'],enemies:['🪨','🦌','🐗','🌧️'],bossE:'🪨',bossN:'巨大落石',skyTop:'#d46a3a',skyBot:'#f0a870'},
  {name:'熊本→福岡',kmStart:61,kmEnd:100,bg:['🌾','🐄','🏔️','🌾','⛩️'],enemies:['🐗','🐻','🚧','💀'],bossE:'💀',bossN:'暴走族リーダー',skyTop:'#3a5a8a',skyBot:'#7aadcc'},
];

const NAKAMA=[
  {id:'cub',name:'カブ乗り',emoji:'🛵',hp:30,atk:8,spawnSec:4.0,speed:1.0,cost:0},
  {id:'dio',name:'原付少年',emoji:'🛵',hp:50,atk:15,spawnSec:5.0,speed:1.1,cost:80},
  {id:'naked',name:'ネイキッド兄貴',emoji:'🏍',hp:120,atk:35,spawnSec:6.0,speed:1.25,cost:400},
  {id:'ss',name:'SSライダー',emoji:'🏎',hp:300,atk:98,spawnSec:7.0,speed:1.5,cost:1500},
  {id:'amrcn',name:'アメリカン姐さん',emoji:'🏍',hp:600,atk:180,spawnSec:7.5,speed:1.2,cost:5000},
];

const PARTS=[
  {id:'muffler',name:'スポーツマフラー',emoji:'🔧',eff:'atk',val:10,desc:'攻撃力+10'},
  {id:'tire',name:'ハイグリップタイヤ',emoji:'🛞',eff:'speed',val:0.1,desc:'速度+0.1'},
  {id:'brake',name:'ブレンボブレーキ',emoji:'🔴',eff:'atk',val:20,desc:'攻撃力+20'},
  {id:'sus',name:'オーリンズサス',emoji:'🟡',eff:'hp',val:50,desc:'HP+50'},
  {id:'cowl',name:'カーボンカウル',emoji:'🛡️',eff:'hp',val:30,desc:'HP+30'},
  {id:'light',name:'LEDヘッドライト',emoji:'💡',eff:'atk',val:15,desc:'攻撃力+15'},
  {id:'chain',name:'ゴールドチェーン',emoji:'⛓️',eff:'speed',val:0.15,desc:'速度+0.15'},
  {id:'seat',name:'ゲルシート',emoji:'🪑',eff:'hp',val:40,desc:'HP+40'},
  {id:'mirror',name:'バーエンドミラー',emoji:'🪞',eff:'atk',val:8,desc:'攻撃力+8'},
  {id:'tank',name:'ビッグタンク',emoji:'⛽',eff:'fuelBonus',val:0.2,desc:'燃料+20%'},
];

const UNIT_W=28;

// ===== ROAD CONFIG (セグメント式) =====
const SEG_H=100;           // 1セグメントの高さ
let ROAD_W=200;            // 道路幅（measureRoadで計算）
const RIDER_SPEED=60;      // ライダーの上向き移動速度 px/s

// ===== STATE =====
let S={
  fuel:50,km:1,stageIdx:0,cleared:false,
  riderLv:1,riderHp:30,riderAtk:8,riderSpeed:1.0,
  clubLv:0,maxRiders:3,tapAtk:5,tapLv:1,
  nakama:{cub:{owned:true,lv:1}},
  parts:[],partsSlots:5,
  riders:[],   // タップ召喚（上限S.maxRiders）
  allies:[],   // 仲間自動召喚（上限なし、秒間隔のみ）
  nTimers:{},  // 仲間ごとのスポーンタイマー
  enemy:null,  // {id,x,hp,mhp,atk,emoji,fd,isBoss,pDrop,lastAtk}
  nxId:0,
  lastTick:0,gt:0,
};

let curTab='rider';

// ===== ROAD GEOMETRY (セグメント式) =====
function measureRoad(){
  ROAD_W=roadEl.clientWidth;
}

function buildRoad(){
  roadEl.innerHTML='';
  var seg=document.createElement('div');
  seg.className='g-seg active';seg.dataset.km=S.km;
  roadEl.appendChild(seg);
  for(var km=S.km-1;km>=1;km--){
    seg=document.createElement('div');
    seg.className='g-seg cleared';seg.dataset.km=km;
    roadEl.appendChild(seg);
  }
  S.riderY=SEG_H*0.65;
}

function addSegment(){
  var oldTop=scrollEl.scrollTop;
  var wasAway=oldTop>10;
  var active=roadEl.querySelector('.g-seg.active');
  if(active){active.classList.remove('active');active.classList.add('cleared')}
  var seg=document.createElement('div');
  seg.className='g-seg active';seg.dataset.km=S.km;
  roadEl.prepend(seg);
  if(wasAway)scrollEl.scrollTop=oldTop+SEG_H;
}

function scrollToFront(){
  scrollEl.scrollTo({top:0,behavior:'smooth'});
}

// ===== CAMERA =====
let _manualScroll=false;
function cameraFollow(){
  if(_manualScroll)return;
  const viewH=scrollEl.clientHeight;
  const target=S.riderY-viewH*0.6;
  scrollEl.scrollTop=Math.max(0,target);
}

// ===== FLOAT / EFFECTS =====
function mkFloat(x,y,text,cls){
  const el=document.createElement('div');
  el.className='g-float '+cls;
  el.style.left=x+'px';el.style.top=y+'px';
  el.textContent=text;
  roadEl.appendChild(el);
  el.addEventListener('animationend',()=>el.remove(),{once:true});
}
function mkTap(x,y){
  const el=document.createElement('div');
  el.className='g-tap-ring';
  el.style.left=x+'px';el.style.top=y+'px';
  area.appendChild(el);
  el.addEventListener('animationend',()=>el.remove(),{once:true});
}
function mkWarn(text){
  const el=document.createElement('div');
  el.className='g-warn';el.textContent=text;
  area.appendChild(el);
  el.addEventListener('animationend',()=>el.remove(),{once:true});
}
function mkHit(x,y){
  const el=document.createElement('div');
  el.className='g-hit';
  el.style.left=(x+Math.random()*10-5)+'px';
  el.style.top=y+'px';
  el.textContent=Math.random()>.5?'⚡':'💥';
  roadEl.appendChild(el);
  el.addEventListener('animationend',()=>el.remove(),{once:true});
}

// ===== HELPERS =====
function fmtN(n){
  if(n>=1e8)return(n/1e8).toFixed(1)+'億';
  if(n>=1e4)return(n/1e4).toFixed(1)+'万';
  return Math.floor(n).toLocaleString('ja-JP');
}
function partBonus(type){
  let t=0;for(const pid of S.parts){const d=PARTS.find(p=>p.id===pid);if(d&&d.eff===type)t+=d.val}return t;
}
function stg(){return STAGES[S.stageIdx]||STAGES[0]}
// All units (riders + allies)
function allUnits(){return S.riders.concat(S.allies)}

// ===== COSTS =====
function riderUpCost(){return Math.floor(20*Math.pow(1.4,S.riderLv-1))}
function clubUpCost(){return Math.floor(500*Math.pow(2.5,S.clubLv))}
function tapUpCost(){return Math.floor(15*Math.pow(1.3,S.tapLv-1))}
function nakamaUpCost(def,lv){return Math.floor(Math.max(10,def.cost*0.3)*Math.pow(1.4,lv-1))}

// ===== ENEMY =====
function spawnEnemy(){
  const st=stg();
  const isBoss=(S.km===st.kmEnd);
  const bm=1+(S.km-1)*0.15;
  const emoji=isBoss?st.bossE:st.enemies[Math.floor(Math.random()*st.enemies.length)];
  const baseHp=S.km<=5?(15+S.km*5):(isBoss?200:40);
  const baseAtk=S.km<=5?Math.max(2,S.km):(isBoss?15:5);
  const hp=Math.floor(baseHp*bm);
  const atk=Math.floor(baseAtk*bm);
  const fd=Math.floor((isBoss?50:10)*bm*(1+partBonus('fuelBonus')));
  const ex=Math.floor(ROAD_W/2)-UNIT_W/2;
  S.enemy={id:S.nxId++,x:ex,y:SEG_H*0.1,hp,mhp:hp,atk,emoji,fd,isBoss,pDrop:isBoss?0.8:0.08,lastAtk:0,inCombat:false};
}

// ===== TAP =====
let lastTapMs=0;
function handleTap(cx,cy){
  const now=Date.now();
  if(now-lastTapMs<150)return;
  lastTapMs=now;

  const rect=area.getBoundingClientRect();
  const x=cx-rect.left,y=cy-rect.top;
  mkTap(x,y);
  area.classList.remove('tap-flash');void area.offsetWidth;area.classList.add('tap-flash');

  // Tap damage (remote — always hits frontline enemy)
  if(S.enemy&&S.enemy.hp>0){
    S.enemy.hp-=S.tapAtk;
    mkFloat(S.enemy.x+10,S.enemy.y-15,fmtN(S.tapAtk),'dmg');
  }

  // Spawn rider
  if(S.riders.length<S.maxRiders){
    summonRider();
  }else{
    mkWarn('召喚レベルがたりません！');
  }
}

function summonRider(){
  if(S.riders.length>=S.maxRiders)return;
  const hp=S.riderHp+partBonus('hp');
  S.riders.push({
    id:S.nxId++,hp,mhp:hp,
    atk:S.riderAtk+partBonus('atk'),
    speed:S.riderSpeed+partBonus('speed'),
    emoji:'🏍',attacking:false,lastAtk:0
  });
}

// ===== AUTO SPAWN (仲間 → S.allies) =====
// 仲間ごとに固定間隔で自動出現。人数上限なし。
function autoSpawn(dt){
  const ownedIds=Object.keys(S.nakama).filter(id=>S.nakama[id].owned);
  for(const nId of ownedIds){
    const def=NAKAMA.find(n=>n.id===nId);
    if(!S.nTimers[nId])S.nTimers[nId]=0;
    S.nTimers[nId]+=dt;
    if(S.nTimers[nId]>=def.spawnSec){
      S.nTimers[nId]=0;
      const lv=S.nakama[nId].lv;const lm=1+(lv-1)*0.3;
      const hp=Math.floor((def.hp+partBonus('hp'))*lm);
      S.allies.push({
        id:S.nxId++,hp,mhp:hp,
        atk:Math.floor((def.atk+partBonus('atk'))*lm),
        speed:def.speed+partBonus('speed'),
        emoji:def.emoji,attacking:false,lastAtk:0
      });
    }
  }
}

// ===== GAME LOOP =====
function loop(ts){
  if(!S.lastTick)S.lastTick=ts;
  const dt=Math.min((ts-S.lastTick)/1000,0.1);
  S.lastTick=ts;S.gt+=dt;

  moveUnits(dt);
  combat();
  cleanup();
  autoSpawn(dt);
  render();
  cameraFollow();
  updateHUD();

  requestAnimationFrame(loop);
}

// ライダーが下から上に移動。敵は固定位置。
function moveUnits(dt){
  if(!S.enemy)return;
  const contactY=S.enemy.y+UNIT_W+5;
  if(S.riderY>contactY){
    S.riderY-=RIDER_SPEED*dt;
    if(S.riderY<contactY)S.riderY=contactY;
    S.enemy.inCombat=false;
    for(const r of allUnits())r.attacking=false;
  }else{
    S.enemy.inCombat=true;
    for(const r of allUnits())r.attacking=true;
  }
}

function combat(){
  if(!S.enemy||S.enemy.hp<=0)return;
  for(const r of allUnits()){
    if(!r.attacking)continue;

    if(S.gt-r.lastAtk>=1.0/r.speed){
      r.lastAtk=S.gt;
      const dmg=r.atk+Math.floor(Math.random()*r.atk*0.2);
      S.enemy.hp-=dmg;
      mkFloat(S.enemy.x+Math.random()*20-10,S.enemy.y-20+Math.random()*15,fmtN(dmg),'dmg');
      mkHit(S.enemy.x+10,S.enemy.y-5);
    }

    if(!S.enemy.lastAtk)S.enemy.lastAtk=S.gt;
    if(S.gt-S.enemy.lastAtk>=2.0){
      S.enemy.lastAtk=S.gt;
      r.hp-=S.enemy.atk;
    }
  }
}

function cleanup(){
  S.riders=S.riders.filter(r=>r.hp>0);
  S.allies=S.allies.filter(r=>r.hp>0);

  if(S.enemy&&S.enemy.hp<=0){
    S.fuel+=S.enemy.fd;
    mkFloat(S.enemy.x,S.enemy.y-30,'💧'+fmtN(S.enemy.fd),'fuel-bounce');

    if(Math.random()<S.enemy.pDrop)dropPart();

    const deathEl=document.querySelector('[data-eid="en"]');
    if(deathEl){
      deathEl.className='g-ent dying';
      deathEl.addEventListener('animationend',()=>deathEl.remove(),{once:true});
    }

    S.km++;
    $('kmNum').textContent=S.km;

    // 新セグメント追加 + ライダー位置調整
    addSegment();
    S.riderY+=SEG_H;

    // Km pop on active segment
    const activeSeg=roadEl.querySelector('.g-seg.active');
    if(activeSeg){
      const kpop=document.createElement('div');
      kpop.className='g-km-pop';kpop.textContent='↑ '+S.km+'km';
      activeSeg.appendChild(kpop);
      kpop.addEventListener('animationend',()=>kpop.remove(),{once:true});
    }

    // 攻撃状態リセット
    for(const r of allUnits()){
      r.attacking=false;
      r.lastAtk=0;
    }

    checkStage();
    updateStageLabel();

    // セグメントフラッシュ
    if(activeSeg){
      activeSeg.classList.add('flash');
      activeSeg.addEventListener('animationend',function(){this.classList.remove('flash')},{once:true});
    }

    // 画面全体フラッシュ
    area.classList.add('defeat-flash');
    area.addEventListener('animationend',function(){this.classList.remove('defeat-flash')},{once:true});

    // 0.3秒遅延で次の敵出現
    setTimeout(function(){spawnEnemy()},300);
    saveGame();
  }
}

function dropPart(){
  const avail=PARTS.filter(p=>!S.parts.includes(p.id));
  if(!avail.length)return;
  const part=avail[Math.floor(Math.random()*avail.length)];
  S.parts.push(part.id);
  mkFloat(S.enemy?S.enemy.x:ROAD_W*0.3,(S.enemy?S.enemy.y:SEG_H*0.3)-20,part.emoji+'GET!','crit');
  renderPanel();updateEquipSlots();saveGame();
}

function checkStage(){
  if(S.cleared)return;
  if(S.stageIdx>=STAGES.length-1){
    // 最終ステージの終点を超えたらクリア
    const st=stg();
    if(st&&S.km>st.kmEnd){
      S.cleared=true;
      mkWarn('🎊 日本縦断達成！おめでとうございます！🎊');
      saveGame();
    }
    return;
  }
  const st=stg();
  if(st&&S.km>st.kmEnd){
    S.stageIdx++;
    mkWarn('🎉 Stage '+(S.stageIdx+1)+' 突入！');
    updateSkyColor();
  }
}

function updateStageLabel(){
  $('stgLabel').textContent='Stage '+(S.stageIdx+1)+': '+stg().name;
}
function updateSkyColor(){
  // 縦スクロール版: ステージはラベルで表示
}

// ===== RENDER =====
const entEls=new Map();
function getEl(id){
  let el=entEls.get(id);
  if(!el){el=document.createElement('div');roadEl.appendChild(el);entEls.set(id,el)}
  return el;
}
let usedIds=new Set();

function renderUnit(r,prefix,hpClass,idx){
  const rid=prefix+r.id;
  usedIds.add(rid);
  // セグメント下部にバラけさせる
  const cols=Math.min(4,Math.max(2,Math.floor(ROAD_W/45)));
  const col=idx%cols;
  const row=Math.floor(idx/cols);
  const spacing=ROAD_W/(cols+1);
  const vx=spacing*(col+1)-UNIT_W/2;
  const vy=S.riderY+row*28;
  const el=getEl(rid);
  el.className='g-ent rider'+(r.attacking?' atk':'');
  el.style.left=vx+'px';
  el.style.top=vy+'px';
  el.style.bottom='';
  if(!el._ad){el.style.animationDelay=(r.id*0.15%0.5)+'s';el._ad=1}
  if(el._em!==r.emoji){el.textContent=r.emoji;el._em=r.emoji}
  const hid=prefix+'h'+r.id;usedIds.add(hid);
  const hel=getEl(hid);hel.className='g-hpbar';
  hel.style.left=(vx+2)+'px';
  hel.style.top=(vy+UNIT_W+2)+'px';
  hel.style.bottom='';
  hel.innerHTML='<div class="g-hpbar-fill '+hpClass+'" style="width:'+Math.max(0,r.hp/r.mhp*100)+'%"></div>';
}

function render(){
  usedIds.clear();

  // Enemy
  if(S.enemy&&S.enemy.hp>0){
    const eid='en';
    usedIds.add(eid);
    const el=getEl(eid);
    el.className='g-ent'+(S.enemy.isBoss?' enemy boss':' enemy');
    el.setAttribute('data-eid','en');
    el.style.left=S.enemy.x+'px';
    el.style.top=S.enemy.y+'px';
    el.style.bottom='';
    if(el._em!==S.enemy.emoji){el.textContent=S.enemy.emoji;el._em=S.enemy.emoji}

    const hid='enh';
    usedIds.add(hid);
    const hel=getEl(hid);
    hel.className='g-hpbar';
    hel.style.left=(S.enemy.x+2)+'px';
    hel.style.top=(S.enemy.y-10)+'px';
    hel.style.bottom='';
    const pct=Math.max(0,S.enemy.hp/S.enemy.mhp*100);
    hel.innerHTML='<div class="g-hpbar-fill enemy" style="width:'+pct+'%"></div>';
  }

  // Riders (tap) — green HP bar
  S.riders.forEach(function(r,i){renderUnit(r,'r','rider-bar',i)});
  // Allies (auto) — blue HP bar
  S.allies.forEach(function(r,i){renderUnit(r,'a','ally-bar',i)});

  // Cleanup unused elements
  for(const[id,el]of entEls){
    if(!usedIds.has(id)){el.remove();entEls.delete(id)}
  }
}

// ===== HUD =====
function updateHUD(){
  $('hFuel').textContent=fmtN(Math.floor(S.fuel));
  $('sRiders').textContent=S.riders.length+'/'+S.maxRiders;
  $('sNakama').textContent=S.allies.length;
  $('sKm').textContent=S.km;
  $('sBonus').textContent='🌟 パーツ'+S.parts.length;
  updateEquipSlots();

  const canBuy=NAKAMA.some(n=>!S.nakama[n.id]?.owned&&S.fuel>=n.cost);
  $('nNotif').style.display=canBuy?'flex':'none';
}

function updateEquipSlots(){
  let h='';
  for(let i=0;i<S.partsSlots;i++){
    const part=S.parts[i]?PARTS.find(p=>p.id===S.parts[i]):null;
    h+='<div class="g-eslot'+(part?' filled':'')+'">'+(part?part.emoji:'▪')+'</div>';
  }
  $('hSlots').innerHTML=h;
}

// ===== TABS & PANEL =====
function switchTab(tab){
  curTab=tab;
  document.querySelectorAll('.g-tab').forEach(t=>t.classList.toggle('active',t.dataset.t===tab));
  renderPanel();
}

function renderPanel(){
  const p=$('gPanel');
  switch(curTab){
    case'rider':renderRider(p);break;
    case'nakama':renderNakama(p);break;
    case'parts':renderPartsTab(p);break;
    case'shop':renderShop(p);break;
  }
}

function renderRider(p){
  const ta=S.riderAtk+partBonus('atk');
  const th=S.riderHp+partBonus('hp');
  p.innerHTML=
    card('🏍','ライダー',S.riderLv,`HP ${th} 攻撃力 ${ta} 速度 ${S.riderSpeed.toFixed(2)}`,'💧'+fmtN(riderUpCost()),'レベルアップ','upgradeRider')+
    card('🏕','ツーリングクラブ',S.clubLv,`同時に走れるライダーの上限を増やす<br>現在: ${S.maxRiders}体`,'💧'+fmtN(clubUpCost()),'レベルアップ','upgradeClub')+
    card('👆','タップ強化',S.tapLv,`タップ攻撃力: ${S.tapAtk}`,'💧'+fmtN(tapUpCost()),'レベルアップ','upgradeTap');
}

function renderNakama(p){
  let h='';
  for(const def of NAKAMA){
    const d=S.nakama[def.id];
    const owned=d?.owned;
    const lv=d?.lv||0;
    const cost=owned?nakamaUpCost(def,lv):def.cost;
    const lm=owned?1+(lv-1)*0.3:1;
    h+=`<div class="g-card${owned?'':' locked'}"><div class="g-card-icon">${def.emoji}</div><div class="g-card-info"><div class="g-card-name">${owned?def.name:'????'} <span class="g-card-lv">LV ${lv}</span></div><div class="g-card-stats"><span>HP ${Math.floor(def.hp*lm)}</span><span>攻撃力 ${Math.floor(def.atk*lm)}</span></div><div class="g-card-stats"><span>出現 ${def.spawnSec}秒</span><span>速度 ${def.speed.toFixed(2)}</span></div></div><div class="g-card-btn${owned?'':' buy'}" data-act="${owned?'upN':'buyN'}" data-id="${def.id}"><div class="g-card-cost">💧${fmtN(cost)}</div><div class="g-card-action">${owned?'レベルアップ':'獲得'}</div></div></div>`;
  }
  p.innerHTML=h;
}

function renderPartsTab(p){
  if(!S.parts.length){
    p.innerHTML='<div style="text-align:center;padding:30px;color:var(--text-mid);font-size:13px"><div style="font-size:40px;margin-bottom:10px">🔧</div><div>パーツは敵から獲得できます。</div><div style="margin-top:8px;font-size:11px">強い敵ほど強力なパーツが！</div><div style="margin-top:8px;font-size:10px;color:#999">持っているだけで効果が発動します</div></div>';
    return;
  }
  let h='';
  for(const pid of S.parts){
    const d=PARTS.find(pp=>pp.id===pid);if(!d)continue;
    h+=`<div class="g-card"><div class="g-card-icon">${d.emoji}</div><div class="g-card-info"><div class="g-card-name">${d.name}</div><div class="g-card-stats"><span>${d.desc}</span></div></div></div>`;
  }
  p.innerHTML=h;
}

function renderShop(p){
  const fa=Math.floor(500*(1+S.km*0.1));
  p.innerHTML=`<div class="g-card"><div class="g-card-icon">⛽</div><div class="g-card-info"><div class="g-card-name">ハイオク満タン</div><div class="g-card-stats"><span>💧${fmtN(fa)} のガソリンを獲得</span></div></div><div class="g-card-btn buy" data-act="buyFuel"><div class="g-card-cost">⭐10</div><div class="g-card-action">購入</div></div></div><div class="g-card"><div class="g-card-icon">🚨</div><div class="g-card-info"><div class="g-card-name">レッカー召喚</div><div class="g-card-stats"><span>60秒間ライダーを大量召喚</span></div></div><div class="g-card-btn buy" data-act="comingSoon"><div class="g-card-cost">⭐8</div><div class="g-card-action">購入</div></div></div><div style="text-align:center;padding:10px;color:#999;font-size:10px">⭐ ポイントは今後のアップデートで獲得可能に</div>`;
}

function card(icon,name,lv,stats,cost,action,act){
  return `<div class="g-card"><div class="g-card-icon">${icon}</div><div class="g-card-info"><div class="g-card-name">${name} <span class="g-card-lv">LV ${lv}</span></div><div class="g-card-stats"><span>${stats}</span></div></div><div class="g-card-btn" data-act="${act}"><div class="g-card-cost">${cost}</div><div class="g-card-action">${action}</div></div></div>`;
}

// ===== UPGRADE FUNCTIONS =====
function upgradeRider(){
  const c=riderUpCost();if(S.fuel<c)return mkWarn('ガソリンが足りません！');
  S.fuel-=c;S.riderLv++;
  S.riderHp=Math.floor(30*Math.pow(1.2,S.riderLv-1));
  S.riderAtk=Math.floor(8*Math.pow(1.25,S.riderLv-1));
  S.riderSpeed=1.0+(S.riderLv-1)*0.05;
  renderPanel();saveGame();
}
function upgradeClub(){
  const c=clubUpCost();
  console.log('[upgradeClub] cost='+c+' fuel='+S.fuel+' clubLv='+S.clubLv+' maxRiders='+S.maxRiders);
  if(S.fuel<c)return mkWarn('ガソリンが足りません！');
  S.fuel-=c;S.clubLv++;S.maxRiders=3+S.clubLv;
  console.log('[upgradeClub] ★ UPGRADED! clubLv='+S.clubLv+' maxRiders='+S.maxRiders);
  mkWarn('🏕 最大ライダー数: '+S.maxRiders);
  renderPanel();saveGame();
}
function upgradeTap(){
  const c=tapUpCost();if(S.fuel<c)return mkWarn('ガソリンが足りません！');
  S.fuel-=c;S.tapLv++;S.tapAtk=Math.floor(5*Math.pow(1.3,S.tapLv-1));
  renderPanel();saveGame();
}
function buyNakama(id){
  const def=NAKAMA.find(n=>n.id===id);
  if(S.fuel<def.cost)return mkWarn('ガソリンが足りません！');
  S.fuel-=def.cost;S.nakama[id]={owned:true,lv:1};
  renderPanel();saveGame();
}
function upgradeNakamaFn(id){
  const def=NAKAMA.find(n=>n.id===id);const lv=S.nakama[id].lv;
  const c=nakamaUpCost(def,lv);if(S.fuel<c)return mkWarn('ガソリンが足りません！');
  S.fuel-=c;S.nakama[id].lv++;
  renderPanel();saveGame();
}
function buyFuel(){
  const amt=Math.floor(500*(1+S.km*0.1));
  S.fuel+=amt;mkWarn('💧'+fmtN(amt)+' 獲得！');saveGame();
}

// ===== SAVE/LOAD =====
const SAVE_KEY='motohub_bikeJudan';
function saveGame(){
  try{localStorage.setItem(SAVE_KEY,JSON.stringify({
    v:2,fuel:S.fuel,km:S.km,stageIdx:S.stageIdx,cleared:S.cleared,
    riderLv:S.riderLv,riderHp:S.riderHp,riderAtk:S.riderAtk,riderSpeed:S.riderSpeed,
    clubLv:S.clubLv,maxRiders:S.maxRiders,tapAtk:S.tapAtk,tapLv:S.tapLv,
    nakama:S.nakama,parts:S.parts,savedAt:new Date().toISOString()
  }))}catch(e){}
}

function loadGame(){
  try{
    const raw=localStorage.getItem(SAVE_KEY);if(!raw)return;
    const d=JSON.parse(raw);
    S.fuel=d.fuel??50;S.km=d.km??1;S.stageIdx=d.stageIdx??0;S.cleared=d.cleared??false;
    S.riderLv=d.riderLv??1;S.riderHp=d.riderHp??30;S.riderAtk=d.riderAtk??8;
    S.riderSpeed=d.riderSpeed??1.0;
    S.clubLv=d.clubLv??0;S.maxRiders=d.maxRiders??3;
    S.tapAtk=d.tapAtk??5;S.tapLv=d.tapLv??1;
    S.nakama=d.nakama??{cub:{owned:true,lv:1}};
    S.parts=d.parts??[];
  }catch(e){}
}

// ===== SETTINGS MODAL =====
function showSettings(){
  $('modalBody').innerHTML='<h3>⚙ 設定</h3><button class="g-modal-btn" onclick="window.location.href=\'/games\'">🏠 ゲーム一覧に戻る</button><button class="g-modal-btn" onclick="window.location.href=\'/\'">🏍 MotoHubトップへ</button><button class="g-modal-btn danger" id="btnReset">🗑 セーブデータ削除</button><button class="g-modal-btn close" id="btnCloseModal">閉じる</button>';
  $('modal').classList.add('show');
  $('btnReset').onclick=function(){
    if(confirm('本当にセーブデータを削除しますか？')){
      localStorage.removeItem(SAVE_KEY);location.reload();
    }
  };
  $('btnCloseModal').onclick=function(){$('modal').classList.remove('show')};
}

// ===== EVENTS =====
let _ptrStart=null;
let _scrollReturnTimer=null;

function setupEvents(){
  // Tap vs scroll detection + manual scroll tracking
  scrollEl.addEventListener('pointerdown',function(e){
    _ptrStart={x:e.clientX,y:e.clientY,t:Date.now()};
    _manualScroll=true;
    if(_scrollReturnTimer)clearTimeout(_scrollReturnTimer);
  });
  scrollEl.addEventListener('pointerup',function(e){
    if(!_ptrStart)return;
    var dx=e.clientX-_ptrStart.x,dy=e.clientY-_ptrStart.y;
    var dist=Math.sqrt(dx*dx+dy*dy);
    var elapsed=Date.now()-_ptrStart.t;
    _ptrStart=null;
    if(dist<12&&elapsed<300){
      handleTap(e.clientX,e.clientY);
      _manualScroll=false;
    }else{
      // Was a scroll gesture — resume camera after 2s
      if(_scrollReturnTimer)clearTimeout(_scrollReturnTimer);
      _scrollReturnTimer=setTimeout(function(){_manualScroll=false},2000);
    }
  });
  scrollEl.addEventListener('pointercancel',function(){_ptrStart=null;_manualScroll=false});

  // Tab switching
  $('gTabs').addEventListener('click',function(e){
    const tab=e.target.closest('.g-tab');
    if(tab&&tab.dataset.t)switchTab(tab.dataset.t);
  });

  // Panel button delegation
  $('gPanel').addEventListener('click',function(e){
    const btn=e.target.closest('[data-act]');if(!btn)return;
    const act=btn.dataset.act,id=btn.dataset.id;
    if(act==='upgradeRider')upgradeRider();
    else if(act==='upgradeClub')upgradeClub();
    else if(act==='upgradeTap')upgradeTap();
    else if(act==='buyN')buyNakama(id);
    else if(act==='upN')upgradeNakamaFn(id);
    else if(act==='buyFuel')buyFuel();
    else if(act==='comingSoon')mkWarn('⭐ 準備中！');
  });

  // Settings button
  $('btnSettings').addEventListener('click',function(e){e.preventDefault();showSettings()});

  // Keyboard (space to tap)
  document.addEventListener('keydown',function(e){
    if(e.code==='Space'){e.preventDefault();
      if(S.enemy&&S.enemy.hp>0){
        S.enemy.hp-=S.tapAtk;
        mkFloat(S.enemy.x+10,S.enemy.y-15,fmtN(S.tapAtk),'dmg');
      }
      if(S.riders.length<S.maxRiders)summonRider();
    }
  });
}

// ===== INIT =====
function init(){
  loadGame();
  buildRoad();
  measureRoad();
  spawnEnemy();
  updateStageLabel();updateSkyColor();
  $('kmNum').textContent=S.km;
  renderPanel();updateHUD();
  setupEvents();
  requestAnimationFrame(loop);
}

setInterval(saveGame,10000);
init();
})();
@endverbatim
</script>
</body>
</html>
