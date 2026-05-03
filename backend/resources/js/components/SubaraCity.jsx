import React, { useState, useEffect, useCallback, useRef, useMemo } from 'react';

// ── Bike data per maker × level ──────────────────────────
const BIKE_NAMES = {
  honda: [
    'スーパーカブ50',  // Lv1
    'モンキー125',    // Lv2
    'CT125',         // Lv3
    'PCX',           // Lv4
    'レブル250',      // Lv5
    'GB350',         // Lv6
    'CB400SF',       // Lv7
    'CBR600RR',      // Lv8
    'CB1300SF',      // Lv9
  ],
  yamaha: [
    'JOG',           // Lv1
    'セロー250',      // Lv2
    'SR400',         // Lv3
    'YZF-R3',        // Lv4
    'MT-07',         // Lv5
    'XSR700',        // Lv6
    'MT-09',         // Lv7
    'YZF-R6',        // Lv8
    'VMAX',          // Lv9
  ],
  kawasaki: [
    'KLX230',        // Lv1
    'エストレヤ',     // Lv2
    'Ninja250',      // Lv3
    'Ninja400',      // Lv4
    'W800',          // Lv5
    'Z900RS',        // Lv6
    'Ninja650',      // Lv7
    'ZX-10R',        // Lv8
    'H2',            // Lv9
  ],
  suzuki: [
    'アドレス',       // Lv1
    'ST250',         // Lv2
    'GSX250R',       // Lv3
    'SV650',         // Lv4
    'V-Strom',       // Lv5
    'GSX-S750',      // Lv6
    'SV1000',        // Lv7
    'ハヤブサ',       // Lv8
    'GSX-R1000R',    // Lv9
  ],
};

const WORLD_BIKES = {
  10: 'R1250GS',
  11: 'Panigale V4',
  12: 'ゴールドウイング',
};

const LEVEL_VALUES = [10, 20, 35, 55, 80, 100, 170, 200, 300, 500, 750, 1000];

function getBikeName(colorId, level) {
  if (level >= 10) return WORLD_BIKES[level] || WORLD_BIKES[12];
  const names = BIKE_NAMES[colorId];
  return names ? names[level - 1] || `Lv${level}` : `Lv${level}`;
}

function getBikeValue(level) {
  if (level <= LEVEL_VALUES.length) return LEVEL_VALUES[level - 1];
  return 1000 + (level - 12) * 500;
}

function bikeImagePath(colorId, level) {
  if (level >= 13) return '/images/subaracity/world_12.png';
  if (level >= 10) return `/images/subaracity/world_${level}.png`;
  return `/images/subaracity/${colorId}_${level}.png`;
}

// ── Maker colors ────────────────────────────────────────
const COLORS = [
  { id: 'honda',    label: 'ホンダ',   bg: '#EF4444', light: '#FEE2E2' },
  { id: 'yamaha',   label: 'ヤマハ',   bg: '#3B82F6', light: '#DBEAFE' },
  { id: 'kawasaki', label: 'カワサキ',  bg: '#22C55E', light: '#DCFCE7' },
  { id: 'suzuki',   label: 'スズキ',   bg: '#EAB308', light: '#FEF9C3' },
  { id: 'world',   label: '海外',     bg: '#F8FAFC', light: '#F1F5F9' },
];

function getColor(id) {
  return COLORS.find(c => c.id === id);
}

// ── Ranks ───────────────────────────────────────────────
const RANKS = [
  { min: 0,     rank: 'F', label: '免許取りたて' },
  { min: 100,   rank: 'E', label: '週末ライダー' },
  { min: 500,   rank: 'D', label: 'ツーリングマスター' },
  { min: 1000,  rank: 'C', label: 'ガレージオーナー' },
  { min: 3000,  rank: 'B', label: 'バイクコレクター' },
  { min: 5000,  rank: 'A', label: '伝説のライダー' },
  { min: 10000, rank: 'S', label: 'スバラシガレージ' },
];

function getRank(score) {
  for (let i = RANKS.length - 1; i >= 0; i--) {
    if (score >= RANKS[i].min) return RANKS[i];
  }
  return RANKS[0];
}

// ── ID counter ──────────────────────────────────────────
let nextId = 1;

// ── Grid helpers ────────────────────────────────────────
const GRID_SIZE = 5;

function randomColor(turn) {
  const count = turn < 30 ? 3 : 4;
  return COLORS[Math.floor(Math.random() * count)].id;
}

function createCell(colorId) {
  return { id: nextId++, colorId, level: 1 };
}

function initGrid() {
  const grid = [];
  for (let r = 0; r < GRID_SIZE; r++) {
    const row = [];
    for (let c = 0; c < GRID_SIZE; c++) {
      row.push(createCell(randomColor(0)));
    }
    grid.push(row);
  }
  return grid;
}

function cloneGrid(grid) {
  return grid.map(row => row.map(cell => cell ? { ...cell } : null));
}

function findConnected(grid, r, c) {
  const cell = grid[r][c];
  if (!cell) return new Set();
  // Lv11+ blocks (gold) are immovable — cannot merge
  if (cell.level >= 11) return new Set();
  const colorId = cell.colorId;
  const visited = new Set();
  const queue = [[r, c]];
  visited.add(`${r},${c}`);
  while (queue.length > 0) {
    const [cr, cc] = queue.shift();
    for (const [dr, dc] of [[0, 1], [0, -1], [1, 0], [-1, 0]]) {
      const nr = cr + dr;
      const nc = cc + dc;
      const key = `${nr},${nc}`;
      if (nr >= 0 && nr < GRID_SIZE && nc >= 0 && nc < GRID_SIZE && !visited.has(key)) {
        const neighbor = grid[nr][nc];
        // Skip Lv11+ blocks — they don't connect
        if (neighbor && neighbor.colorId === colorId && neighbor.level < 11) {
          visited.add(key);
          queue.push([nr, nc]);
        }
      }
    }
  }
  return visited;
}

function gravity(grid) {
  const newGrid = cloneGrid(grid);
  for (let c = 0; c < GRID_SIZE; c++) {
    const col = [];
    for (let r = GRID_SIZE - 1; r >= 0; r--) {
      if (newGrid[r][c]) col.push(newGrid[r][c]);
    }
    for (let r = GRID_SIZE - 1; r >= 0; r--) {
      newGrid[r][c] = col[GRID_SIZE - 1 - r] || null;
    }
  }
  return newGrid;
}

function gravityWithTracking(grid) {
  const newGrid = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(null));
  const movements = new Map(); // "r,c" → number of rows fallen
  for (let c = 0; c < GRID_SIZE; c++) {
    const cells = [];
    for (let r = 0; r < GRID_SIZE; r++) {
      if (grid[r][c]) cells.push({ cell: grid[r][c], origRow: r });
    }
    let writeRow = GRID_SIZE - 1;
    for (let i = cells.length - 1; i >= 0; i--) {
      newGrid[writeRow][c] = cells[i].cell;
      const distance = writeRow - cells[i].origRow;
      if (distance > 0) {
        movements.set(`${writeRow},${c}`, distance);
      }
      writeRow--;
    }
  }
  return { grid: newGrid, movements };
}

function fillEmpty(grid, turn) {
  const newGrid = cloneGrid(grid);
  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      if (!newGrid[r][c]) {
        newGrid[r][c] = createCell(randomColor(turn));
      }
    }
  }
  return newGrid;
}

function checkGameOver(grid, mechanicPoints) {
  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      if (!grid[r][c]) return false;
    }
  }
  if (mechanicPoints > 0) return false;
  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      const cell = grid[r][c];
      if (!cell) continue;
      // Lv11+ blocks are immovable — skip them
      if (cell.level >= 11) continue;
      for (const [dr, dc] of [[0, 1], [1, 0]]) {
        const nr = r + dr;
        const nc = c + dc;
        if (nr < GRID_SIZE && nc < GRID_SIZE) {
          const neighbor = grid[nr][nc];
          if (neighbor && neighbor.colorId === cell.colorId && neighbor.level < 11) return false;
        }
      }
    }
  }
  return true;
}

function computeScore(grid) {
  let total = 0;
  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      const cell = grid[r][c];
      if (cell) total += getBikeValue(cell.level);
    }
  }
  return total;
}

function getMaxLevelInfo(grid) {
  let max = 1;
  let colorId = 'honda';
  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      if (grid[r][c] && grid[r][c].level > max) {
        max = grid[r][c].level;
        colorId = grid[r][c].colorId;
      }
    }
  }
  return { level: max, colorId };
}

// ── localStorage ────────────────────────────────────────
const STORAGE_KEY = 'motohub_subaracity';

function saveData(bestScore, maxLevel) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ bestScore, maxLevel }));
  } catch {}
}

function loadData() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return { bestScore: 0, maxLevel: 1 };
    const data = JSON.parse(raw);
    return {
      bestScore: typeof data.bestScore === 'number' ? data.bestScore : 0,
      maxLevel: typeof data.maxLevel === 'number' ? data.maxLevel : 1,
    };
  } catch {
    return { bestScore: 0, maxLevel: 1 };
  }
}

// ── CSS injection ───────────────────────────────────────
const STYLE_ID = 'subaracity-styles';

function injectStyles() {
  if (document.getElementById(STYLE_ID)) return;
  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = `
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
  `;
  document.head.appendChild(style);
}

// ── Main component ──────────────────────────────────────
export default function SubaraCity() {
  // ── Core game state ──
  const [grid, setGrid] = useState(() => initGrid());
  const [turn, setTurn] = useState(0);
  const [mechanicPoints, setMechanicPoints] = useState(2);
  const [mechanicMode, setMechanicMode] = useState(false);
  const [selected, setSelected] = useState(null);
  const [highlighted, setHighlighted] = useState(new Set());
  const [gameOver, setGameOver] = useState(false);

  // ── Score / persistence ──
  const saved = useRef(loadData());
  const [bestScore, setBestScore] = useState(saved.current.bestScore);
  const [maxLevel, setMaxLevel] = useState(saved.current.maxLevel);

  // ── Animation state ──
  const [animPhase, setAnimPhase] = useState('idle');
  const [slideTarget, setSlideTarget] = useState(null);
  const [slideConnected, setSlideConnected] = useState(null);
  const [flashCell, setFlashCell] = useState(null);
  const [poppingCell, setPoppingCell] = useState(null);
  const [cellAnims, setCellAnims] = useState(new Map());
  const mergeDataRef = useRef(null);
  const animTimeoutsRef = useRef([]);
  const cellDimsRef = useRef({ w: 0, h: 0, gap: 4 });
  const gridRef = useRef(null);

  // ── Audio ──
  const [muted, setMuted] = useState(false);
  const mutedRef = useRef(false);
  const bgmRef = useRef(null);
  const bgmStartedRef = useRef(false);

  // ── UI ──
  const [showHowTo, setShowHowTo] = useState(false);
  const [announce, setAnnounce] = useState(null);

  const isAnimating = animPhase !== 'idle';

  useEffect(() => { injectStyles(); }, []);
  useEffect(() => { mutedRef.current = muted; }, [muted]);

  // ── BGM setup ──
  useEffect(() => {
    const audio = new Audio('/audio/puzzle/bgm.mp3');
    audio.loop = true;
    audio.volume = 0.25;
    bgmRef.current = audio;
    return () => { audio.pause(); audio.src = ''; };
  }, []);

  // ── BGM mute sync ──
  useEffect(() => {
    if (!bgmRef.current) return;
    if (muted) {
      bgmRef.current.pause();
    } else if (bgmStartedRef.current && !gameOver) {
      bgmRef.current.play().catch(() => {});
    }
  }, [muted, gameOver]);

  // ── Pause BGM on game over ──
  useEffect(() => {
    if (gameOver && bgmRef.current) bgmRef.current.pause();
  }, [gameOver]);

  const tryStartBgm = useCallback(() => {
    if (bgmStartedRef.current || mutedRef.current || !bgmRef.current) return;
    bgmRef.current.play().then(() => { bgmStartedRef.current = true; }).catch(() => {});
  }, []);

  // ── Persist best score ──
  useEffect(() => {
    saveData(bestScore, maxLevel);
  }, [bestScore, maxLevel]);

  // ── Sound ──
  const playSound = useCallback((path) => {
    if (mutedRef.current) return;
    try {
      const audio = new Audio(path);
      audio.volume = 0.5;
      audio.play().catch(() => {});
    } catch {}
  }, []);

  // ── Current score / rank ──
  const score = useMemo(() => computeScore(grid), [grid]);
  const rank = useMemo(() => getRank(score), [score]);
  const maxLevelInfo = useMemo(() => getMaxLevelInfo(grid), [grid]);

  useEffect(() => {
    if (score > bestScore) setBestScore(score);
  }, [score, bestScore]);

  useEffect(() => {
    if (maxLevelInfo.level > maxLevel) setMaxLevel(maxLevelInfo.level);
  }, [maxLevelInfo, maxLevel]);

  // ── Measure grid cells ──
  const measureCells = useCallback(() => {
    if (!gridRef.current) return;
    const rect = gridRef.current.getBoundingClientRect();
    const padding = 4;
    const gap = 4;
    const available = rect.width - padding * 2 - gap * (GRID_SIZE - 1);
    const cellSize = available / GRID_SIZE;
    cellDimsRef.current = { w: cellSize, h: cellSize, gap };
  }, []);

  // ── Cleanup timeouts on unmount ──
  useEffect(() => {
    return () => {
      animTimeoutsRef.current.forEach(clearTimeout);
    };
  }, []);

  // ── Toggle mute ──
  const toggleMute = useCallback(() => {
    setMuted(prev => {
      const next = !prev;
      if (!bgmRef.current) return next;
      if (next) {
        bgmRef.current.pause();
      } else if (bgmStartedRef.current) {
        bgmRef.current.play().catch(() => {});
      }
      return next;
    });
  }, []);

  // ── Cell tap handler ──
  const handleCellTap = useCallback((r, c) => {
    if (gameOver || isAnimating) return;
    if (!grid[r][c]) return;

    tryStartBgm();

    // Mechanic mode
    if (mechanicMode) {
      const newGrid = cloneGrid(grid);
      newGrid[r][c] = null;
      const afterGravity = gravity(newGrid);
      const afterFill = fillEmpty(afterGravity, turn);
      setGrid(afterFill);
      setMechanicPoints(p => p - 1);
      setMechanicMode(false);
      setSelected(null);
      setHighlighted(new Set());
      playSound('/audio/warashibe/se_click.mp3');

      setTimeout(() => {
        if (checkGameOver(afterFill, mechanicPoints - 1)) {
          setGameOver(true);
          playSound('/audio/puzzle/gameover.mp3');
        }
      }, 200);
      return;
    }

    // Re-tap selected → merge
    if (selected && selected.r === r && selected.c === c && highlighted.size >= 2) {
      performMerge(r, c, highlighted);
      return;
    }

    // Find connected
    const connected = findConnected(grid, r, c);
    if (connected.size >= 2) {
      setSelected({ r, c });
      setHighlighted(connected);
      playSound('/audio/warashibe/se_click.mp3');
    } else {
      setSelected(null);
      setHighlighted(new Set());
    }
  }, [grid, gameOver, isAnimating, mechanicMode, selected, highlighted, turn, mechanicPoints, playSound, tryStartBgm]);

  // ── Merge with animation sequence ──
  const performMerge = useCallback((r, c, connected) => {
    // Clear previous timeouts
    animTimeoutsRef.current.forEach(clearTimeout);
    animTimeoutsRef.current = [];

    // Measure grid cells
    measureCells();

    // Compute merge result: sum of all block levels
    let levelSum = 0;
    for (const key of connected) {
      const [cr, cc] = key.split(',').map(Number);
      if (grid[cr][cc]) levelSum += grid[cr][cc].level;
    }
    const blockCount = connected.size;
    const baseColorId = grid[r][c].colorId;

    let newLevel, colorId;
    if (baseColorId === 'world') {
      // White (Lv10) blocks: newLevel = count + 9
      newLevel = blockCount + 9;
      colorId = 'world';
    } else if (levelSum >= 10) {
      // Level sum reaches 10+ → becomes Lv10 white/world
      newLevel = 10;
      colorId = 'world';
    } else {
      // Colored blocks: newLevel = sum of levels (cap 9)
      newLevel = Math.min(levelSum, 9);
      colorId = baseColorId;
    }

    // Build merged grid
    const newGrid = cloneGrid(grid);
    for (const key of connected) {
      const [cr, cc] = key.split(',').map(Number);
      newGrid[cr][cc] = null;
    }
    newGrid[r][c] = { id: nextId++, colorId, level: newLevel };

    // Gravity with movement tracking
    const { grid: afterGravity, movements: gravityMovements } = gravityWithTracking(newGrid);
    const newTurn = turn + 1;

    // Find cells that will be filled
    const filledCells = new Set();
    for (let dr = 0; dr < GRID_SIZE; dr++) {
      for (let dc = 0; dc < GRID_SIZE; dc++) {
        if (!afterGravity[dr][dc]) filledCells.add(`${dr},${dc}`);
      }
    }
    const afterFill = fillEmpty(afterGravity, newTurn);

    let newMechanicPts = mechanicPoints;
    if (newTurn > 0 && newTurn % 100 === 0) newMechanicPts += 1;

    // Build cascade animation data
    const dims = cellDimsRef.current;
    const cellStep = dims.w + dims.gap;
    const anims = new Map();
    let maxGravityEnd = 0;

    // Gravity fall: per column, top to bottom, staggered 30ms
    for (let col = 0; col < GRID_SIZE; col++) {
      let order = 0;
      for (let row = 0; row < GRID_SIZE; row++) {
        const k = `${row},${col}`;
        const dist = gravityMovements.get(k);
        if (dist) {
          const delay = order * 30;
          anims.set(k, { type: 'gravity', fallY: `${-dist * cellStep}px`, delay });
          maxGravityEnd = Math.max(maxGravityEnd, delay + 200);
          order++;
        }
      }
    }

    // New fill drop: per column, top to bottom, staggered 40ms, starts after gravity
    let maxFillEnd = 0;
    for (let col = 0; col < GRID_SIZE; col++) {
      let order = 0;
      for (let row = 0; row < GRID_SIZE; row++) {
        const k = `${row},${col}`;
        if (filledCells.has(k)) {
          const delay = maxGravityEnd + order * 40;
          anims.set(k, { type: 'fill', fillY: `${-2 * cellStep}px`, delay });
          maxFillEnd = Math.max(maxFillEnd, delay + 300);
          order++;
        }
      }
    }

    const totalDropDuration = Math.max(maxGravityEnd, maxFillEnd, 300);

    // Store for later phases
    mergeDataRef.current = { afterFill, newTurn, newMechanicPts, newLevel, anims, totalDropDuration, targetKey: `${r},${c}` };

    // === Phase 1: Slide (300ms) ===
    playSound('/audio/warashibe/se_craft.mp3');
    setSlideTarget({ r, c });
    setSlideConnected(connected);
    setSelected(null);
    setHighlighted(new Set());
    setAnimPhase('sliding');

    // === Phase 2: Flash (200ms) ===
    const t1 = setTimeout(() => {
      setAnimPhase('flash');
      setFlashCell(`${r},${c}`);
      if (newLevel >= 5) playSound('/audio/warashibe/se_unlock.mp3');
    }, 300);

    // === Phase 3: Pop + gravity cascade + fill cascade ===
    const t2 = setTimeout(() => {
      setFlashCell(null);
      setSlideTarget(null);
      setSlideConnected(null);
      setGrid(afterFill);
      setTurn(newTurn);
      setMechanicPoints(newMechanicPts);
      setPoppingCell(`${r},${c}`);
      setCellAnims(anims);
      setAnimPhase('result');
    }, 500);

    // === Phase 4: Cleanup (after all cascade animations finish) ===
    const cleanupDelay = 500 + totalDropDuration + 50;
    const t3 = setTimeout(() => {
      setPoppingCell(null);
      setCellAnims(new Map());
      setAnimPhase('idle');
      mergeDataRef.current = null;

      if (checkGameOver(afterFill, newMechanicPts)) {
        setGameOver(true);
        playSound('/audio/puzzle/gameover.mp3');
      }
    }, cleanupDelay);

    // === スズキ参戦アナウンス ===
    let t4 = null;
    let t5 = null;
    if (newTurn === 30) {
      t4 = setTimeout(() => {
        setAnnounce('in');
        playSound('/audio/warashibe/se_unlock.mp3');
      }, cleanupDelay + 100);
      t5 = setTimeout(() => {
        setAnnounce('out');
        setTimeout(() => setAnnounce(null), 300);
      }, cleanupDelay + 1100);
    }

    animTimeoutsRef.current = [t1, t2, t3, t4, t5].filter(Boolean);
  }, [grid, turn, mechanicPoints, playSound, measureCells]);

  // ── Reset game ──
  const resetGame = useCallback(() => {
    animTimeoutsRef.current.forEach(clearTimeout);
    animTimeoutsRef.current = [];
    nextId = 1;
    setGrid(initGrid());
    setTurn(0);
    setMechanicPoints(2);
    setMechanicMode(false);
    setSelected(null);
    setHighlighted(new Set());
    setGameOver(false);
    setAnimPhase('idle');
    setSlideTarget(null);
    setSlideConnected(null);
    setFlashCell(null);
    setPoppingCell(null);
    setCellAnims(new Map());
    setAnnounce(null);
    mergeDataRef.current = null;

    if (bgmRef.current && !mutedRef.current) {
      bgmRef.current.currentTime = 0;
      bgmRef.current.play().then(() => { bgmStartedRef.current = true; }).catch(() => {});
    }
  }, []);

  // ── Share ──
  const shareText = useMemo(() => {
    return `\u{1F3CD} MotoHub\u306E\u30D0\u30A4\u30AF\u30AC\u30EC\u30FC\u30B8\u30D1\u30BA\u30EB\u3067\u7DCF\u984D${score}\u4E07\u5186\u306E\u30AC\u30EC\u30FC\u30B8\u3092\u9054\u6210\uFF01\u30E9\u30F3\u30AF: ${rank.rank} #MotoHub #\u30D0\u30A4\u30AF\u597D\u304D\u3068\u7E4B\u304C\u308A\u305F\u3044 motohub.jp/games/subaracity`;
  }, [score, rank]);

  const shareUrl = useMemo(() => {
    return `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}`;
  }, [shareText]);

  const maxBikeName = getBikeName(maxLevelInfo.colorId, maxLevelInfo.level);

  // ── Compute slide transforms for rendering ──
  const getSlideStyle = useCallback((r, c) => {
    if ((animPhase !== 'sliding' && animPhase !== 'flash') || !slideTarget || !slideConnected?.has(`${r},${c}`)) return null;
    if (r === slideTarget.r && c === slideTarget.c) return null; // target stays
    const dims = cellDimsRef.current;
    const dx = (slideTarget.c - c) * (dims.w + dims.gap);
    const dy = (slideTarget.r - r) * (dims.h + dims.gap);
    return { '--sc-dx': `${dx}px`, '--sc-dy': `${dy}px` };
  }, [animPhase, slideTarget, slideConnected]);

  // ── Render ──
  return (
    <div style={{
      minHeight: 'calc(100vh - 64px)',
      backgroundColor: '#0f172a',
      padding: '12px 8px 24px',
      fontFamily: "'Noto Sans JP', sans-serif",
      color: '#e2e8f0',
    }}>
      <div style={{ maxWidth: '420px', margin: '0 auto' }}>

        {/* Header */}
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <h1 style={{ fontSize: 'clamp(18px, 5vw, 24px)', fontWeight: '900', color: '#f1f5f9', margin: 0 }}>
              バイクガレージ
            </h1>
            <button onClick={toggleMute} aria-label={muted ? '音声ON' : '音声OFF'} style={{
              background: 'none', border: 'none', fontSize: '18px', cursor: 'pointer',
              padding: '2px', lineHeight: 1, color: '#94a3b8', WebkitTapHighlightColor: 'transparent',
            }}>
              {muted ? '\u{1F507}' : '\u{1F50A}'}
            </button>
          </div>
          <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
            <InfoBox label="\u30BF\u30FC\u30F3" value={`${turn}\u5E74\u76EE`} />
            <InfoBox label="\u{1F527}" value={`\u00D7${mechanicPoints}`} />
          </div>
        </div>

        {/* Controls */}
        <div style={{ display: 'flex', gap: '8px', marginBottom: '10px' }}>
          <button onClick={resetGame} style={btnStyle}>リセット</button>
          <button
            onClick={() => {
              if (mechanicPoints > 0 && !isAnimating) {
                setMechanicMode(m => !m);
                setSelected(null);
                setHighlighted(new Set());
              }
            }}
            disabled={mechanicPoints <= 0 || isAnimating}
            style={{
              ...btnStyle,
              backgroundColor: mechanicMode ? '#f59e0b' : '#475569',
              opacity: (mechanicPoints <= 0 || isAnimating) ? 0.4 : 1,
              cursor: (mechanicPoints <= 0 || isAnimating) ? 'default' : 'pointer',
            }}
          >
            {mechanicMode ? 'メカニック中…' : 'メカニック\u{1F527}'}
          </button>
        </div>

        {/* Mechanic mode indicator */}
        {mechanicMode && (
          <div style={{
            textAlign: 'center', padding: '6px', marginBottom: '8px',
            backgroundColor: '#f59e0b', borderRadius: '8px', color: '#000',
            fontSize: '13px', fontWeight: 'bold',
          }}>
            消去するブロックをタップしてください
          </div>
        )}

        {/* Grid */}
        <div style={{ position: 'relative' }}>
          <div className="sc-grid" ref={gridRef} style={{ opacity: gameOver ? 0.3 : 1, transition: 'opacity 0.5s' }}>
            {grid.map((row, r) =>
              row.map((cell, c) => {
                const key = `${r},${c}`;

                if (!cell) {
                  return <div key={key} className="sc-cell sc-cell-empty" />;
                }

                const color = getColor(cell.colorId);
                const bikeName = getBikeName(cell.colorId, cell.level);
                const isHighlighted = highlighted.has(key);
                const isSlideTarget = slideTarget && slideTarget.r === r && slideTarget.c === c;

                // Determine animation class and style
                let animClass = '';
                let cellAnimStyle = {};
                const slideStyle = getSlideStyle(r, c);
                const animData = cellAnims.get(key);

                if (slideStyle) {
                  animClass = 'sc-cell-sliding';
                } else if (isSlideTarget && (animPhase === 'sliding' || animPhase === 'flash')) {
                  animClass = 'sc-cell-target-glow';
                } else if (poppingCell === key) {
                  animClass = 'sc-cell-pop';
                } else if (animData) {
                  if (animData.type === 'gravity') {
                    animClass = 'sc-cell-gravity';
                    cellAnimStyle = { '--sc-fall-y': animData.fallY, '--sc-fall-delay': `${animData.delay}ms` };
                  } else if (animData.type === 'fill') {
                    animClass = 'sc-cell-fill';
                    cellAnimStyle = { '--sc-fill-y': animData.fillY, '--sc-fill-delay': `${animData.delay}ms` };
                  }
                } else if (isHighlighted) {
                  animClass = 'sc-cell-highlight';
                }

                return (
                  <div
                    key={key}
                    className={`sc-cell ${animClass}`}
                    style={{
                      backgroundColor: cell.level >= 11 ? '#78350f' : (color ? color.bg : '#475569'),
                      border: cell.level >= 11 ? '2px solid #fbbf24' : cell.colorId === 'world' ? '2px solid #94a3b8' : 'none',
                      cursor: isAnimating ? 'default' : mechanicMode ? 'crosshair' : cell.level >= 11 ? 'not-allowed' : 'pointer',
                      opacity: cell.level >= 11 ? 0.9 : 1,
                      ...(slideStyle || {}),
                      ...cellAnimStyle,
                    }}
                    onClick={() => handleCellTap(r, c)}
                  >
                    <img
                      src={bikeImagePath(cell.colorId, cell.level)}
                      alt={bikeName}
                      draggable={false}
                      style={{
                        width: 'clamp(28px, 8vw, 48px)',
                        height: 'clamp(22px, 6vw, 36px)',
                        objectFit: 'contain',
                        pointerEvents: 'none',
                      }}
                      onError={(e) => { e.target.style.display = 'none'; }}
                    />
                    <span style={{
                      position: 'absolute',
                      top: '2px',
                      right: '3px',
                      fontSize: '8px',
                      fontWeight: 'bold',
                      color: cell.level >= 11 ? '#fbbf24' : cell.colorId === 'world' ? '#1e293b' : '#fff',
                      backgroundColor: cell.level >= 11 ? 'rgba(0,0,0,0.6)' : cell.colorId === 'world' ? 'rgba(255,255,255,0.8)' : 'rgba(0,0,0,0.4)',
                      borderRadius: '3px',
                      padding: '0 3px',
                      lineHeight: '14px',
                    }}>
                      Lv{cell.level}
                    </span>
                    <span style={{
                      fontSize: 'clamp(6px, 1.6vw, 9px)',
                      fontWeight: 'bold',
                      color: cell.level >= 11 ? '#fbbf24' : cell.colorId === 'world' ? '#1e293b' : '#fff',
                      lineHeight: 1,
                      textAlign: 'center',
                      textShadow: cell.level >= 11 ? '0 1px 2px rgba(0,0,0,0.8)' : cell.colorId === 'world' ? 'none' : '0 1px 2px rgba(0,0,0,0.5)',
                      wordBreak: 'keep-all',
                    }}>
                      {bikeName}
                    </span>
                    {/* Flash overlay */}
                    {flashCell === key && <div className="sc-flash-overlay" />}
                  </div>
                );
              })
            )}
          </div>

          {/* Game Over Overlay */}
          {gameOver && (
            <div className="sc-overlay">
              <p style={{ fontSize: '28px', fontWeight: '900', color: '#f1f5f9', margin: '0 0 8px' }}>GAME OVER</p>
              <p style={{ fontSize: '16px', color: '#94a3b8', margin: '0 0 4px' }}>
                ガレージ総額: <span style={{ color: '#fbbf24', fontWeight: 'bold' }}>{score.toLocaleString()}万円</span>
              </p>
              <p style={{ fontSize: '14px', color: '#94a3b8', margin: '0 0 4px' }}>
                ランク: <span style={{ fontWeight: 'bold', color: '#f1f5f9' }}>{rank.rank}</span> — {rank.label}
              </p>
              <p style={{ fontSize: '13px', color: '#64748b', margin: '0 0 16px' }}>
                最高レベル: {maxBikeName}
              </p>
              <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', justifyContent: 'center' }}>
                <button onClick={resetGame} style={overlayBtnStyle}>もう一度遊ぶ</button>
                <a href={shareUrl} target="_blank" rel="noopener noreferrer" style={{
                  ...overlayBtnStyle,
                  backgroundColor: '#1d9bf0',
                  textDecoration: 'none',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '4px',
                }}>
                  Xでシェア
                </a>
              </div>
            </div>
          )}
        </div>

        {/* スズキ参戦アナウンス */}
        {announce && (
          <div className="sc-announce-overlay">
            <div className={`sc-announce-box ${announce === 'out' ? 'leaving' : ''}`} style={{
              background: 'linear-gradient(135deg, #EAB308 0%, #CA8A04 100%)',
              border: '3px solid #FDE047',
              boxShadow: '0 0 40px rgba(234,179,8,0.5)',
            }}>
              <div style={{ fontSize: '32px', marginBottom: '4px' }}>{'\u{1F3CD}'}</div>
              <div style={{ fontSize: '22px', fontWeight: '900', color: '#fff', textShadow: '0 2px 4px rgba(0,0,0,0.3)' }}>
                スズキ参戦！
              </div>
              <div style={{ fontSize: '13px', color: '#FEFCE8', marginTop: '4px', fontWeight: 'bold' }}>
                4色目の黄色ブロックが出現！
              </div>
            </div>
          </div>
        )}

        {/* Score Section */}
        <div style={{
          marginTop: '12px', backgroundColor: '#1e293b', borderRadius: '12px',
          padding: '10px 14px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        }}>
          <div>
            <span style={{ fontSize: '11px', color: '#64748b' }}>ガレージ総額</span>
            <p style={{ fontSize: 'clamp(18px, 5vw, 24px)', fontWeight: '900', color: '#fbbf24', margin: '2px 0 0' }}>
              {score.toLocaleString()}<span style={{ fontSize: '12px', color: '#94a3b8' }}>万円</span>
            </p>
          </div>
          <div style={{ textAlign: 'center' }}>
            <span style={{ fontSize: '11px', color: '#64748b' }}>ランク</span>
            <p style={{ fontSize: '20px', fontWeight: '900', color: '#f1f5f9', margin: '2px 0 0' }}>
              {rank.rank}
            </p>
            <span style={{ fontSize: '10px', color: '#94a3b8' }}>{rank.label}</span>
          </div>
          <div style={{ textAlign: 'right' }}>
            <span style={{ fontSize: '11px', color: '#64748b' }}>BEST</span>
            <p style={{ fontSize: '16px', fontWeight: '900', color: '#94a3b8', margin: '2px 0 0' }}>
              {bestScore.toLocaleString()}<span style={{ fontSize: '10px' }}>万円</span>
            </p>
          </div>
        </div>

        {/* Collection strip — by level */}
        <div style={{
          marginTop: '12px', backgroundColor: '#1e293b', borderRadius: '12px',
          padding: '10px 12px',
        }}>
          <p style={{ fontSize: '11px', fontWeight: 'bold', color: '#64748b', margin: '0 0 6px' }}>レベル図鑑</p>
          <div className="sc-evo-strip">
            {Array.from({ length: 12 }, (_, i) => {
              const lv = i + 1;
              const reached = lv <= maxLevel;
              const name = lv >= 10 ? WORLD_BIKES[lv] : getBikeName('honda', lv);
              const imgPath = lv >= 10 ? `/images/subaracity/world_${lv}.png` : `/images/subaracity/honda_${lv}.png`;
              return (
                <div key={lv} style={{
                  flexShrink: 0, width: '56px', height: '66px', borderRadius: '8px',
                  display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                  backgroundColor: '#334155', opacity: reached ? 1 : 0.3,
                  filter: reached ? 'none' : 'grayscale(1)', transition: 'opacity 0.3s, filter 0.3s',
                  padding: '3px 2px 2px', gap: '2px',
                }}>
                  <img src={imgPath} alt={name} draggable={false}
                    style={{ width: '36px', height: '28px', objectFit: 'contain' }}
                    onError={(e) => { e.target.style.display = 'none'; }} />
                  <span style={{ fontSize: '7px', fontWeight: 'bold', color: '#e2e8f0', textAlign: 'center', lineHeight: 1.1 }}>
                    {name}
                  </span>
                  <span style={{ fontSize: '7px', color: '#94a3b8' }}>
                    Lv{lv} / {getBikeValue(lv)}万円
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        {/* Game Over: bike links */}
        {gameOver && (
          <div style={{
            marginTop: '12px', backgroundColor: '#1e293b', borderRadius: '12px',
            padding: '10px 14px',
          }}>
            <p style={{ fontSize: '12px', fontWeight: 'bold', color: '#64748b', margin: '0 0 8px' }}>
              登場バイクの実際の相場を見る
            </p>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
              {COLORS.filter(color => BIKE_NAMES[color.id]).map(color =>
                BIKE_NAMES[color.id].slice(0, 5).map((name, i) => (
                  <a key={`${color.id}-${i}`} href={`/bikes/search?q=${encodeURIComponent(name)}`}
                    style={{
                      fontSize: '11px', color: '#60a5fa', textDecoration: 'none',
                      padding: '4px 8px', backgroundColor: '#0f172a', borderRadius: '6px',
                    }}
                  >
                    {name}
                  </a>
                ))
              )}
            </div>
          </div>
        )}

        {/* How-to button */}
        <div style={{ textAlign: 'center', marginTop: '14px' }}>
          <button
            onClick={() => setShowHowTo(true)}
            style={{
              ...btnStyle,
              backgroundColor: '#334155',
              fontSize: '14px',
              padding: '10px 24px',
              borderRadius: '8px',
            }}
          >
            遊び方
          </button>
        </div>

        {/* Footer text */}
        <p style={{ textAlign: 'center', fontSize: '11px', color: '#475569', marginTop: '8px' }}>
          同じメーカー色の隣接ブロックをタップして合体！
        </p>
      </div>

      {/* How-to Modal */}
      {showHowTo && <HowToModal onClose={() => setShowHowTo(false)} />}
    </div>
  );
}

// ── How-to Modal ────────────────────────────────────────
function HowToModal({ onClose }) {
  return (
    <div className="sc-modal-backdrop" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="sc-modal">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
          <h2 style={{ fontSize: '18px', fontWeight: '900', margin: 0, color: '#f1f5f9' }}>遊び方</h2>
          <button onClick={onClose} style={{
            background: 'none', border: 'none', color: '#94a3b8', fontSize: '22px',
            cursor: 'pointer', padding: '0 4px', lineHeight: 1,
          }}>&times;</button>
        </div>

        {/* SVG Diagram */}
        <div style={{ marginBottom: '16px', backgroundColor: '#0f172a', borderRadius: '12px', padding: '12px' }}>
          <svg viewBox="0 0 290 110" xmlns="http://www.w3.org/2000/svg" style={{ width: '100%', height: 'auto' }}>
            {/* Before grid */}
            <text x="52" y="12" textAnchor="middle" fill="#94a3b8" fontSize="9" fontWeight="bold">1. タップで選択</text>
            <rect x="5" y="18" width="95" height="88" rx="8" fill="#1e293b" />
            {/* Row 1 */}
            <rect x="10" y="22" width="26" height="26" rx="5" fill="#3B82F6" />
            <text x="23" y="38" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="39" y="22" width="26" height="26" rx="5" fill="#22C55E" />
            <text x="52" y="38" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="68" y="22" width="26" height="26" rx="5" fill="#EAB308" />
            <text x="81" y="38" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            {/* Row 2 - two honda highlighted */}
            <rect x="10" y="51" width="26" height="26" rx="5" fill="#EF4444" stroke="#fff" strokeWidth="2" />
            <text x="23" y="68" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="39" y="51" width="26" height="26" rx="5" fill="#EF4444" stroke="#fff" strokeWidth="2" />
            <text x="52" y="68" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="68" y="51" width="26" height="26" rx="5" fill="#3B82F6" />
            <text x="81" y="68" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            {/* Row 3 */}
            <rect x="10" y="80" width="26" height="26" rx="5" fill="#EF4444" stroke="#fff" strokeWidth="2" />
            <text x="23" y="97" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="39" y="80" width="26" height="26" rx="5" fill="#22C55E" />
            <text x="52" y="97" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="68" y="80" width="26" height="26" rx="5" fill="#EAB308" />
            <text x="81" y="97" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>

            {/* Arrow */}
            <text x="125" y="68" textAnchor="middle" fill="#fbbf24" fontSize="22" fontWeight="bold">{'\u2192'}</text>
            <text x="125" y="82" textAnchor="middle" fill="#64748b" fontSize="7">合体！</text>

            {/* After grid */}
            <text x="220" y="12" textAnchor="middle" fill="#94a3b8" fontSize="9" fontWeight="bold">2. レベルアップ！</text>
            <rect x="175" y="18" width="95" height="88" rx="8" fill="#1e293b" />
            {/* Row 1 - new blocks dropped */}
            <rect x="180" y="22" width="26" height="26" rx="5" fill="#22C55E" opacity="0.6" />
            <text x="193" y="38" textAnchor="middle" fill="#fff" fontSize="5">NEW</text>
            <rect x="209" y="22" width="26" height="26" rx="5" fill="#22C55E" />
            <text x="222" y="38" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="238" y="22" width="26" height="26" rx="5" fill="#EAB308" />
            <text x="251" y="38" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            {/* Row 2 */}
            <rect x="180" y="51" width="26" height="26" rx="5" fill="#3B82F6" />
            <text x="193" y="68" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="209" y="51" width="26" height="26" rx="5" fill="#EF4444" />
            <text x="222" y="68" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv2</text>
            <rect x="238" y="51" width="26" height="26" rx="5" fill="#3B82F6" />
            <text x="251" y="68" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            {/* Row 3 */}
            <rect x="180" y="80" width="26" height="26" rx="5" fill="#3B82F6" opacity="0.6" />
            <text x="193" y="97" textAnchor="middle" fill="#fff" fontSize="5">NEW</text>
            <rect x="209" y="80" width="26" height="26" rx="5" fill="#22C55E" />
            <text x="222" y="97" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>
            <rect x="238" y="80" width="26" height="26" rx="5" fill="#EAB308" />
            <text x="251" y="97" textAnchor="middle" fill="#fff" fontSize="6" fontWeight="bold">Lv1</text>

            {/* Highlight Lv2 */}
            <rect x="207" y="49" width="30" height="30" rx="6" fill="none" stroke="#fbbf24" strokeWidth="2" strokeDasharray="3,2" />
          </svg>
        </div>

        {/* Rules */}
        <div style={{ fontSize: '13px', lineHeight: 2, color: '#cbd5e1' }}>
          <p style={{ display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
            <span style={{ color: '#fbbf24', fontWeight: 'bold', flexShrink: 0 }}>1.</span>
            同じ色（メーカー）のバイクをタップして選択、もう一度タップで合体！
          </p>
          <p style={{ display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
            <span style={{ color: '#fbbf24', fontWeight: 'bold', flexShrink: 0 }}>2.</span>
            合体するとレベルアップして上位バイクに変化
          </p>
          <p style={{ display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
            <span style={{ color: '#fbbf24', fontWeight: 'bold', flexShrink: 0 }}>3.</span>
            Lv10以上は全メーカー共通の世界的名車に進化！
          </p>
          <p style={{ display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
            <span style={{ color: '#fbbf24', fontWeight: 'bold', flexShrink: 0 }}>4.</span>
            <span>メカニックポイント（<span style={{ fontSize: '15px' }}>{'\u{1F527}'}</span>）で邪魔なブロックを1つ消せる</span>
          </p>
          <p style={{ display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
            <span style={{ color: '#fbbf24', fontWeight: 'bold', flexShrink: 0 }}>5.</span>
            100ターンごとにメカニックポイント+1
          </p>
          <p style={{ display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
            <span style={{ color: '#fbbf24', fontWeight: 'bold', flexShrink: 0 }}>6.</span>
            ガレージ総額を増やして「スバラシガレージ」ランクを目指そう！
          </p>
        </div>

        {/* Maker color legend */}
        <div style={{
          marginTop: '14px', padding: '10px', backgroundColor: '#0f172a',
          borderRadius: '10px', fontSize: '12px',
        }}>
          <p style={{ fontWeight: 'bold', color: '#64748b', margin: '0 0 6px' }}>メーカーカラー</p>
          <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
            {COLORS.map(c => (
              <div key={c.id} style={{
                display: 'flex', alignItems: 'center', gap: '4px',
              }}>
                <span style={{
                  width: '14px', height: '14px', borderRadius: '4px',
                  backgroundColor: c.bg, display: 'inline-block',
                  border: c.id === 'world' ? '1px solid #94a3b8' : 'none',
                }} />
                <span style={{ color: '#94a3b8' }}>{c.label}</span>
              </div>
            ))}
          </div>
          <p style={{ color: '#64748b', fontSize: '11px', margin: '6px 0 0' }}>
            ※ 30ターン目からスズキ（黄��も出現！
          </p>
          <p style={{ color: '#64748b', fontSize: '11px', margin: '4px 0 0' }}>
            ※ 合体レベル = ブロックのレベル合計（例: Lv3+Lv2=Lv5）{'\n'}
            ※ 合計10以上 → 海外バイク（白Lv10）に進化！{'\n'}
            ※ 白Lv10同士の合体 → 金ブロック（Lv11+）に！{'\n'}
            ※ 金ブロックは合体不可（メカニックでのみ消去可能）
          </p>
        </div>

        {/* Close button */}
        <button onClick={onClose} style={{
          ...overlayBtnStyle,
          width: '100%',
          marginTop: '16px',
          backgroundColor: '#3b82f6',
        }}>
          閉じる
        </button>
      </div>
    </div>
  );
}

// ── Sub-components ──────────────────────────────────────
function InfoBox({ label, value }) {
  return (
    <div style={{
      backgroundColor: '#1e293b', borderRadius: '6px', padding: '3px 10px',
      textAlign: 'center', minWidth: '50px',
    }}>
      <div style={{ fontSize: '9px', color: '#64748b', fontWeight: 'bold' }}>{label}</div>
      <div style={{ fontSize: 'clamp(12px, 3vw, 15px)', fontWeight: '900', color: '#f1f5f9' }}>{value}</div>
    </div>
  );
}

const btnStyle = {
  padding: '8px 14px', backgroundColor: '#475569', color: '#e2e8f0', border: 'none',
  borderRadius: '6px', fontWeight: 'bold', fontSize: '13px', cursor: 'pointer',
  WebkitTapHighlightColor: 'transparent',
};

const overlayBtnStyle = {
  padding: '10px 20px', backgroundColor: '#475569', color: '#fff', border: 'none',
  borderRadius: '8px', fontWeight: 'bold', fontSize: '14px', cursor: 'pointer',
  WebkitTapHighlightColor: 'transparent',
};
