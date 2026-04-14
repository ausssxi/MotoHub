const IMG = '/images/warashibe/';

/* ─── パーツ ─── */
export const PARTS = {
  old_helmet:          { id: 'old_helmet',          name: '古いヘルメット',       desc: 'リターンライダーの思い出の品' },
  stock_muffler:       { id: 'stock_muffler',       name: '純正マフラー',         desc: 'MT-07の純正マフラー' },
  aftermarket_muffler: { id: 'aftermarket_muffler', name: '社外マフラー',         desc: 'ヨシムラ管。いい音がする' },
  pannier_case:        { id: 'pannier_case',        name: 'パニアケース',         desc: 'ロングツーリングに最適' },
  sponsor_sticker:     { id: 'sponsor_sticker',     name: 'スポンサーステッカー', desc: '有名チームのロゴ入り' },
  racing_cowl:         { id: 'racing_cowl',         name: 'レーシングカウル',     desc: '軽量レース用カウル' },
  travel_diary:        { id: 'travel_diary',        name: '旅日記',               desc: '世界一周の記録' },
  turbo_kit:           { id: 'turbo_kit',           name: 'ターボキット',         desc: '伝説のメカニックの技術の結晶' },
};

/* ─── バイク (21車種) ─── */
export const BIKES = {
  /* Phase 1 */
  cub50:    { id: 'cub50',    name: 'スーパーカブ50',          cc: '50cc',   ccNum: 50,   category: '実用',           price: '10-20万',  icon: IMG + 'bike_cub50.png' },
  pcx:      { id: 'pcx',      name: 'PCX125',                 cc: '125cc',  ccNum: 125,  category: 'スクーター',     price: '20-35万',  icon: IMG + 'bike_pcx.png' },
  address:  { id: 'address',  name: 'アドレス110',             cc: '110cc',  ccNum: 110,  category: 'スクーター',     price: '10-20万',  icon: IMG + 'bike_address.png' },
  ct125:    { id: 'ct125',    name: 'CT125 ハンターカブ',       cc: '125cc',  ccNum: 125,  category: 'アウトドア',     price: '30-45万',  icon: IMG + 'bike_ct125.png' },
  crf:      { id: 'crf',      name: 'CRF250L',                cc: '250cc',  ccNum: 250,  category: 'オフロード',     price: '40-55万',  icon: IMG + 'bike_crf.png' },
  gb350:    { id: 'gb350',    name: 'GB350',                  cc: '350cc',  ccNum: 350,  category: 'ネオクラシック', price: '45-60万',  icon: IMG + 'bike_gb350.png' },
  /* Phase 2 — base */
  ninja250: { id: 'ninja250', name: 'Ninja250',               cc: '250cc',  ccNum: 250,  category: 'スポーツ',       price: '35-50万',  icon: IMG + 'bike_ninja250.png' },
  cb400:    { id: 'cb400',    name: 'CB400SF',                cc: '400cc',  ccNum: 400,  category: 'ネイキッド',     price: '50-80万',  icon: IMG + 'bike_cb400.png' },
  yzfr3:    { id: 'yzfr3',    name: 'YZF-R3',                 cc: '320cc',  ccNum: 320,  category: 'SS',             price: '45-60万',  icon: IMG + 'bike_yzfr3.png' },
  mt07:     { id: 'mt07',     name: 'MT-07',                  cc: '690cc',  ccNum: 690,  category: 'ストリート',     price: '55-75万',  icon: IMG + 'bike_mt07.png' },
  z900:     { id: 'z900',     name: 'Z900',                   cc: '950cc',  ccNum: 950,  category: 'ストファイ',     price: '75-100万', icon: IMG + 'bike_z900.png' },
  rebel1100:{ id: 'rebel1100',name: 'レブル1100',              cc: '1100cc', ccNum: 1100, category: 'クルーザー',     price: '85-110万', icon: IMG + 'bike_rebel1100.png' },
  vstrom:   { id: 'vstrom',   name: 'Vストローム650',          cc: '650cc',  ccNum: 650,  category: 'アドベンチャー', price: '55-80万',  icon: IMG + 'bike_vstrom.png' },
  mt09:     { id: 'mt09',     name: 'MT-09',                  cc: '890cc',  ccNum: 890,  category: 'ストリート',     price: '80-100万', icon: IMG + 'bike_mt09.png' },
  cbr600:   { id: 'cbr600',   name: 'CBR600RR',               cc: '600cc',  ccNum: 600,  category: 'SS',             price: '90-130万', icon: IMG + 'bike_cbr600.png' },
  zx10r:    { id: 'zx10r',    name: 'ZX-10R',                 cc: '1000cc', ccNum: 1000, category: 'SS',             price: '120-180万',icon: IMG + 'bike_zx10r.png' },
  hayabusa: { id: 'hayabusa', name: '隼（Hayabusa）',          cc: '1340cc', ccNum: 1340, category: 'メガスポーツ',   price: '130-200万',icon: IMG + 'bike_hayabusa.png' },
  /* Phase 2 — custom (synthesis) */
  yzfr3c:   { id: 'yzfr3c',   name: 'YZF-R3 カスタム',         cc: '320cc',  ccNum: 320,  category: 'SS',             price: '50-70万',  icon: IMG + 'bike_yzfr3c.png',  isCustom: true },
  z900t:    { id: 'z900t',    name: 'Z900 ツアラー仕様',       cc: '950cc',  ccNum: 950,  category: 'ストファイ',     price: '80-110万', icon: IMG + 'bike_z900t.png',   isCustom: true },
  cbr600r:  { id: 'cbr600r',  name: 'CBR600RR レーサー仕様',   cc: '600cc',  ccNum: 600,  category: 'SS',             price: '100-140万',icon: IMG + 'bike_cbr600r.png', isCustom: true },
  zx10rf:   { id: 'zx10rf',   name: 'ZX-10R フルチューン',     cc: '1000cc', ccNum: 1000, category: 'SS',             price: '150-200万',icon: IMG + 'bike_zx10rf.png', isCustom: true },
};

/* ─── 合成レシピ ─── */
export const SYNTHESIS = [
  { id: 'yzfr3c',  bike: 'yzfr3',  part: 'aftermarket_muffler', result: 'yzfr3c'  },
  { id: 'z900t',   bike: 'z900',   part: 'pannier_case',        result: 'z900t'   },
  { id: 'cbr600r', bike: 'cbr600', part: 'racing_cowl',         result: 'cbr600r' },
  { id: 'zx10rf',  bike: 'zx10r',  part: 'turbo_kit',           result: 'zx10rf'  },
];

/* ─── NPC (21人) ─── */
export const NPCS = {
  /* ══════════ 街エリア ══════════ */
  soba_shop: {
    id: 'soba_shop', name: '蕎麦屋のおやじ（ゲンさん）', area: 'street',
    wants: null, gives: 'cub50', image: IMG + 'npc_soba_shop.png', isShop: true,
    idle: 'もう引退だからなぁ…カブが余っちまって…',
    greeting: ['おう、若いの。バイク乗りかい。','ウチは来月で店じまいなんだ。','出前用のカブが何台も余っちまってよ。','欲しけりゃ持っていきな。タダでいいよ'],
    alreadyOwned: 'もうカブ持ってるじゃねぇか！ また必要になったら来な',
  },

  commuter: {
    id: 'commuter', name: '通勤おじさん（田中さん）', area: 'street',
    wants: 'cub50', gives: 'pcx', image: IMG + 'npc_commuter.png',
    idle: 'はぁ…ガソリン代が…今月もう3回も給油したよ…',
    greeting: ['ん？ キミ、バイク乗りかい？','いやね、ウチのPCXも悪くないんだけど、','もっと燃費のいいバイクがないかなぁって…'],
    correct: ['こっ…これは！ スーパーカブ！！','リッター60km…いや、70km走るって聞いたことある…！','これだよ、これが欲しかったんだ！'],
    correctAfter: ['大事にしてくれよ、PCX。','まぁ俺にはもうカブがあるからいいんだけどね…ふふ…'],
    wrong: {
      ct125: 'ハンターカブ？ いいねぇ…でも若い子向けでしょ？ おじさんが乗ったら似合わないよ…',
      gb350: 'クラシックか…味はあるけど、燃費で選ぶ俺には贅沢だなぁ',
      crf: '泥がつくやつはちょっと… 奥さんに怒られる未来が見える',
      address: 'アドレス…通勤にはいいけど、もっと燃費が…もっと…',
      pcx: 'それ俺が今乗ってるやつなんだけど…？',
      ninja250: '速そうだねぇ…でも会社の駐輪場に停めたら目立ちすぎるんだよ',
      z900: 'うわっ…でかい…！ 維持費考えただけで胃が痛くなってきた…',
      hayabusa: 'うわっ…でかい…！ ガソリン何リッター入るの…？ 遠慮するよ',
      rebel1100: 'アメリカンか…かっこいいけど、通勤には大げさだよね',
      _default: 'うーん…もっと燃費のいいやつが…',
    },
  },

  college_girl: {
    id: 'college_girl', name: '女子大生（ミサキ）', area: 'street',
    wants: 'pcx', gives: 'ct125', image: IMG + 'npc_college_girl.png',
    idle: 'もっとオシャレなバイクでカフェ巡りしたいなぁ…',
    greeting: ['あ、こんにちは！','私、去年ハンターカブ買ったんだけど、','結局キャンプ1回しか行ってなくて…','街乗りでオシャレなバイクに乗り換えたいなって'],
    correct: ['えっ、PCX!? めっちゃいいじゃん！','メットインに荷物入るし、スカートでも乗れるし…','CT125…正直ちょっと持て余してたの。交換してくれる？'],
    correctAfter: ['ハンターカブ、すっごくいいバイクだよ！','…私が使いこなせなかっただけで'],
    wrong: {
      cub50: 'カブ…？ おばあちゃんが乗ってるイメージ… いや、レトロ可愛いのはわかるよ？ でもちょっと…',
      address: 'アドレス…配達の人が乗ってるイメージ… おしゃれじゃないかも…',
      ct125: 'え、それ私が今乗ってるやつなんだけど…？',
      crf: '泥だらけのバイクでスタバ行けないでしょ…',
      gb350: 'GB350！ 可愛いけど…メットインがないのはちょっと…',
      ninja250: 'えー、かっこいいけど…前傾姿勢でしょ？ ネイルしたまま乗りたいんだけど',
      z900: 'え、無理無理無理！ 重すぎ！ 私が求めてるのは"映え"であって"筋肉"じゃないから！',
      hayabusa: 'え、無理無理無理！ 重すぎ！ これ停めるだけで筋トレじゃん…',
      _default: 'うーん、それはちょっと私には合わないかも…',
    },
  },

  delivery: {
    id: 'delivery', name: '出前バイトくん（ユウキ）', area: 'street',
    wants: 'cub50', gives: 'address', image: IMG + 'npc_delivery.png',
    idle: 'あーーー、今日も配達30件…ケツ痛い…',
    greeting: ['あ、先輩ライダーっすか？','俺、今アドレスで配達してんすけど、','正直キツくて。もっと頑丈で燃費いいのないっすかね'],
    correct: ['うおっ！ カブじゃん！！','配達の先輩がみんな「最終的にカブに戻る」って言ってたんすよ！','これが配達の最終兵器…！'],
    correctAfter: ['アドレスもいいバイクっすよ。','…ただ、カブの前ではすべてが霞むんすわ'],
    wrong: {
      pcx: 'PCXか…悪くないけど、カブほどの耐久性はないんすよね',
      ct125: 'ハンターカブ…かっこいいっすけど、配達にはちょいオーバースペックっす',
      gb350: 'オシャレっすね…でも汁こぼしてシート汚したら泣くじゃないっすか',
      crf: 'オフ車で配達…？ 段差には強そうだけど箱が積めないっす',
      z900: 'えっ…こんなの配達に使えないっすよ…駐禁切られまくるっす',
      hayabusa: 'えっ…こんなの配達に使えないっすよ…',
      _default: 'えっ…それ配達に使えないっすよ…',
    },
  },

  /* ══════════ 郊外エリア ══════════ */
  camper: {
    id: 'camper', name: 'キャンプライダー（タケシ）', area: 'suburb',
    wants: 'ct125', gives: 'crf', image: IMG + 'npc_camper.png',
    idle: 'テント、寝袋、焚き火台…全部積みたい…',
    greeting: ['よう！ いいバイク乗ってるな。','俺、CRFでキャンプ行ってるんだけどさ、','荷物が積めなくてパンパンなんだよ。','もっと積載できるバイクに乗り換えたいんだわ'],
    correct: ['ハンターカブ！！ これだよこれ！！','リアキャリアにホムセン箱つけたら最強じゃん！','キャンプツーリングの最適解はこれだったんだ…！'],
    correctAfter: ['CRFはいいバイクだぞ。林道はガチで楽しい。','…荷物さえ積めればな'],
    wrong: {
      cub50: 'カブ50…積載はいいけど高速乗れないからキャンプ場まで辿り着けないんだわ',
      pcx: 'スクーターでキャンプ？ メットインに焚き火台は入らないだろ',
      gb350: 'クラシックか…見た目はいいが積載が不安だな',
      address: 'アドレスでキャンプ…？ さすがに荷物が乗らないだろ',
      rebel1100: 'アメリカン…ロマンはわかる。でも砂利道で立ちゴケする未来しか見えない',
      _default: 'うーん、それだとキャンプ道具が積めないなぁ…',
    },
  },

  bike_girl: {
    id: 'bike_girl', name: 'バイク女子（アヤカ）', area: 'suburb',
    wants: 'ct125', gives: 'gb350', image: IMG + 'npc_bike_girl.png',
    idle: 'ハンターカブ可愛すぎて気になる…',
    greeting: ['あ、ちょっとそのバイク見せてもらっていい？','私、GB350に乗ってるんだけど、','最近カブ系の丸っこいフォルムにハマっちゃって…','乗り換えようか迷ってるの'],
    correct: ['やっぱりハンターカブ最高に可愛い！！','サムネ映えするし、女子ウケもいいし…','GB350より動画のネタにもなりそう！'],
    correctAfter: ['GB350大事にしてね。鼓動感、最高だから。','…私の動画のコメント欄が荒れなきゃいいけど'],
    wrong: {
      cub50: 'カブ50！ かわいい！ …けど50ccだと高速乗れないからツーリング動画が撮れない…',
      pcx: 'PCXは便利だけど、動画映えしないんだよね…',
      crf: 'オフ車はね…汚れるの… 洗車動画ばっかりになっちゃう',
      address: 'アドレスは…さすがにYouTuber的に厳しいかな…',
      ninja250: 'Ninja系は色がちょっと…私の世界観と合わなくて',
      z900: 'うーん…私のチャンネル的にはデカすぎるかな',
      _default: 'うーん…私のチャンネル的にはちょっと違うかな',
    },
  },

  returner: {
    id: 'returner', name: 'リターンライダー（ヤマモトさん）', area: 'suburb',
    wants: 'gb350', gives: 'cb400', bonusPart: 'old_helmet',
    image: IMG + 'npc_returner.png',
    idle: '昔はよく走ったなぁ…CB400SF…\nでも今の俺には、もう少し落ち着いたバイクが似合うかな',
    greeting: ['おう、若いの。いいバイクに乗ってるな。','俺、20年ぶりにバイクに復帰したんだけどさ、','CB400SFで飛ばす歳でもないんだよな。','もっとゆったり走れるクラシックなバイクがいいなぁって'],
    correct: ['おお…GB350…！','このエンジンの単気筒の鼓動…昔のGBを思い出すなぁ…','これだよ、俺が求めてたのは。速さじゃない、味わいなんだ。','CB400SFはお前さんにやるよ。大事に乗ってくれ'],
    correctAfter: ['あ、そうだ。昔使ってたヘルメットも持っていきな。','もう俺のデカい頭には合わないんだ。はっはっは'],
    wrong: {
      zx10r: 'おいおい、俺を殺す気か…？ 20年ブランクの爺さんにリッターSSは自殺行為だぞ',
      hayabusa: 'おいおい、俺を殺す気か…？ 20年ブランクにこれは…',
      z900: 'おいおい、大型ネイキッドか…反射神経が昔の半分もないんだから',
      cbr600: 'おいおい、俺を殺す気か…？ SSは自殺行為だぞ',
      cub50: 'カブか…。実用的なのはわかるが、リターンライダーの夢が萎むわ',
      crf: 'オフロード…。腰を壊す未来が見える',
      mt07: 'MT-07か…いいバイクだけど、見た目が新しすぎるんだよな。俺が求めてるのは"懐かしさ"なんだ',
      cb400: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、もう少し落ち着いた雰囲気のバイクがいいんだが…',
    },
  },

  offroad: {
    id: 'offroad', name: '林道ライダー（コバヤシ）', area: 'suburb',
    wants: 'crf', gives: 'ninja250', image: IMG + 'npc_offroad.png',
    idle: 'Ninjaでダート走ったら転びまくった…\nやっぱりオフ車買わないとダメか…',
    greeting: ['お、CRF乗り？ いいなぁ…','俺、Ninja250でずっとサーキット通ってたんだけど、','最近林道にハマっちゃって。ロードタイヤじゃ話にならないんだよ。','ちゃんとしたオフ車が欲しいんだわ'],
    correct: ['CRF250L！ これよこれ！','21インチのフロントタイヤ…ロンスイの安定感…','もうNinjaで砂利道ビビりながら走るのは終わりだ！'],
    correctAfter: ['Ninjaは峠で最高のバイクだぞ。','…林道には持っていくなよ。俺みたいになる'],
    wrong: {
      z900: '林道にリッターバイク…？ 倒したら一人で起こせないぞ',
      hayabusa: '林道にリッターバイク…？ 命知らずだぞ',
      zx10r: '林道にリッターバイク…？ 倒したら一人で起こせないし、命知らずだぞ',
      pcx: 'スクーターで林道…？ 10分で動けなくなるぞ。マジで',
      address: 'スクーターで林道…？ 10分で動けなくなるぞ',
      rebel1100: 'アメリカンで林道は…ステップ擦って火花散らすのが趣味なら止めないけど',
      ninja250: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、それだと林道は厳しいな…オフ車が欲しいんだ',
    },
  },

  /* ══════════ 峠エリア ══════════ */
  touge: {
    id: 'touge', name: '峠の走り屋（リュウジ）', area: 'pass',
    wants: 'ninja250', gives: 'yzfr3', image: IMG + 'npc_touge.png',
    idle: 'R3、パワーはいいんだけど…\nもう少し軽いバイクでコーナー攻めたいな…',
    greeting: ['よう。この峠、攻めに来たのか？','俺はR3で走ってるんだけどさ、最近もっと軽いバイクで','切り返しをキレキレにしたいと思ってるんだよ。','250ccクラスのスポーツ、興味あるんだよな'],
    correct: ['Ninja250！！ 車重166kg…これだよ！','R3より20kg近く軽い…コーナーの切り返しが全然違うはず！','パワーじゃない、軽さが武器なんだ！','R3はお前にやる。直線は速いぞ'],
    correctAfter: ['R3の倒立フォーク、峠で信頼できるから。','…俺はもう軽さの世界に行く'],
    wrong: {
      hayabusa: '重い！ 重すぎる！！ リッターバイクで峠のヘアピン攻めるのは腕力トレーニングだぞ',
      zx10r: '重い！ 重すぎる！！ "曲がる楽しさ"がわからんのか',
      z900: '重い…峠のヘアピン攻めるのは腕力トレーニングだぞ',
      cub50: '…お前、俺をナメてんのか？ いや、カブで峠攻める動画は見たことあるけど…',
      pcx: '…お前、俺をナメてんのか？',
      address: '…お前、俺をナメてんのか？',
      rebel1100: 'アメリカンで峠…？ バンク角3度くらいで接地するだろ',
      gb350: 'クラシック系か…味はあるけど、峠でタイムを出すバイクじゃないんだよなぁ',
      yzfr3: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、それだとコーナー攻めには向かないな…',
    },
  },

  newbie: {
    id: 'newbie', name: '教習所帰りくん（ソウタ）', area: 'pass',
    wants: 'cb400', gives: 'mt07', bonusPart: 'stock_muffler',
    image: IMG + 'npc_newbie.png',
    idle: 'MT-07…パワーありすぎ…怖い…\n教習所のバイクが懐かしい…',
    greeting: ['あ、あの…先輩ですか？','僕、先月大型免許取ったばっかりで、MT-07買ったんですけど…','パワーがありすぎて怖くて…','教習車と同じCB400SFなら安心できるかなって…'],
    correct: ['CB400SF！！ これ教習所で乗ってたやつ！','このクラッチの感覚…このポジション…安心する…！','MT-07は600ccの力があるんで、僕にはまだ早かったんです…'],
    correctAfter: ['MT-07、マフラー純正のままなんで、余ったやつも持っていってください。','社外マフラーに替えようと思って買ってたんですけど…'],
    wrong: {
      hayabusa: 'ひぃぃ！ こ、これ何馬力あるんですか…！？ 僕にリッターSSは…死にます…',
      zx10r: 'ひぃぃ！ こ、これ何馬力あるんですか…！？ 死にます…',
      z900: 'ひぃぃ！ パワーありすぎます…MT-07で怖がってる僕に…',
      cub50: 'カブ…。正直ちょっと惹かれます… でもせっかく大型免許取ったのに…',
      ninja250: '250cc…。いいなぁ、扱いやすそう… でも大型免許取った意味がなくなっちゃう…',
      rebel1100: 'アメリカンは…格好いいですけど、ニーグリップできないバイクはまだ怖い…',
      cb400: 'それ俺…いや僕が欲しいやつです…？ あ、でもそれ僕のじゃないですよね？',
      _default: 'うーん…教習車と同じCB400SFが安心なんです…',
    },
  },

  parts_shop: {
    id: 'parts_shop', name: 'パーツ屋のオヤジ', area: 'pass',
    isPartsShop: true, wantsPart: 'stock_muffler', givesPart: 'aftermarket_muffler',
    image: IMG + 'npc_parts_shop.png',
    idle: '純正マフラーの在庫が切れちまった…\n誰か余ってるやつ持ってないかな',
    greeting: ['おう、兄ちゃん！ ウチはマフラー専門店だ。','今、純正マフラーの買い取り強化中なんだ。','純正マフラー持ってたら、社外品と交換するぜ！'],
    correct: ['おっ！ これ状態いいじゃねぇか！','よし、約束通り社外マフラーと交換だ。','ヨシムラ管…いい音するぞ。峠で目立つこと間違いなし！'],
    noPartMessage: '純正マフラーを持ってないのか？ 手に入ったらまた来てくれよ',
  },

  ex_racer: {
    id: 'ex_racer', name: '元レーサー（カズマ師匠）', area: 'pass',
    wants: 'yzfr3c', gives: 'z900', image: IMG + 'npc_ex_racer.png',
    idle: '弟子に練習させるバイクが必要なんだが…\nZ900じゃ初心者には荷が重すぎる',
    greeting: ['おう。お前、いい目をしてるな。','俺は昔レースをやってた。今は若いのを育ててる。','弟子に乗せる練習用バイクを探してるんだ。','適度なパワーで、ちゃんとチューンされたやつがいい'],
    correct: ['ほう…R3にアフターマフラー…。','排気効率が上がって中回転域のトルクが出てるな。','これなら弟子にサーキットの基本を教えるのにちょうどいい。','代わりにZ900をやろう。俺にはもうパワーは要らん'],
    correctAfter: ['Z900、素直な特性のいいバイクだ。','お前ならうまく乗りこなせるだろう'],
    wrong: {
      yzfr3: 'R3か…悪くない。だがノーマルのままでは物足りん。もう少し手を入れたやつはないのか？ 吸排気が変わるだけで別のバイクになるんだがな',
      cub50: '…お前、俺をからかってるのか？ カブのレースもあるにはあるが…俺が育てたいのはロードレーサーだ',
      hayabusa: '隼で練習…？ 初心者に300馬力は殺しにかかってるぞ',
      z900: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、弟子の練習用にはちょっと違うな…',
    },
  },

  /* ══════════ 海岸通りエリア ══════════ */
  seaside: {
    id: 'seaside', name: '海沿いマスター（ゴロウさん）', area: 'coast',
    wants: ['gb350', 'cb400'], gives: 'rebel1100', image: IMG + 'npc_seaside.png',
    idle: '腰がなぁ…レブル1100でも腰に来るようになっちまった…',
    greeting: ['よぉ、若いの。海沿い走るのは気持ちいいだろ？','俺ぁ長いことアメリカンに乗ってきたんだが、','腰がもう限界でな…。もう少しコンパクトで、','エンジンの味わいがあるバイクに乗り換えたいんだ'],
    correctMap: {
      gb350: ['おお…GB350…。この単気筒の鼓動…','ドコドコ感がたまらんな…。これなら軽いし、','海沿いをのんびり流すにはちょうどいい。','レブル1100はお前さんにやるよ。まだまだ走れるバイクだ'],
      cb400: ['CB400SF…懐かしいな。昔、教習所でコイツに乗ったっけ。','4気筒の滑らかさ…年寄りには優しいエンジンだ。','レブル1100と交換してやるよ。大事に乗ってくれ'],
    },
    correctAfter: ['海沿いをのんびり走るのが一番だ。','若いうちに色んな道を走っておけよ'],
    wrong: {
      z900: '前傾姿勢…？ 腰が…腰が…！ 今これに乗ったら整体通いだぞ',
      zx10r: '前傾姿勢…？ 腰が…腰が…！',
      cbr600: '前傾姿勢…？ 腰が…腰が…！',
      hayabusa: '前傾姿勢…？ 腰が…腰が…！ 若い頃ならともかく…',
      crf: '砂浜走れって？ 腰への衝撃でぎっくり腰確定だわ',
      cub50: 'カブか…。味はあるがな、ハーレーからカブは…落差がすごすぎる',
      rebel1100: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、もう少しコンパクトで味わいのあるバイクがいいんだが…',
    },
  },

  gear_shop: {
    id: 'gear_shop', name: '用品店の店員', area: 'coast',
    isPartsShop: true, wantsPart: 'old_helmet', givesPart: 'pannier_case',
    image: IMG + 'npc_gear_shop.png',
    idle: 'ヴィンテージヘルメットのコレクションを増やしたいんだよなぁ…',
    greeting: ['いらっしゃい！ 当店はバイク用品専門店です。','実は店長の趣味でヴィンテージヘルメットを集めてまして…','古いヘルメットをお持ちでしたら、パニアケースと交換しますよ！'],
    correct: ['おおっ！ このヘルメット、年代物じゃないですか！','状態も悪くない…コレクションに加えさせてもらいます！','約束通り、パニアケースをどうぞ。ロングツーリングに最適ですよ'],
    noPartMessage: '古いヘルメットをお持ちでないですか…？ 手に入ったらまたお越しください',
  },

  world_rider: {
    id: 'world_rider', name: '世界一周ライダー（マコト）', area: 'coast',
    wants: 'z900t', gives: 'vstrom', bonusPart: 'travel_diary',
    image: IMG + 'npc_world_rider.png',
    idle: 'Vストロームで世界一周してきたけど…\n日本の高速はもっとパワーがあるバイクで走りたいな',
    greeting: ['おう、旅人か？ 俺は去年、Vストロームで世界一周してきた。','次は日本一周するんだが、高速メインで走りたいんだ。','パニアケース付きで高速が楽なバイクがあれば最高なんだが'],
    correct: ['Z900にパニアケース付き…！ これは理想的だ！','高速で余裕のあるパワーに、荷物も積める…','Vストロームは最高の相棒だったが、次のステージに進む時だ'],
    correctAfter: ['この旅日記、世界一周で書いたやつだ。持っていってくれ。','バイク乗りなら、きっと何か感じるものがあるはずだ'],
    wrong: {
      z900: 'Z900…パワーは申し分ないんだが、荷物をどこに積むんだ？ 旅にはパニアが必須だぞ。どこかでパニアケースを手に入れて付けてくれないか',
      cub50: 'カブで世界一周したやつは知ってるぞ。すごい話だが…俺は高速で120km/h出したいんだ',
      vstrom: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、高速が楽でパニアが付くバイクがいいんだが…',
    },
  },

  /* ══════════ サーキットエリア ══════════ */
  streetfighter: {
    id: 'streetfighter', name: 'ストファイ兄さん（テツヤ）', area: 'circuit',
    wants: 'z900', gives: 'mt09', image: IMG + 'npc_streetfighter.png',
    idle: 'ヤマハもいいけど…やっぱカワサキだよな…\nあのライムグリーン…漢のバイク…',
    greeting: ['おう！ いいバイク持ってんじゃん！','俺、MT-09でずっとストリート走ってたんだけどさ、','最近カワサキに惚れちまってよ。','Z系のストファイに乗り換えたいんだわ'],
    correct: ['Z900…！ このスガタミヤのシャープなライン…！','漢カワサキの血統…！ 俺が求めてたのはコレだ！','MT-09をやるよ。ヤマハも最高のバイクだ。','…でも俺の心はもうカワサキに決まったんだ'],
    correctAfter: ['MT-09、クロスプレーンエンジンは最高だぞ。','…カワサキの漢として言うのも変だけどな'],
    wrong: {
      ninja250: '250cc…？ 俺が欲しいのは"圧"なんだよ。信号待ちで隣に並んだ時の"圧"',
      yzfr3: '250cc…？ 俺が欲しいのは"圧"なんだよ',
      rebel1100: 'アメリカンか…嫌いじゃないけど、ストファイの"攻めてる感"がないんだよな',
      cub50: '…これはこれで、ある種の漢気を感じるが…俺が欲しいのはそういう方向じゃない',
      mt09: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、カワサキのZ系が欲しいんだわ…',
    },
  },

  trackday: {
    id: 'trackday', name: '走行会常連（ナオキ）', area: 'circuit',
    wants: 'mt09', gives: 'cbr600', bonusPart: 'sponsor_sticker',
    image: IMG + 'npc_trackday.png',
    idle: 'サーキット用はCBR600RRがあるから…\n街乗り用のバイクが欲しいんだよな…',
    greeting: ['お、サーキットに興味あるのか？','俺、CBR600RRでずっと走行会出てるんだけどさ、','街乗り用のバイクがなくて困ってるんだよ。','トルクがあってストリートで楽しいバイクがないかな'],
    correct: ['MT-09！ クロスプレーンエンジンのトルク感…！','街乗りで使うにはこれ以上ないだろ。','CBR600RRはサーキット専用にしてたから、','お前に譲るよ。大事に乗ってくれ'],
    correctAfter: ['あ、そうだ。去年のスポンサーステッカー、もう使わないから持ってけ。','サーキットのピットクルーが欲しがってた気がする'],
    wrong: {
      ninja250: '250か…。街乗りには十分だけど、高速の合流でもう少し余裕が欲しいんだよな',
      yzfr3: '250か…。600以上のトルク感を知っちゃうと戻れない',
      hayabusa: '隼で街乗り…？ 近所のコンビニに300馬力で行くのか…？',
      cbr600: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、もっとトルクがあってストリートで楽しいやつがいいな…',
    },
  },

  pitcrew: {
    id: 'pitcrew', name: 'ピットクルー', area: 'circuit',
    isPartsShop: true, wantsPart: 'sponsor_sticker', givesPart: 'racing_cowl',
    image: IMG + 'npc_pitcrew.png',
    idle: 'チームのスポンサーロゴ、新しいの探さないとなぁ…',
    greeting: ['お、ライダーさん！ ウチのチーム、来シーズン用のカウルが余ってるんだ。','スポンサーステッカーと交換してくれない？','チームのPRに使いたいんだよ'],
    correct: ['おお、これ去年の有名チームのステッカーじゃん！','これは価値がある！ レーシングカウル、持っていってくれ！','CBRに付けたら一気にレーサー仕様になるぞ'],
    noPartMessage: 'スポンサーステッカーを持ってないのか？ 走行会常連に聞いてみな',
  },

  retired_racer: {
    id: 'retired_racer', name: '引退レーサー（ケンジ）', area: 'circuit',
    wants: 'cbr600r', gives: 'zx10r', image: IMG + 'npc_retired_racer.png',
    idle: '後輩に渡すレース用バイクがないんだよなぁ…\nZX-10Rじゃ初心者レーサーには危険すぎる',
    greeting: ['おう、いい走りしてたな。見てたぞ。','俺は昔、全日本を走ってた。今は後輩を育ててる。','600ccのレーサーが欲しいんだ。','若いやつに最初からリッターは危険だからな'],
    correct: ['CBR600RRにレーシングカウル…完璧だ。','600ccのレーサーは基本を学ぶのに最適なんだ。','コイツなら後輩も安全にレースの世界に入れる。','ZX-10R、お前に託す。リッターの世界へようこそ'],
    correctAfter: ['ZX-10R、リッターの力を知れ。','お前ならうまく使いこなせるはずだ'],
    wrong: {
      cbr600: 'CBR600RR…いいマシンだが、ノーマルのままか。レースに出すならレーシングカウルが要るんだ。保安部品を外して、軽量カウルを付けないとな',
      ninja250: '250で全日本…？ 俺が育てたいのはST600クラスのライダーだ',
      yzfr3: '300ccクラスか…悪くないが、俺が育てたいのはST600クラスだ',
      hayabusa: '隼…速いマシンだが、サーキットのレギュレーションに合わない',
      zx10r: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん、600ccのレーサー仕様じゃないと…',
    },
  },

  mechanic: {
    id: 'mechanic', name: '伝説のメカニック', area: 'circuit',
    isPartsShop: true, wantsPart: 'travel_diary', givesPart: 'turbo_kit',
    visibleIfBikeEverOwned: 'vstrom',
    image: IMG + 'npc_mechanic.png',
    idle: 'バイクの魂を理解するやつにしか、俺の技術は渡さない…',
    greeting: ['…お前、ただの走り屋じゃないな。','俺は50年バイクをいじってきた。','今まで誰にもターボキットを渡したことはない。','だが…バイクで世界を見てきた人間の言葉には興味がある。','旅の記録…そういうものを持っていたら見せてくれないか'],
    correct: ['…………。','世界一周か。砂漠も、山岳も、豪雨の中も走ったのか。','バイクは機械だが、乗る人間の魂が宿る。','…よし。ターボキットを受け取れ。お前になら渡せる'],
    noPartMessage: '…手ぶらか。俺の技術はタダじゃない。バイクで世界を見た人間の記録を持ってこい',
  },

  legend: {
    id: 'legend', name: '伝説のライダー（タツヤ）', area: 'circuit',
    wants: 'zx10rf', gives: 'hayabusa', image: IMG + 'npc_legend.png',
    idle: '速さの先に何があるか…俺はもう見つけた。\nだが、まだ夢を追うやつがいるなら…',
    greeting: ['…お前、ここまで来たか。','カブ一台から始めて、ここまでたどり着いたのか。','…面白いやつだ。','俺が持っているのは隼。最速の名を持つバイクだ。','だが、ただの速さでは俺の心は動かない。','限界までチューンされたマシンを見せてみろ'],
    correct: ['…ZX-10R、フルチューン仕様。','エンジン、足回り、空力…すべてが究極を目指している。','このマシンには、お前がここまで歩んできた道のりが詰まっている。','いいだろう。隼を受け取れ。','俺はもう速さの先にあるものを見つけた。','お前もいつか、わかる時が来る。','…カブから隼まで。大した旅だったな'],
    correctAfter: ['隼を手にした者よ…','どのバイクに乗っても、お前はライダーだ'],
    wrong: {
      zx10r: 'ZX-10R…いいマシンだ。だが、ノーマルか。限界はまだ先にある。このマシンのポテンシャルを全て引き出してから来い',
      cub50: '……カブか。\n……ふっ。\n面白いやつだな。だが、まだ早い。お前の旅はまだ始まったばかりだ',
      hayabusa: 'それ…俺が持っているやつだ。二台は要らんだろう？',
      _default: '…まだだな。準備が足りないだけだ。また来い',
    },
    /* 隠しイベント: クリア後にカブ50で話しかけた場合 */
    hiddenEvent: [
      '…お前、隼を手に入れた後にカブで来たのか。',
      '……はっはっは！！',
      'お前…わかってるじゃないか。',
      '速さの先にあるもの…それは"原点"だ。',
      '1340ccも50ccも、走る喜びは同じなんだ。',
      'お前に教えることはもう何もない。',
      'どのバイクに乗っても、お前はライダーだ',
    ],
  },
};

/* ─── エリア (5箇所) ─── */
export const AREAS = {
  street: {
    id: 'street', name: '街', emoji: '🏙️',
    bg: IMG + 'bg_street.png',
    npcs: ['soba_shop', 'commuter', 'college_girl', 'delivery'],
  },
  suburb: {
    id: 'suburb', name: '郊外', emoji: '🌲',
    bg: IMG + 'bg_suburb.png',
    npcs: ['camper', 'bike_girl', 'returner', 'offroad'],
  },
  pass: {
    id: 'pass', name: '峠', emoji: '⛰️',
    bg: IMG + 'bg_pass.png',
    npcs: ['touge', 'newbie', 'parts_shop', 'ex_racer'],
  },
  coast: {
    id: 'coast', name: '海岸通り', emoji: '🌊',
    bg: IMG + 'bg_coast.png',
    npcs: ['seaside', 'gear_shop', 'world_rider'],
  },
  circuit: {
    id: 'circuit', name: 'サーキット', emoji: '🏁',
    bg: IMG + 'bg_circuit.png',
    npcs: ['streetfighter', 'trackday', 'pitcrew', 'retired_racer', 'mechanic', 'legend'],
  },
};
