import React, { useReducer, useEffect, useCallback, useState, useRef } from 'react';
import { BIKES, NPCS, AREAS, PARTS, SYNTHESIS } from './data';

/* ═══════════════════════════════════════════
   Constants
   ═══════════════════════════════════════════ */
const IMG = '/images/warashibe/';
const SND = '/sounds/warashibe/';
const SAVE_KEY = 'warashibe_save';
const AUDIO_KEY = 'warashibe_audio';
const COLORS = { bg: '#1a1a2e', card: '#16213e', text: '#e0e0e0', accent: '#e94560', success: '#4caf50', gold: '#ffd700' };

/* ═══════════════════════════════════════════
   Audio Manager
   - unlock() must be called inside a user gesture (click/tap) before any audio will play
   - playBgm/playSe log to console for debugging
   ═══════════════════════════════════════════ */
class AudioMgr {
  constructor() {
    this._bgm = null;
    this._bgmKey = null;
    this._bgmPlaying = false;
    this._vol = 0.5;
    this._muted = false;
    this._unlocked = false;
    try {
      const s = JSON.parse(localStorage.getItem(AUDIO_KEY));
      if (s) { this._vol = s.vol ?? 0.5; this._muted = s.muted ?? false; }
    } catch {}
  }
  _persist() { try { localStorage.setItem(AUDIO_KEY, JSON.stringify({ vol: this._vol, muted: this._muted })); } catch {} }

  /** Must be called inside onClick/onTouchEnd — unlocks AudioContext for iOS/Android */
  unlock() {
    if (this._unlocked) return;
    this._unlocked = true;
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (Ctx) { const ctx = new Ctx(); ctx.resume().then(() => ctx.close()).catch(() => {}); }
    } catch {}
    console.log('[AudioMgr] unlocked');
  }

  playBgm(key) {
    /* Skip only if the SAME key is already actually playing */
    if (this._bgmKey === key && this._bgmPlaying) return;
    if (this._bgm) { this._bgm.pause(); this._bgm = null; }
    this._bgmKey = key;
    this._bgmPlaying = false;
    const url = `${SND}bgm_${key}.mp3`;
    console.log('[AudioMgr] playBgm:', url);
    try {
      const a = new Audio(url);
      a.loop = true;
      a.volume = this._muted ? 0 : this._vol;
      a.onerror = () => { console.warn('[AudioMgr] BGM load error:', url); this._bgm = null; this._bgmPlaying = false; };
      const p = a.play();
      if (p && p.then) {
        p.then(() => { console.log('[AudioMgr] BGM playing:', key); this._bgmPlaying = true; })
         .catch(e => { console.warn('[AudioMgr] BGM play rejected:', key, e.message); this._bgmPlaying = false; });
      }
      this._bgm = a;
    } catch (e) { console.warn('[AudioMgr] BGM exception:', e); this._bgm = null; this._bgmPlaying = false; }
  }

  stopBgm() { if (this._bgm) { this._bgm.pause(); this._bgm = null; this._bgmKey = null; this._bgmPlaying = false; } }

  playSe(key) {
    const url = `${SND}se_${key}.mp3`;
    try {
      const a = new Audio(url);
      a.volume = this._muted ? 0 : this._vol;
      a.onerror = () => console.warn('[AudioMgr] SE load error:', url);
      const p = a.play();
      if (p && p.then) {
        p.catch(e => console.warn('[AudioMgr] SE play rejected:', key, e.message));
      }
    } catch (e) { console.warn('[AudioMgr] SE exception:', e); }
  }

  setVol(v) { this._vol = v; if (this._bgm) this._bgm.volume = this._muted ? 0 : v; this._persist(); }
  toggleMute() { this._muted = !this._muted; if (this._bgm) this._bgm.volume = this._muted ? 0 : this._vol; this._persist(); return this._muted; }
  get vol() { return this._vol; }
  get muted() { return this._muted; }
}
const audio = new AudioMgr();

/* ═══════════════════════════════════════════
   Save / Load
   ═══════════════════════════════════════════ */
function saveGame(st) {
  try {
    localStorage.setItem(SAVE_KEY, JSON.stringify({
      v: 1, currentArea: st.currentArea, bikes: st.bikes, parts: st.parts,
      unlockedAreas: st.unlockedAreas, bikesEverOwned: st.bikesEverOwned, gameClear: st.gameClear,
    }));
  } catch {}
}
function loadGame() { try { const r = localStorage.getItem(SAVE_KEY); return r ? JSON.parse(r) : null; } catch { return null; } }
function deleteSave() { try { localStorage.removeItem(SAVE_KEY); } catch {} }

/* ═══════════════════════════════════════════
   Helpers
   ═══════════════════════════════════════════ */
function getVisibleNpcs(areaId, state) {
  const area = AREAS[areaId];
  if (!area) return [];
  return area.npcs.filter(id => {
    const npc = NPCS[id];
    if (npc.visibleIfBikeEverOwned) return state.bikesEverOwned.includes(npc.visibleIfBikeEverOwned);
    return true;
  });
}

function checkNewAreaUnlocks(bikes, currentUnlocked) {
  const unlocked = [...currentUnlocked];
  const msgs = [];
  const maxCc = Math.max(0, ...bikes.map(b => BIKES[b]?.ccNum || 0));
  const has = id => bikes.includes(id);
  if (!unlocked.includes('suburb') && has('ct125')) { unlocked.push('suburb'); msgs.push('新エリア「郊外」が開放された！'); }
  if (!unlocked.includes('pass') && maxCc >= 250) { unlocked.push('pass'); msgs.push('新エリア「峠」が開放された！'); }
  if (!unlocked.includes('coast') && maxCc >= 400) { unlocked.push('coast'); msgs.push('新エリア「海岸通り」が開放された！'); }
  if (!unlocked.includes('circuit') && (maxCc >= 600 || has('z900'))) { unlocked.push('circuit'); msgs.push('新エリア「サーキット」が開放された！'); }
  return { unlocked, msgs };
}

/* ═══════════════════════════════════════════
   State & Reducer
   ═══════════════════════════════════════════ */
const initialState = {
  phase: 'title', currentArea: 'street', npcIndex: 0, scene: 'explore',
  bikes: [], parts: [], selectedBike: null, unlockedAreas: ['street'],
  bikesEverOwned: [], gameClear: false,
  activeNpc: null, dialogLines: [], dialogIndex: 0, dialogPhase: 'idle', synthResult: null,
};

function reducer(state, action) {
  switch (action.type) {
    case 'START':
      return { ...state, phase: 'prologue' };

    case 'CONTINUE': {
      const s = loadGame();
      if (!s) return { ...state, phase: 'prologue' };
      return { ...initialState, phase: 'playing', scene: 'explore', currentArea: s.currentArea || 'street',
        bikes: s.bikes || [], parts: s.parts || [], unlockedAreas: s.unlockedAreas || ['street'],
        bikesEverOwned: s.bikesEverOwned || [], gameClear: s.gameClear || false };
    }

    case 'PROLOGUE_END':
      return { ...state, phase: 'playing', scene: 'explore', bikes: [], npcIndex: 0 };

    case 'NAV': {
      const vis = getVisibleNpcs(state.currentArea, state);
      if (vis.length <= 1) return state;
      return { ...state, npcIndex: (state.npcIndex + action.dir + vis.length) % vis.length };
    }

    case 'TALK': {
      const vis = getVisibleNpcs(state.currentArea, state);
      const npcId = vis[state.npcIndex]; const npc = NPCS[npcId];
      if (!npc) return state;
      if (npc.isShop) {
        if (state.bikes.includes('cub50'))
          return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: [npc.alreadyOwned], dialogIndex: 0, dialogPhase: 'shopRefuse' };
        return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: npc.greeting, dialogIndex: 0, dialogPhase: 'shopGreeting' };
      }
      if (npc.isPartsShop) {
        if (state.parts.includes(npc.wantsPart))
          return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: npc.greeting, dialogIndex: 0, dialogPhase: 'partsShopGreeting' };
        return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: [...npc.greeting, npc.noPartMessage], dialogIndex: 0, dialogPhase: 'partsShopRefuse' };
      }
      return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: npc.greeting, dialogIndex: 0, dialogPhase: 'greeting' };
    }

    case 'NEXT_LINE': {
      if (state.dialogIndex < state.dialogLines.length - 1)
        return { ...state, dialogIndex: state.dialogIndex + 1 };
      const dp = state.dialogPhase;
      if (dp === 'shopGreeting') {
        const nb = [...state.bikes, 'cub50'];
        return { ...state, bikes: nb, bikesEverOwned: [...new Set([...state.bikesEverOwned, 'cub50'])],
          dialogLines: ['スーパーカブ50 を手に入れた！'], dialogIndex: 0, dialogPhase: 'done' };
      }
      if (dp === 'shopRefuse' || dp === 'afterChat' || dp === 'partsShopRefuse')
        return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0 };
      if (dp === 'partsShopGreeting') return { ...state, dialogPhase: 'partsShopChoice' };
      if (dp === 'partsExchangeDone')
        return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0 };
      if (dp === 'greeting') return { ...state, dialogPhase: 'choice' };
      if (dp === 'correct') return { ...state, dialogPhase: 'exchangeReady' };
      if (dp === 'correctAfter' || dp === 'done' || dp === 'hiddenEvent')
        return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0 };
      if (dp === 'wrong') return { ...state, dialogPhase: 'choice' };
      return state;
    }

    case 'PICK_BIKE': return { ...state, scene: 'bikeSelect' };

    case 'SHOW': {
      const npc = NPCS[state.activeNpc]; const bid = action.bikeId;
      if (state.gameClear && state.activeNpc === 'legend' && bid === 'cub50' && npc.hiddenEvent)
        return { ...state, scene: 'dialog', selectedBike: bid, dialogLines: npc.hiddenEvent, dialogIndex: 0, dialogPhase: 'hiddenEvent' };
      const ok = Array.isArray(npc.wants) ? npc.wants.includes(bid) : bid === npc.wants;
      if (ok) {
        const cl = npc.correctMap ? npc.correctMap[bid] : npc.correct;
        return { ...state, scene: 'dialog', selectedBike: bid, dialogLines: cl, dialogIndex: 0, dialogPhase: 'correct' };
      }
      const wm = (npc.wrong && (npc.wrong[bid] || npc.wrong._default)) || 'うーん、それじゃないかな…';
      return { ...state, scene: 'dialog', selectedBike: bid, dialogLines: [wm], dialogIndex: 0, dialogPhase: 'wrong' };
    }

    case 'EXCHANGE': {
      const npc = NPCS[state.activeNpc];
      const nb = state.bikes.filter(b => b !== state.selectedBike); nb.push(npc.gives);
      const np = [...state.parts]; const ne = [...new Set([...state.bikesEverOwned, npc.gives])];
      const { unlocked, msgs } = checkNewAreaUnlocks(nb, state.unlockedAreas);
      const lines = [`${BIKES[npc.gives].name} を手に入れた！`, ...msgs, ...npc.correctAfter];
      if (npc.bonusPart) { np.push(npc.bonusPart); lines.push(`📦 ${PARTS[npc.bonusPart].name} を手に入れた！`); }
      const gc = npc.gives === 'hayabusa' ? true : state.gameClear;
      return { ...state, bikes: nb, parts: np, unlockedAreas: unlocked, bikesEverOwned: ne, gameClear: gc,
        selectedBike: null, dialogLines: lines, dialogIndex: 0,
        dialogPhase: gc && npc.gives === 'hayabusa' ? 'gameClearAfter' : 'correctAfter' };
    }

    case 'PARTS_EXCHANGE': {
      const npc = NPCS[state.activeNpc];
      const np = state.parts.filter(p => p !== npc.wantsPart); np.push(npc.givesPart);
      return { ...state, parts: np, dialogLines: [...npc.correct, `📦 ${PARTS[npc.givesPart].name} を手に入れた！`], dialogIndex: 0, dialogPhase: 'partsExchangeDone' };
    }

    case 'SYNTH': {
      const r = SYNTHESIS.find(x => x.id === action.recipeId); if (!r) return state;
      const nb = state.bikes.filter(b => b !== r.bike); nb.push(r.result);
      const np = state.parts.filter(p => p !== r.part);
      const ne = [...new Set([...state.bikesEverOwned, r.result])];
      const { unlocked } = checkNewAreaUnlocks(nb, state.unlockedAreas);
      return { ...state, bikes: nb, parts: np, unlockedAreas: unlocked, bikesEverOwned: ne, synthResult: r.result, scene: 'synthResult' };
    }

    case 'GAME_CLEAR': return { ...state, phase: 'gameClear' };
    case 'CONTINUE_AFTER_CLEAR':
      return { ...state, phase: 'playing', scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0 };
    case 'BACK_DIALOG': return { ...state, scene: 'dialog', dialogPhase: 'choice' };
    case 'LEAVE': return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0, selectedBike: null };
    case 'OPEN_MAP': return { ...state, scene: 'map' };
    case 'OPEN_INV': return { ...state, scene: 'inventory' };
    case 'OPEN_SYNTH': return { ...state, scene: 'synthesis' };
    case 'GO_AREA': return { ...state, currentArea: action.areaId, npcIndex: 0, scene: 'explore' };
    case 'CLOSE': return { ...state, scene: 'explore', synthResult: null };
    default: return state;
  }
}

/* ═══════════════════════════════════════════
   Prologue lines
   ═══════════════════════════════════════════ */
const PROLOGUE = [
  'ある日のこと——',
  '蕎麦屋のゲンさんが引退することになった。',
  '『出前用のカブ、余っちまったからよ。持ってきな。』',
  'こうして手に入れた、一台のスーパーカブ50。',
  'いつか、あの伝説のバイクに乗りたい——',
  'カブ一台から始まる、交換の旅。',
];

/* ═══════════════════════════════════════════
   CSS injection
   ═══════════════════════════════════════════ */
function injectStyles() {
  const id = 'warashibe-keyframes';
  if (document.getElementById(id)) return;
  const s = document.createElement('style');
  s.id = id;
  s.textContent = `
    @keyframes ws-fadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    @keyframes ws-popIn{0%{transform:scale(0)}60%{transform:scale(1.12)}100%{transform:scale(1)}}
    @keyframes ws-shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}
    @keyframes ws-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}
    @keyframes ws-fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    @keyframes ws-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
    @keyframes ws-slideIn{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}
    @keyframes ws-glow{0%{box-shadow:0 0 0 rgba(255,215,0,0)}50%{box-shadow:0 0 40px rgba(255,215,0,.6)}100%{box-shadow:0 0 0 rgba(255,215,0,0)}}
    input[type=range].ws-vol{-webkit-appearance:none;appearance:none;height:4px;border-radius:2px;background:rgba(255,255,255,.25);outline:none}
    input[type=range].ws-vol::-webkit-slider-thumb{-webkit-appearance:none;width:14px;height:14px;border-radius:50%;background:#e94560;cursor:pointer}
    input[type=range].ws-vol::-moz-range-thumb{width:14px;height:14px;border-radius:50%;background:#e94560;cursor:pointer;border:none}
    .ws-shell{position:relative;width:100%;height:calc(100vh - 64px);overflow:hidden;display:flex;flex-direction:column}
    @media(max-width:768px){
      .ws-shell{height:auto;min-height:calc(100vh - 120px);overflow-y:auto;-webkit-overflow-scrolling:touch}
      .ws-npc-img{max-height:25vh!important;width:auto!important}
    }
  `;
  document.head.appendChild(s);
}

/* ═══════════════════════════════════════════
   Shared styles
   ═══════════════════════════════════════════ */
const S = {
  /* theme-only — layout handled by .ws-shell CSS class */
  shell: { fontFamily: "'Noto Sans JP', sans-serif", color: COLORS.text, background: COLORS.bg },
  btn: (bg = COLORS.accent) => ({ background: bg, color: '#fff', border: 'none', borderRadius: 8, padding: '12px 24px', fontSize: 16, fontWeight: 700, cursor: 'pointer', fontFamily: "'Noto Sans JP', sans-serif", transition: 'transform .1s' }),
  card: { background: COLORS.card, borderRadius: 12, padding: 16, margin: '8px 0' },
  bgImg: { position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', zIndex: 0 },
};

/* ═══════════════════════════════════════════
   Main App
   ═══════════════════════════════════════════ */
export default function App() {
  const [state, dispatch] = useReducer(reducer, initialState);
  const [, bump] = useState(0);
  const stRef = useRef(state); stRef.current = state;

  useEffect(() => { injectStyles(); }, []);

  /* BGM — title BGM is triggered by user tap (START/CONTINUE) to satisfy autoplay policy */
  useEffect(() => {
    if (state.phase === 'prologue') audio.playBgm('title');
    else if (state.phase === 'gameClear') audio.playBgm('ending');
    else if (state.phase === 'playing') audio.playBgm(state.currentArea);
  }, [state.phase, state.currentArea]);

  /* SE on dialog phase transitions */
  const prevDp = useRef(state.dialogPhase);
  useEffect(() => {
    const prev = prevDp.current; const cur = state.dialogPhase;
    prevDp.current = cur;
    if (cur === 'wrong' && prev !== 'wrong') audio.playSe('wrong');
  }, [state.dialogPhase]);

  /* SE on area unlock */
  const prevAreas = useRef(state.unlockedAreas.length);
  useEffect(() => {
    if (state.unlockedAreas.length > prevAreas.current) audio.playSe('unlock');
    prevAreas.current = state.unlockedAreas.length;
  }, [state.unlockedAreas.length]);

  /* Auto-save */
  useEffect(() => {
    if (state.phase === 'playing' || state.phase === 'gameClear') saveGame(state);
  }, [state]);

  /* dispatch wrapper with SE */
  const d = useCallback((type, payload) => {
    audio.unlock();
    const s = stRef.current;
    const click = () => audio.playSe('click');
    if (['NAV','OPEN_MAP','OPEN_INV','OPEN_SYNTH','CLOSE','BACK_DIALOG','LEAVE','PICK_BIKE','GO_AREA','START','PROLOGUE_END','GAME_CLEAR','CONTINUE_AFTER_CLEAR','CONTINUE'].includes(type)) click();
    if (type === 'TALK') audio.playSe('talk');
    if (type === 'EXCHANGE' || type === 'PARTS_EXCHANGE') audio.playSe('correct');
    if (type === 'SYNTH') audio.playSe('craft');
    if (type === 'SHOW') {
      const npc = NPCS[s.activeNpc];
      if (npc) {
        const ok = s.gameClear && s.activeNpc === 'legend' && payload?.bikeId === 'cub50'
          ? true
          : Array.isArray(npc.wants) ? npc.wants.includes(payload?.bikeId) : payload?.bikeId === npc.wants;
        audio.playSe(ok ? 'correct' : 'wrong');
      }
    }
    if (type === 'NEXT_LINE' && s.dialogPhase === 'shopGreeting' && s.dialogIndex >= s.dialogLines.length - 1) audio.playSe('cub');
    /* Start BGM on user gesture to bypass autoplay block */
    if (type === 'START') { audio.playBgm('title'); deleteSave(); }
    if (type === 'CONTINUE') { const sv = loadGame(); audio.playBgm(sv?.currentArea || 'street'); }
    dispatch({ type, ...payload });
  }, []);

  const audioBump = useCallback(() => bump(c => c + 1), []);

  if (state.phase === 'title') return <TitleScreen d={d} hasSave={!!loadGame()} audioBump={audioBump} />;
  if (state.phase === 'prologue') return <PrologueScreen d={d} audioBump={audioBump} />;
  if (state.phase === 'gameClear') return <GameClearScreen state={state} d={d} audioBump={audioBump} />;

  const area = AREAS[state.currentArea];
  const visNpcs = getVisibleNpcs(state.currentArea, state);
  const npcId = visNpcs[state.npcIndex] || visNpcs[0];
  const npc = NPCS[npcId];

  return (
    <div style={S.shell} className="ws-shell">
      {/* BG */}
      <img src={area.bg} alt="" style={S.bgImg} />
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to bottom, rgba(26,26,46,.3) 0%, rgba(26,26,46,.85) 100%)', zIndex: 0 }} />

      {/* Content */}
      <div style={{ position: 'relative', zIndex: 1, flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
        {/* Top bar */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px 6px', flexShrink: 0, position: 'relative', zIndex: 10 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <VolCtrl audioBump={audioBump} />
            <span style={{ fontSize: 13, fontWeight: 700, opacity: .7 }}>
              {area.emoji} {area.name}
              {state.gameClear && <span style={{ marginLeft: 6, color: COLORS.gold, fontSize: 11 }}>🏆</span>}
            </span>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            <HdrBtn label={`🔧${state.parts.length > 0 ? ' ' + state.parts.length : ''}`} onClick={() => d('OPEN_SYNTH')} />
            <HdrBtn label={`📦 ${state.bikes.length}`} onClick={() => d('OPEN_INV')} />
            <HdrBtn label="🗺️" onClick={() => d('OPEN_MAP')} />
          </div>
        </div>

        {/* Scenes */}
        {state.scene === 'explore' && npc && <ExploreScene state={state} npc={npc} visNpcs={visNpcs} d={d} />}
        {state.scene === 'dialog' && <DialogScene state={state} npc={NPCS[state.activeNpc]} d={d} />}
        {state.scene === 'bikeSelect' && <BikeSelectScene state={state} d={d} />}
        {state.scene === 'inventory' && <InventoryScene state={state} d={d} />}
        {state.scene === 'map' && <MapScene state={state} d={d} />}
        {state.scene === 'synthesis' && <SynthesisScene state={state} d={d} />}
        {state.scene === 'synthResult' && <SynthResultScene state={state} d={d} />}
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Small UI pieces
   ═══════════════════════════════════════════ */
function HdrBtn({ label, onClick }) {
  return (
    <button style={{ ...S.btn(COLORS.card), padding: '5px 10px', fontSize: 12, border: '1px solid rgba(255,255,255,.15)' }} onClick={onClick}>
      {label}
    </button>
  );
}

function VolCtrl({ audioBump }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 2 }}>
      <button style={{ background: 'none', border: 'none', color: COLORS.text, fontSize: 14, cursor: 'pointer', padding: '2px 4px', lineHeight: 1 }}
        onClick={() => { audio.toggleMute(); audioBump(); }}>
        {audio.muted ? '🔇' : '🔊'}
      </button>
      <input type="range" className="ws-vol" min="0" max="100"
        value={audio.muted ? 0 : Math.round(audio.vol * 100)}
        onChange={e => { audio.setVol(parseInt(e.target.value) / 100); audioBump(); }}
        style={{ width: 44, cursor: 'pointer' }} />
    </div>
  );
}

/* ═══════════════════════════════════════════
   Title Screen  — fix: <img> object-fit cover
   ═══════════════════════════════════════════ */
function TitleScreen({ d, hasSave, audioBump }) {
  return (
    <div style={S.shell} className="ws-shell">
      <img src={`${IMG}title_screen.png`} alt="" style={S.bgImg} />
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,.45)', zIndex: 1 }} />
      <div style={{ position: 'relative', zIndex: 2, flex: 1, display: 'flex', flexDirection: 'column' }}>
        {/* Volume top-right */}
        <div style={{ display: 'flex', justifyContent: 'flex-end', padding: '10px 12px' }}>
          <VolCtrl audioBump={audioBump} />
        </div>
        {/* Center */}
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', animation: 'ws-fadeIn .8s ease-out' }}>
          <h1 style={{ fontSize: 28, fontWeight: 900, marginBottom: 6, textShadow: '0 2px 8px rgba(0,0,0,.7)', textAlign: 'center', padding: '0 16px' }}>
            バイクわらしべ長者
          </h1>
          <p style={{ fontSize: 13, marginBottom: 32, opacity: .85, textShadow: '0 1px 4px rgba(0,0,0,.6)' }}>
            カブ50から始まる、交換の旅
          </p>
          <button style={{ ...S.btn(), padding: '14px 48px', fontSize: 18, animation: 'ws-pulse 2s infinite' }}
            onPointerDown={e => (e.currentTarget.style.transform = 'scale(.95)')}
            onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
            onClick={() => d('START')}>
            はじめから
          </button>
          {hasSave && (
            <button style={{ ...S.btn(COLORS.card), padding: '12px 40px', fontSize: 16, marginTop: 12, border: '1px solid rgba(255,255,255,.25)' }}
              onPointerDown={e => (e.currentTarget.style.transform = 'scale(.95)')}
              onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
              onClick={() => d('CONTINUE')}>
              つづきから
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Prologue  — fix: smaller font, padding, break-word
   ═══════════════════════════════════════════ */
function PrologueScreen({ d, audioBump }) {
  const [vc, setVc] = useState(0);
  useEffect(() => { if (vc < PROLOGUE.length) { const t = setTimeout(() => setVc(c => c + 1), 1200); return () => clearTimeout(t); } }, [vc]);
  const done = vc >= PROLOGUE.length;

  return (
    <div style={S.shell} className="ws-shell">
      <img src={`${IMG}title_screen.png`} alt="" style={S.bgImg} />
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,.7)', zIndex: 1 }} />
      <div style={{ position: 'relative', zIndex: 2, flex: 1, display: 'flex', flexDirection: 'column' }}>
        <div style={{ display: 'flex', justifyContent: 'flex-end', padding: '10px 12px' }}>
          <VolCtrl audioBump={audioBump} />
        </div>
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 24px', overflowY: 'auto' }}>
          <div style={{ maxWidth: 440, textAlign: 'center' }}>
            {PROLOGUE.map((line, i) => (
              <p key={i} style={{
                fontSize: i === 2 ? 14 : 15, lineHeight: 2, overflowWrap: 'break-word', wordBreak: 'break-word',
                opacity: i < vc ? 1 : 0, transform: i < vc ? 'translateY(0)' : 'translateY(12px)',
                transition: 'opacity .8s ease-out, transform .8s ease-out',
                textShadow: '0 1px 6px rgba(0,0,0,.8)', fontStyle: i === 2 ? 'italic' : 'normal',
              }}>
                {line}
              </p>
            ))}
            {done && (
              <button style={{ ...S.btn(), padding: '14px 48px', fontSize: 18, marginTop: 32, animation: 'ws-fadeIn .6s ease-out' }}
                onPointerDown={e => (e.currentTarget.style.transform = 'scale(.95)')}
                onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
                onClick={() => d('PROLOGUE_END')}>
                はじめる
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Explore Scene
   ═══════════════════════════════════════════ */
function ExploreScene({ state, npc, visNpcs, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
      {/* NPC centered */}
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 16px' }}>
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', animation: 'ws-fadeIn .4s ease-out', width: '100%' }}>
          <img src={npc.image} alt={npc.name} className="ws-npc-img"
            style={{ width: 180, height: 180, objectFit: 'contain', animation: 'ws-float 3s ease-in-out infinite', filter: 'drop-shadow(0 4px 16px rgba(0,0,0,.6))' }} />
          <p style={{ fontSize: 16, fontWeight: 700, marginTop: 8, textAlign: 'center', textShadow: '0 1px 4px rgba(0,0,0,.6)' }}>{npc.name}</p>
          <p style={{ fontSize: 14, opacity: .75, marginTop: 4, fontStyle: 'italic', textAlign: 'center', lineHeight: 1.6, whiteSpace: 'pre-line', padding: '0 8px' }}>
            「{npc.idle}」
          </p>
        </div>
      </div>
      {/* Bottom nav */}
      <div style={{ padding: '12px 16px 20px', background: 'linear-gradient(to top, rgba(26,26,46,.95) 60%, rgba(26,26,46,0) 100%)', flexShrink: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, maxWidth: 480, margin: '0 auto' }}>
          {visNpcs.length > 1 && <NavBtn label="◀" onClick={() => d('NAV', { dir: -1 })} />}
          <button style={{ ...S.btn(), flex: 1, padding: '14px 0', fontSize: 16 }}
            onPointerDown={e => (e.currentTarget.style.transform = 'scale(.97)')}
            onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
            onClick={() => d('TALK')}>
            話しかける
          </button>
          {visNpcs.length > 1 && <NavBtn label="▶" onClick={() => d('NAV', { dir: 1 })} />}
        </div>
      </div>
    </div>
  );
}
function NavBtn({ label, onClick }) {
  return <button style={{ ...S.btn(COLORS.card), padding: '12px 16px', fontSize: 18, border: '1px solid rgba(255,255,255,.15)' }} onClick={onClick}>{label}</button>;
}

/* ═══════════════════════════════════════════
   Dialog Scene
   ═══════════════════════════════════════════ */
function DialogScene({ state, npc, d }) {
  const line = state.dialogLines[state.dialogIndex] || '';
  const last = state.dialogIndex >= state.dialogLines.length - 1;
  const dp = state.dialogPhase;

  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
      {npc && (
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <img src={npc.image} alt={npc.name} className="ws-npc-img"
            style={{ width: 180, height: 180, objectFit: 'contain', filter: 'drop-shadow(0 4px 16px rgba(0,0,0,.6))',
              animation: dp === 'correct' ? 'ws-popIn .4s ease-out' : dp === 'wrong' ? 'ws-shake .4s' : undefined }} />
        </div>
      )}
      {/* Dialog bottom */}
      <div style={{ padding: '0 12px 20px', background: 'linear-gradient(to top, rgba(26,26,46,.95) 60%, rgba(26,26,46,0) 100%)', flexShrink: 0 }}>
        <div style={{ maxWidth: 480, margin: '0 auto' }}>
          <div style={{ ...S.card, background: 'rgba(22,33,62,.95)', border: '1px solid rgba(255,255,255,.1)', minHeight: 90, cursor: last && !['choice','exchangeReady','partsShopChoice'].includes(dp) ? 'default' : 'pointer', animation: 'ws-fadeUp .3s ease-out', textAlign: 'center' }}
            onClick={() => { if (['choice','exchangeReady','partsShopChoice'].includes(dp)) return; d('NEXT_LINE'); }}>
            {npc && <p style={{ fontSize: 16, fontWeight: 700, color: COLORS.accent, marginBottom: 6 }}>{npc.name}</p>}
            <p style={{ fontSize: 15, lineHeight: 1.7, whiteSpace: 'pre-line' }}>{line}</p>
            {!last && <p style={{ fontSize: 12, opacity: .5, marginTop: 6 }}>▼</p>}
          </div>

          {dp === 'choice' && (
            <div style={{ display: 'flex', gap: 8, marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button style={{ ...S.btn(), flex: 1, fontSize: 15 }} onClick={() => d('PICK_BIKE')}>バイクを見せる</button>
              <button style={{ ...S.btn(COLORS.card), flex: 1, fontSize: 15, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('LEAVE')}>やめる</button>
            </div>
          )}
          {dp === 'exchangeReady' && (
            <div style={{ marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button style={{ ...S.btn(COLORS.success), width: '100%', fontSize: 15 }} onClick={() => d('EXCHANGE')}>交換する！</button>
            </div>
          )}
          {dp === 'partsShopChoice' && (
            <div style={{ display: 'flex', gap: 8, marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button style={{ ...S.btn(COLORS.success), flex: 1, fontSize: 15 }} onClick={() => d('PARTS_EXCHANGE')}>交換する</button>
              <button style={{ ...S.btn(COLORS.card), flex: 1, fontSize: 15, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('LEAVE')}>やめる</button>
            </div>
          )}
          {dp === 'gameClearAfter' && last && (
            <div style={{ marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button style={{ ...S.btn(COLORS.gold), width: '100%', fontSize: 16, color: '#1a1a2e' }} onClick={() => d('GAME_CLEAR')}>🏁 ゲームクリア！</button>
            </div>
          )}
          {(dp === 'done' || dp === 'partsExchangeDone' || dp === 'hiddenEvent' || (dp === 'correctAfter' && last)) && (
            <div style={{ marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button style={{ ...S.btn(COLORS.card), width: '100%', fontSize: 15, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('LEAVE')}>戻る</button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Bike Select Scene (scrollable)
   ═══════════════════════════════════════════ */
function BikeSelectScene({ state, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden', maxWidth: 480, margin: '0 auto', width: '100%' }}>
      <h2 style={{ fontSize: 15, fontWeight: 700, textAlign: 'center', margin: '12px 0 8px', animation: 'ws-fadeIn .3s ease-out', flexShrink: 0 }}>どのバイクを見せる？</h2>
      <div style={{ flex: 1, overflowY: 'auto', padding: '0 16px', WebkitOverflowScrolling: 'touch' }}>
        {state.bikes.map((bid, i) => {
          const b = BIKES[bid]; if (!b) return null;
          return (
            <button key={bid + '-' + i}
              style={{ ...S.card, display: 'flex', alignItems: 'center', gap: 12, cursor: 'pointer', width: '100%',
                border: b.isCustom ? `1px solid ${COLORS.gold}44` : '1px solid rgba(255,255,255,.1)',
                animation: `ws-slideIn .3s ease-out ${i * .05}s both` }}
              onClick={() => d('SHOW', { bikeId: bid })}>
              <img src={b.icon} alt={b.name} style={{ width: 52, height: 52, objectFit: 'contain' }} />
              <div style={{ textAlign: 'left' }}>
                <p style={{ fontSize: 14, fontWeight: 700 }}>{b.isCustom && <span style={{ color: COLORS.gold, marginRight: 4 }}>★</span>}{b.name}</p>
                <p style={{ fontSize: 11, opacity: .6 }}>{b.cc} / {b.category}</p>
              </div>
            </button>
          );
        })}
      </div>
      <div style={{ padding: '8px 16px 16px', flexShrink: 0 }}>
        <button style={{ ...S.btn(COLORS.card), width: '100%', fontSize: 14, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('BACK_DIALOG')}>戻る</button>
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Inventory Scene (scrollable)
   ═══════════════════════════════════════════ */
function InventoryScene({ state, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden', maxWidth: 480, margin: '0 auto', width: '100%' }}>
      <h2 style={{ fontSize: 15, fontWeight: 700, textAlign: 'center', margin: '12px 0 8px', flexShrink: 0 }}>所持バイク</h2>
      <div style={{ flex: 1, overflowY: 'auto', padding: '0 16px', WebkitOverflowScrolling: 'touch' }}>
        {state.bikes.length === 0 && <p style={{ textAlign: 'center', opacity: .6, marginTop: 16 }}>バイクを持っていません</p>}
        {state.bikes.map((bid, i) => {
          const b = BIKES[bid]; if (!b) return null;
          return (
            <div key={bid + '-' + i} style={{ ...S.card, display: 'flex', alignItems: 'center', gap: 12, border: b.isCustom ? `1px solid ${COLORS.gold}44` : '1px solid rgba(255,255,255,.08)', animation: `ws-slideIn .3s ease-out ${i * .05}s both` }}>
              <img src={b.icon} alt={b.name} style={{ width: 52, height: 52, objectFit: 'contain' }} />
              <div>
                <p style={{ fontSize: 14, fontWeight: 700 }}>{b.isCustom && <span style={{ color: COLORS.gold, marginRight: 4 }}>★</span>}{b.name}</p>
                <p style={{ fontSize: 11, opacity: .6 }}>{b.cc} / {b.category} / {b.price}</p>
              </div>
            </div>
          );
        })}
        {state.parts.length > 0 && (
          <>
            <h3 style={{ fontSize: 14, fontWeight: 700, textAlign: 'center', margin: '16px 0 6px' }}>所持パーツ</h3>
            {state.parts.map((pid, i) => {
              const p = PARTS[pid]; if (!p) return null;
              return (
                <div key={pid + '-' + i} style={{ ...S.card, display: 'flex', alignItems: 'center', gap: 12, border: '1px solid rgba(255,255,255,.08)', animation: `ws-slideIn .3s ease-out ${i * .05}s both` }}>
                  <span style={{ fontSize: 24, width: 52, textAlign: 'center' }}>🔧</span>
                  <div>
                    <p style={{ fontSize: 14, fontWeight: 700 }}>{p.name}</p>
                    <p style={{ fontSize: 11, opacity: .6 }}>{p.desc}</p>
                  </div>
                </div>
              );
            })}
          </>
        )}
      </div>
      <div style={{ padding: '8px 16px 16px', flexShrink: 0 }}>
        <button style={{ ...S.btn(COLORS.card), width: '100%', fontSize: 14, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('CLOSE')}>閉じる</button>
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Map Scene (scrollable)
   ═══════════════════════════════════════════ */
function MapScene({ state, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden', maxWidth: 480, margin: '0 auto', width: '100%' }}>
      <h2 style={{ fontSize: 15, fontWeight: 700, textAlign: 'center', margin: '12px 0 8px', flexShrink: 0 }}>エリアマップ</h2>
      <div style={{ flex: 1, overflowY: 'auto', padding: '0 16px', WebkitOverflowScrolling: 'touch' }}>
        {Object.values(AREAS).map((a, i) => {
          const ok = state.unlockedAreas.includes(a.id);
          return (
            <button key={a.id} disabled={!ok}
              style={{ ...S.card, width: '100%', cursor: ok ? 'pointer' : 'not-allowed', opacity: ok ? 1 : .4,
                border: state.currentArea === a.id ? `2px solid ${COLORS.accent}` : '1px solid rgba(255,255,255,.1)',
                display: 'flex', alignItems: 'center', gap: 12,
                backgroundImage: ok ? `linear-gradient(135deg,rgba(22,33,62,.9),rgba(22,33,62,.7)),url(${a.bg})` : undefined,
                backgroundSize: 'cover', backgroundPosition: 'center',
                animation: `ws-fadeIn .3s ease-out ${i * .1}s both` }}
              onClick={() => ok && d('GO_AREA', { areaId: a.id })}>
              <span style={{ fontSize: 26 }}>{ok ? a.emoji : '🔒'}</span>
              <div style={{ textAlign: 'left' }}>
                <p style={{ fontSize: 15, fontWeight: 700 }}>{a.name}</p>
                <p style={{ fontSize: 11, opacity: .6 }}>{ok ? `NPC: ${getVisibleNpcs(a.id, state).length}人` : '???'}</p>
              </div>
              {state.currentArea === a.id && <span style={{ marginLeft: 'auto', fontSize: 11, color: COLORS.accent, fontWeight: 700 }}>現在地</span>}
            </button>
          );
        })}
      </div>
      <div style={{ padding: '8px 16px 16px', flexShrink: 0 }}>
        <button style={{ ...S.btn(COLORS.card), width: '100%', fontSize: 14, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('CLOSE')}>閉じる</button>
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Synthesis Scene (scrollable)
   ═══════════════════════════════════════════ */
function SynthesisScene({ state, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden', maxWidth: 480, margin: '0 auto', width: '100%' }}>
      <h2 style={{ fontSize: 15, fontWeight: 700, textAlign: 'center', margin: '12px 0 8px', flexShrink: 0 }}>🔧 合成</h2>
      <div style={{ flex: 1, overflowY: 'auto', padding: '0 16px', WebkitOverflowScrolling: 'touch' }}>
        {SYNTHESIS.map((r, i) => {
          const hb = state.bikes.includes(r.bike), hp = state.parts.includes(r.part), can = hb && hp;
          const bd = BIKES[r.bike], pd = PARTS[r.part], rd = BIKES[r.result];
          if (!bd || !pd || !rd) return null;
          return (
            <div key={r.id} style={{ ...S.card, opacity: can ? 1 : .45, border: can ? `1px solid ${COLORS.gold}66` : '1px solid rgba(255,255,255,.08)', animation: `ws-fadeIn .3s ease-out ${i * .1}s both` }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, justifyContent: 'center', marginBottom: 6 }}>
                <img src={bd.icon} alt="" style={{ width: 36, height: 36, objectFit: 'contain', opacity: hb ? 1 : .3 }} />
                <span style={{ fontWeight: 700 }}>+</span>
                <span style={{ fontSize: 18, opacity: hp ? 1 : .3 }}>🔧</span>
                <span style={{ fontWeight: 700 }}>=</span>
                <img src={rd.icon} alt="" style={{ width: 36, height: 36, objectFit: 'contain' }} />
              </div>
              <p style={{ fontSize: 12, textAlign: 'center', opacity: .8 }}>{bd.name} + {pd.name}</p>
              <p style={{ fontSize: 13, fontWeight: 700, textAlign: 'center', color: COLORS.gold, marginTop: 2 }}>→ {rd.name}</p>
              {can && (
                <button style={{ ...S.btn(COLORS.success), width: '100%', marginTop: 8, fontSize: 14 }} onClick={() => d('SYNTH', { recipeId: r.id })}>
                  合成する！
                </button>
              )}
            </div>
          );
        })}
        {state.parts.length > 0 && (
          <>
            <h3 style={{ fontSize: 13, fontWeight: 700, textAlign: 'center', margin: '14px 0 6px', opacity: .7 }}>所持パーツ</h3>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, justifyContent: 'center', marginBottom: 8 }}>
              {state.parts.map((pId, i) => { const p = PARTS[pId]; return p ? <span key={pId + '-' + i} style={{ ...S.card, padding: '6px 10px', fontSize: 11, margin: 0 }}>🔧 {p.name}</span> : null; })}
            </div>
          </>
        )}
      </div>
      <div style={{ padding: '8px 16px 16px', flexShrink: 0 }}>
        <button style={{ ...S.btn(COLORS.card), width: '100%', fontSize: 14, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('CLOSE')}>閉じる</button>
      </div>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Synth Result Scene
   ═══════════════════════════════════════════ */
function SynthResultScene({ state, d }) {
  const b = BIKES[state.synthResult]; if (!b) return null;
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '0 16px', animation: 'ws-fadeIn .5s ease-out' }}>
      <p style={{ fontSize: 14, opacity: .7, marginBottom: 12 }}>🔧 合成成功！</p>
      <div style={{ animation: 'ws-glow 2s infinite', borderRadius: 16, padding: 20 }}>
        <img src={b.icon} alt={b.name} style={{ width: 140, height: 140, objectFit: 'contain', animation: 'ws-popIn .5s ease-out' }} />
      </div>
      <p style={{ fontSize: 18, fontWeight: 900, color: COLORS.gold, marginTop: 12 }}>★ {b.name}</p>
      <p style={{ fontSize: 12, opacity: .6, marginTop: 4 }}>{b.cc} / {b.category} / {b.price}</p>
      <button style={{ ...S.btn(), marginTop: 28, padding: '12px 44px', fontSize: 16 }} onClick={() => d('CLOSE')}>OK</button>
    </div>
  );
}

/* ═══════════════════════════════════════════
   Game Clear Screen
   ═══════════════════════════════════════════ */
function GameClearScreen({ state, d, audioBump }) {
  return (
    <div style={{ ...S.shell, alignItems: 'center', justifyContent: 'center', background: 'linear-gradient(135deg, #1a1a2e 0%, #0f3460 50%, #1a1a2e 100%)' }} className="ws-shell">
      <div style={{ textAlign: 'center', padding: '0 24px', animation: 'ws-fadeIn 1s ease-out' }}>
        <p style={{ fontSize: 44, marginBottom: 12 }}>🏁</p>
        <h1 style={{ fontSize: 24, fontWeight: 900, color: COLORS.gold, marginBottom: 6, textShadow: '0 0 20px rgba(255,215,0,.4)' }}>
          ゲームクリア！
        </h1>
        <p style={{ fontSize: 14, lineHeight: 1.8, marginBottom: 8 }}>カブ50から始まった交換の旅——</p>
        <div style={{ animation: 'ws-glow 2s infinite', borderRadius: 16, padding: 20, display: 'inline-block', margin: '12px 0' }}>
          <img src={BIKES.hayabusa.icon} alt="隼" style={{ width: 150, height: 150, objectFit: 'contain' }} />
        </div>
        <p style={{ fontSize: 18, fontWeight: 900, color: COLORS.gold }}>隼（Hayabusa）を手に入れた！</p>
        <p style={{ fontSize: 12, opacity: .6, marginTop: 6 }}>
          入手バイク: {state.bikesEverOwned.length}種 / 所持パーツ: {state.parts.length}個
        </p>
        <button style={{ ...S.btn(), padding: '12px 44px', fontSize: 16, marginTop: 28 }}
          onClick={() => d('CONTINUE_AFTER_CLEAR')}>
          まだまだ遊ぶ
        </button>
        <p style={{ fontSize: 11, opacity: .4, marginTop: 10 }}>ヒント：伝説のライダーに、あのバイクで話しかけてみよう…</p>
      </div>
    </div>
  );
}
