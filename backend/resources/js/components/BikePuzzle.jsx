import React, { useState, useEffect, useCallback, useRef, useMemo } from 'react';

// ── Bike data ──────────────────────────────────────────────
const BIKES = [
  { level: 1,  name: 'スーパーカブ50', cc: '50cc',   color: '#FFF3E0' },
  { level: 2,  name: 'モンキー125',    cc: '125cc',  color: '#FFF9C4' },
  { level: 3,  name: 'PCX',           cc: '160cc',  color: '#E3F2FD' },
  { level: 4,  name: 'レブル250',      cc: '250cc',  color: '#E0E0E0' },
  { level: 5,  name: 'Ninja400',      cc: '400cc',  color: '#C8E6C9' },
  { level: 6,  name: 'GB350',         cc: '350cc',  color: '#D7CCC8' },
  { level: 7,  name: 'Z900RS',        cc: '900cc',  color: '#FFE0B2' },
  { level: 8,  name: 'CB1300SF',      cc: '1300cc', color: '#FFCDD2' },
  { level: 9,  name: 'ハヤブサ',       cc: '1340cc', color: '#CFD8DC' },
  { level: 10, name: 'H2',            cc: '998cc',  color: '#B2EBF2' },
  { level: 11, name: 'ゴールドウイング', cc: '1833cc', color: '#FFD54F' },
];

function getBike(level) {
  return BIKES[level - 1] || null;
}

// ── Tile ID counter ────────────────────────────────────────
let nextId = 1;

// ── Grid helpers ───────────────────────────────────────────
function createEmptyGrid() {
  return Array(4).fill(null).map(() => Array(4).fill(0));
}

function tilesToGrid(tiles) {
  const grid = createEmptyGrid();
  for (const t of tiles) grid[t.row][t.col] = Math.max(grid[t.row][t.col], t.level);
  return grid;
}

function canMove(grid) {
  for (let r = 0; r < 4; r++)
    for (let c = 0; c < 4; c++) {
      if (grid[r][c] === 0) return true;
      if (c + 1 < 4 && grid[r][c] === grid[r][c + 1]) return true;
      if (r + 1 < 4 && grid[r][c] === grid[r + 1][c]) return true;
    }
  return false;
}

function spawnTile(tiles) {
  const occupied = new Set(tiles.map(t => `${t.row},${t.col}`));
  const empty = [];
  for (let r = 0; r < 4; r++)
    for (let c = 0; c < 4; c++)
      if (!occupied.has(`${r},${c}`)) empty.push([r, c]);
  if (empty.length === 0) return null;
  const [row, col] = empty[Math.floor(Math.random() * empty.length)];
  return { id: nextId++, level: Math.random() < 0.9 ? 1 : 2, row, col, isNew: true, merging: false, consumed: false };
}

function createInitialTiles() {
  const t1 = { id: nextId++, level: Math.random() < 0.9 ? 1 : 2, row: 0, col: 0, isNew: false, merging: false, consumed: false };
  const second = spawnTile([t1]);
  return second ? [t1, { ...second, isNew: false }] : [t1];
}

// ── Move calculation ───────────────────────────────────────
function processLine(lineTiles, reverse) {
  if (lineTiles.length === 0) return { results: [], score: 0 };
  const sorted = [...lineTiles].sort((a, b) => reverse ? b.pos - a.pos : a.pos - b.pos);
  let score = 0;
  const results = [];
  let target = reverse ? 3 : 0;
  const step = reverse ? -1 : 1;

  let i = 0;
  while (i < sorted.length) {
    if (i + 1 < sorted.length && sorted[i].level === sorted[i + 1].level) {
      const newLevel = sorted[i].level + 1;
      score += Math.pow(2, newLevel);
      // Closer tile is the merge survivor, further tile is consumed
      results.push({ id: sorted[i].id, newPos: target, merged: true, newLevel });
      results.push({ id: sorted[i + 1].id, newPos: target, consumed: true });
      i += 2;
    } else {
      results.push({ id: sorted[i].id, newPos: target });
      i += 1;
    }
    target += step;
  }
  return { results, score };
}

function calculateMove(tiles, direction) {
  const isH = direction === 'left' || direction === 'right';
  const reverse = direction === 'right' || direction === 'down';
  const lines = new Map();

  for (const tile of tiles) {
    const key = isH ? tile.row : tile.col;
    if (!lines.has(key)) lines.set(key, []);
    lines.get(key).push({ id: tile.id, level: tile.level, pos: isH ? tile.col : tile.row });
  }

  let totalScore = 0;
  const moveMap = new Map();

  for (const [lineKey, lineTiles] of lines) {
    const { results, score } = processLine(lineTiles, reverse);
    totalScore += score;
    for (const r of results) {
      moveMap.set(r.id, {
        newRow: isH ? parseInt(lineKey) : r.newPos,
        newCol: isH ? r.newPos : parseInt(lineKey),
        consumed: r.consumed || false,
        merged: r.merged || false,
        newLevel: r.newLevel,
      });
    }
  }

  const moved = [...moveMap.entries()].some(([id, m]) => {
    const t = tiles.find(t => t.id === id);
    return t.row !== m.newRow || t.col !== m.newCol || m.merged;
  });

  return { moveMap, score: totalScore, moved };
}

// ── localStorage ───────────────────────────────────────────
const STORAGE_KEY = 'motohub_puzzle';

function saveGame(state) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      tiles: state.tiles.map(({ id, level, row, col }) => ({ id, level, row, col })),
      score: state.score,
      bestScore: state.bestScore,
      maxLevel: state.maxLevel,
      gameOver: state.gameOver,
      gameWon: state.gameWon,
      keepPlaying: state.keepPlaying,
    }));
  } catch {}
}

function isValidGrid(grid) {
  return Array.isArray(grid) && grid.length === 4
    && grid.every(row => Array.isArray(row) && row.length === 4
      && row.every(v => typeof v === 'number' && v >= 0 && v <= 11));
}

function loadGame() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw);
    if (!data) return null;

    // New tile-based format
    if (Array.isArray(data.tiles) && data.tiles.length > 0) {
      const valid = data.tiles.every(t =>
        typeof t.id === 'number' && typeof t.level === 'number'
        && typeof t.row === 'number' && typeof t.col === 'number'
        && t.level >= 1 && t.level <= 11 && t.row >= 0 && t.row < 4 && t.col >= 0 && t.col < 4
      );
      if (valid) return data;
    }

    // Legacy grid format
    if (isValidGrid(data.grid)) {
      const tiles = [];
      let id = 1;
      for (let r = 0; r < 4; r++)
        for (let c = 0; c < 4; c++)
          if (data.grid[r][c] > 0) tiles.push({ id: id++, level: data.grid[r][c], row: r, col: c });
      return { ...data, tiles };
    }
  } catch {}
  try { localStorage.removeItem(STORAGE_KEY); } catch {}
  return null;
}

function clearSave() {
  try { localStorage.removeItem(STORAGE_KEY); } catch {}
}

// ── CSS (injected once) ────────────────────────────────────
const STYLE_ID = 'bike-puzzle-styles';
const SP = 'min(2vw, 8px)';

function injectStyles() {
  if (document.getElementById(STYLE_ID)) return;
  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = `
    @keyframes bike-puzzle-pop {
      0%   { transform: scale(1); }
      50%  { transform: scale(1.18); }
      100% { transform: scale(1); }
    }
    @keyframes bike-puzzle-appear {
      0%   { opacity: 0; transform: scale(0); }
      100% { opacity: 1; transform: scale(1); }
    }
    .bp-container {
      position: relative;
      max-width: 400px;
      margin: 0 auto;
      touch-action: none;
      user-select: none;
      -webkit-user-select: none;
    }
    .bp-grid-bg {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      grid-template-rows: repeat(4, 1fr);
      gap: ${SP};
      background: #bbada0;
      border-radius: 12px;
      padding: ${SP};
      aspect-ratio: 1;
    }
    .bp-empty-cell {
      border-radius: ${SP};
      background: rgba(238,228,218,0.35);
    }
    .bp-tile {
      position: absolute;
      border-radius: ${SP};
      overflow: hidden;
      transition: left 0.3s ease, top 0.3s ease;
      width: calc((100% - 5 * ${SP}) / 4);
      height: calc((100% - 5 * ${SP}) / 4);
    }
    .bp-tile-merged {
      animation: bike-puzzle-pop 0.35s ease;
    }
    .bp-tile-new {
      animation: bike-puzzle-appear 0.3s ease;
    }
    .bp-evo-strip {
      display: flex;
      gap: 6px;
      overflow-x: auto;
      padding-bottom: 4px;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
    }
    .bp-evo-strip::-webkit-scrollbar { height: 4px; }
    .bp-evo-strip::-webkit-scrollbar-thumb { background: #ccc; border-radius: 2px; }
    .bp-overlay {
      position: absolute;
      inset: 0;
      background: rgba(238,228,218,0.85);
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 10;
      animation: bike-puzzle-appear 0.3s ease;
    }
  `;
  document.head.appendChild(style);
}

// ── Tile rendering ─────────────────────────────────────────
function tilePos(row, col) {
  return {
    left: `calc(${SP} + ${col} * (100% - ${SP}) / 4)`,
    top: `calc(${SP} + ${row} * (100% - ${SP}) / 4)`,
  };
}

function TileView({ tile }) {
  const bike = getBike(tile.level);
  if (!bike) return null;

  const isDark = tile.level === 11;
  const nameSize = tile.level >= 9 ? '0.45em' : tile.level >= 7 ? '0.5em' : tile.level >= 4 ? '0.55em' : '0.6em';

  let animClass = '';
  if (tile.merging) animClass = 'bp-tile-merged';
  else if (tile.isNew) animClass = 'bp-tile-new';

  return (
    <div
      className={`bp-tile ${animClass}`}
      style={{
        ...tilePos(tile.row, tile.col),
        backgroundColor: bike.color,
        zIndex: tile.consumed ? 0 : tile.merging ? 2 : 1,
      }}
    >
      <img
        src={`/images/puzzle/level-${tile.level}.png`}
        alt={bike.name}
        draggable={false}
        style={{
          position: 'absolute',
          inset: '4px',
          width: 'calc(100% - 8px)',
          height: 'calc(100% - 20px)',
          objectFit: 'contain',
        }}
        onError={(e) => {
          e.target.style.display = 'none';
          e.target.nextSibling.style.display = 'flex';
        }}
      />
      <span style={{
        display: 'none',
        position: 'absolute',
        inset: 0,
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '0.6em',
        fontWeight: 'bold',
        color: isDark ? '#fff' : '#333',
        textAlign: 'center',
      }}>
        {bike.name}
      </span>
      <span style={{
        position: 'absolute',
        bottom: '2px',
        left: 0,
        right: 0,
        fontSize: nameSize,
        fontWeight: 'bold',
        lineHeight: 1,
        textAlign: 'center',
        color: isDark ? '#fff' : '#333',
        wordBreak: 'keep-all',
      }}>
        {bike.name}
      </span>
    </div>
  );
}

// ── Main component ─────────────────────────────────────────
export default function BikePuzzle() {
  const [tiles, setTiles] = useState(() => {
    const saved = loadGame();
    if (saved?.tiles) {
      nextId = Math.max(...saved.tiles.map(t => t.id)) + 1;
      return saved.tiles.map(t => ({ ...t, isNew: false, merging: false, consumed: false }));
    }
    return createInitialTiles();
  });
  const [score, setScore] = useState(() => loadGame()?.score ?? 0);
  const [bestScore, setBestScore] = useState(() => loadGame()?.bestScore ?? 0);
  const [gameOver, setGameOver] = useState(() => loadGame()?.gameOver ?? false);
  const [gameWon, setGameWon] = useState(() => loadGame()?.gameWon ?? false);
  const [keepPlaying, setKeepPlaying] = useState(() => loadGame()?.keepPlaying ?? false);
  const [maxLevel, setMaxLevel] = useState(() => loadGame()?.maxLevel ?? 1);
  const [history, setHistory] = useState([]);
  const [moving, setMoving] = useState(false);

  // Audio
  const [muted, setMuted] = useState(false);
  const [bgmStarted, setBgmStarted] = useState(false);
  const bgmRef = useRef(null);
  const mutedRef = useRef(muted);

  const gridRef = useRef(null);
  const touchRef = useRef(null);
  const moveTimeoutRef = useRef(null);

  useEffect(() => { injectStyles(); }, []);

  // ── Persist ────────────────────────────────────────────
  useEffect(() => {
    const cleanTiles = tiles.filter(t => !t.consumed);
    saveGame({ tiles: cleanTiles, score, bestScore, maxLevel, gameOver, gameWon, keepPlaying });
  }, [tiles, score, bestScore, maxLevel, gameOver, gameWon, keepPlaying]);

  useEffect(() => {
    if (score > bestScore) setBestScore(score);
  }, [score, bestScore]);

  useEffect(() => {
    const max = tiles.reduce((m, t) => Math.max(m, t.level), 0);
    if (max > maxLevel) setMaxLevel(max);
  }, [tiles, maxLevel]);

  // Clear animation flags
  useEffect(() => {
    if (!tiles.some(t => t.isNew || t.merging)) return;
    const t = setTimeout(() => {
      setTiles(prev => prev.map(t => ({ ...t, isNew: false, merging: false })));
    }, 400);
    return () => clearTimeout(t);
  }, [tiles]);

  // ── BGM ────────────────────────────────────────────────
  useEffect(() => {
    const audio = new Audio('/audio/puzzle/bgm.mp3');
    audio.loop = true;
    audio.volume = 0.3;
    bgmRef.current = audio;
    return () => { audio.pause(); audio.src = ''; };
  }, []);

  useEffect(() => { mutedRef.current = muted; }, [muted]);

  useEffect(() => {
    if (bgmRef.current) bgmRef.current.muted = muted;
  }, [muted]);

  // ── Sound effects ────────────────────────────────────────
  const playSound = useCallback((name) => {
    if (mutedRef.current) return;
    try {
      const audio = new Audio(`/audio/puzzle/${name}.mp3`);
      audio.volume = 0.5;
      audio.play().catch(() => {});
    } catch {}
  }, []);

  useEffect(() => {
    if (bgmRef.current && gameOver) bgmRef.current.pause();
  }, [gameOver]);

  const tryStartBgm = useCallback(() => {
    if (bgmStarted || muted || !bgmRef.current) return;
    bgmRef.current.play().then(() => setBgmStarted(true)).catch(() => {});
  }, [bgmStarted, muted]);

  const toggleMute = useCallback(() => {
    setMuted(prev => {
      const next = !prev;
      if (!bgmRef.current) return next;
      if (next) { bgmRef.current.pause(); }
      else { bgmRef.current.play().catch(() => {}); setBgmStarted(true); }
      return next;
    });
  }, []);

  // ── Core move (2-phase) ────────────────────────────────
  const move = useCallback((direction) => {
    if (moving || gameOver || (gameWon && !keepPlaying)) return;

    const { moveMap, score: moveScore, moved } = calculateMove(tiles, direction);
    if (!moved) return;

    tryStartBgm();
    setHistory(prev => [...prev, { tiles: tiles.map(t => ({ ...t })), score }]);
    setMoving(true);

    playSound('move');

    // Phase 1: Slide all tiles to new positions
    setTiles(prev => prev.map(tile => {
      const m = moveMap.get(tile.id);
      if (!m) return tile;
      return { ...tile, row: m.newRow, col: m.newCol, consumed: m.consumed, merging: false, isNew: false };
    }));

    // Phase 2: After slide, apply merges + spawn
    moveTimeoutRef.current = setTimeout(() => {
      // Compute final tiles from closure data
      const phase1 = tiles.map(t => {
        const m = moveMap.get(t.id);
        return m ? { ...t, row: m.newRow, col: m.newCol } : t;
      });

      let finalTiles = phase1.filter(t => !moveMap.get(t.id)?.consumed);

      finalTiles = finalTiles.map(t => {
        const m = moveMap.get(t.id);
        if (m?.merged) return { ...t, level: m.newLevel, merging: true, isNew: false, consumed: false };
        return { ...t, merging: false, isNew: false, consumed: false };
      });

      const spawned = spawnTile(finalTiles);
      if (spawned) finalTiles.push(spawned);

      setTiles(finalTiles);
      setScore(s => s + moveScore);
      setMoving(false);

      // Check win / game over
      const maxLvl = finalTiles.reduce((m, t) => Math.max(m, t.level), 0);
      if (maxLvl >= 11 && !gameWon) {
        setGameWon(true);
        playSound('clear');
      }
      if (maxLvl > maxLevel) setMaxLevel(maxLvl);

      const grid = tilesToGrid(finalTiles);
      if (!canMove(grid)) {
        setTimeout(() => { setGameOver(true); playSound('gameover'); }, 400);
      }
    }, 300);
  }, [tiles, score, moving, gameOver, gameWon, keepPlaying, maxLevel, tryStartBgm, playSound]);

  // ── Keyboard ───────────────────────────────────────────
  useEffect(() => {
    const handleKeyDown = (e) => {
      const map = {
        ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right',
        w: 'up', W: 'up', a: 'left', A: 'left', s: 'down', S: 'down', d: 'right', D: 'right',
      };
      const dir = map[e.key];
      if (dir) { e.preventDefault(); move(dir); }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [move]);

  // ── Touch swipe ────────────────────────────────────────
  useEffect(() => {
    const el = gridRef.current;
    if (!el) return;
    const onTouchStart = (e) => {
      touchRef.current = { x: e.touches[0].clientX, y: e.touches[0].clientY, time: Date.now() };
    };
    const onTouchMove = (e) => { if (touchRef.current) e.preventDefault(); };
    const onTouchEnd = (e) => {
      if (!touchRef.current) return;
      const t = e.changedTouches[0];
      const dx = t.clientX - touchRef.current.x;
      const dy = t.clientY - touchRef.current.y;
      const dt = Date.now() - touchRef.current.time;
      touchRef.current = null;
      if (dt > 500 || Math.max(Math.abs(dx), Math.abs(dy)) < 30) return;
      if (Math.abs(dx) > Math.abs(dy)) move(dx > 0 ? 'right' : 'left');
      else move(dy > 0 ? 'down' : 'up');
    };
    el.addEventListener('touchstart', onTouchStart, { passive: true });
    el.addEventListener('touchmove', onTouchMove, { passive: false });
    el.addEventListener('touchend', onTouchEnd, { passive: true });
    return () => {
      el.removeEventListener('touchstart', onTouchStart);
      el.removeEventListener('touchmove', onTouchMove);
      el.removeEventListener('touchend', onTouchEnd);
    };
  }, [move]);

  // Cleanup timeout on unmount
  useEffect(() => () => {
    if (moveTimeoutRef.current) clearTimeout(moveTimeoutRef.current);
  }, []);

  // ── Actions ────────────────────────────────────────────
  const resetGame = () => {
    if (moveTimeoutRef.current) { clearTimeout(moveTimeoutRef.current); moveTimeoutRef.current = null; }
    setMoving(false);
    setTiles(createInitialTiles());
    setScore(0);
    setGameOver(false);
    setGameWon(false);
    setKeepPlaying(false);
    setHistory([]);
    setMaxLevel(1);
    clearSave();
    if (bgmRef.current && !muted && bgmStarted) bgmRef.current.play().catch(() => {});
  };

  const undo = () => {
    if (history.length === 0) return;
    if (moveTimeoutRef.current) { clearTimeout(moveTimeoutRef.current); moveTimeoutRef.current = null; }
    setMoving(false);
    const prev = history[history.length - 1];
    setTiles(prev.tiles);
    setScore(prev.score);
    setHistory(history.slice(0, -1));
    setGameOver(false);
  };

  const maxBike = getBike(maxLevel);

  // ── Render ─────────────────────────────────────────────
  return (
    <div style={{
      minHeight: 'calc(100vh - 64px)',
      backgroundColor: '#faf8ef',
      padding: '12px 8px 24px',
      fontFamily: "'Noto Sans JP', sans-serif",
    }}>
      <div style={{ maxWidth: '420px', margin: '0 auto' }}>

        {/* Header */}
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <h1 style={{ fontSize: 'clamp(20px, 5vw, 26px)', fontWeight: '900', color: '#776e65', margin: 0 }}>
              バイク2048
            </h1>
            <button onClick={toggleMute} aria-label={muted ? '音声ON' : '音声OFF'} style={{
              background: 'none', border: 'none', fontSize: '20px', cursor: 'pointer',
              padding: '2px', lineHeight: 1, color: '#776e65', WebkitTapHighlightColor: 'transparent',
            }}>
              {muted ? '\u{1F507}' : '\u{1F50A}'}
            </button>
          </div>
          <div style={{ display: 'flex', gap: '6px' }}>
            <ScoreBox label="スコア" value={score} />
            <ScoreBox label="BEST" value={bestScore} />
          </div>
        </div>

        {/* Controls */}
        <div style={{ display: 'flex', gap: '8px', marginBottom: '10px' }}>
          <button onClick={resetGame} style={btnStyle}>リセット</button>
          <button onClick={undo} disabled={history.length === 0}
            style={{ ...btnStyle, opacity: history.length === 0 ? 0.5 : 1, cursor: history.length === 0 ? 'default' : 'pointer' }}>
            1手戻す
          </button>
        </div>

        {/* How to play */}
        <HowToPlay />

        {/* Grid */}
        <div className="bp-container" ref={gridRef} style={{ fontSize: 'clamp(12px, 3.5vw, 16px)' }}>
          <div className="bp-grid-bg">
            {Array(16).fill(null).map((_, i) => <div key={i} className="bp-empty-cell" />)}
          </div>
          {tiles.map(tile => <TileView key={tile.id} tile={tile} />)}

          {/* Game Over */}
          {gameOver && (
            <div className="bp-overlay">
              <p style={{ fontSize: '28px', fontWeight: '900', color: '#776e65', margin: '0 0 8px' }}>GAME OVER</p>
              <p style={{ fontSize: '16px', color: '#776e65', margin: '0 0 4px' }}>スコア: {score.toLocaleString()}</p>
              <p style={{ fontSize: '14px', color: '#999', margin: '0 0 16px' }}>最高レベル: {maxBike?.name}</p>
              <button onClick={resetGame} style={overlayBtnStyle}>もう一度</button>
            </div>
          )}
          {/* Win */}
          {gameWon && !keepPlaying && !gameOver && (
            <div className="bp-overlay">
              <p style={{ fontSize: '22px', fontWeight: '900', color: '#776e65', margin: '0 0 8px' }}>おめでとう！</p>
              <p style={{ fontSize: '14px', color: '#776e65', margin: '0 0 4px' }}>ゴールドウイング到達！</p>
              <p style={{ fontSize: '16px', color: '#776e65', margin: '0 0 16px' }}>スコア: {score.toLocaleString()}</p>
              <div style={{ display: 'flex', gap: '8px' }}>
                <button onClick={() => setKeepPlaying(true)} style={overlayBtnStyle}>続ける</button>
                <button onClick={resetGame} style={overlayBtnStyle}>リセット</button>
              </div>
            </div>
          )}
        </div>

        {/* Evolution Chart */}
        <div style={{ marginTop: '14px', backgroundColor: '#fff', borderRadius: '12px', padding: '10px 12px', boxShadow: '0 1px 3px rgba(0,0,0,0.08)' }}>
          <p style={{ fontSize: '11px', fontWeight: 'bold', color: '#999', margin: '0 0 6px' }}>進化チャート</p>
          <div className="bp-evo-strip">
            {BIKES.map((bike, i) => {
              const reached = i + 1 <= maxLevel;
              const isDark = i + 1 === 11;
              return (
                <div key={i} style={{
                  flexShrink: 0, width: '56px', height: '62px', borderRadius: '8px',
                  display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                  backgroundColor: bike.color, opacity: reached ? 1 : 0.3,
                  filter: reached ? 'none' : 'grayscale(1)', transition: 'opacity 0.3s, filter 0.3s',
                  padding: '3px 2px 2px', gap: '1px',
                }}>
                  <img src={`/images/puzzle/level-${i + 1}.png`} alt={bike.name} draggable={false}
                    style={{ width: '36px', height: '28px', objectFit: 'contain' }}
                    onError={(e) => { e.target.style.display = 'none'; }} />
                  <span style={{ fontSize: '7px', fontWeight: 'bold', color: isDark ? '#fff' : '#333', textAlign: 'center', lineHeight: 1.1 }}>
                    {bike.name}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        <ControlHint />
      </div>
    </div>
  );
}

// ── Sub-components ─────────────────────────────────────────
const HOW_TO_PLAY_KEY = 'motohub_puzzle_howto_seen';

function HowToPlay() {
  const [seen, setSeen] = useState(() => {
    try { return localStorage.getItem(HOW_TO_PLAY_KEY) === '1'; } catch { return false; }
  });
  const [open, setOpen] = useState(false);

  if (seen) return null;

  const dismiss = () => {
    try { localStorage.setItem(HOW_TO_PLAY_KEY, '1'); } catch {}
    setSeen(true);
  };

  return (
    <div style={{
      backgroundColor: '#eff6ff', borderRadius: '10px', padding: '8px 12px',
      marginBottom: '10px', fontSize: '13px', color: '#4b5563',
    }}>
      <button onClick={() => setOpen(o => !o)} style={{
        background: 'none', border: 'none', cursor: 'pointer', width: '100%',
        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        fontSize: '13px', fontWeight: 'bold', color: '#4b5563', padding: 0,
        WebkitTapHighlightColor: 'transparent',
      }}>
        <span>遊び方</span>
        <span style={{ fontSize: '10px' }}>{open ? '\u25B2' : '\u25BC'}</span>
      </button>
      {open && (
        <div style={{ marginTop: '6px', lineHeight: 1.7 }}>
          <p>・上下左右にスワイプ（PCは矢印キー）でタイルを移動</p>
          <p>・同じバイクがぶつかると合体してグレードアップ！</p>
          <p>・カブ50 → モンキー125 → PCX → ... → ゴールドウイングを目指そう</p>
          <p>・動かせなくなったらゲームオーバー</p>
          <button onClick={(e) => { e.stopPropagation(); dismiss(); setOpen(false); }} style={{
            marginTop: '6px', background: 'none', border: 'none', cursor: 'pointer',
            fontSize: '11px', color: '#9ca3af', textDecoration: 'underline', padding: 0,
          }}>閉じて次回から非表示</button>
        </div>
      )}
    </div>
  );
}

function ControlHint() {
  const isTouch = useMemo(() => {
    try { return window.matchMedia('(pointer: coarse)').matches; } catch { return false; }
  }, []);
  return (
    <p style={{ textAlign: 'center', fontSize: '11px', color: '#aaa', marginTop: '10px' }}>
      {isTouch ? 'スワイプで操作' : <><span style={{ fontFamily: 'monospace', letterSpacing: '2px' }}>← ↑ ↓ →</span> 矢印キーで操作</>}
    </p>
  );
}

function ScoreBox({ label, value }) {
  return (
    <div style={{ backgroundColor: '#bbada0', borderRadius: '6px', padding: '3px 10px', textAlign: 'center', minWidth: '56px' }}>
      <div style={{ fontSize: '9px', color: '#eee4da', fontWeight: 'bold', textTransform: 'uppercase' }}>{label}</div>
      <div style={{ fontSize: 'clamp(14px, 3.5vw, 18px)', fontWeight: '900', color: '#fff' }}>{value.toLocaleString()}</div>
    </div>
  );
}

const btnStyle = {
  padding: '8px 14px', backgroundColor: '#8f7a66', color: '#fff', border: 'none',
  borderRadius: '6px', fontWeight: 'bold', fontSize: '13px', cursor: 'pointer',
  WebkitTapHighlightColor: 'transparent',
};
const overlayBtnStyle = {
  padding: '10px 20px', backgroundColor: '#8f7a66', color: '#fff', border: 'none',
  borderRadius: '6px', fontWeight: 'bold', fontSize: '14px', cursor: 'pointer',
  WebkitTapHighlightColor: 'transparent',
};
