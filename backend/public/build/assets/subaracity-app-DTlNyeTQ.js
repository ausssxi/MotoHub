import{r as l,j as e,c as rt}from"./client-Ccx6zi3W.js";const Ne={honda:["スーパーカブ50","モンキー125","CT125","PCX","レブル250","GB350","CB400SF","CBR600RR","CB1300SF"],yamaha:["JOG","セロー250","SR400","YZF-R3","MT-07","XSR700","MT-09","YZF-R6","VMAX"],kawasaki:["KLX230","エストレヤ","Ninja250","Ninja400","W800","Z900RS","Ninja650","ZX-10R","H2"],suzuki:["アドレス","ST250","GSX250R","SV650","V-Strom","GSX-S750","SV1000","ハヤブサ","GSX-R1000R"]},ge={10:"R1250GS",11:"Panigale V4",12:"ゴールドウイング"},$e=[10,20,35,55,80,100,170,200,300,500,750,1e3];function he(t,n){if(n>=10)return ge[n]||ge[12];const i=Ne[t];return i?i[n-1]||`Lv${n}`:`Lv${n}`}function Ge(t){return t<=$e.length?$e[t-1]:1e3+(t-12)*500}function ot(t,n){return n>=13?"/images/subaracity/world_12.png":n>=10?`/images/subaracity/world_${n}.png`:`/images/subaracity/${t}_${n}.png`}const ne=[{id:"honda",label:"ホンダ",bg:"#EF4444",light:"#FEE2E2"},{id:"yamaha",label:"ヤマハ",bg:"#3B82F6",light:"#DBEAFE"},{id:"kawasaki",label:"カワサキ",bg:"#22C55E",light:"#DCFCE7"},{id:"suzuki",label:"スズキ",bg:"#EAB308",light:"#FEF9C3"}];function st(t){return ne.find(n=>n.id===t)}const ee=[{min:0,rank:"F",label:"免許取りたて"},{min:100,rank:"E",label:"週末ライダー"},{min:500,rank:"D",label:"ツーリングマスター"},{min:1e3,rank:"C",label:"ガレージオーナー"},{min:3e3,rank:"B",label:"バイクコレクター"},{min:5e3,rank:"A",label:"伝説のライダー"},{min:1e4,rank:"S",label:"スバラシガレージ"}];function at(t){for(let n=ee.length-1;n>=0;n--)if(t>=ee[n].min)return ee[n];return ee[0]}let me=1;const d=5;function De(t){const n=t<30?3:4;return ne[Math.floor(Math.random()*n)].id}function _e(t){return{id:me++,colorId:t,level:1}}function Ie(){const t=[];for(let n=0;n<d;n++){const i=[];for(let o=0;o<d;o++)i.push(_e(De(0)));t.push(i)}return t}function te(t){return t.map(n=>n.map(i=>i?{...i}:null))}function ct(t,n,i){const o=t[n][i];if(!o)return new Set;const r=o.colorId,u=new Set,f=[[n,i]];for(u.add(`${n},${i}`);f.length>0;){const[w,z]=f.shift();for(const[k,I]of[[0,1],[0,-1],[1,0],[-1,0]]){const j=w+k,g=z+I,L=`${j},${g}`;if(j>=0&&j<d&&g>=0&&g<d&&!u.has(L)){const D=t[j][g];D&&D.colorId===r&&(u.add(L),f.push([j,g]))}}}return u}function dt(t){const n=te(t);for(let i=0;i<d;i++){const o=[];for(let r=d-1;r>=0;r--)n[r][i]&&o.push(n[r][i]);for(let r=d-1;r>=0;r--)n[r][i]=o[d-1-r]||null}return n}function ft(t){const n=Array.from({length:d},()=>Array(d).fill(null)),i=new Map;for(let o=0;o<d;o++){const r=[];for(let f=0;f<d;f++)t[f][o]&&r.push({cell:t[f][o],origRow:f});let u=d-1;for(let f=r.length-1;f>=0;f--){n[u][o]=r[f].cell;const w=u-r[f].origRow;w>0&&i.set(`${u},${o}`,w),u--}}return{grid:n,movements:i}}function Le(t,n){const i=te(t);for(let o=0;o<d;o++)for(let r=0;r<d;r++)i[o][r]||(i[o][r]=_e(De(n)));return i}function Te(t,n){for(let i=0;i<d;i++)for(let o=0;o<d;o++)if(!t[i][o])return!1;if(n>0)return!1;for(let i=0;i<d;i++)for(let o=0;o<d;o++){const r=t[i][o];if(r)for(const[u,f]of[[0,1],[1,0]]){const w=i+u,z=o+f;if(w<d&&z<d){const k=t[w][z];if(k&&k.colorId===r.colorId)return!1}}}return!0}function xt(t){let n=0;for(let i=0;i<d;i++)for(let o=0;o<d;o++){const r=t[i][o];r&&(n+=Ge(r.level))}return n}function pt(t){let n=1,i="honda";for(let o=0;o<d;o++)for(let r=0;r<d;r++)t[o][r]&&t[o][r].level>n&&(n=t[o][r].level,i=t[o][r].colorId);return{level:n,colorId:i}}const He="motohub_subaracity";function ht(t,n){try{localStorage.setItem(He,JSON.stringify({bestScore:t,maxLevel:n}))}catch{}}function ut(){try{const t=localStorage.getItem(He);if(!t)return{bestScore:0,maxLevel:1};const n=JSON.parse(t);return{bestScore:typeof n.bestScore=="number"?n.bestScore:0,maxLevel:typeof n.maxLevel=="number"?n.maxLevel:1}}catch{return{bestScore:0,maxLevel:1}}}const Be="subaracity-styles";function gt(){if(document.getElementById(Be))return;const t=document.createElement("style");t.id=Be,t.textContent=`
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
  `,document.head.appendChild(t)}function mt(){const[t,n]=l.useState(()=>Ie()),[i,o]=l.useState(0),[r,u]=l.useState(2),[f,w]=l.useState(!1),[z,k]=l.useState(null),[I,j]=l.useState(new Set),[g,L]=l.useState(!1),D=l.useRef(ut()),[_,Ye]=l.useState(D.current.bestScore),[H,Oe]=l.useState(D.current.maxLevel),[T,Y]=l.useState("idle"),[E,le]=l.useState(null),[be,ie]=l.useState(null),[Pe,re]=l.useState(null),[Ve,oe]=l.useState(null),[Xe,se]=l.useState(new Map),ae=l.useRef(null),B=l.useRef([]),ce=l.useRef({w:0,h:0,gap:4}),de=l.useRef(null),[F,Ke]=l.useState(!1),K=l.useRef(!1),y=l.useRef(null),O=l.useRef(!1),[Ze,je]=l.useState(!1),[Se,Z]=l.useState(null),$=T!=="idle";l.useEffect(()=>{gt()},[]),l.useEffect(()=>{K.current=F},[F]),l.useEffect(()=>{const s=new Audio("/audio/puzzle/bgm.mp3");return s.loop=!0,s.volume=.25,y.current=s,()=>{s.pause(),s.src=""}},[]),l.useEffect(()=>{y.current&&(F?y.current.pause():O.current&&!g&&y.current.play().catch(()=>{}))},[F,g]),l.useEffect(()=>{g&&y.current&&y.current.pause()},[g]);const ve=l.useCallback(()=>{O.current||K.current||!y.current||y.current.play().then(()=>{O.current=!0}).catch(()=>{})},[]);l.useEffect(()=>{ht(_,H)},[_,H]);const W=l.useCallback(s=>{if(!K.current)try{const a=new Audio(s);a.volume=.5,a.play().catch(()=>{})}catch{}},[]),A=l.useMemo(()=>xt(t),[t]),M=l.useMemo(()=>at(A),[A]),P=l.useMemo(()=>pt(t),[t]);l.useEffect(()=>{A>_&&Ye(A)},[A,_]),l.useEffect(()=>{P.level>H&&Oe(P.level)},[P,H]);const we=l.useCallback(()=>{if(!de.current)return;const s=de.current.getBoundingClientRect(),a=4,c=4,x=(s.width-a*2-c*(d-1))/d;ce.current={w:x,h:x,gap:c}},[]);l.useEffect(()=>()=>{B.current.forEach(clearTimeout)},[]);const Je=l.useCallback(()=>{Ke(s=>{const a=!s;return y.current&&(a?y.current.pause():O.current&&y.current.play().catch(()=>{})),a})},[]),Ue=l.useCallback((s,a)=>{if(g||$||!t[s][a])return;if(ve(),f){const p=te(t);p[s][a]=null;const x=dt(p),R=Le(x,i);n(R),u(C=>C-1),w(!1),k(null),j(new Set),W("/audio/warashibe/se_click.mp3"),setTimeout(()=>{Te(R,r-1)&&(L(!0),W("/audio/puzzle/gameover.mp3"))},200);return}if(z&&z.r===s&&z.c===a&&I.size>=2){qe(s,a,I);return}const c=ct(t,s,a);c.size>=2?(k({r:s,c:a}),j(c),W("/audio/warashibe/se_click.mp3")):(k(null),j(new Set))},[t,g,$,f,z,I,i,r,W,ve]),qe=l.useCallback((s,a,c)=>{B.current.forEach(clearTimeout),B.current=[],we();let p=1;for(const h of c){const[m,v]=h.split(",").map(Number);t[m][v]&&(p=Math.max(p,t[m][v].level))}const x=p+1,R=t[s][a].colorId,C=te(t);for(const h of c){const[m,v]=h.split(",").map(Number);C[m][v]=null}C[s][a]={id:me++,colorId:R,level:x};const{grid:J,movements:fe}=ft(C),b=i+1,N=new Set;for(let h=0;h<d;h++)for(let m=0;m<d;m++)J[h][m]||N.add(`${h},${m}`);const G=Le(J,b);let S=r;b>0&&b%100===0&&(S+=1);const U=ce.current,ze=U.w+U.gap,q=new Map;let Q=0;for(let h=0;h<d;h++){let m=0;for(let v=0;v<d;v++){const V=`${v},${h}`,X=fe.get(V);if(X){const Re=m*30;q.set(V,{type:"gravity",fallY:`${-X*ze}px`,delay:Re}),Q=Math.max(Q,Re+200),m++}}}let xe=0;for(let h=0;h<d;h++){let m=0;for(let v=0;v<d;v++){const V=`${v},${h}`;if(N.has(V)){const X=Q+m*40;q.set(V,{type:"fill",fillY:`${-2*ze}px`,delay:X}),xe=Math.max(xe,X+300),m++}}}const Ee=Math.max(Q,xe,300);ae.current={afterFill:G,newTurn:b,newMechanicPts:S,newLevel:x,anims:q,totalDropDuration:Ee,targetKey:`${s},${a}`},W("/audio/warashibe/se_craft.mp3"),le({r:s,c:a}),ie(c),k(null),j(new Set),Y("sliding");const nt=setTimeout(()=>{Y("flash"),re(`${s},${a}`),x>=5&&W("/audio/warashibe/se_unlock.mp3")},300),lt=setTimeout(()=>{re(null),le(null),ie(null),n(G),o(b),u(S),oe(`${s},${a}`),se(q),Y("result")},500),pe=500+Ee+50,it=setTimeout(()=>{oe(null),se(new Map),Y("idle"),ae.current=null,Te(G,S)&&(L(!0),W("/audio/puzzle/gameover.mp3"))},pe);let We=null,Ae=null;b===30&&(We=setTimeout(()=>{Z("in"),W("/audio/warashibe/se_unlock.mp3")},pe+100),Ae=setTimeout(()=>{Z("out"),setTimeout(()=>Z(null),300)},pe+1100)),B.current=[nt,lt,it,We,Ae].filter(Boolean)},[t,i,r,W,we]),ke=l.useCallback(()=>{B.current.forEach(clearTimeout),B.current=[],me=1,n(Ie()),o(0),u(2),w(!1),k(null),j(new Set),L(!1),Y("idle"),le(null),ie(null),re(null),oe(null),se(new Map),Z(null),ae.current=null,y.current&&!K.current&&(y.current.currentTime=0,y.current.play().then(()=>{O.current=!0}).catch(()=>{}))},[]),Ce=l.useMemo(()=>`🏍 MotoHubのバイクガレージパズルで総額${A}万円のガレージを達成！ランク: ${M.rank} #MotoHub #バイク好きと繋がりたい motohub.jp/games/subaracity`,[A,M]),Qe=l.useMemo(()=>`https://twitter.com/intent/tweet?text=${encodeURIComponent(Ce)}`,[Ce]),et=he(P.colorId,P.level),tt=l.useCallback((s,a)=>{if(T!=="sliding"&&T!=="flash"||!E||!be?.has(`${s},${a}`)||s===E.r&&a===E.c)return null;const c=ce.current,p=(E.c-a)*(c.w+c.gap),x=(E.r-s)*(c.h+c.gap);return{"--sc-dx":`${p}px`,"--sc-dy":`${x}px`}},[T,E,be]);return e.jsxs("div",{style:{minHeight:"calc(100vh - 64px)",backgroundColor:"#0f172a",padding:"12px 8px 24px",fontFamily:"'Noto Sans JP', sans-serif",color:"#e2e8f0"},children:[e.jsxs("div",{style:{maxWidth:"420px",margin:"0 auto"},children:[e.jsxs("div",{style:{display:"flex",alignItems:"center",justifyContent:"space-between",marginBottom:"10px"},children:[e.jsxs("div",{style:{display:"flex",alignItems:"center",gap:"8px"},children:[e.jsx("h1",{style:{fontSize:"clamp(18px, 5vw, 24px)",fontWeight:"900",color:"#f1f5f9",margin:0},children:"バイクガレージ"}),e.jsx("button",{onClick:Je,"aria-label":F?"音声ON":"音声OFF",style:{background:"none",border:"none",fontSize:"18px",cursor:"pointer",padding:"2px",lineHeight:1,color:"#94a3b8",WebkitTapHighlightColor:"transparent"},children:F?"🔇":"🔊"})]}),e.jsxs("div",{style:{display:"flex",gap:"6px",alignItems:"center"},children:[e.jsx(Fe,{label:"\\u30BF\\u30FC\\u30F3",value:`${i}年目`}),e.jsx(Fe,{label:"\\u{1F527}",value:`×${r}`})]})]}),e.jsxs("div",{style:{display:"flex",gap:"8px",marginBottom:"10px"},children:[e.jsx("button",{onClick:ke,style:ue,children:"リセット"}),e.jsx("button",{onClick:()=>{r>0&&!$&&(w(s=>!s),k(null),j(new Set))},disabled:r<=0||$,style:{...ue,backgroundColor:f?"#f59e0b":"#475569",opacity:r<=0||$?.4:1,cursor:r<=0||$?"default":"pointer"},children:f?"メカニック中…":"メカニック🔧"})]}),f&&e.jsx("div",{style:{textAlign:"center",padding:"6px",marginBottom:"8px",backgroundColor:"#f59e0b",borderRadius:"8px",color:"#000",fontSize:"13px",fontWeight:"bold"},children:"消去するブロックをタップしてください"}),e.jsxs("div",{style:{position:"relative"},children:[e.jsx("div",{className:"sc-grid",ref:de,style:{opacity:g?.3:1,transition:"opacity 0.5s"},children:t.map((s,a)=>s.map((c,p)=>{const x=`${a},${p}`;if(!c)return e.jsx("div",{className:"sc-cell sc-cell-empty"},x);const R=st(c.colorId),C=he(c.colorId,c.level),J=I.has(x),fe=E&&E.r===a&&E.c===p;let b="",N={};const G=tt(a,p),S=Xe.get(x);return G?b="sc-cell-sliding":fe&&(T==="sliding"||T==="flash")?b="sc-cell-target-glow":Ve===x?b="sc-cell-pop":S?S.type==="gravity"?(b="sc-cell-gravity",N={"--sc-fall-y":S.fallY,"--sc-fall-delay":`${S.delay}ms`}):S.type==="fill"&&(b="sc-cell-fill",N={"--sc-fill-y":S.fillY,"--sc-fill-delay":`${S.delay}ms`}):J&&(b="sc-cell-highlight"),e.jsxs("div",{className:`sc-cell ${b}`,style:{backgroundColor:R?R.bg:"#475569",cursor:$?"default":f?"crosshair":"pointer",...G||{},...N},onClick:()=>Ue(a,p),children:[e.jsx("img",{src:ot(c.colorId,c.level),alt:C,draggable:!1,style:{width:"clamp(28px, 8vw, 48px)",height:"clamp(22px, 6vw, 36px)",objectFit:"contain",pointerEvents:"none"},onError:U=>{U.target.style.display="none"}}),e.jsxs("span",{style:{position:"absolute",top:"2px",right:"3px",fontSize:"8px",fontWeight:"bold",color:"#fff",backgroundColor:"rgba(0,0,0,0.4)",borderRadius:"3px",padding:"0 3px",lineHeight:"14px"},children:["Lv",c.level]}),e.jsx("span",{style:{fontSize:"clamp(6px, 1.6vw, 9px)",fontWeight:"bold",color:"#fff",lineHeight:1,textAlign:"center",textShadow:"0 1px 2px rgba(0,0,0,0.5)",wordBreak:"keep-all"},children:C}),Pe===x&&e.jsx("div",{className:"sc-flash-overlay"})]},x)}))}),g&&e.jsxs("div",{className:"sc-overlay",children:[e.jsx("p",{style:{fontSize:"28px",fontWeight:"900",color:"#f1f5f9",margin:"0 0 8px"},children:"GAME OVER"}),e.jsxs("p",{style:{fontSize:"16px",color:"#94a3b8",margin:"0 0 4px"},children:["ガレージ総額: ",e.jsxs("span",{style:{color:"#fbbf24",fontWeight:"bold"},children:[A.toLocaleString(),"万円"]})]}),e.jsxs("p",{style:{fontSize:"14px",color:"#94a3b8",margin:"0 0 4px"},children:["ランク: ",e.jsx("span",{style:{fontWeight:"bold",color:"#f1f5f9"},children:M.rank})," — ",M.label]}),e.jsxs("p",{style:{fontSize:"13px",color:"#64748b",margin:"0 0 16px"},children:["最高レベル: ",et]}),e.jsxs("div",{style:{display:"flex",gap:"8px",flexWrap:"wrap",justifyContent:"center"},children:[e.jsx("button",{onClick:ke,style:ye,children:"もう一度遊ぶ"}),e.jsx("a",{href:Qe,target:"_blank",rel:"noopener noreferrer",style:{...ye,backgroundColor:"#1d9bf0",textDecoration:"none",display:"inline-flex",alignItems:"center",gap:"4px"},children:"Xでシェア"})]})]})]}),Se&&e.jsx("div",{className:"sc-announce-overlay",children:e.jsxs("div",{className:`sc-announce-box ${Se==="out"?"leaving":""}`,style:{background:"linear-gradient(135deg, #EAB308 0%, #CA8A04 100%)",border:"3px solid #FDE047",boxShadow:"0 0 40px rgba(234,179,8,0.5)"},children:[e.jsx("div",{style:{fontSize:"32px",marginBottom:"4px"},children:"🏍"}),e.jsx("div",{style:{fontSize:"22px",fontWeight:"900",color:"#fff",textShadow:"0 2px 4px rgba(0,0,0,0.3)"},children:"スズキ参戦！"}),e.jsx("div",{style:{fontSize:"13px",color:"#FEFCE8",marginTop:"4px",fontWeight:"bold"},children:"4色目の黄色ブロックが出現！"})]})}),e.jsxs("div",{style:{marginTop:"12px",backgroundColor:"#1e293b",borderRadius:"12px",padding:"10px 14px",display:"flex",justifyContent:"space-between",alignItems:"center"},children:[e.jsxs("div",{children:[e.jsx("span",{style:{fontSize:"11px",color:"#64748b"},children:"ガレージ総額"}),e.jsxs("p",{style:{fontSize:"clamp(18px, 5vw, 24px)",fontWeight:"900",color:"#fbbf24",margin:"2px 0 0"},children:[A.toLocaleString(),e.jsx("span",{style:{fontSize:"12px",color:"#94a3b8"},children:"万円"})]})]}),e.jsxs("div",{style:{textAlign:"center"},children:[e.jsx("span",{style:{fontSize:"11px",color:"#64748b"},children:"ランク"}),e.jsx("p",{style:{fontSize:"20px",fontWeight:"900",color:"#f1f5f9",margin:"2px 0 0"},children:M.rank}),e.jsx("span",{style:{fontSize:"10px",color:"#94a3b8"},children:M.label})]}),e.jsxs("div",{style:{textAlign:"right"},children:[e.jsx("span",{style:{fontSize:"11px",color:"#64748b"},children:"BEST"}),e.jsxs("p",{style:{fontSize:"16px",fontWeight:"900",color:"#94a3b8",margin:"2px 0 0"},children:[_.toLocaleString(),e.jsx("span",{style:{fontSize:"10px"},children:"万円"})]})]})]}),e.jsxs("div",{style:{marginTop:"12px",backgroundColor:"#1e293b",borderRadius:"12px",padding:"10px 12px"},children:[e.jsx("p",{style:{fontSize:"11px",fontWeight:"bold",color:"#64748b",margin:"0 0 6px"},children:"レベル図鑑"}),e.jsx("div",{className:"sc-evo-strip",children:Array.from({length:12},(s,a)=>{const c=a+1,p=c<=H,x=c>=10?ge[c]:he("honda",c),R=c>=10?`/images/subaracity/world_${c}.png`:`/images/subaracity/honda_${c}.png`;return e.jsxs("div",{style:{flexShrink:0,width:"56px",height:"66px",borderRadius:"8px",display:"flex",flexDirection:"column",alignItems:"center",justifyContent:"center",backgroundColor:"#334155",opacity:p?1:.3,filter:p?"none":"grayscale(1)",transition:"opacity 0.3s, filter 0.3s",padding:"3px 2px 2px",gap:"2px"},children:[e.jsx("img",{src:R,alt:x,draggable:!1,style:{width:"36px",height:"28px",objectFit:"contain"},onError:C=>{C.target.style.display="none"}}),e.jsx("span",{style:{fontSize:"7px",fontWeight:"bold",color:"#e2e8f0",textAlign:"center",lineHeight:1.1},children:x}),e.jsxs("span",{style:{fontSize:"7px",color:"#94a3b8"},children:["Lv",c," / ",Ge(c),"万円"]})]},c)})})]}),g&&e.jsxs("div",{style:{marginTop:"12px",backgroundColor:"#1e293b",borderRadius:"12px",padding:"10px 14px"},children:[e.jsx("p",{style:{fontSize:"12px",fontWeight:"bold",color:"#64748b",margin:"0 0 8px"},children:"登場バイクの実際の相場を見る"}),e.jsx("div",{style:{display:"flex",flexWrap:"wrap",gap:"6px"},children:ne.map(s=>Ne[s.id].slice(0,5).map((a,c)=>e.jsx("a",{href:`/bikes/search?q=${encodeURIComponent(a)}`,style:{fontSize:"11px",color:"#60a5fa",textDecoration:"none",padding:"4px 8px",backgroundColor:"#0f172a",borderRadius:"6px"},children:a},`${s.id}-${c}`)))})]}),e.jsx("div",{style:{textAlign:"center",marginTop:"14px"},children:e.jsx("button",{onClick:()=>je(!0),style:{...ue,backgroundColor:"#334155",fontSize:"14px",padding:"10px 24px",borderRadius:"8px"},children:"遊び方"})}),e.jsx("p",{style:{textAlign:"center",fontSize:"11px",color:"#475569",marginTop:"8px"},children:"同じメーカー色の隣接ブロックをタップして合体！"})]}),Ze&&e.jsx(yt,{onClose:()=>je(!1)})]})}function yt({onClose:t}){return e.jsx("div",{className:"sc-modal-backdrop",onClick:n=>{n.target===n.currentTarget&&t()},children:e.jsxs("div",{className:"sc-modal",children:[e.jsxs("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:"16px"},children:[e.jsx("h2",{style:{fontSize:"18px",fontWeight:"900",margin:0,color:"#f1f5f9"},children:"遊び方"}),e.jsx("button",{onClick:t,style:{background:"none",border:"none",color:"#94a3b8",fontSize:"22px",cursor:"pointer",padding:"0 4px",lineHeight:1},children:"×"})]}),e.jsx("div",{style:{marginBottom:"16px",backgroundColor:"#0f172a",borderRadius:"12px",padding:"12px"},children:e.jsxs("svg",{viewBox:"0 0 290 110",xmlns:"http://www.w3.org/2000/svg",style:{width:"100%",height:"auto"},children:[e.jsx("text",{x:"52",y:"12",textAnchor:"middle",fill:"#94a3b8",fontSize:"9",fontWeight:"bold",children:"1. タップで選択"}),e.jsx("rect",{x:"5",y:"18",width:"95",height:"88",rx:"8",fill:"#1e293b"}),e.jsx("rect",{x:"10",y:"22",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"23",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"39",y:"22",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"52",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"68",y:"22",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"81",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"10",y:"51",width:"26",height:"26",rx:"5",fill:"#EF4444",stroke:"#fff",strokeWidth:"2"}),e.jsx("text",{x:"23",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"39",y:"51",width:"26",height:"26",rx:"5",fill:"#EF4444",stroke:"#fff",strokeWidth:"2"}),e.jsx("text",{x:"52",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"68",y:"51",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"81",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"10",y:"80",width:"26",height:"26",rx:"5",fill:"#EF4444",stroke:"#fff",strokeWidth:"2"}),e.jsx("text",{x:"23",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"39",y:"80",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"52",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"68",y:"80",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"81",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("text",{x:"125",y:"68",textAnchor:"middle",fill:"#fbbf24",fontSize:"22",fontWeight:"bold",children:"→"}),e.jsx("text",{x:"125",y:"82",textAnchor:"middle",fill:"#64748b",fontSize:"7",children:"合体！"}),e.jsx("text",{x:"220",y:"12",textAnchor:"middle",fill:"#94a3b8",fontSize:"9",fontWeight:"bold",children:"2. レベルアップ！"}),e.jsx("rect",{x:"175",y:"18",width:"95",height:"88",rx:"8",fill:"#1e293b"}),e.jsx("rect",{x:"180",y:"22",width:"26",height:"26",rx:"5",fill:"#22C55E",opacity:"0.6"}),e.jsx("text",{x:"193",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"5",children:"NEW"}),e.jsx("rect",{x:"209",y:"22",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"222",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"238",y:"22",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"251",y:"38",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"180",y:"51",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"193",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"209",y:"51",width:"26",height:"26",rx:"5",fill:"#EF4444"}),e.jsx("text",{x:"222",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv2"}),e.jsx("rect",{x:"238",y:"51",width:"26",height:"26",rx:"5",fill:"#3B82F6"}),e.jsx("text",{x:"251",y:"68",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"180",y:"80",width:"26",height:"26",rx:"5",fill:"#3B82F6",opacity:"0.6"}),e.jsx("text",{x:"193",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"5",children:"NEW"}),e.jsx("rect",{x:"209",y:"80",width:"26",height:"26",rx:"5",fill:"#22C55E"}),e.jsx("text",{x:"222",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"238",y:"80",width:"26",height:"26",rx:"5",fill:"#EAB308"}),e.jsx("text",{x:"251",y:"97",textAnchor:"middle",fill:"#fff",fontSize:"6",fontWeight:"bold",children:"Lv1"}),e.jsx("rect",{x:"207",y:"49",width:"30",height:"30",rx:"6",fill:"none",stroke:"#fbbf24",strokeWidth:"2",strokeDasharray:"3,2"})]})}),e.jsxs("div",{style:{fontSize:"13px",lineHeight:2,color:"#cbd5e1"},children:[e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"1."}),"同じ色（メーカー）のバイクをタップして選択、もう一度タップで合体！"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"2."}),"合体するとレベルアップして上位バイクに変化"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"3."}),"Lv10以上は全メーカー共通の世界的名車に進化！"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"4."}),e.jsxs("span",{children:["メカニックポイント（",e.jsx("span",{style:{fontSize:"15px"},children:"🔧"}),"）で邪魔なブロックを1つ消せる"]})]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"5."}),"100ターンごとにメカニックポイント+1"]}),e.jsxs("p",{style:{display:"flex",gap:"8px",alignItems:"flex-start"},children:[e.jsx("span",{style:{color:"#fbbf24",fontWeight:"bold",flexShrink:0},children:"6."}),"ガレージ総額を増やして「スバラシガレージ」ランクを目指そう！"]})]}),e.jsxs("div",{style:{marginTop:"14px",padding:"10px",backgroundColor:"#0f172a",borderRadius:"10px",fontSize:"12px"},children:[e.jsx("p",{style:{fontWeight:"bold",color:"#64748b",margin:"0 0 6px"},children:"メーカーカラー"}),e.jsx("div",{style:{display:"flex",gap:"8px",flexWrap:"wrap"},children:ne.map(n=>e.jsxs("div",{style:{display:"flex",alignItems:"center",gap:"4px"},children:[e.jsx("span",{style:{width:"14px",height:"14px",borderRadius:"4px",backgroundColor:n.bg,display:"inline-block"}}),e.jsx("span",{style:{color:"#94a3b8"},children:n.label})]},n.id))}),e.jsx("p",{style:{color:"#64748b",fontSize:"11px",margin:"6px 0 0"},children:"※ 30ターン目からスズキ（黄）も出現！"})]}),e.jsx("button",{onClick:t,style:{...ye,width:"100%",marginTop:"16px",backgroundColor:"#3b82f6"},children:"閉じる"})]})})}function Fe({label:t,value:n}){return e.jsxs("div",{style:{backgroundColor:"#1e293b",borderRadius:"6px",padding:"3px 10px",textAlign:"center",minWidth:"50px"},children:[e.jsx("div",{style:{fontSize:"9px",color:"#64748b",fontWeight:"bold"},children:t}),e.jsx("div",{style:{fontSize:"clamp(12px, 3vw, 15px)",fontWeight:"900",color:"#f1f5f9"},children:n})]})}const ue={padding:"8px 14px",backgroundColor:"#475569",color:"#e2e8f0",border:"none",borderRadius:"6px",fontWeight:"bold",fontSize:"13px",cursor:"pointer",WebkitTapHighlightColor:"transparent"},ye={padding:"10px 20px",backgroundColor:"#475569",color:"#fff",border:"none",borderRadius:"8px",fontWeight:"bold",fontSize:"14px",cursor:"pointer",WebkitTapHighlightColor:"transparent"},Me=document.getElementById("subaracity-root");Me&&rt.createRoot(Me).render(e.jsx(mt,{}));
