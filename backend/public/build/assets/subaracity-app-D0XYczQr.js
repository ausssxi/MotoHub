import{r as i,j as e,c as st}from"./client-Ccx6zi3W.js";const me={honda:["スーパーカブ50","モンキー125","CT125","PCX","レブル250","GB350","CB400SF","CBR600RR","CB1300SF"],yamaha:["JOG","セロー250","SR400","YZF-R3","MT-07","XSR700","MT-09","YZF-R6","VMAX"],kawasaki:["KLX230","エストレヤ","Ninja250","Ninja400","W800","Z900RS","Ninja650","ZX-10R","H2"],suzuki:["アドレス","ST250","GSX250R","SV650","V-Strom","GSX-S750","SV1000","ハヤブサ","GSX-R1000R"]},be={10:"R1250GS",11:"Panigale V4",12:"ゴールドウイング"},Fe=[10,20,35,55,80,100,170,200,300,500,750,1e3];function ue(t,n){if(n>=10)return be[n]||be[12];const o=me[t];return o?o[n-1]||`Lv${n}`:`Lv${n}`}function _e(t){return t<=Fe.length?Fe[t-1]:1e3+(t-12)*500}function at(t,n){return n>=13?"/images/subaracity/world_12.png":n>=10?`/images/subaracity/world_${n}.png`:`/images/subaracity/${t}_${n}.png`}const ie=[{id:"honda",label:"ホンダ",bg:"#EF4444",light:"#FEE2E2"},{id:"yamaha",label:"ヤマハ",bg:"#3B82F6",light:"#DBEAFE"},{id:"kawasaki",label:"カワサキ",bg:"#22C55E",light:"#DCFCE7"},{id:"suzuki",label:"スズキ",bg:"#EAB308",light:"#FEF9C3"},{id:"world",label:"海外",bg:"#F8FAFC",light:"#F1F5F9"}];function ct(t){return ie.find(n=>n.id===t)}const ne=[{min:0,rank:"F",label:"免許取りたて"},{min:100,rank:"E",label:"週末ライダー"},{min:500,rank:"D",label:"ツーリングマスター"},{min:1e3,rank:"C",label:"ガレージオーナー"},{min:3e3,rank:"B",label:"バイクコレクター"},{min:5e3,rank:"A",label:"伝説のライダー"},{min:1e4,rank:"S",label:"スバラシガレージ"}];function dt(t){for(let n=ne.length-1;n>=0;n--)if(t>=ne[n].min)return ne[n];return ne[0]}let ye=1;const d=5;function He(t){const n=t<30?3:4;return ie[Math.floor(Math.random()*n)].id}function Ye(t){return{id:ye++,colorId:t,level:1}}function Te(){const t=[];for(let n=0;n<d;n++){const o=[];for(let s=0;s<d;s++)o.push(Ye(He(0)));t.push(o)}return t}function le(t){return t.map(n=>n.map(o=>o?{...o}:null))}function ft(t,n,o){const s=t[n][o];if(!s)return new Set;if(s.level>=11)return new Set;const r=s.colorId,u=new Set,f=[[n,o]];for(u.add(`${n},${o}`);f.length>0;){const[w,E]=f.shift();for(const[v,$]of[[0,1],[0,-1],[1,0],[-1,0]]){const j=w+v,g=E+$,F=`${j},${g}`;if(j>=0&&j<d&&g>=0&&g<d&&!u.has(F)){const T=t[j][g];T&&T.colorId===r&&T.level<11&&(u.add(F),f.push([j,g]))}}}return u}function xt(t){const n=le(t);for(let o=0;o<d;o++){const s=[];for(let r=d-1;r>=0;r--)n[r][o]&&s.push(n[r][o]);for(let r=d-1;r>=0;r--)n[r][o]=s[d-1-r]||null}return n}function pt(t){const n=Array.from({length:d},()=>Array(d).fill(null)),o=new Map;for(let s=0;s<d;s++){const r=[];for(let f=0;f<d;f++)t[f][s]&&r.push({cell:t[f][s],origRow:f});let u=d-1;for(let f=r.length-1;f>=0;f--){n[u][s]=r[f].cell;const w=u-r[f].origRow;w>0&&o.set(`${u},${s}`,w),u--}}return{grid:n,movements:o}}function Be(t,n){const o=le(t);for(let s=0;s<d;s++)for(let r=0;r<d;r++)o[s][r]||(o[s][r]=Ye(He(n)));return o}function Me(t,n){for(let o=0;o<d;o++)for(let s=0;s<d;s++)if(!t[o][s])return!1;if(n>0)return!1;for(let o=0;o<d;o++)for(let s=0;s<d;s++){const r=t[o][s];if(r&&!(r.level>=11))for(const[u,f]of[[0,1],[1,0]]){const w=o+u,E=s+f;if(w<d&&E<d){const v=t[w][E];if(v&&v.colorId===r.colorId&&v.level<11)return!1}}}return!0}function ht(t){let n=0;for(let o=0;o<d;o++)for(let s=0;s<d;s++){const r=t[o][s];r&&(n+=_e(r.level))}return n}function ut(t){let n=1,o="honda";for(let s=0;s<d;s++)for(let r=0;r<d;r++)t[s][r]&&t[s][r].level>n&&(n=t[s][r].level,o=t[s][r].colorId);return{level:n,colorId:o}}const Oe="motohub_subaracity";function gt(t,n){try{localStorage.setItem(Oe,JSON.stringify({bestScore:t,maxLevel:n}))}catch{}}function mt(){try{const t=localStorage.getItem(Oe);if(!t)return{bestScore:0,maxLevel:1};const n=JSON.parse(t);return{bestScore:typeof n.bestScore=="number"?n.bestScore:0,maxLevel:typeof n.maxLevel=="number"?n.maxLevel:1}}catch{return{bestScore:0,maxLevel:1}}}const Ne="subaracity-styles";function bt(){if(document.getElementById(Ne))return;const t=document.createElement("style");t.id=Ne,t.textContent=`
    @keyframes sc-slide-to {
      0%   { transform: translate(0, 0); opacity: 1; animation-timing-function: ease-in-out; }
      50%  { transform: translate(var(--sc-dx), 0); opacity: 0.85; animation-timing-function: ease-in-out; }
      100% { transform: translate(var(--sc-dx), var(--sc-dy)); opacity: 0.3; }
    }
    @keyframes sc-flash {
      0%   { opacity: 0; transform: scale(0.5); }
      40%  { opacity: 1; transform: scale(1.1); }
      100% { opacity: 0; transform: scale(1.4); }
    }
    @keyframes sc-pop {
      0%   { transform: scale(0); }
      60%  { transform: scale(1.2); }
      100% { transform: scale(1); }
    }
    @keyframes sc-gravity-fall {
      0%   { transform: translateY(var(--sc-fall-y)); }
      100% { transform: translateY(0); }
    }
    @keyframes sc-fill-drop {
      0%   { transform: translateY(var(--sc-fill-y)); opacity: 0; }
      50%  { opacity: 1; }
      70%  { transform: translateY(0); }
      82%  { transform: translateY(6%); }
      100% { transform: translateY(0); opacity: 1; }
    }
    @keyframes sc-highlight-pulse {
      0%, 100% { box-shadow: inset 0 0 0 3px rgba(255,255,255,0.5); }
      50%      { box-shadow: inset 0 0 0 3px rgba(255,255,255,1); }
    }
    @keyframes sc-target-glow {
      0%, 100% { box-shadow: 0 0 8px rgba(255,255,255,0.3); }
      50%      { box-shadow: 0 0 16px rgba(255,255,255,0.7); }
    }
    @keyframes sc-announce-in {
      0%   { opacity: 0; transform: scale(0.3) rotate(-10deg); }
      50%  { opacity: 1; transform: scale(1.1) rotate(2deg); }
      70%  { transform: scale(0.95) rotate(-1deg); }
      100% { opacity: 1; transform: scale(1) rotate(0deg); }
    }
    @keyframes sc-announce-out {
      0%   { opacity: 1; transform: scale(1); }
      100% { opacity: 0; transform: scale(1.3); }
    }
    .sc-announce-overlay {
      position: fixed;
      inset: 0;
      z-index: 200;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0,0,0,0.6);
      animation: sc-backdrop-in 0.2s ease;
      pointer-events: none;
    }
    .sc-announce-box {
      padding: 20px 32px;
      border-radius: 16px;
      text-align: center;
      animation: sc-announce-in 0.5s ease forwards;
    }
    .sc-announce-box.leaving {
      animation: sc-announce-out 0.3s ease forwards;
    }
    @keyframes sc-modal-in {
      0%   { opacity: 0; transform: scale(0.9) translateY(20px); }
      100% { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes sc-backdrop-in {
      0%   { opacity: 0; }
      100% { opacity: 1; }
    }
    .sc-cell-sliding {
      animation: sc-slide-to 300ms linear forwards;
      animation-iteration-count: 1;
      z-index: 5;
      transition: none !important;
    }
    .sc-cell-pop {
      animation: sc-pop 300ms ease;
    }
    .sc-cell-gravity {
      animation: sc-gravity-fall 200ms ease-out backwards;
      animation-delay: var(--sc-fall-delay, 0ms);
    }
    .sc-cell-fill {
      animation: sc-fill-drop 300ms ease-out backwards;
      animation-delay: var(--sc-fill-delay, 0ms);
    }
    .sc-cell-highlight {
      animation: sc-highlight-pulse 0.8s ease infinite;
    }
    .sc-cell-target-glow {
      animation: sc-target-glow 0.8s ease infinite;
    }
    .sc-flash-overlay {
      position: absolute;
      inset: -12px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255,255,255,0.95) 0%, rgba(255,255,180,0.4) 50%, transparent 70%);
      animation: sc-flash 200ms ease-out forwards;
      z-index: 15;
      pointer-events: none;
    }
    .sc-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      grid-template-rows: repeat(5, 1fr);
      gap: 4px;
      border-radius: 12px;
      padding: 4px;
      background: #1e293b;
      aspect-ratio: 1;
      max-width: 400px;
      margin: 0 auto;
    }
    .sc-cell {
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      cursor: pointer;
      transition: transform 0.1s ease;
      overflow: visible;
      -webkit-tap-highlight-color: transparent;
    }
    .sc-cell:active {
      transform: scale(0.95);
    }
    .sc-cell-empty {
      background: rgba(255,255,255,0.05);
      cursor: default;
      overflow: hidden;
    }
    .sc-cell-empty:active {
      transform: none;
    }
    .sc-evo-strip {
      display: flex;
      gap: 6px;
      overflow-x: auto;
      padding-bottom: 4px;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
    }
    .sc-evo-strip::-webkit-scrollbar { height: 4px; }
    .sc-evo-strip::-webkit-scrollbar-thumb { background: #555; border-radius: 2px; }
    .sc-overlay {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.92);
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 10;
      animation: sc-pop 0.3s ease;
    }
    .sc-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.7);
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
      animation: sc-backdrop-in 0.2s ease;
    }
    .sc-modal {
      background: #1e293b;
      border-radius: 16px;
      padding: 20px;
      max-width: 380px;
      width: 100%;
      max-height: 80vh;
      overflow-y: auto;
      animation: sc-modal-in 0.3s ease;
      color: #e2e8f0;
    }
    .sc-modal::-webkit-scrollbar { width: 4px; }
    .sc-modal::-webkit-scrollbar-thumb { background: #475569; border-radius: 2px; }
  `,document.head.appendChild(t)}function yt(){const[t,n]=i.useState(()=>Te()),[o,s]=i.useState(0),[r,u]=i.useState(2),[f,w]=i.useState(!1),[E,v]=i.useState(null),[$,j]=i.useState(new Set),[g,F]=i.useState(!1),T=i.useRef(mt()),[_,Pe]=i.useState(T.current.bestScore),[H,Ve]=i.useState(T.current.maxLevel),[B,Y]=i.useState("idle"),[W,oe]=i.useState(null),[je,re]=i.useState(null),[Xe,se]=i.useState(null),[Ke,ae]=i.useState(null),[Ze,ce]=i.useState(new Map),de=i.useRef(null),M=i.useRef([]),fe=i.useRef({w:0,h:0,gap:4}),xe=i.useRef(null),[N,Je]=i.useState(!1),U=i.useRef(!1),b=i.useRef(null),O=i.useRef(!1),[Ue,Se]=i.useState(!1),[we,q]=i.useState(null),L=B!=="idle";i.useEffect(()=>{bt()},[]),i.useEffect(()=>{U.current=N},[N]),i.useEffect(()=>{const a=new Audio("/audio/puzzle/bgm.mp3");return a.loop=!0,a.volume=.25,b.current=a,()=>{a.pause(),a.src=""}},[]),i.useEffect(()=>{b.current&&(N?b.current.pause():O.current&&!g&&b.current.play().catch(()=>{}))},[N,g]),i.useEffect(()=>{g&&b.current&&b.current.pause()},[g]);const ke=i.useCallback(()=>{O.current||U.current||!b.current||b.current.play().then(()=>{O.current=!0}).catch(()=>{})},[]);i.useEffect(()=>{gt(_,H)},[_,H]);const A=i.useCallback(a=>{if(!U.current)try{const c=new Audio(a);c.volume=.5,c.play().catch(()=>{})}catch{}},[]),R=i.useMemo(()=>ht(t),[t]),G=i.useMemo(()=>dt(R),[R]),P=i.useMemo(()=>ut(t),[t]);i.useEffect(()=>{R>_&&Pe(R)},[R,_]),i.useEffect(()=>{P.level>H&&Ve(P.level)},[P,H]);const Ce=i.useCallback(()=>{if(!xe.current)return;const a=xe.current.getBoundingClientRect(),c=4,l=4,x=(a.width-c*2-l*(d-1))/d;fe.current={w:x,h:x,gap:l}},[]);i.useEffect(()=>()=>{M.current.forEach(clearTimeout)},[]);const qe=i.useCallback(()=>{Je(a=>{const c=!a;return b.current&&(c?b.current.pause():O.current&&b.current.play().catch(()=>{})),c})},[]),Qe=i.useCallback((a,c)=>{if(g||L||!t[a][c])return;if(ke(),f){const p=le(t);p[a][c]=null;const x=xt(p),k=Be(x,o);n(k),u(y=>y-1),w(!1),v(null),j(new Set),A("/audio/warashibe/se_click.mp3"),setTimeout(()=>{Me(k,r-1)&&(F(!0),A("/audio/puzzle/gameover.mp3"))},200);return}if(E&&E.r===a&&E.c===c&&$.size>=2){et(a,c,$);return}const l=ft(t,a,c);l.size>=2?(v({r:a,c}),j(l),A("/audio/warashibe/se_click.mp3")):(v(null),j(new Set))},[t,g,L,f,E,$,o,r,A,ke]),et=i.useCallback((a,c,l)=>{M.current.forEach(clearTimeout),M.current=[],Ce();let p=0;for(const h of l){const[m,S]=h.split(",").map(Number);t[m][S]&&(p+=t[m][S].level)}const x=l.size,k=t[a][c].colorId;let y,D;k==="world"?(y=x+9,D="world"):p>=10?(y=10,D="world"):(y=Math.min(p,9),D=k);const V=le(t);for(const h of l){const[m,S]=h.split(",").map(Number);V[m][S]=null}V[a][c]={id:ye++,colorId:D,level:y};const{grid:C,movements:X}=pt(V),I=o+1,z=new Set;for(let h=0;h<d;h++)for(let m=0;m<d;m++)C[h][m]||z.add(`${h},${m}`);const K=Be(C,I);let Q=r;I>0&&I%100===0&&(Q+=1);const We=fe.current,Ae=We.w+We.gap,ee=new Map;let te=0;for(let h=0;h<d;h++){let m=0;for(let S=0;S<d;S++){const Z=`${S},${h}`,J=X.get(Z);if(J){const $e=m*30;ee.set(Z,{type:"gravity",fallY:`${-J*Ae}px`,delay:$e}),te=Math.max(te,$e+200),m++}}}let pe=0;for(let h=0;h<d;h++){let m=0;for(let S=0;S<d;S++){const Z=`${S},${h}`;if(z.has(Z)){const J=te+m*40;ee.set(Z,{type:"fill",fillY:`${-2*Ae}px`,delay:J}),pe=Math.max(pe,J+300),m++}}}const Re=Math.max(te,pe,300);de.current={afterFill:K,newTurn:I,newMechanicPts:Q,newLevel:y,anims:ee,totalDropDuration:Re,targetKey:`${a},${c}`},A("/audio/warashibe/se_craft.mp3"),oe({r:a,c}),re(l),v(null),j(new Set),Y("sliding");const it=setTimeout(()=>{Y("flash"),se(`${a},${c}`),y>=5&&A("/audio/warashibe/se_unlock.mp3")},300),ot=setTimeout(()=>{se(null),oe(null),re(null),n(K),s(I),u(Q),ae(`${a},${c}`),ce(ee),Y("result")},500),he=500+Re+50,rt=setTimeout(()=>{ae(null),ce(new Map),Y("idle"),de.current=null,Me(K,Q)&&(F(!0),A("/audio/puzzle/gameover.mp3"))},he);let Ie=null,Le=null;I===30&&(Ie=setTimeout(()=>{q("in"),A("/audio/warashibe/se_unlock.mp3")},he+100),Le=setTimeout(()=>{q("out"),setTimeout(()=>q(null),300)},he+1100)),M.current=[it,ot,rt,Ie,Le].filter(Boolean)},[t,o,r,A,Ce]),ze=i.useCallback(()=>{M.current.forEach(clearTimeout),M.current=[],ye=1,n(Te()),s(0),u(2),w(!1),v(null),j(new Set),F(!1),Y("idle"),oe(null),re(null),se(null),ae(null),ce(new Map),q(null),de.current=null,b.current&&!U.current&&(b.current.currentTime=0,b.current.play().then(()=>{O.current=!0}).catch(()=>{}))},[]),Ee=i.useMemo(()=>`🏍 MotoHubのバイクガレージパズルで総額${R}万円のガレージを達成！ランク: ${G.rank} #MotoHub #バイク好きと繋がりたい motohub.jp/games/subaracity`,[R,G]),tt=i.useMemo(()=>`https://twitter.com/intent/tweet?text=${encodeURIComponent(Ee)}`,[Ee]),nt=ue(P.colorId,P.level),lt=i.useCallback((a,c)=>{if(B!=="sliding"&&B!=="flash"||!W||!je?.has(`${a},${c}`)||a===W.r&&c===W.c)return null;const l=fe.current,p=(W.c-c)*(l.w+l.gap),x=(W.r-a)*(l.h+l.gap);return{"--sc-dx":`${p}px`,"--sc-dy":`${x}px`}},[B,W,je]);return e.jsxs("div",{style:{minHeight:"calc(100vh - 64px)",backgroundColor:"#0f172a",padding:"12px 8px 24px",fontFamily:"'Noto Sans JP', sans-serif",color:"#e2e8f0"},children:[e.jsxs("div",{style:{maxWidth:"420px",margin:"0 auto"},children:[e.jsxs("div",{style:{display:"flex",alignItems:"center",justifyContent:"space-between",marginBottom:"10px"},children:[e.jsxs("div",{style:{display:"flex",alignItems:"center",gap:"8px"},children:[e.jsx("h1",{style:{fontSize:"clamp(18px, 5vw, 24px)",fontWeight:"900",color:"#f1f5f9",margin:0},children:"バイクガレージ"}),e.jsx("button",{onClick:qe,"aria-label":N?"音声ON":"音声OFF",style:{background:"none",border:"none",fontSize:"18px",cursor:"pointer",padding:"2px",lineHeight:1,color:"#94a3b8",WebkitTapHighlightColor:"transparent"},children:N?"🔇":"🔊"})]}),e.jsxs("div",{style:{display:"flex",gap:"6px",alignItems:"center"},children:[e.jsx(Ge,{label:"\\u30BF\\u30FC\\u30F3",value:`${o}年目`}),e.jsx(Ge,{label:"\\u{1F527}",value:`×${r}`})]})]}),e.jsxs("div",{style:{display:"flex",gap:"8px",marginBottom:"10px"},children:[e.jsx("button",{onClick:ze,style:ge,children:"リセット"}),e.jsx("button",{onClick:()=>{r>0&&!L&&(w(a=>!a),v(null),j(new Set))},disabled:r<=0||L,style:{...ge,backgroundColor:f?"#f59e0b":"#475569",opacity:r<=0||L?.4:1,cursor:r<=0||L?"default":"pointer"},children:f?"メカニック中…":"メカニック🔧"})]}),f&&e.jsx("div",{style:{textAlign:"center",padding:"6px",marginBottom:"8px",backgroundColor:"#f59e0b",borderRadius:"8px",color:"#000",fontSize:"13px",fontWeight:"bold"},children:"消去するブロックをタップしてください"}),e.jsxs("div",{style:{position:"relative"},children:[e.jsx("div",{className:"sc-grid",ref:xe,style:{opacity:g?.3:1,transition:"opacity 0.5s"},children:t.map((a,c)=>a.map((l,p)=>{const x=`${c},${p}`;if(!l)return e.jsx("div",{className:"sc-cell sc-cell-empty"},x);const k=ct(l.colorId),y=ue(l.colorId,l.level),D=$.has(x),V=W&&W.r===c&&W.c===p;let C="",X={};const I=lt(c,p),z=Ze.get(x);return I?C="sc-cell-sliding":V&&(B==="sliding"||B==="flash")?C="sc-cell-target-glow":Ke===x?C="sc-cell-pop":z?z.type==="gravity"?(C="sc-cell-gravity",X={"--sc-fall-y":z.fallY,"--sc-fall-delay":`${z.delay}ms`}):z.type==="fill"&&(C="sc-cell-fill",X={"--sc-fill-y":z.fillY,"--sc-fill-delay":`${z.delay}ms`}):D&&(C="sc-cell-highlight"),e.jsxs("div",{className:`sc-cell ${C}`,style:{backgroundColor:l.level>=11?"#78350f":k?k.bg:"#475569",border:l.level>=11?"2px solid #fbbf24":l.colorId==="world"?"2px solid #94a3b8":"none",cursor:L?"default":f?"crosshair":l.level>=11?"not-allowed":"pointer",opacity:l.level>=11?.9:1,...I||{},...X},onClick:()=>Qe(c,p),children:[e.jsx("img",{src:at(l.colorId,l.level),alt:y,draggable:!1,style:{width:"clamp(28px, 8vw, 48px)",height:"clamp(22px, 6vw, 36px)",objectFit:"contain",pointerEvents:"none"},onError:K=>{K.target.style.display="none"}}),e.jsxs("span",{style:{position:"absolute",top:"2px",right:"3px",fontSize:"8px",fontWeight:"bold",color:l.level>=11?"#fbbf24":l.colorId==="world"?"#1e293b":"#fff",backgroundColor:l.level>=11?"rgba(0,0,0,0.6)":l.colorId==="world"?"rgba(255,255,255,0.8)":"rgba(0,0,0,0.4)",borderRadius:"3px",padding:"0 3px",lineHeight:"14px"},children:["Lv",l.level]}),e.jsx("span",{style:{fontSize:"clamp(6px, 1.6vw, 9px)",fontWeight:"bold",color:l.level>=11?"#fbbf24":l.colorId==="world"?"#1e293b":"#fff",lineHeight:1,textAlign:"center",textShadow:l.level>=11?"0 1px 2px rgba(0,0,0,0.8)":l.colorId==="world"?"none":"0 1px 2px rgba(0,0,0,0.5)",wordBreak:"keep-all"},children:y}),Xe===x&&e.jsx("div",{className:"sc-flash-overlay"})]},x)}))}),g&&e.jsxs("div",{className:"sc-overlay",children:[e.jsx("p",{style:{fontSize:"28px",fontWeight:"900",color:"#f1f5f9",margin:"0 0 8px"},children:"GAME OVER"}),e.jsxs("p",{style:{fontSize:"16px",color:"#94a3b8",margin:"0 0 4px"},children:["ガレージ総額: ",e.jsxs("span",{style:{color:"#fbbf24",fontWeight:"bold"},children:[R.toLocaleString(),"万円"]})]}),e.jsxs("p",{style:{fontSize:"14px",color:"#94a3b8",margin:"0 0 4px"},children:["ランク: ",e.jsx("span",{style:{fontWeight:"bold",color:"#f1f5f9"},children:G.rank})," — ",G.label]}),e.jsxs("p",{style:{fontSize:"13px",color:"#64748b",margin:"0 0 16px"},children:["最高レベル: ",nt]}),e.jsxs("div",{style:{display:"flex",gap:"8px",flexWrap:"wrap",justifyContent:"center"},children:[e.jsx("button",{onClick:ze,style:ve,children:"もう一度遊ぶ"}),e.jsx("a",{href:tt,target:"_blank",rel:"noopener noreferrer",style:{...ve,backgroundColor:"#1d9bf0",textDecoration:"none",display:"inline-flex",alignItems:"center",gap:"4px"},children:"Xでシェア"})]})]})]}),we&&e.jsx("div",{className:"sc-announce-overlay",children:e.jsxs("div",{className:`sc-announce-box ${we==="out"?"leaving":""}`,style:{background:"linear-gradient(135deg, #EAB308 0%, #CA8A04 100%)",border:"3px solid #FDE047",boxShadow:"0 0 40px rgba(234,179,8,0.5)"},children:[e.jsx("div",{style:{fontSize:"32px",marginBottom:"4px"},children:"🏍"}),e.jsx("div",{style:{fontSize:"22px",fontWeight:"900",color:"#fff",textShadow:"0 2px 4px rgba(0,0,0,0.3)"},children:"スズキ参戦！"}),e.jsx("div",{style:{fontSize:"13px",color:"#FEFCE8",marginTop:"4px",fontWeight:"bold"},children:"4色目の黄色ブロックが出現！"})]})}),e.jsxs("div",{style:{marginTop:"12px",backgroundColor:"#1e293b",borderRadius:"12px",padding:"10px 14px",display:"flex",justifyContent:"space-between",alignItems:"center"},children:[e.jsxs("div",{children:[e.jsx("span",{style:{fontSize:"11px",color:"#64748b"},children:"ガレージ総額"}),e.jsxs("p",{style:{fontSize:"clamp(18px, 5vw, 24px)",fontWeight:"900",color:"#fbbf24",margin:"2px 0 0"},children:[R.toLocaleString(),e.jsx("span",{style:{fontSize:"12px",color:"#94a3b8"},children:"万円"})]})]}),e.jsxs("div",{style:{textAlign:"center"},children:[e.jsx("span",{style:{fontSize:"11px",color:"#64748b"},children:"ランク"}),e.jsx("p",{style:{fontSize:"20px",fontWeight:"900",color:"#f1f5f9",margin:"2px 0 0"},children:G.rank}),e.jsx("span",{style:{fontSize:"10px",color:"#94a3b8"},children:G.label})]}),e.jsxs("div",{style:{textAlign:"right"},children:[e.jsx("span",{style:{fontSize:"11px",color:"#64748b"},children:"BEST"}),e.jsxs("p",{style:{fontSize:"16px",fontWeight:"900",color:"#94a3b8",margin:"2px 0 0"},children:[_.toLocaleString(),e.jsx("span",{style:{fontSize:"10px"},children:"万円"})]})]})]}),e.jsxs("div",{style:{marginTop:"12px",backgroundColor:"#1e293b",borderRadius:"12px",padding:"10px 12px"},children:[e.jsx("p",{style:{fontSize:"11px",fontWeight:"bold",color:"#64748b",margin:"0 0 6px"},children:"レベル図鑑"}),e.jsx("div",{className:"sc-evo-strip",children:Array.from({length:12},(a,c)=>{const l=c+1,p=l<=H,x=l>=10?be[l]:ue("honda",l),k=l>=10?`/images/subaracity/world_${l}.png`:`/images/subaracity/honda_${l}.png`;return e.jsxs("div",{style:{flexShrink:0,width:"56px",height:"66px",borderRadius:"8px",display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",backgroundColor:"#334155",opacity:p?1:.3,filter:p?"none":"grayscale(1)",transition:"opacity 0.3s, filter 0.3s",padding:"3px 2px 2px",gap:"2px"},children:[e.jsx("img",{src:k,alt:x,draggable:!1,style:{width:"36px",height:"28px",objectFit:"contain"},onError:y=>{y.target.style.display="none"}}),e.jsx("span",{style:{fontSize:"7px",fontWeight:"bold",color:"#e2e8f0",textAlign:"center",lineHeight:1.1},children:x}),e.jsxs("span",{style:{fontSize:"7px",color:"#94a3b8"},children:["Lv",l," / ",_e(l),"万円"]})]},l)})})]}),g&&e.jsxs("div",{style:{marginTop:"12px",backgroundColor:"#1e293b",borderRadius:"12px",padding:"10px 14px"},children:[e.jsx("p",{style:{fontSize:"12px",fontWeight:"bold",color:"#64748b",margin:"0 0 8px"},children:"登場バイクの実際の相場を見る"}),e.jsx("div",{style:{display:"flex",flexWrap:"wrap",gap:"6px"},children:ie.filter(a=>me[a.id]).map(a=>me[a.id].slice(0,5).map((c,l)=>e.jsx("a",{href:`/bikes/search?q=${encodeURIComponent(c)}`,style:{fontSize:"11px",color:"#60a5fa",textDecoration:"none",padding:"4px 8px",backgroundColor:"#0f172a",borderRadius:"6px"},children:c},`${a.id}-${l}`)))})]}),e.jsx("div",{style:{textAlign:"center",marginTop:"14px"},children:e.jsx("button",{onClick:()=>Se(!0),style:{...ge,backgroundColor:"#334155",fontSize:"14px",padding:"10px 24px",borderRadius:"8px"},children:"遊び方"})}),e.jsx("p",{style:{textAlign:"center",fontSize:"11px",color:"#475569",marginTop:"8px"},children:"同じメーカー色の隣接ブロックをタップして合体！"})]}),Ue&&e.jsx(vt,{onClose:()=>Se(!1)})]})}function vt({onClose:t}){return e.jsx("div",{className:"sc-modal-backdrop",onClick:n=>{n.target===n.currentTarget&&t()},children:e.jsxs("div",{className:"sc-modal",children:[e.jsxs("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:"16px"},children:[e.jsx("h2",{style:{fontSize:"18px",fontWeight:"900",margin:0,color:"#f1f5f9"},children:"遊び方"}),e.jsx("button",{onClick:t,style:{background:"none",border:"none",color:"#94a3b8",fontSize:"22px",cursor:"pointer",padding:"0 4px",lineHeight:1},children:"×"})]}),e.jsx("div",{style:{marginBottom:"16px",backgroundColor:"#0f172a",borderRadius:"12px",padding:"12px"},children:e.jsxs("svg",{viewBox:"0 0 290 110",xmlns:"http://www.w3.org/2000/svg",style:{width:"100%",height:"auto"},children:[e.jsx("text",{x:"52",y:"12",textAnchor:"middle",fill:"#94a3b8",fontSize:"9",fontWeight:"bold",children:"1. タップで選択"}),e.jsx("rect",{x:"5",y:"18",width:"95",height:"88",rx:"8",fill:"#1e293b"}),e.jsx("rect",{x:"10",y:"22",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"23",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"39",y:"22",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"52",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"68",y:"22",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"81",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"10",y:"51",width:"26",height:"26",rx:"5",fill:"#EF4444",stroke:"#fff",strokeWidth:"2"}),e.jsx("text",{x:"23",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"39",y:"51",width:"26",height:"26",rx:"5",fill:"#EF4444",stroke:"#fff",strokeWidth:"2"}),e.jsx("text",{x:"52",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"68",y:"51",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"81",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"10",y:"80",width:"26",height:"26",rx:"5",fill:"#EF4444",stroke:"#fff",strokeWidth:"2"}),e.jsx("text",{x:"23",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"39",y:"80",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"52",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"68",y:"80",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"81",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("text",{x:"125",y:"68",textAnchor:"middle",fill:"#fbbf24",fontSize:"22",fontWeight:"bold",children:"→"}),e.jsx("text",{x:"125",y:"82",textAnchor:"middle",fill:"#64748b",fontSize:"7",children:"合体！"}),e.jsx("text",{x:"220",y:"12",textAnchor:"middle",fill:"#94a3b8",fontSize:"9",fontWeight:"bold",children:"2. レベルアップ！"}),e.jsx("rect",{x:"175",y:"18",width:"95",height:"88",rx:"8",fill:"#1e293b"}),e.jsx("rect",{x:"180",y:"22",width:"26",height:"26",rx:"5",fill:"#22C55E",opacity:"0.6"}),e.jsx("text",{x:"193",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"5",children:"NEW"}),e.jsx("rect",{x:"209",y:"22",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"222",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"238",y:"22",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"251",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"180",y:"51",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"193",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"209",y:"51",width:"26",height:"26",rx:"5",fill:"#EF4444"}),e.jsx("text",{x:"222",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv2"}),e.jsx("rect",{x:"238",y:"51",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"251",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"180",y:"80",width:"26",height:"26",rx:"5",fill:"#3B82F6",opacity:"0.6"}),e.jsx("text",{x:"193",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"5",children:"NEW"}),e.jsx("rect",{x:"209",y:"80",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"222",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"238",y:"80",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"251",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"207",y:"49",width:"30",height:"30",rx:"6",fill:"none",stroke:"#fbbf24",strokeWidth:"2",strokeDasharray:"3,2"})]})}),e.jsxs("div",{style:{fontSize:"13px",lineHeight:2,color:"#cbd5e1"},children:[e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"1."}),"同じ色（メーカー）のバイクをタップして選択、もう一度タップで合体！"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"2."}),"合体するとレベルアップして上位バイクに変化"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"3."}),"Lv10以上は全メーカー共通の世界的名車に進化！"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"4."}),e.jsxs("span",{children:["メカニックポイント（",e.jsx("span",{style:{fontSize:"15px"},children:"🔧"}),"）で邪魔なブロックを1つ消せる"]})]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"5."}),"100ターンごとにメカニックポイント+1"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"6."}),"ガレージ総額を増やして「スバラシガレージ」ランクを目指そう！"]})]}),e.jsxs("div",{style:{marginTop:"14px",padding:"10px",backgroundColor:"#0f172a",borderRadius:"10px",fontSize:"12px"},children:[e.jsx("p",{style:{fontWeight:"bold",color:"#64748b",margin:"0 0 6px"},children:"メーカーカラー"}),e.jsx("div",{style:{display:"flex",gap:"8px",flexWrap:"wrap"},children:ie.map(n=>e.jsxs("div",{style:{display:"flex",alignItems:"center",gap:"4px"},children:[e.jsx("span",{style:{width:"14px",height:"14px",borderRadius:"4px",backgroundColor:n.bg,display:"inline-block",border:n.id==="world"?"1px solid #94a3b8":"none"}}),e.jsx("span",{style:{color:"#94a3b8"},children:n.label})]},n.id))}),e.jsx("p",{style:{color:"#64748b",fontSize:"11px",margin:"6px 0 0"},children:"※ 30ターン目からスズキ（黄��も出現！"}),e.jsxs("p",{style:{color:"#64748b",fontSize:"11px",margin:"4px 0 0"},children:["※ 合体レベル = ブロックのレベル合計（例: Lv3+Lv2=Lv5）",`
`,"※ 合計10以上 → 海外バイク（白Lv10）に進化！",`
`,"※ 白Lv10同士の合体 → 金ブロック（Lv11+）に！",`
`,"※ 金ブロックは合体不可（メカニックでのみ消去可能）"]})]}),e.jsx("button",{onClick:t,style:{...ve,width:"100%",marginTop:"16px",backgroundColor:"#3b82f6"},children:"閉じる"})]})})}function Ge({label:t,value:n}){return e.jsxs("div",{style:{backgroundColor:"#1e293b",borderRadius:"6px",padding:"3px 10px",textAlign:"center",minWidth:"50px"},children:[e.jsx("div",{style:{fontSize:"9px",color:"#64748b",fontWeight:"bold"},children:t}),e.jsx("div",{style:{fontSize:"clamp(12px, 3vw, 15px)",fontWeight:"900",color:"#f1f5f9"},children:n})]})}const ge={padding:"8px 14px",backgroundColor:"#475569",color:"#e2e8f0",border:"none",borderRadius:"6px",fontWeight:"bold",fontSize:"13px",cursor:"pointer",WebkitTapHighlightColor:"transparent"},ve={padding:"10px 20px",backgroundColor:"#475569",color:"#fff",border:"none",borderRadius:"8px",fontWeight:"bold",fontSize:"14px",cursor:"pointer",WebkitTapHighlightColor:"transparent"},De=document.getElementById("subaracity-root");De&&st.createRoot(De).render(e.jsx(yt,{}));
