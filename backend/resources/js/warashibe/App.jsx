import React, { useReducer, useEffect, useCallback, useState } from 'react';
import { BIKES, NPCS, AREAS } from './data';

/* ─── constants ─── */
const IMG = '/images/warashibe/';
const COLORS = { bg: '#1a1a2e', card: '#16213e', text: '#e0e0e0', accent: '#e94560', success: '#4caf50' };

/* ─── initial state ─── */
const initialState = {
  phase: 'title',
  currentArea: 'street',
  npcIndex: 0,
  scene: 'explore',
  bikes: [],
  selectedBike: null,
  unlockedAreas: ['street'],
  activeNpc: null,
  dialogLines: [],
  dialogIndex: 0,
  dialogPhase: 'idle',
};

/* ─── reducer ─── */
function reducer(state, action) {
  switch (action.type) {
    case 'START':
      return { ...state, phase: 'prologue' };

    case 'PROLOGUE_END':
      return { ...state, phase: 'playing', scene: 'explore', bikes: [], npcIndex: 0 };

    case 'NAV': {
      const area = AREAS[state.currentArea];
      const len = area.npcs.length;
      const next = (state.npcIndex + action.dir + len) % len;
      return { ...state, npcIndex: next };
    }

    case 'TALK': {
      const area = AREAS[state.currentArea];
      const npcId = area.npcs[state.npcIndex];
      const npc = NPCS[npcId];

      if (npc.isShop) {
        if (state.bikes.includes('cub50')) {
          return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: [npc.alreadyOwned], dialogIndex: 0, dialogPhase: 'shopRefuse' };
        }
        return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: npc.greeting, dialogIndex: 0, dialogPhase: 'shopGreeting' };
      }

      // Check if NPC already exchanged (has nothing to give now) — but NPCs can re-exchange
      const lines = npc.greeting;
      return { ...state, scene: 'dialog', activeNpc: npcId, dialogLines: lines, dialogIndex: 0, dialogPhase: 'greeting' };
    }

    case 'NEXT_LINE': {
      if (state.dialogIndex < state.dialogLines.length - 1) {
        return { ...state, dialogIndex: state.dialogIndex + 1 };
      }
      // End of lines
      const npc = NPCS[state.activeNpc];
      if (state.dialogPhase === 'shopGreeting') {
        const newBikes = [...state.bikes, 'cub50'];
        return {
          ...state,
          bikes: newBikes,
          dialogLines: ['スーパーカブ50 を手に入れた！'],
          dialogIndex: 0,
          dialogPhase: 'done',
        };
      }
      if (state.dialogPhase === 'shopRefuse' || state.dialogPhase === 'afterChat') {
        return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0 };
      }
      if (state.dialogPhase === 'greeting') {
        return { ...state, dialogPhase: 'choice' };
      }
      if (state.dialogPhase === 'correct') {
        return { ...state, dialogPhase: 'exchangeReady' };
      }
      if (state.dialogPhase === 'correctAfter') {
        return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0 };
      }
      if (state.dialogPhase === 'wrong') {
        return { ...state, dialogPhase: 'choice' };
      }
      if (state.dialogPhase === 'done') {
        return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0 };
      }
      return state;
    }

    case 'PICK_BIKE':
      return { ...state, scene: 'bikeSelect' };

    case 'SHOW': {
      const npc = NPCS[state.activeNpc];
      const bikeId = action.bikeId;
      if (bikeId === npc.wants) {
        return { ...state, scene: 'dialog', selectedBike: bikeId, dialogLines: npc.correct, dialogIndex: 0, dialogPhase: 'correct' };
      }
      const wrongMsg = (npc.wrong && (npc.wrong[bikeId] || npc.wrong._default)) || 'うーん、それじゃないかな…';
      return { ...state, scene: 'dialog', selectedBike: bikeId, dialogLines: [wrongMsg], dialogIndex: 0, dialogPhase: 'wrong' };
    }

    case 'EXCHANGE': {
      const npc = NPCS[state.activeNpc];
      const newBikes = state.bikes.filter(b => b !== state.selectedBike);
      newBikes.push(npc.gives);
      const unlocked = [...state.unlockedAreas];
      const lines = [`${BIKES[npc.gives].name} を手に入れた！`];
      if (npc.gives === 'ct125' && !unlocked.includes('suburb')) {
        unlocked.push('suburb');
        lines.push('新しいエリア「郊外」が開放された！');
      }
      lines.push(...npc.correctAfter);
      return {
        ...state,
        bikes: newBikes,
        unlockedAreas: unlocked,
        selectedBike: null,
        dialogLines: lines,
        dialogIndex: 0,
        dialogPhase: 'correctAfter',
      };
    }

    case 'BACK_DIALOG':
      return { ...state, scene: 'dialog', dialogPhase: 'choice' };

    case 'LEAVE':
      return { ...state, scene: 'explore', activeNpc: null, dialogPhase: 'idle', dialogLines: [], dialogIndex: 0, selectedBike: null };

    case 'OPEN_MAP':
      return { ...state, scene: 'map' };

    case 'OPEN_INV':
      return { ...state, scene: 'inventory' };

    case 'GO_AREA':
      return { ...state, currentArea: action.areaId, npcIndex: 0, scene: 'explore' };

    case 'CLOSE':
      return { ...state, scene: 'explore' };

    default:
      return state;
  }
}

/* ─── prologue lines ─── */
const PROLOGUE = [
  'ある日のこと——',
  '蕎麦屋のゲンさんが引退することになった。',
  '『出前用のカブ、余っちまったからよ。持ってきな。』',
  'こうして手に入れた、一台のスーパーカブ50。',
  'いつか、あの伝説のバイクに乗りたい——',
  'カブ一台から始まる、交換の旅。',
];

/* ─── CSS injection ─── */
function injectStyles() {
  const id = 'warashibe-keyframes';
  if (document.getElementById(id)) return;
  const style = document.createElement('style');
  style.id = id;
  style.textContent = `
    @keyframes ws-fadeIn { from { opacity:0; transform:translateY(12px) } to { opacity:1; transform:translateY(0) } }
    @keyframes ws-popIn { 0% { transform:scale(0) } 60% { transform:scale(1.12) } 100% { transform:scale(1) } }
    @keyframes ws-shake { 0%,100% { transform:translateX(0) } 20% { transform:translateX(-6px) } 40% { transform:translateX(6px) } 60% { transform:translateX(-4px) } 80% { transform:translateX(4px) } }
    @keyframes ws-pulse { 0%,100% { transform:scale(1) } 50% { transform:scale(1.05) } }
    @keyframes ws-fadeUp { from { opacity:0; transform:translateY(20px) } to { opacity:1; transform:translateY(0) } }
    @keyframes ws-float { 0%,100% { transform:translateY(0) } 50% { transform:translateY(-6px) } }
    @keyframes ws-slideIn { from { opacity:0; transform:translateX(-20px) } to { opacity:1; transform:translateX(0) } }
    @keyframes ws-exchangeGlow { 0% { box-shadow:0 0 0 rgba(76,175,80,0) } 50% { box-shadow:0 0 30px rgba(76,175,80,.6) } 100% { box-shadow:0 0 0 rgba(76,175,80,0) } }
  `;
  document.head.appendChild(style);
}

/* ─── styles ─── */
const S = {
  wrap: { width: '100%', minHeight: '100vh', fontFamily: "'Noto Sans JP', sans-serif", color: COLORS.text, background: COLORS.bg, position: 'relative', overflow: 'hidden' },
  btn: (bg = COLORS.accent) => ({ background: bg, color: '#fff', border: 'none', borderRadius: 8, padding: '12px 24px', fontSize: 16, fontWeight: 700, cursor: 'pointer', fontFamily: "'Noto Sans JP', sans-serif", transition: 'transform .1s', }),
  card: { background: COLORS.card, borderRadius: 12, padding: 16, margin: '8px 0' },
};

/* ─── component ─── */
export default function App() {
  const [state, dispatch] = useReducer(reducer, initialState);

  useEffect(() => { injectStyles(); }, []);

  const d = useCallback((type, payload) => dispatch({ type, ...payload }), []);

  /* ─── Title Screen ─── */
  if (state.phase === 'title') {
    return (
      <div style={S.wrap}>
        <div style={{
          minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
          backgroundImage: `url(${IMG}title_screen.png)`, backgroundSize: 'cover', backgroundPosition: 'center',
          position: 'relative',
        }}>
          <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,.45)' }} />
          <div style={{ position: 'relative', zIndex: 1, textAlign: 'center', animation: 'ws-fadeIn .8s ease-out' }}>
            <h1 style={{ fontSize: 32, fontWeight: 900, marginBottom: 8, textShadow: '0 2px 8px rgba(0,0,0,.7)' }}>
              バイクわらしべ長者
            </h1>
            <p style={{ fontSize: 14, marginBottom: 32, opacity: .85, textShadow: '0 1px 4px rgba(0,0,0,.6)' }}>
              カブ50から始まる、交換の旅
            </p>
            <button
              style={{ ...S.btn(), padding: '14px 48px', fontSize: 18, animation: 'ws-pulse 2s infinite' }}
              onPointerDown={e => (e.currentTarget.style.transform = 'scale(.95)')}
              onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
              onClick={() => d('START')}
            >
              はじめる
            </button>
          </div>
        </div>
      </div>
    );
  }

  /* ─── Prologue Screen ─── */
  if (state.phase === 'prologue') {
    return <PrologueScreen d={d} />;
  }

  /* ─── Playing ─── */
  const area = AREAS[state.currentArea];
  const npcId = area.npcs[state.npcIndex];
  const npc = NPCS[npcId];

  return (
    <div style={S.wrap}>
      {/* Background */}
      <div style={{
        position: 'fixed', top: 0, left: 0,
        width: '100%', height: '100vh',
        backgroundImage: `url(${area.bg})`, backgroundSize: 'cover', backgroundPosition: 'center',
        zIndex: 0,
      }}>
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to bottom, rgba(26,26,46,.3) 0%, rgba(26,26,46,.85) 100%)' }} />
      </div>

      {/* Content */}
      <div style={{ position: 'relative', zIndex: 1, minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
        {/* Top bar */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 16px' }}>
          <span style={{ fontSize: 14, fontWeight: 700, opacity: .7 }}>{area.name}</span>
          <div style={{ display: 'flex', gap: 8 }}>
            <button style={{ ...S.btn(COLORS.card), padding: '6px 12px', fontSize: 13, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('OPEN_INV')}>
              📦 {state.bikes.length}
            </button>
            <button style={{ ...S.btn(COLORS.card), padding: '6px 12px', fontSize: 13, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('OPEN_MAP')}>
              🗺️
            </button>
          </div>
        </div>

        {/* Scene */}
        {state.scene === 'explore' && <ExploreScene state={state} npc={npc} area={area} d={d} />}
        {state.scene === 'dialog' && <DialogScene state={state} npc={NPCS[state.activeNpc]} d={d} />}
        {state.scene === 'bikeSelect' && <BikeSelectScene state={state} d={d} />}
        {state.scene === 'inventory' && <InventoryScene state={state} d={d} />}
        {state.scene === 'map' && <MapScene state={state} d={d} />}
      </div>

    </div>
  );
}

/* ─── Explore Scene ─── */
function ExploreScene({ state, npc, area, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
      {/* NPC */}
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', width: '100%' }}>
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', animation: 'ws-fadeIn .4s ease-out', width: '100%', padding: '0 16px' }}>
          <img
            src={npc.image}
            alt={npc.name}
            style={{ width: 200, height: 200, objectFit: 'contain', animation: 'ws-float 3s ease-in-out infinite', filter: 'drop-shadow(0 4px 16px rgba(0,0,0,.6))' }}
          />
          <p style={{ fontSize: 18, fontWeight: 700, marginTop: 10, textAlign: 'center', textShadow: '0 1px 4px rgba(0,0,0,.6)' }}>{npc.name}</p>
          <p style={{ fontSize: 16, opacity: .75, marginTop: 6, fontStyle: 'italic', textAlign: 'center', lineHeight: 1.6 }}>「{npc.idle}」</p>
        </div>
      </div>

      {/* Navigation arrows + talk — fixed bottom */}
      <div style={{ position: 'fixed', bottom: 0, left: 0, width: '100%', padding: '16px 16px 24px', background: 'linear-gradient(to top, rgba(26,26,46,.95) 0%, rgba(26,26,46,0) 100%)', zIndex: 10 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, maxWidth: 480, margin: '0 auto' }}>
          {area.npcs.length > 1 && (
            <button style={{ ...S.btn(COLORS.card), padding: '12px 16px', fontSize: 18, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('NAV', { dir: -1 })}>◀</button>
          )}
          <button
            style={{ ...S.btn(), flex: 1, padding: '14px 0', fontSize: 16 }}
            onPointerDown={e => (e.currentTarget.style.transform = 'scale(.97)')}
            onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
            onClick={() => d('TALK')}
          >
            話しかける
          </button>
          {area.npcs.length > 1 && (
            <button style={{ ...S.btn(COLORS.card), padding: '12px 16px', fontSize: 18, border: '1px solid rgba(255,255,255,.15)' }} onClick={() => d('NAV', { dir: 1 })}>▶</button>
          )}
        </div>
      </div>
    </div>
  );
}

/* ─── Dialog Scene ─── */
function DialogScene({ state, npc, d }) {
  const line = state.dialogLines[state.dialogIndex] || '';
  const isLastLine = state.dialogIndex >= state.dialogLines.length - 1;

  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
      {/* NPC image */}
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <img
          src={npc.image}
          alt={npc.name}
          style={{
            width: 200, height: 200, objectFit: 'contain',
            filter: 'drop-shadow(0 4px 16px rgba(0,0,0,.6))',
            animation: state.dialogPhase === 'correct' ? 'ws-popIn .4s ease-out' : state.dialogPhase === 'wrong' ? 'ws-shake .4s' : undefined,
          }}
        />
      </div>

      {/* Dialog + buttons — fixed bottom */}
      <div style={{ position: 'fixed', bottom: 0, left: 0, width: '100%', padding: '0 16px 24px', background: 'linear-gradient(to top, rgba(26,26,46,.95) 60%, rgba(26,26,46,0) 100%)', zIndex: 10 }}>
        <div style={{ maxWidth: 480, margin: '0 auto' }}>
          {/* Dialog box */}
          <div
            style={{
              ...S.card, background: 'rgba(22,33,62,.95)', border: '1px solid rgba(255,255,255,.1)',
              minHeight: 100, cursor: isLastLine && state.dialogPhase !== 'choice' ? 'default' : 'pointer',
              animation: 'ws-fadeUp .3s ease-out', textAlign: 'center',
            }}
            onClick={() => {
              if (state.dialogPhase === 'choice' || state.dialogPhase === 'exchangeReady') return;
              d('NEXT_LINE');
            }}
          >
            <p style={{ fontSize: 18, fontWeight: 700, color: COLORS.accent, marginBottom: 8, textAlign: 'center' }}>{npc.name}</p>
            <p style={{ fontSize: 16, lineHeight: 1.7, textAlign: 'center' }}>{line}</p>
            {!isLastLine && <p style={{ textAlign: 'center', fontSize: 12, opacity: .5, marginTop: 8 }}>▼</p>}
          </div>

          {/* Choice buttons */}
          {state.dialogPhase === 'choice' && (
            <div style={{ display: 'flex', gap: 8, marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button
                style={{ ...S.btn(), flex: 1, fontSize: 15 }}
                onPointerDown={e => (e.currentTarget.style.transform = 'scale(.97)')}
                onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
                onClick={() => d('PICK_BIKE')}
              >
                バイクを見せる
              </button>
              <button
                style={{ ...S.btn(COLORS.card), flex: 1, fontSize: 15, border: '1px solid rgba(255,255,255,.15)' }}
                onClick={() => d('LEAVE')}
              >
                やめる
              </button>
            </div>
          )}

          {/* Exchange confirm */}
          {state.dialogPhase === 'exchangeReady' && (
            <div style={{ display: 'flex', gap: 8, marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button
                style={{ ...S.btn(COLORS.success), flex: 1, fontSize: 15 }}
                onPointerDown={e => (e.currentTarget.style.transform = 'scale(.97)')}
                onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
                onClick={() => d('EXCHANGE')}
              >
                交換する！
              </button>
            </div>
          )}

          {/* Done - leave */}
          {(state.dialogPhase === 'done' || (state.dialogPhase === 'correctAfter' && isLastLine)) && (
            <div style={{ marginTop: 8, animation: 'ws-fadeIn .3s ease-out' }}>
              <button
                style={{ ...S.btn(COLORS.card), width: '100%', fontSize: 15, border: '1px solid rgba(255,255,255,.15)' }}
                onClick={() => d('LEAVE')}
              >
                戻る
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

/* ─── Bike Select Scene ─── */
function BikeSelectScene({ state, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', padding: '0 16px 24px', maxWidth: 480, margin: '0 auto', width: '100%' }}>
      <h2 style={{ fontSize: 16, fontWeight: 700, textAlign: 'center', margin: '16px 0', animation: 'ws-fadeIn .3s ease-out' }}>
        どのバイクを見せる？
      </h2>
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 8, animation: 'ws-fadeIn .4s ease-out' }}>
        {state.bikes.map((bikeId, i) => {
          const bike = BIKES[bikeId];
          return (
            <button
              key={bikeId + '-' + i}
              style={{
                ...S.card, display: 'flex', alignItems: 'center', gap: 12, cursor: 'pointer',
                border: '1px solid rgba(255,255,255,.1)', transition: 'transform .1s, border-color .2s',
                animation: `ws-slideIn .3s ease-out ${i * .06}s both`,
              }}
              onPointerDown={e => (e.currentTarget.style.transform = 'scale(.97)')}
              onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
              onPointerLeave={e => (e.currentTarget.style.transform = 'scale(1)')}
              onClick={() => d('SHOW', { bikeId })}
            >
              <img src={bike.icon} alt={bike.name} style={{ width: 56, height: 56, objectFit: 'contain' }} />
              <div style={{ textAlign: 'left' }}>
                <p style={{ fontSize: 14, fontWeight: 700 }}>{bike.name}</p>
                <p style={{ fontSize: 11, opacity: .6 }}>{bike.cc} / {bike.category}</p>
              </div>
            </button>
          );
        })}
      </div>
      <button
        style={{ ...S.btn(COLORS.card), width: '100%', marginTop: 12, fontSize: 14, border: '1px solid rgba(255,255,255,.15)' }}
        onClick={() => d('BACK_DIALOG')}
      >
        戻る
      </button>
    </div>
  );
}

/* ─── Inventory Scene ─── */
function InventoryScene({ state, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', padding: '0 16px 24px', maxWidth: 480, margin: '0 auto', width: '100%' }}>
      <h2 style={{ fontSize: 16, fontWeight: 700, textAlign: 'center', margin: '16px 0' }}>所持バイク</h2>
      {state.bikes.length === 0 ? (
        <p style={{ textAlign: 'center', opacity: .6, marginTop: 32 }}>バイクを持っていません</p>
      ) : (
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 8 }}>
          {state.bikes.map((bikeId, i) => {
            const bike = BIKES[bikeId];
            return (
              <div key={bikeId + '-' + i} style={{ ...S.card, display: 'flex', alignItems: 'center', gap: 12, border: '1px solid rgba(255,255,255,.08)', animation: `ws-slideIn .3s ease-out ${i * .06}s both` }}>
                <img src={bike.icon} alt={bike.name} style={{ width: 56, height: 56, objectFit: 'contain' }} />
                <div>
                  <p style={{ fontSize: 14, fontWeight: 700 }}>{bike.name}</p>
                  <p style={{ fontSize: 11, opacity: .6 }}>{bike.cc} / {bike.category} / {bike.price}</p>
                </div>
              </div>
            );
          })}
        </div>
      )}
      <button
        style={{ ...S.btn(COLORS.card), width: '100%', marginTop: 12, fontSize: 14, border: '1px solid rgba(255,255,255,.15)' }}
        onClick={() => d('CLOSE')}
      >
        閉じる
      </button>
    </div>
  );
}

/* ─── Map Scene ─── */
function MapScene({ state, d }) {
  return (
    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', padding: '0 16px 24px', maxWidth: 480, margin: '0 auto', width: '100%' }}>
      <h2 style={{ fontSize: 16, fontWeight: 700, textAlign: 'center', margin: '16px 0' }}>エリアマップ</h2>
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 12 }}>
        {Object.values(AREAS).map((a, i) => {
          const unlocked = state.unlockedAreas.includes(a.id);
          return (
            <button
              key={a.id}
              disabled={!unlocked}
              style={{
                ...S.card, cursor: unlocked ? 'pointer' : 'not-allowed',
                opacity: unlocked ? 1 : .4,
                border: state.currentArea === a.id ? `2px solid ${COLORS.accent}` : '1px solid rgba(255,255,255,.1)',
                display: 'flex', alignItems: 'center', gap: 12,
                backgroundImage: unlocked ? `linear-gradient(135deg, rgba(22,33,62,.9), rgba(22,33,62,.7)), url(${a.bg})` : undefined,
                backgroundSize: 'cover', backgroundPosition: 'center',
                animation: `ws-fadeIn .3s ease-out ${i * .1}s both`,
                transition: 'transform .1s',
              }}
              onPointerDown={e => unlocked && (e.currentTarget.style.transform = 'scale(.97)')}
              onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
              onClick={() => unlocked && d('GO_AREA', { areaId: a.id })}
            >
              <span style={{ fontSize: 28 }}>{unlocked ? (a.id === 'street' ? '🏙️' : '🌲') : '🔒'}</span>
              <div style={{ textAlign: 'left' }}>
                <p style={{ fontSize: 16, fontWeight: 700 }}>{a.name}</p>
                <p style={{ fontSize: 12, opacity: .6 }}>{unlocked ? `NPC: ${a.npcs.length}人` : '???'}</p>
              </div>
              {state.currentArea === a.id && <span style={{ marginLeft: 'auto', fontSize: 11, color: COLORS.accent, fontWeight: 700 }}>現在地</span>}
            </button>
          );
        })}
      </div>
      <button
        style={{ ...S.btn(COLORS.card), width: '100%', marginTop: 12, fontSize: 14, border: '1px solid rgba(255,255,255,.15)' }}
        onClick={() => d('CLOSE')}
      >
        閉じる
      </button>
    </div>
  );
}

/* ─── Prologue Screen ─── */
function PrologueScreen({ d }) {
  const [visibleCount, setVisibleCount] = useState(0);

  useEffect(() => {
    if (visibleCount < PROLOGUE.length) {
      const t = setTimeout(() => setVisibleCount(c => c + 1), 1200);
      return () => clearTimeout(t);
    }
  }, [visibleCount]);

  const allShown = visibleCount >= PROLOGUE.length;

  return (
    <div style={{
      width: '100%', minHeight: '100vh', fontFamily: "'Noto Sans JP', sans-serif", color: COLORS.text,
      backgroundImage: `url(${IMG}title_screen.png)`, backgroundSize: 'cover', backgroundPosition: 'center',
      position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
    }}>
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,.7)' }} />
      <div style={{ position: 'relative', zIndex: 1, textAlign: 'center', padding: '0 24px', maxWidth: 520 }}>
        {PROLOGUE.map((line, i) => (
          <p
            key={i}
            style={{
              fontSize: i === 2 ? 17 : 18,
              lineHeight: 2.2,
              opacity: i < visibleCount ? 1 : 0,
              transform: i < visibleCount ? 'translateY(0)' : 'translateY(12px)',
              transition: 'opacity .8s ease-out, transform .8s ease-out',
              textShadow: '0 1px 6px rgba(0,0,0,.8)',
              fontStyle: i === 2 ? 'italic' : 'normal',
            }}
          >
            {line}
          </p>
        ))}
        {allShown && (
          <button
            style={{
              ...S.btn(), padding: '14px 48px', fontSize: 18, marginTop: 40,
              animation: 'ws-fadeIn .6s ease-out',
            }}
            onPointerDown={e => (e.currentTarget.style.transform = 'scale(.95)')}
            onPointerUp={e => (e.currentTarget.style.transform = 'scale(1)')}
            onClick={() => d('PROLOGUE_END')}
          >
            はじめる
          </button>
        )}
      </div>
    </div>
  );
}
