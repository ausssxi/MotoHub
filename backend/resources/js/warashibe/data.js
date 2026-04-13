const IMG = '/images/warashibe/';

export const BIKES = {
  cub50:   { id: 'cub50',   name: 'スーパーカブ50',    cc: '50cc',  category: '実用',         price: '10-20万', icon: IMG + 'bike_cub50.png' },
  pcx:     { id: 'pcx',     name: 'PCX125',           cc: '125cc', category: 'スクーター',    price: '20-35万', icon: IMG + 'bike_pcx.png' },
  address: { id: 'address', name: 'アドレス110',       cc: '110cc', category: 'スクーター',    price: '10-20万', icon: IMG + 'bike_address.png' },
  ct125:   { id: 'ct125',   name: 'CT125 ハンターカブ', cc: '125cc', category: 'アウトドア',    price: '30-45万', icon: IMG + 'bike_ct125.png' },
  crf:     { id: 'crf',     name: 'CRF250L',          cc: '250cc', category: 'オフロード',    price: '40-55万', icon: IMG + 'bike_crf.png' },
  gb350:   { id: 'gb350',   name: 'GB350',            cc: '350cc', category: 'ネオクラシック', price: '45-60万', icon: IMG + 'bike_gb350.png' },
};

export const NPCS = {
  soba_shop: {
    id: 'soba_shop',
    name: '蕎麦屋のおやじ（ゲンさん）',
    area: 'street',
    wants: null,
    gives: 'cub50',
    image: IMG + 'npc_soba_shop.png',
    isShop: true,
    idle: 'もう引退だからなぁ…カブが余っちまって…',
    greeting: [
      'おう、若いの。バイク乗りかい。',
      'ウチは来月で店じまいなんだ。',
      '出前用のカブが何台も余っちまってよ。',
      '欲しけりゃ持っていきな。タダでいいよ',
    ],
    alreadyOwned: 'もうカブ持ってるじゃねぇか！ また必要になったら来な',
    afterExchange: [
      'おう、また来たか！ カブが要るのか？',
      '何台でも持ってきな。倉庫パンパンだからよ',
    ],
  },
  commuter: {
    id: 'commuter',
    name: '通勤おじさん（田中さん）',
    area: 'street',
    wants: 'cub50',
    gives: 'pcx',
    image: IMG + 'npc_commuter.png',
    idle: 'はぁ…ガソリン代が…今月もう3回も給油したよ…',
    greeting: [
      'ん？ キミ、バイク乗りかい？',
      'いやね、ウチのPCXも悪くないんだけど、',
      'もっと燃費のいいバイクがないかなぁって…',
    ],
    correct: [
      'こっ…これは！ スーパーカブ！！',
      'リッター60km…いや、70km走るって聞いたことある…！',
      'これだよ、これが欲しかったんだ！',
    ],
    correctAfter: [
      '大事にしてくれよ、PCX。',
      'まぁ俺にはもうカブがあるからいいんだけどね…ふふ…',
    ],
    wrong: {
      ct125: 'ハンターカブ？ いいねぇ…でも若い子向けでしょ？ おじさんが乗ったら似合わないよ…',
      gb350: 'クラシックか…味はあるけど、燃費で選ぶ俺には贅沢だなぁ',
      crf: '泥がつくやつはちょっと… 奥さんに怒られる未来が見える',
      address: 'アドレス…通勤にはいいけど、もっと燃費が…もっと…',
      pcx: 'それ俺が今乗ってるやつなんだけど…？',
      _default: 'うーん…もっと燃費のいいやつが…',
    },
    afterExchange: [
      'カブ最高だよ…今朝の通勤、燃費リッター68kmだった…',
      'もうPCXには戻れないね。ふふ…',
    ],
  },
  college_girl: {
    id: 'college_girl',
    name: '女子大生（ミサキ）',
    area: 'street',
    wants: 'pcx',
    gives: 'ct125',
    image: IMG + 'npc_college_girl.png',
    idle: 'もっとオシャレなバイクでカフェ巡りしたいなぁ…',
    greeting: [
      'あ、こんにちは！',
      '私、去年ハンターカブ買ったんだけど、',
      '結局キャンプ1回しか行ってなくて…',
      '街乗りでオシャレなバイクに乗り換えたいなって',
    ],
    correct: [
      'えっ、PCX!? めっちゃいいじゃん！',
      'メットインに荷物入るし、スカートでも乗れるし…',
      'CT125…正直ちょっと持て余してたの。交換してくれる？',
    ],
    correctAfter: [
      'ハンターカブ、すっごくいいバイクだよ！',
      '…私が使いこなせなかっただけで',
    ],
    wrong: {
      cub50: 'カブ…？ おばあちゃんが乗ってるイメージ… いや、レトロ可愛いのはわかるよ？ でもちょっと…',
      address: 'アドレス…配達の人が乗ってるイメージ… おしゃれじゃないかも…',
      ct125: 'え、それ私が今乗ってるやつなんだけど…？',
      crf: '泥だらけのバイクでスタバ行けないでしょ…',
      gb350: 'GB350！ 可愛いけど…メットインがないのはちょっと…',
      _default: 'うーん、それはちょっと私には合わないかも…',
    },
    afterExchange: [
      'PCX最高～！ 今日もカフェ3軒ハシゴしちゃった！',
      'メットインにエコバッグ入るのが神すぎる',
    ],
  },
  delivery: {
    id: 'delivery',
    name: '出前バイトくん（ユウキ）',
    area: 'street',
    wants: 'cub50',
    gives: 'address',
    image: IMG + 'npc_delivery.png',
    idle: 'あーーー、今日も配達30件…ケツ痛い…',
    greeting: [
      'あ、先輩ライダーっすか？',
      '俺、今アドレスで配達してんすけど、',
      '正直キツくて。もっと頑丈で燃費いいのないっすかね',
    ],
    correct: [
      'うおっ！ カブじゃん！！',
      '配達の先輩がみんな「最終的にカブに戻る」って言ってたんすよ！',
      'これが配達の最終兵器…！',
    ],
    correctAfter: [
      'アドレスもいいバイクっすよ。',
      '…ただ、カブの前ではすべてが霞むんすわ',
    ],
    wrong: {
      pcx: 'PCXか…悪くないけど、カブほどの耐久性はないんすよね',
      ct125: 'ハンターカブ…かっこいいっすけど、配達にはちょいオーバースペックっす',
      gb350: 'オシャレっすね…でも汁こぼしてシート汚したら泣くじゃないっすか',
      crf: 'オフ車で配達…？ 段差には強そうだけど箱が積めないっす',
      _default: 'えっ…それ配達に使えないっすよ…',
    },
    afterExchange: [
      'カブ、マジ最強っす！ 配達効率が爆上がりっすよ！',
      '先輩に感謝っす！',
    ],
  },
  camper: {
    id: 'camper',
    name: 'キャンプライダー（タケシ）',
    area: 'suburb',
    wants: 'ct125',
    gives: 'crf',
    image: IMG + 'npc_camper.png',
    idle: 'テント、寝袋、焚き火台…全部積みたい…',
    greeting: [
      'よう！ いいバイク乗ってるな。',
      '俺、CRFでキャンプ行ってるんだけどさ、',
      '荷物が積めなくてパンパンなんだよ。',
      'もっと積載できるバイクに乗り換えたいんだわ',
    ],
    correct: [
      'ハンターカブ！！ これだよこれ！！',
      'リアキャリアにホムセン箱つけたら最強じゃん！',
      'キャンプツーリングの最適解はこれだったんだ…！',
    ],
    correctAfter: [
      'CRFはいいバイクだぞ。林道はガチで楽しい。',
      '…荷物さえ積めればな',
    ],
    wrong: {
      cub50: 'カブ50…積載はいいけど高速乗れないからキャンプ場まで辿り着けないんだわ',
      pcx: 'スクーターでキャンプ？ メットインに焚き火台は入らないだろ',
      gb350: 'クラシックか…見た目はいいが積載が不安だな',
      address: 'アドレスでキャンプ…？ さすがに荷物が乗らないだろ',
      _default: 'うーん、それだとキャンプ道具が積めないなぁ…',
    },
    afterExchange: [
      'ハンターカブ、ホムセン箱つけたら積載量が倍になったぞ！',
      '来週のキャンプが楽しみだ！',
    ],
  },
  bike_girl: {
    id: 'bike_girl',
    name: 'バイク女子（アヤカ）',
    area: 'suburb',
    wants: 'ct125',
    gives: 'gb350',
    image: IMG + 'npc_bike_girl.png',
    idle: 'ハンターカブ可愛すぎて気になる…',
    greeting: [
      'あ、ちょっとそのバイク見せてもらっていい？',
      '私、GB350に乗ってるんだけど、',
      '最近カブ系の丸っこいフォルムにハマっちゃって…',
      '乗り換えようか迷ってるの',
    ],
    correct: [
      'やっぱりハンターカブ最高に可愛い！！',
      'サムネ映えするし、女子ウケもいいし…',
      'GB350より動画のネタにもなりそう！',
    ],
    correctAfter: [
      'GB350大事にしてね。鼓動感、最高だから。',
      '…私の動画のコメント欄が荒れなきゃいいけど',
    ],
    wrong: {
      cub50: 'カブ50！ かわいい！ …けど50ccだと高速乗れないからツーリング動画が撮れない…',
      pcx: 'PCXは便利だけど、動画映えしないんだよね…',
      crf: 'オフ車はね…汚れるの… 洗車動画ばっかりになっちゃう',
      address: 'アドレスは…さすがにYouTuber的に厳しいかな…',
      _default: 'うーん…私のチャンネル的にはちょっと違うかな',
    },
    afterExchange: [
      'ハンターカブの納車動画、もう10万回再生いったよ！',
      '交換してくれてありがとう！',
    ],
  },
};

export const AREAS = {
  street: {
    id: 'street',
    name: '街',
    bg: IMG + 'bg_street.png',
    npcs: ['soba_shop', 'commuter', 'college_girl', 'delivery'],
  },
  suburb: {
    id: 'suburb',
    name: '郊外',
    bg: IMG + 'bg_suburb.png',
    npcs: ['camper', 'bike_girl'],
  },
};
