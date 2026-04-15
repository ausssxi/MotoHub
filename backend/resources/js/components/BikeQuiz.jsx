import { useState, useEffect, useCallback, useRef } from "react";

// ── Fallback Quiz Data (used when API is unavailable) ──
const FALLBACK_QUESTIONS = {
  price: [
    { q: "ホンダ CB400SFの中古平均価格は？", choices: ["約45万円", "約58万円", "約72万円", "約85万円"], answer: 1 },
    { q: "カワサキ Z900RSの中古平均価格は？", choices: ["約95万円", "約110万円", "約128万円", "約145万円"], answer: 2 },
    { q: "ヤマハ YZF-R25の中古平均価格は？", choices: ["約28万円", "約38万円", "約48万円", "約58万円"], answer: 1 },
    { q: "スズキ GSX-S1000の中古平均価格は？", choices: ["約65万円", "約78万円", "約92万円", "約105万円"], answer: 2 },
    { q: "ホンダ PCX160の中古平均価格は？", choices: ["約22万円", "約30万円", "約38万円", "約45万円"], answer: 1 },
    { q: "カワサキ Ninja400の中古平均価格は？", choices: ["約42万円", "約55万円", "約68万円", "約80万円"], answer: 1 },
    { q: "ヤマハ MT-07の中古平均価格は？", choices: ["約48万円", "約58万円", "約68万円", "約78万円"], answer: 1 },
    { q: "ホンダ レブル250の中古平均価格は？", choices: ["約38万円", "約48万円", "約58万円", "約68万円"], answer: 2 },
    { q: "スズキ ジクサー150の中古平均価格は？", choices: ["約18万円", "約25万円", "約33万円", "約40万円"], answer: 1 },
    { q: "カワサキ ZX-25Rの中古平均価格は？", choices: ["約55万円", "約68万円", "約78万円", "約90万円"], answer: 2 },
  ],
  ranking: [
    { q: "2026年3月の中古バイク売れ筋1位は？", choices: ["PCX", "レブル250", "Z900RS", "CB400SF"], answer: 0 },
    { q: "250ccクラスで最も売れているのは？", choices: ["Ninja250", "YZF-R25", "レブル250", "CBR250RR"], answer: 2 },
    { q: "大型バイクで売れ筋TOP3に入らないのは？", choices: ["Z900RS", "GB350", "ハヤブサ", "CB1300SF"], answer: 3 },
    { q: "スクーターで最も中古流通が多いのは？", choices: ["TMAX", "PCX", "フォルツァ", "マジェスティ"], answer: 1 },
    { q: "アドベンチャー系で一番人気は？", choices: ["Vストローム650", "テネレ700", "アフリカツイン", "Vストローム250"], answer: 2 },
    { q: "カワサキ車で最も売れているのは？", choices: ["Z900RS", "Ninja400", "ZX-6R", "W800"], answer: 0 },
    { q: "ヤマハ車で中古流通量1位は？", choices: ["MT-09", "YZF-R25", "セロー250", "SR400"], answer: 3 },
    { q: "レトロ系バイクで最も人気なのは？", choices: ["W800", "Z900RS", "SV650X", "XSR700"], answer: 1 },
    { q: "原付二種で売れ筋1位は？", choices: ["アドレス125", "PCX", "Dio110", "NMAX"], answer: 1 },
    { q: "2025年に最も値上がりした車種は？", choices: ["RZ250", "NSR250R", "TZR250", "Γ250"], answer: 1 },
  ],
  speed: [
    { q: "レブル250の平均売却日数は？", choices: ["約7日", "約14日", "約21日", "約30日"], answer: 0 },
    { q: "Z900RSの平均売却日数は？", choices: ["約5日", "約10日", "約18日", "約25日"], answer: 1 },
    { q: "最も売却が早い価格帯は？", choices: ["〜30万円", "30〜60万円", "60〜100万円", "100万円〜"], answer: 0 },
    { q: "ハーレー スポーツスターの平均売却日数は？", choices: ["約10日", "約20日", "約35日", "約50日"], answer: 2 },
    { q: "大型SSの平均売却日数は？", choices: ["約12日", "約22日", "約32日", "約42日"], answer: 1 },
    { q: "オフロード車の平均売却日数は？", choices: ["約8日", "約15日", "約25日", "約35日"], answer: 2 },
    { q: "旧車（20年以上）の平均売却日数は？", choices: ["約15日", "約30日", "約45日", "約60日"], answer: 2 },
    { q: "PCXの平均売却日数は？", choices: ["約5日", "約10日", "約15日", "約20日"], answer: 0 },
    { q: "春（3〜5月）に最も売却が早い車種は？", choices: ["CB400SF", "セロー250", "PCX", "Ninja400"], answer: 2 },
    { q: "売却日数が最も長いカテゴリは？", choices: ["ネイキッド", "SS", "アメリカン", "ビッグスクーター"], answer: 3 },
  ],
  region: [
    { q: "東京都で最も人気のバイクは？", choices: ["PCX", "レブル250", "Z900RS", "CB400SF"], answer: 0 },
    { q: "北海道で最も人気のカテゴリは？", choices: ["SS", "アドベンチャー", "ネイキッド", "スクーター"], answer: 1 },
    { q: "大阪で中古流通量が最も多いメーカーは？", choices: ["ホンダ", "ヤマハ", "カワサキ", "スズキ"], answer: 0 },
    { q: "沖縄で最も人気のある排気量帯は？", choices: ["〜125cc", "126〜250cc", "251〜400cc", "401cc〜"], answer: 0 },
    { q: "愛知県で人気1位のバイクは？", choices: ["Z900RS", "Ninja400", "CB400SF", "MT-07"], answer: 0 },
    { q: "福岡で最も売れているスクーターは？", choices: ["PCX", "NMAX", "フォルツァ", "アドレス125"], answer: 0 },
    { q: "バイク保有台数が最も多い都道府県は？", choices: ["東京都", "愛知県", "大阪府", "神奈川県"], answer: 0 },
    { q: "レブル250が最も売れている地域は？", choices: ["関東", "関西", "中部", "九州"], answer: 0 },
    { q: "アドベンチャーバイクが最も人気の地域は？", choices: ["北海道", "東北", "関東", "中部"], answer: 0 },
    { q: "冬でもバイクが売れる地域TOP1は？", choices: ["沖縄", "鹿児島", "東京", "大阪"], answer: 0 },
  ],
};

// ── Category definitions (questions loaded from API) ──
const CATEGORIES = [
  { id: "price", label: "中古価格", sub: "平均価格を当てろ！", icon: "💰", color: "#FF4136" },
  { id: "ranking", label: "売れ筋ランキング", sub: "今売れてるのは？", icon: "🏆", color: "#FF851B" },
  { id: "speed", label: "売却スピード", sub: "何日で売れる？", icon: "⚡", color: "#2ECC40" },
  { id: "region", label: "地域別人気", sub: "どこで人気？", icon: "📍", color: "#B10DC9" },
];

const PHASES = { SELECT: 0, READY: 1, PLAY: 2, RESULT_ANS: 3, FINAL: 4 };

// ── Character Images ──

const CHAR_IMAGES = {
  main: {
    normal: "/images/quiz/main-normal.png",
    correct: "/images/quiz/main-correct.png",
    wrong: "/images/quiz/main-wrong.png",
  },
  sub: {
    normal: "/images/quiz/sub-normal.png",
    correct: "/images/quiz/sub-correct.png",
    wrong: "/images/quiz/sub-wrong.png",
  },
};

// Character component - uses Gemini-generated images
const CharacterSprite = ({ type, mood, size = 80, style: extraStyle = {} }) => {
  const src = CHAR_IMAGES[type]?.[mood];
  return (
    <img
      src={src}
      alt={`${type}-${mood}`}
      style={{
        width: size,
        height: size,
        objectFit: "contain",
        background: "transparent",
        display: "block",
        ...extraStyle,
      }}
    />
  );
};

const CharSpeech = ({ text, color = "#333", tailDirection = "down", highlight, animation = "" }) => (
  <div style={{
    position: "relative", display: "inline-block", padding: "8px 16px", borderRadius: 14,
    background: color, color: "#fff", fontSize: 13, fontWeight: 800, lineHeight: 1.5,
    boxShadow: "0 2px 8px rgba(0,0,0,0.3)", marginBottom: tailDirection === "down" ? 10 : 0,
    marginTop: tailDirection === "up" ? 10 : 0, maxWidth: 160,
    animation: animation || "mq-popIn 0.3s ease-out",
  }}>
    {highlight && (
      <span style={{
        background: "#FFFDE7", color: "#1a1a2e", padding: "2px 8px", borderRadius: 6,
        fontWeight: 900, fontSize: 15, display: "inline",
        textDecoration: "underline", textDecorationColor: "#FFD70088", textUnderlineOffset: 3,
      }}>{highlight}</span>
    )}
    {text}
    {/* Tail */}
    <div style={{
      position: "absolute",
      ...(tailDirection === "down" ? {
        bottom: -8, left: "50%", transform: "translateX(-50%)",
        width: 0, height: 0, borderLeft: "8px solid transparent", borderRight: "8px solid transparent",
        borderTop: `8px solid ${color}`,
      } : {
        top: -8, left: "50%", transform: "translateX(-50%)",
        width: 0, height: 0, borderLeft: "8px solid transparent", borderRight: "8px solid transparent",
        borderBottom: `8px solid ${color}`,
      }),
    }} />
  </div>
);

// Random speech lines for variety
const SPEECH = {
  thinking: ["よく考えてね！", "どれかな〜？", "わかるかな？", "ヒントはないよ！", "自信ある？"],
  correct_main: ["よく出来たネ！", "さすが！", "バイク博士だね！", "完璧！👏", "その通り！"],
  correct_sub: ["すごーい！", "やった〜！", "天才だ！", "カッコいい！", "ナイス！🔥"],
  wrong_main: ["ドンマイ！", "残念…！", "惜しい〜！", "次がんばろ！", "気にすんな！"],
  wrong_sub: ["あちゃ〜…", "う〜ん…", "次こそ！", "ドンマイ…", "落ち込むな！"],
};
const pickSpeech = (key) => SPEECH[key][Math.floor(Math.random() * SPEECH[key].length)];
const Q_COUNT = 10;
const POINTS_CORRECT = 10;

// ── Shuffle helper ──
function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// ── Rivet dot for buttons ──
const Rivet = ({ pos }) => {
  const styles = {
    TL: { top: 8, left: 8 },
    TR: { top: 8, right: 8 },
    BL: { bottom: 8, left: 8 },
    BR: { bottom: 8, right: 8 },
  };
  return (
    <span
      style={{
        position: "absolute",
        width: 10,
        height: 10,
        borderRadius: "50%",
        background: "rgba(0,0,0,0.25)",
        boxShadow: "inset 0 1px 2px rgba(0,0,0,0.4), 0 1px 0 rgba(255,255,255,0.15)",
        ...styles[pos],
      }}
    />
  );
};

// ── Main Component ──
export default function MotoHubQuiz() {
  const [phase, setPhase] = useState(PHASES.SELECT);
  const [category, setCategory] = useState(null);
  const [questions, setQuestions] = useState([]);
  const [qIndex, setQIndex] = useState(0);
  const [score, setScore] = useState(0);
  const [timer, setTimer] = useState(0);
  const [timerRunning, setTimerRunning] = useState(false);
  const [selected, setSelected] = useState(null);
  const [isCorrect, setIsCorrect] = useState(null);
  const [showOverlay, setShowOverlay] = useState(false);
  const [hiddenChoices, setHiddenChoices] = useState([]);
  const [helpHalf, setHelpHalf] = useState(2);
  const [helpStop, setHelpStop] = useState(4);
  const [timerStopped, setTimerStopped] = useState(false);
  const [fadeIn, setFadeIn] = useState(true);
  const [shakeWrong, setShakeWrong] = useState(false);
  const [speechMain, setSpeechMain] = useState("");
  const [speechSub, setSpeechSub] = useState("");
  const [thinkingSpeech, setThinkingSpeech] = useState("");
  const timerRef = useRef(null);

  // Timer
  useEffect(() => {
    if (timerRunning && !timerStopped) {
      timerRef.current = setInterval(() => setTimer((t) => t + 1), 10);
    }
    return () => clearInterval(timerRef.current);
  }, [timerRunning, timerStopped]);

  const formatTime = (t) => {
    const mins = Math.floor(t / 6000);
    const secs = Math.floor((t % 6000) / 100);
    const cs = t % 100;
    return { mins, secs: String(secs).padStart(2, "0"), cs: String(cs).padStart(2, "0") };
  };

  const [loading, setLoading] = useState(false);

  const startGame = async (cat) => {
    setCategory(cat);
    setQIndex(0);
    setScore(0);
    setTimer(0);
    setHelpHalf(2);
    setHelpStop(4);
    setLoading(true);
    setPhase(PHASES.READY);

    let qs;
    try {
      const res = await fetch(`/api/quiz/questions?category=${cat.id}&count=${Q_COUNT}`);
      if (!res.ok) throw new Error("API error");
      const data = await res.json();
      if (Array.isArray(data) && data.length > 0) {
        qs = data;
      } else {
        throw new Error("Empty response");
      }
    } catch {
      qs = shuffle(FALLBACK_QUESTIONS[cat.id] || []).slice(0, Q_COUNT);
    }
    setQuestions(qs);
    setLoading(false);
  };

  const beginPlay = () => {
    setTimerRunning(true);
    setTimerStopped(false);
    setThinkingSpeech(pickSpeech("thinking"));
    setPhase(PHASES.PLAY);
    setFadeIn(true);
  };

  const handleAnswer = (idx) => {
    if (selected !== null) return;
    setSelected(idx);
    const correct = questions[qIndex].answer === idx;
    setIsCorrect(correct);
    if (correct) {
      setScore((s) => s + POINTS_CORRECT);
      setSpeechMain(pickSpeech("correct_main"));
      setSpeechSub(pickSpeech("correct_sub"));
    } else {
      setShakeWrong(true);
      setSpeechMain(pickSpeech("wrong_main"));
      setSpeechSub(pickSpeech("wrong_sub"));
    }
    setShowOverlay(true);
    setPhase(PHASES.RESULT_ANS);
  };

  const nextQuestion = () => {
    if (qIndex + 1 >= questions.length) {
      setTimerRunning(false);
      setPhase(PHASES.FINAL);
      return;
    }
    setQIndex((i) => i + 1);
    setSelected(null);
    setIsCorrect(null);
    setShowOverlay(false);
    setHiddenChoices([]);
    setShakeWrong(false);
    setTimerStopped(false);
    setThinkingSpeech(pickSpeech("thinking"));
    setFadeIn(true);
    setPhase(PHASES.PLAY);
  };

  const useHalfHelp = () => {
    if (helpHalf <= 0 || selected !== null) return;
    const curr = questions[qIndex];
    const wrong = curr.choices.map((_, i) => i).filter((i) => i !== curr.answer && !hiddenChoices.includes(i));
    const toHide = shuffle(wrong).slice(0, 2);
    setHiddenChoices(toHide);
    setHelpHalf((h) => h - 1);
  };

  const useStopHelp = () => {
    if (helpStop <= 0 || selected !== null) return;
    setTimerStopped(true);
    setHelpStop((h) => h - 1);
  };

  const restart = () => {
    setPhase(PHASES.SELECT);
    setCategory(null);
    setQuestions([]);
    setQIndex(0);
    setScore(0);
    setTimer(0);
    setSelected(null);
    setIsCorrect(null);
    setShowOverlay(false);
    setHiddenChoices([]);
    setShakeWrong(false);
    setTimerStopped(false);
  };

  const retryCategory = () => {
    if (!category) return;
    setSelected(null);
    setIsCorrect(null);
    setShowOverlay(false);
    setHiddenChoices([]);
    setShakeWrong(false);
    setTimerStopped(false);
    setTimerRunning(false);
    setSpeechMain("");
    setSpeechSub("");
    setThinkingSpeech("");
    startGame(category);
  };

  const time = formatTime(timer);
  const maxScore = Q_COUNT * POINTS_CORRECT;
  const scoreRatio = score / maxScore;

  const getResultMessage = () => {
    if (scoreRatio >= 0.9) return { text: "バイクマスター！🏍️", sub: "あなたはバイク博士！" };
    if (scoreRatio >= 0.7) return { text: "なかなかやるね！💪", sub: "もう少しで完璧！" };
    if (scoreRatio >= 0.5) return { text: "まあまあだね！", sub: "もっと挑戦しよう！" };
    return { text: "もっとがんばれ！🔥", sub: "MotoHubで勉強だ！" };
  };

  // ── Keyframe injection ──
  useEffect(() => {
    const id = "motoquiz-keyframes";
    if (document.getElementById(id)) return;
    const style = document.createElement("style");
    style.id = id;
    style.textContent = `
      @import url('https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@500;700;800;900&display=swap');
      @keyframes mq-fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
      @keyframes mq-popIn { 0% { transform:scale(0); } 60% { transform:scale(1.15); } 100% { transform:scale(1); } }
      @keyframes mq-shake { 0%,100% { transform:translateX(0); } 20% { transform:translateX(-12px); } 40% { transform:translateX(12px); } 60% { transform:translateX(-8px); } 80% { transform:translateX(8px); } }
      @keyframes mq-pulse { 0%,100% { transform:scale(1); } 50% { transform:scale(1.05); } }
      @keyframes mq-slideDown { from { opacity:0; transform:translateY(-30px); } to { opacity:1; transform:translateY(0); } }
      @keyframes mq-glow { 0%,100% { box-shadow: 0 0 5px rgba(255,65,54,0.3); } 50% { box-shadow: 0 0 25px rgba(255,65,54,0.6); } }
      @keyframes mq-correctBounce { 0% { transform:scale(0) rotate(-10deg); } 50% { transform:scale(1.2) rotate(5deg); } 100% { transform:scale(1) rotate(0); } }
      @keyframes mq-countPulse { 0% { transform:scale(1); opacity:1; } 50% { transform:scale(1.8); opacity:0.6; } 100% { transform:scale(1); opacity:1; } }
      @keyframes mq-revealSlide { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }
      @keyframes mq-float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
      @keyframes mq-shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
    `;
    document.head.appendChild(style);
  }, []);

  const baseFont = "'M PLUS Rounded 1c', sans-serif";

  // ═══════════════════════════════════════════
  // CATEGORY SELECT SCREEN
  // ═══════════════════════════════════════════
  if (phase === PHASES.SELECT) {
    return (
      <div style={{ minHeight: "100vh", background: "linear-gradient(180deg, #0a0a0a 0%, #1a1a2e 50%, #0a0a0a 100%)", fontFamily: baseFont, overflowX: "hidden", maxWidth: "100vw", boxSizing: "border-box" }}>
        {/* Hero */}
        <div style={{ textAlign: "center", padding: "40px 20px 20px", animation: "mq-fadeUp 0.6s ease-out" }}>
          <div style={{ fontSize: 13, letterSpacing: 4, color: "#888", textTransform: "uppercase", marginBottom: 8 }}>MotoHub Presents</div>
          <h1 style={{
            fontSize: 42, fontWeight: 900, color: "#fff", margin: "0 0 4px", lineHeight: 1.1,
            textShadow: "0 0 30px rgba(255,65,54,0.4), 0 2px 0 #333",
          }}>
            バイク<span style={{ color: "#FF4136" }}>4択</span>クイズ
          </h1>
          <div style={{ width: 60, height: 3, background: "linear-gradient(90deg, #FF4136, #FF851B)", margin: "12px auto 0", borderRadius: 2 }} />
        </div>

        {/* Features */}
        <div style={{
          display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, padding: "16px 20px",
          maxWidth: 480, margin: "0 auto",
          animation: "mq-fadeUp 0.6s ease-out 0.15s both",
        }}>
          {[
            { icon: "📱", label: "スマホ対応" },
            { icon: "🏅", label: "スコア記録" },
            { icon: "⏱️", label: "タイムアタック" },
            { icon: "💡", label: "お助けアイテム" },
          ].map((f, i) => (
            <div key={i} style={{
              display: "flex", alignItems: "center", justifyContent: "center", gap: 10, padding: "12px 14px",
              background: "rgba(255,255,255,0.04)", borderRadius: 12, border: "1px solid rgba(255,255,255,0.08)",
              textAlign: "center",
            }}>
              <span style={{ fontSize: 22 }}>{f.icon}</span>
              <span style={{ color: "#ccc", fontSize: 13, fontWeight: 700 }}>{f.label}</span>
            </div>
          ))}
        </div>

        {/* Category label */}
        <div style={{
          textAlign: "center", padding: "24px 0 16px", color: "#888", fontSize: 12, letterSpacing: 3, textTransform: "uppercase",
          animation: "mq-fadeUp 0.6s ease-out 0.3s both",
        }}>
          カテゴリを選択
        </div>

        {/* Categories */}
        <div style={{ display: "flex", flexDirection: "column", gap: 12, padding: "0 20px 40px", maxWidth: 480, margin: "0 auto", animation: "mq-fadeUp 0.6s ease-out 0.35s both" }}>
          {CATEGORIES.map((cat, i) => (
            <button
              key={cat.id}
              onClick={() => startGame(cat)}
              style={{
                position: "relative", display: "flex", alignItems: "center", gap: 16,
                padding: "18px 20px", border: "none", borderRadius: 16, cursor: "pointer",
                background: `linear-gradient(135deg, ${cat.color}dd, ${cat.color}99)`,
                boxShadow: `0 4px 20px ${cat.color}44, inset 0 1px 0 rgba(255,255,255,0.2)`,
                transition: "transform 0.15s, box-shadow 0.15s",
                animation: `mq-fadeUp 0.5s ease-out ${0.4 + i * 0.08}s both`,
              }}
              onPointerDown={(e) => (e.currentTarget.style.transform = "scale(0.97)")}
              onPointerUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
              onPointerLeave={(e) => (e.currentTarget.style.transform = "scale(1)")}
            >
              <Rivet pos="TL" /><Rivet pos="TR" /><Rivet pos="BL" /><Rivet pos="BR" />
              <span style={{ fontSize: 36, filter: "drop-shadow(0 2px 4px rgba(0,0,0,0.3))" }}>{cat.icon}</span>
              <div style={{ textAlign: "left" }}>
                <div style={{ fontSize: 20, fontWeight: 900, color: "#fff", textShadow: "0 1px 3px rgba(0,0,0,0.3)" }}>{cat.label}</div>
                <div style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 500 }}>{cat.sub}</div>
              </div>
              <span style={{
                marginLeft: "auto", fontSize: 22, color: "rgba(255,255,255,0.7)",
                transition: "transform 0.2s",
              }}>▶</span>
            </button>
          ))}
        </div>

        {/* Footer */}
        <div style={{ textAlign: "center", padding: "0 0 30px", color: "#555", fontSize: 11 }}>
          Powered by <span style={{ color: "#FF4136", fontWeight: 700 }}>MotoHub</span> の実データ
        </div>
      </div>
    );
  }

  // ═══════════════════════════════════════════
  // READY SCREEN
  // ═══════════════════════════════════════════
  if (phase === PHASES.READY) {
    return (
      <div style={{
        minHeight: "100vh", background: "linear-gradient(180deg, #0d0d1a 0%, #1a1a2e 100%)",
        fontFamily: baseFont, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
        padding: 20,
      }}>
        <div style={{ animation: "mq-popIn 0.5s ease-out", textAlign: "center" }}>
          <div style={{ fontSize: 13, letterSpacing: 3, color: "#888", marginBottom: 8 }}>MotoHub Quiz</div>
          <h2 style={{ fontSize: 36, fontWeight: 900, color: "#fff", margin: "0 0 4px" }}>
            <span style={{ color: category.color }}>{category.icon}</span> {category.label}
          </h2>
          <div style={{ color: "#aaa", fontSize: 14, marginBottom: 16 }}>{category.sub}</div>

          {/* Characters */}
          <div style={{ display: "flex", gap: 20, justifyContent: "center", alignItems: "flex-end", marginBottom: 20, animation: "mq-fadeUp 0.5s ease-out 0.15s both" }}>
            <CharacterSprite type="sub" mood="normal" size={110} style={{ animation: "mq-float 3s ease-in-out infinite" }} />
            <CharacterSprite type="main" mood="normal" size={120} style={{ animation: "mq-float 3s ease-in-out infinite 0.5s" }} />
          </div>
        </div>

        {/* Quiz info card */}
        <div style={{
          background: "rgba(255,255,255,0.06)", borderRadius: 20, padding: "24px 28px", marginBottom: 32,
          border: "1px solid rgba(255,255,255,0.1)", width: "100%", maxWidth: 320,
          animation: "mq-fadeUp 0.5s ease-out 0.2s both",
        }}>
          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 12 }}>
            <span style={{ color: "#888", fontSize: 13 }}>出題数</span>
            <span style={{ color: "#fff", fontWeight: 800, fontSize: 18 }}>{Q_COUNT}問</span>
          </div>
          <div style={{ height: 1, background: "rgba(255,255,255,0.08)", margin: "0 0 12px" }} />
          <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 12 }}>
            <span style={{ color: "#888", fontSize: 13 }}>正解ポイント</span>
            <span style={{ color: "#2ECC40", fontWeight: 800, fontSize: 18 }}>+{POINTS_CORRECT}点</span>
          </div>
          <div style={{ height: 1, background: "rgba(255,255,255,0.08)", margin: "0 0 12px" }} />
          <div style={{ display: "flex", justifyContent: "space-between" }}>
            <span style={{ color: "#888", fontSize: 13 }}>お助けアイテム</span>
            <span style={{ color: "#FF851B", fontWeight: 800, fontSize: 14 }}>2種類</span>
          </div>
        </div>

        {/* Start button */}
        <button
          onClick={beginPlay}
          disabled={loading}
          style={{
            padding: "18px 60px", fontSize: 22, fontWeight: 900, fontFamily: baseFont,
            color: "#fff", border: "none", borderRadius: 50,
            cursor: loading ? "wait" : "pointer",
            opacity: loading ? 0.6 : 1,
            background: `linear-gradient(135deg, ${category.color}, ${category.color}cc)`,
            boxShadow: `0 6px 30px ${category.color}55`,
            animation: "mq-pulse 2s ease-in-out infinite, mq-fadeUp 0.5s ease-out 0.4s both",
            transition: "transform 0.15s, opacity 0.3s",
            position: "relative",
          }}
          onPointerDown={(e) => !loading && (e.currentTarget.style.transform = "scale(0.95)")}
          onPointerUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
        >
          {loading ? "問題を準備中..." : "🏍️ START!"}
        </button>

        <button
          onClick={restart}
          style={{
            marginTop: 20, padding: "10px 24px", fontSize: 13, fontWeight: 700, fontFamily: baseFont,
            color: "#888", background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.1)",
            borderRadius: 25, cursor: "pointer", animation: "mq-fadeUp 0.5s ease-out 0.5s both",
          }}
        >
          カテゴリに戻る
        </button>
      </div>
    );
  }

  // ═══════════════════════════════════════════
  // PLAY & RESULT_ANS SCREENS
  // ═══════════════════════════════════════════
  if (phase === PHASES.PLAY || phase === PHASES.RESULT_ANS) {
    const curr = questions[qIndex];
    const catColor = category.color;

    return (
      <div style={{
        minHeight: "100vh", fontFamily: baseFont, display: "flex", flexDirection: "column",
        background: phase === PHASES.RESULT_ANS
          ? (isCorrect ? "linear-gradient(180deg, #1a3a1a 0%, #0d1a0d 100%)" : "linear-gradient(180deg, #2a1a1a 0%, #1a0d0d 100%)")
          : "linear-gradient(180deg, #0d0d1a 0%, #1a1a2e 100%)",
        transition: "background 0.4s",
      }}>
        {/* Top bar */}
        <div style={{
          display: "flex", justifyContent: "space-between", alignItems: "center",
          padding: "12px 16px", background: "rgba(0,0,0,0.5)", borderBottom: `3px solid ${catColor}`,
        }}>
          <div style={{ color: catColor, fontSize: 12, fontWeight: 800, letterSpacing: 1 }}>
            {category.icon} {category.label}
          </div>
          <div style={{ display: "flex", alignItems: "baseline", gap: 2 }}>
            <span style={{ color: "#888", fontSize: 11 }}>⏱</span>
            <span style={{ color: timerStopped ? "#FF851B" : "#fff", fontSize: 22, fontWeight: 900, fontVariantNumeric: "tabular-nums" }}>
              {time.mins}<span style={{ fontSize: 11, color: "#888" }}>分</span>{time.secs}<span style={{ fontSize: 11, color: "#888" }}>秒</span><span style={{ fontSize: 14 }}>{time.cs}</span>
            </span>
          </div>
          <div style={{ textAlign: "right" }}>
            <span style={{ color: "#888", fontSize: 11 }}>スコア </span>
            <span style={{ color: "#fff", fontSize: 20, fontWeight: 900 }}>{score}</span>
            <span style={{ color: "#666", fontSize: 11 }}>点</span>
          </div>
        </div>

        {/* Progress bar */}
        <div style={{ height: 4, background: "rgba(255,255,255,0.08)" }}>
          <div style={{
            height: "100%", width: `${((qIndex + (phase === PHASES.RESULT_ANS ? 1 : 0)) / questions.length) * 100}%`,
            background: `linear-gradient(90deg, ${catColor}, #FF851B)`,
            transition: "width 0.4s ease",
            borderRadius: "0 2px 2px 0",
          }} />
        </div>

        {/* Question area */}
        <div style={{
          flex: 1, display: "flex", flexDirection: "column", padding: "20px 16px",
          width: "100%", maxWidth: 640, margin: "0 auto",
          animation: fadeIn ? "mq-fadeUp 0.35s ease-out" : "none",
          onAnimationEnd: () => setFadeIn(false),
        }}>
          {/* Question number */}
          <div style={{ textAlign: "center", marginBottom: 12 }}>
            <span style={{
              display: "inline-block", padding: "4px 16px", borderRadius: 20,
              background: "rgba(255,255,255,0.08)", border: "1px solid rgba(255,255,255,0.12)",
              color: "#aaa", fontSize: 12, fontWeight: 700,
            }}>
              第 <span style={{ color: catColor, fontSize: 18, fontWeight: 900 }}>{qIndex + 1}</span> 問 / {questions.length}
            </span>
          </div>

          {/* Question card */}
          <div style={{
            background: "#fff", borderRadius: 16, padding: "20px 24px",
            margin: "0 0 12px", boxShadow: "0 4px 20px rgba(0,0,0,0.3)",
            animation: phase === PHASES.RESULT_ANS ? "none" : "mq-slideDown 0.4s ease-out",
          }}>
            <div style={{ fontSize: 17, fontWeight: 700, color: "#1a1a2e", lineHeight: 1.6, textAlign: "center" }}>
              {curr.q}
            </div>
          </div>

          {/* Characters + answer feedback area */}
          <div style={{
            display: "flex", alignItems: "flex-end", justifyContent: "center",
            gap: 12, padding: "12px 8px 4px", minHeight: 160, overflow: "hidden",
          }}>
            {/* Sub character (left) */}
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 6, minWidth: 100, flexShrink: 0 }}>
              {phase === PHASES.PLAY && (
                <CharSpeech text="手伝うよ〜" color="#E65100" tailDirection="down" />
              )}
              {phase === PHASES.RESULT_ANS && (
                <CharSpeech text={speechSub} color={isCorrect ? "#2E7D32" : "#616161"} tailDirection="down" />
              )}
              <CharacterSprite
                type="sub"
                mood={phase === PHASES.RESULT_ANS ? (isCorrect ? "correct" : "wrong") : "normal"}
                size={100}
                style={{ animation: phase === PHASES.RESULT_ANS && !isCorrect ? "mq-shake 0.5s ease-out" : "mq-float 3s ease-in-out infinite" }}
              />
            </div>

            {/* Answer bubble (center, only during result) */}
            {phase === PHASES.RESULT_ANS && (
              <div style={{
                position: "relative", padding: "10px 14px", borderRadius: 16,
                background: isCorrect ? "#FFFDE7" : "#E3F2FD",
                border: `3px solid ${isCorrect ? "#FFD700" : "#90CAF9"}`,
                animation: "mq-popIn 0.4s ease-out",
                boxShadow: isCorrect ? "0 4px 15px rgba(255,215,0,0.3)" : "0 4px 15px rgba(144,202,249,0.3)",
                marginBottom: 8, flex: "1 1 auto", minWidth: 0, maxWidth: "85vw",
              }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: "#1a1a2e", lineHeight: 1.4, textAlign: "center" }}>
                  正解は
                  <span style={{
                    display: "block", margin: "4px 0",
                    fontSize: 18, fontWeight: 900, color: isCorrect ? "#2E7D32" : "#1565C0",
                    background: isCorrect ? "#C8E6C9" : "#BBDEFB",
                    padding: "4px 8px", borderRadius: 6,
                    overflowWrap: "anywhere", wordBreak: "break-word",
                  }}>{curr.choices[curr.answer]}</span>
                  だよ！
                </div>
              </div>
            )}

            {/* Main character (right) */}
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 6, minWidth: 110, flexShrink: 0 }}>
              {phase === PHASES.PLAY && (
                <CharSpeech text={thinkingSpeech} color="#4a3728" tailDirection="down" />
              )}
              {phase === PHASES.RESULT_ANS && (
                <CharSpeech text={speechMain} color={isCorrect ? "#2E7D32" : "#C62828"} tailDirection="down" />
              )}
              <CharacterSprite
                type="main"
                mood={phase === PHASES.RESULT_ANS ? (isCorrect ? "correct" : "wrong") : "normal"}
                size={110}
                style={{ animation: isCorrect && phase === PHASES.RESULT_ANS ? "mq-correctBounce 0.5s ease-out" : "mq-float 3s ease-in-out infinite 0.5s" }}
              />
            </div>
          </div>

          {/* Spacer */}
          <div style={{ flex: 1, minHeight: 8 }} />

          {/* Choices */}
          <div style={{
            display: "flex", flexDirection: "column", gap: 10,
            animation: shakeWrong && phase === PHASES.RESULT_ANS ? "mq-shake 0.4s ease-out" : "none",
          }}>
            {curr.choices.map((ch, i) => {
              const hidden = hiddenChoices.includes(i);
              const isAnswer = i === curr.answer;
              const isSelected = selected === i;
              let bg = `linear-gradient(135deg, ${catColor}dd, ${catColor}99)`;
              let opacity = 1;
              let border = "2px solid transparent";
              let scale = 1;

              if (phase === PHASES.RESULT_ANS) {
                if (isAnswer) {
                  bg = "linear-gradient(135deg, #2ECC40, #27AE60)";
                  border = "2px solid #2ECC40";
                  scale = 1.02;
                } else if (isSelected && !isCorrect) {
                  bg = "linear-gradient(135deg, #555, #444)";
                  opacity = 0.6;
                } else {
                  opacity = 0.35;
                }
              }

              if (hidden) {
                return (
                  <div key={i} style={{
                    padding: "16px 20px", borderRadius: 14, background: "rgba(255,255,255,0.03)",
                    border: "2px dashed rgba(255,255,255,0.08)", textAlign: "center",
                    color: "rgba(255,255,255,0.15)", fontSize: 14, fontWeight: 700,
                  }}>
                    ─ ─ ─
                  </div>
                );
              }

              return (
                <button
                  key={i}
                  onClick={() => handleAnswer(i)}
                  disabled={phase === PHASES.RESULT_ANS}
                  style={{
                    position: "relative", padding: "16px 20px", border, borderRadius: 14,
                    background: bg, cursor: phase === PHASES.RESULT_ANS ? "default" : "pointer",
                    opacity, transform: `scale(${scale})`, transition: "all 0.25s ease",
                    boxShadow: isAnswer && phase === PHASES.RESULT_ANS ? "0 0 20px rgba(46,204,64,0.4)" : `0 3px 12px rgba(0,0,0,0.3)`,
                    fontFamily: baseFont,
                  }}
                  onPointerDown={(e) => { if (phase === PHASES.PLAY) e.currentTarget.style.transform = "scale(0.96)"; }}
                  onPointerUp={(e) => { if (phase === PHASES.PLAY) e.currentTarget.style.transform = "scale(1)"; }}
                  onPointerLeave={(e) => { if (phase === PHASES.PLAY) e.currentTarget.style.transform = "scale(1)"; }}
                >
                  <Rivet pos="TL" /><Rivet pos="TR" /><Rivet pos="BL" /><Rivet pos="BR" />
                  <span style={{
                    fontSize: 17, fontWeight: 800, color: "#fff",
                    textShadow: "0 1px 3px rgba(0,0,0,0.3)",
                  }}>
                    {ch}
                  </span>
                </button>
              );
            })}
          </div>

          {/* Help items & next */}
          <div style={{ marginTop: 16 }}>
            {phase === PHASES.PLAY && (
              <div style={{ display: "flex", gap: 10, animation: "mq-fadeUp 0.3s ease-out" }}>
                <button
                  onClick={useHalfHelp}
                  disabled={helpHalf <= 0}
                  style={{
                    flex: 1, padding: "14px 10px", borderRadius: 14, border: "none", cursor: helpHalf > 0 ? "pointer" : "default",
                    background: helpHalf > 0 ? "linear-gradient(135deg, #FFD700, #FFA500)" : "rgba(255,255,255,0.06)",
                    opacity: helpHalf > 0 ? 1 : 0.35, fontFamily: baseFont, transition: "all 0.2s",
                    boxShadow: helpHalf > 0 ? "0 3px 15px rgba(255,215,0,0.3)" : "none",
                  }}
                >
                  <div style={{ fontSize: 15, fontWeight: 900, color: helpHalf > 0 ? "#1a1a2e" : "#666" }}>💡 2択にする！</div>
                  <div style={{ fontSize: 11, color: helpHalf > 0 ? "rgba(0,0,0,0.5)" : "#555", fontWeight: 600 }}>残り{helpHalf}回</div>
                </button>
                <button
                  onClick={useStopHelp}
                  disabled={helpStop <= 0}
                  style={{
                    flex: 1, padding: "14px 10px", borderRadius: 14, border: "none", cursor: helpStop > 0 ? "pointer" : "default",
                    background: helpStop > 0 ? "linear-gradient(135deg, #FFD700, #FFA500)" : "rgba(255,255,255,0.06)",
                    opacity: helpStop > 0 ? 1 : 0.35, fontFamily: baseFont, transition: "all 0.2s",
                    boxShadow: helpStop > 0 ? "0 3px 15px rgba(255,215,0,0.3)" : "none",
                  }}
                >
                  <div style={{ fontSize: 15, fontWeight: 900, color: helpStop > 0 ? "#1a1a2e" : "#666" }}>⏱ タイマー停止！</div>
                  <div style={{ fontSize: 11, color: helpStop > 0 ? "rgba(0,0,0,0.5)" : "#555", fontWeight: 600 }}>残り{helpStop}回</div>
                </button>
              </div>
            )}

            {phase === PHASES.RESULT_ANS && (
              <button
                onClick={nextQuestion}
                style={{
                  width: "100%", padding: "16px", borderRadius: 14, border: "none",
                  background: "linear-gradient(135deg, #fff, #eee)", cursor: "pointer",
                  fontFamily: baseFont, fontSize: 17, fontWeight: 900, color: "#1a1a2e",
                  boxShadow: "0 4px 20px rgba(255,255,255,0.15)",
                  animation: "mq-fadeUp 0.3s ease-out",
                  transition: "transform 0.15s",
                }}
                onPointerDown={(e) => (e.currentTarget.style.transform = "scale(0.97)")}
                onPointerUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
              >
                {qIndex + 1 >= questions.length ? "結果を見る 🏁" : "次の問題 ▶"}
              </button>
            )}
          </div>
        </div>

        {/* Overlay animation */}
        {showOverlay && (
          <div style={{
            position: "fixed", top: 0, left: 0, right: 0, bottom: 0,
            display: "flex", alignItems: "center", justifyContent: "center",
            pointerEvents: "none", zIndex: 100,
            animation: "mq-popIn 0.5s ease-out",
          }}>
            <div style={{
              width: 180, height: 180, borderRadius: "50%",
              border: `8px solid ${isCorrect ? "#2ECC40" : "#FF4136"}`,
              background: isCorrect ? "rgba(46,204,64,0.12)" : "rgba(255,65,54,0.12)",
              display: "flex", alignItems: "center", justifyContent: "center",
              animation: "mq-correctBounce 0.6s ease-out",
              backdropFilter: "blur(4px)",
            }}>
              <span style={{
                fontSize: 52, fontWeight: 900, color: isCorrect ? "#2ECC40" : "#FF4136",
                textShadow: `0 0 30px ${isCorrect ? "rgba(46,204,64,0.5)" : "rgba(255,65,54,0.5)"}`,
              }}>
                {isCorrect ? "正解!" : "ミス!"}
              </span>
            </div>
          </div>
        )}
      </div>
    );
  }

  // ═══════════════════════════════════════════
  // FINAL RESULT SCREEN
  // ═══════════════════════════════════════════
  if (phase === PHASES.FINAL) {
    const result = getResultMessage();
    return (
      <div style={{
        minHeight: "100vh", fontFamily: baseFont,
        background: "linear-gradient(180deg, #0d0d1a 0%, #1a1a2e 50%, #0d0d1a 100%)",
        display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
        padding: 20,
      }}>
        <div style={{ textAlign: "center", animation: "mq-fadeUp 0.6s ease-out" }}>
          <div style={{ fontSize: 13, letterSpacing: 3, color: "#888", marginBottom: 8 }}>RESULT</div>
          <div style={{ fontSize: 18, color: "#aaa", fontWeight: 700, marginBottom: 4 }}>今回の得点は</div>
          <div style={{ animation: "mq-popIn 0.6s ease-out 0.2s both" }}>
            <span style={{
              fontSize: 96, fontWeight: 900, color: "#fff",
              textShadow: `0 0 40px ${category.color}66, 0 4px 0 #333`,
              lineHeight: 1,
            }}>{score}</span>
            <span style={{ fontSize: 28, color: "#aaa", fontWeight: 700 }}>点</span>
            <span style={{ fontSize: 16, color: "#666" }}> / {maxScore}</span>
          </div>
        </div>

        {/* Time */}
        <div style={{
          marginTop: 16, textAlign: "center", color: "#888", fontSize: 15, fontWeight: 700,
          animation: "mq-fadeUp 0.5s ease-out 0.3s both",
        }}>
          かかった時間：
          <span style={{ color: "#fff", fontSize: 28, fontWeight: 900 }}>{time.mins}:{time.secs}</span>
          <span style={{ color: "#aaa", fontSize: 16 }}>.{time.cs}</span>
        </div>

        {/* Message */}
        <div style={{
          marginTop: 24, padding: "20px 32px", borderRadius: 20,
          background: `linear-gradient(135deg, ${category.color}22, ${category.color}11)`,
          border: `2px solid ${category.color}44`, textAlign: "center",
          animation: "mq-fadeUp 0.5s ease-out 0.45s both",
        }}>
          <div style={{ fontSize: 24, fontWeight: 900, color: "#fff", marginBottom: 4 }}>{result.text}</div>
          <div style={{ fontSize: 14, color: "#aaa", fontWeight: 600 }}>{result.sub}</div>
        </div>

        {/* Score bar */}
        <div style={{
          width: "100%", maxWidth: 320, marginTop: 28,
          animation: "mq-fadeUp 0.5s ease-out 0.55s both",
        }}>

          {/* Characters reaction */}
          <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "center", gap: 16, marginBottom: 16 }}>
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "flex-end" }}>
              <CharacterSprite type="sub" mood={scoreRatio >= 0.5 ? "correct" : "wrong"} size={90}
                style={{ animation: scoreRatio >= 0.7 ? "mq-correctBounce 0.6s ease-out" : "mq-float 3s ease-in-out infinite" }} />
            </div>
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "flex-end", paddingBottom: 12 }}>
              <CharSpeech text={result.sub} color={scoreRatio >= 0.7 ? "#2ECC40" : scoreRatio >= 0.5 ? "#FF851B" : "#FF4136"} tailDirection="down" />
            </div>
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "flex-end" }}>
              <CharacterSprite type="main" mood={scoreRatio >= 0.5 ? "correct" : "wrong"} size={100}
                style={{ animation: scoreRatio >= 0.7 ? "mq-correctBounce 0.6s ease-out 0.2s both" : "mq-float 3s ease-in-out infinite 0.5s" }} />
            </div>
          </div>
          <div style={{ height: 12, borderRadius: 6, background: "rgba(255,255,255,0.08)", overflow: "hidden" }}>
            <div style={{
              height: "100%", borderRadius: 6,
              width: `${scoreRatio * 100}%`,
              background: `linear-gradient(90deg, ${category.color}, #FFD700)`,
              transition: "width 1s ease-out",
              animation: "mq-shimmer 2s linear infinite",
              backgroundSize: "200% 100%",
            }} />
          </div>
        </div>

        {/* Buttons */}
        <div style={{
          display: "flex", gap: 12, marginTop: 32, width: "100%", maxWidth: 320,
          animation: "mq-fadeUp 0.5s ease-out 0.65s both",
        }}>
          <button
            onClick={retryCategory}
            style={{
              flex: 1, padding: "16px", borderRadius: 14, border: "none", cursor: "pointer",
              background: `linear-gradient(135deg, ${category.color}, ${category.color}cc)`,
              fontFamily: baseFont, fontSize: 15, fontWeight: 900, color: "#fff",
              boxShadow: `0 4px 20px ${category.color}44`,
              transition: "transform 0.15s",
            }}
            onPointerDown={(e) => (e.currentTarget.style.transform = "scale(0.97)")}
            onPointerUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
          >
            🔁 もう一度
          </button>
          <button
            onClick={restart}
            style={{
              flex: 1, padding: "16px", borderRadius: 14, border: "1px solid rgba(255,255,255,0.15)",
              cursor: "pointer", background: "rgba(255,255,255,0.06)",
              fontFamily: baseFont, fontSize: 15, fontWeight: 900, color: "#ccc",
              transition: "transform 0.15s",
            }}
            onPointerDown={(e) => (e.currentTarget.style.transform = "scale(0.97)")}
            onPointerUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
          >
            📋 カテゴリ選択
          </button>
        </div>

        {/* MotoHub CTA */}
        <div style={{
          marginTop: 32, textAlign: "center", animation: "mq-fadeUp 0.5s ease-out 0.8s both",
        }}>
          <div style={{ color: "#555", fontSize: 12, marginBottom: 8 }}>もっとバイクの相場を知りたい？</div>
          <a href="/" style={{
            display: "inline-block", padding: "10px 24px", borderRadius: 25,
            background: "linear-gradient(135deg, #FF4136, #FF851B)",
            color: "#fff", fontSize: 14, fontWeight: 800, cursor: "pointer",
            boxShadow: "0 4px 20px rgba(255,65,54,0.3)",
            textDecoration: "none",
          }}>
            MotoHub で検索する →
          </a>
        </div>
      </div>
    );
  }

  return null;
}
