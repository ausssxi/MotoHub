-- bike_models slug migration
-- Generated: 2026-05-06 16:58:33
-- Records: 648

START TRANSACTION;

UPDATE bike_models SET slug = 'cx-euro' WHERE id = 190; -- cxユーロ
UPDATE bike_models SET slug = 'e-sai' WHERE id = 207; -- e−彩
UPDATE bike_models SET slug = 'r-and-p' WHERE id = 281; -- r&p
UPDATE bike_models SET slug = 'sh-mode' WHERE id = 3488; -- sh モード
UPDATE bike_models SET slug = 'africa-twin' WHERE id = 386; -- アフリカツイン
UPDATE bike_models SET slug = 'ihatov' WHERE id = 3387; -- イーハトーブ
UPDATE bike_models SET slug = 'eve-pax' WHERE id = 387; -- イブパックス
UPDATE bike_models SET slug = 'integra' WHERE id = 388; -- インテグラ
UPDATE bike_models SET slug = 'ape-type-d' WHERE id = 3435; -- エイプ タイプd
UPDATE bike_models SET slug = 'express' WHERE id = 389; -- エクスプレス
UPDATE bike_models SET slug = 'elsinore-125' WHERE id = 3392; -- エルシノア125
UPDATE bike_models SET slug = 'elsinore-250' WHERE id = 390; -- エルシノア250
UPDATE bike_models SET slug = 'cabra' WHERE id = 398; -- カブラ
UPDATE bike_models SET slug = 'cabra-s' WHERE id = 399; -- カブラs
UPDATE bike_models SET slug = 'caren' WHERE id = 400; -- カレン
UPDATE bike_models SET slug = 'cabina-50' WHERE id = 401; -- キャビーナ50
UPDATE bike_models SET slug = 'cabina-90' WHERE id = 402; -- キャビーナ90
UPDATE bike_models SET slug = 'creage-tact' WHERE id = 407; -- クレージュタクト
UPDATE bike_models SET slug = 'crea-scoopy' WHERE id = 404; -- クレアスクーピー
UPDATE bike_models SET slug = 'crea-scoopy-i' WHERE id = 405; -- クレアスクーピーi
UPDATE bike_models SET slug = 'crea-scoopy-i-special' WHERE id = 406; -- クレアスクーピーi スペシャル
UPDATE bike_models SET slug = 'g-dash' WHERE id = 3436; -- ジーダッシュ
UPDATE bike_models SET slug = 'gyro-e' WHERE id = 3586; -- ジャイロe
UPDATE bike_models SET slug = 'gyro-up' WHERE id = 417; -- ジャイロup
UPDATE bike_models SET slug = 'gyro-x' WHERE id = 418; -- ジャイロx
UPDATE bike_models SET slug = 'gyro-x-standard' WHERE id = 3437; -- ジャイロx スタンダード
UPDATE bike_models SET slug = 'gyro-x-basic' WHERE id = 3438; -- ジャイロx ベーシック
UPDATE bike_models SET slug = 'gyro-canopy' WHERE id = 421; -- ジャイロキャノピー
UPDATE bike_models SET slug = 'gyro-canopy-e' WHERE id = 3587; -- ジャイロキャノピーe
UPDATE bike_models SET slug = 'shine-100' WHERE id = 413; -- シャイン100
UPDATE bike_models SET slug = 'shadow-400-custom' WHERE id = 423; -- シャドウ400カスタム
UPDATE bike_models SET slug = 'shadow-400-classic' WHERE id = 424; -- シャドウ400クラシック
UPDATE bike_models SET slug = 'shadow-400-slasher' WHERE id = 3397; -- シャドウ400スラッシャー
UPDATE bike_models SET slug = 'shadow-750-slasher' WHERE id = 3398; -- シャドウ750スラッシャー
UPDATE bike_models SET slug = 'shadow-750-phantom' WHERE id = 3399; -- シャドウ750ファントム
UPDATE bike_models SET slug = 'shadow-special' WHERE id = 426; -- シャドウスペシャル
UPDATE bike_models SET slug = 'shadow-slasher' WHERE id = 427; -- シャドウスラッシャー
UPDATE bike_models SET slug = 'shadow-slasher-deluxe' WHERE id = 429; -- シャドウスラッシャー・デラックス
UPDATE bike_models SET slug = 'shadow-slasher-750' WHERE id = 428; -- シャドウスラッシャー750
UPDATE bike_models SET slug = 'chaly' WHERE id = 431; -- シャリー
UPDATE bike_models SET slug = 'chaly-50' WHERE id = 3400; -- シャリー50
UPDATE bike_models SET slug = 'chaly-70' WHERE id = 432; -- シャリー70
UPDATE bike_models SET slug = 'julio' WHERE id = 433; -- ジュリオ
UPDATE bike_models SET slug = 'julio-spring-collection' WHERE id = 434; -- ジュリオ スプリングコレクション
UPDATE bike_models SET slug = 'joker' WHERE id = 3440; -- ジョーカー
UPDATE bike_models SET slug = 'joker-50' WHERE id = 436; -- ジョーカー50
UPDATE bike_models SET slug = 'joker-90' WHERE id = 437; -- ジョーカー90
UPDATE bike_models SET slug = 'joy' WHERE id = 435; -- ジョイ
UPDATE bike_models SET slug = 'gyro-cab' WHERE id = 438; -- ジョルカブ
UPDATE bike_models SET slug = 'giorno-4-stroke' WHERE id = 3441; -- ジョルノ (4サイクル)
UPDATE bike_models SET slug = 'giorno-sport' WHERE id = 3442; -- ジョルノ スポルト
UPDATE bike_models SET slug = 'giorno-deluxe' WHERE id = 3443; -- ジョルノ デラックス
UPDATE bike_models SET slug = 'giorno-plus' WHERE id = 3492; -- ジョルノ プラス
UPDATE bike_models SET slug = 'giorno-dx' WHERE id = 440; -- ジョルノdx
UPDATE bike_models SET slug = 'giorno-sp' WHERE id = 441; -- ジョルノsp
UPDATE bike_models SET slug = 'giorno-crea' WHERE id = 442; -- ジョルノクレア
UPDATE bike_models SET slug = 'silk-road' WHERE id = 444; -- シルクロード
UPDATE bike_models SET slug = 'silver-wing' WHERE id = 445; -- シルバーウイング
UPDATE bike_models SET slug = 'silver-wing-gt' WHERE id = 446; -- シルバーウイングgt
UPDATE bike_models SET slug = 'zouk' WHERE id = 3451; -- ズーク
UPDATE bike_models SET slug = 'super-cub-type-x' WHERE id = 457; -- スーパーカブ タイプx
UPDATE bike_models SET slug = 'super-cub-100' WHERE id = 450; -- スーパーカブ100
UPDATE bike_models SET slug = 'super-cub-110-street' WHERE id = 456; -- スーパーカブ110ストリート
UPDATE bike_models SET slug = 'super-cub-50-street' WHERE id = 3445; -- スーパーカブ50 ストリート
UPDATE bike_models SET slug = 'super-cub-50-custom' WHERE id = 461; -- スーパーカブ50カスタム
UPDATE bike_models SET slug = 'super-cub-70-custom' WHERE id = 467; -- スーパーカブ70カスタム
UPDATE bike_models SET slug = 'super-cub-90-custom' WHERE id = 470; -- スーパーカブ90カスタム
UPDATE bike_models SET slug = 'super-dio' WHERE id = 3447; -- スーパーディオ
UPDATE bike_models SET slug = 'super-hawk' WHERE id = 477; -- スーパーホーク
UPDATE bike_models SET slug = 'super-hawk-250' WHERE id = 3407; -- スーパーホーク250
UPDATE bike_models SET slug = 'super-hawk-3' WHERE id = 3408; -- スーパーホーク3
UPDATE bike_models SET slug = 'zoomer' WHERE id = 479; -- ズーマー
UPDATE bike_models SET slug = 'zoomer-e' WHERE id = 482; -- ズーマー イー
UPDATE bike_models SET slug = 'zoomer-deluxe' WHERE id = 480; -- ズーマー・デラックス
UPDATE bike_models SET slug = 'zoomer-x' WHERE id = 481; -- ズーマーx
UPDATE bike_models SET slug = 'sky' WHERE id = 3402; -- スカイ
UPDATE bike_models SET slug = 'squash' WHERE id = 483; -- スカッシュ
UPDATE bike_models SET slug = 'scoopy-i-prestige' WHERE id = 486; -- スクーピーi プレステージ
UPDATE bike_models SET slug = 'stand-up-tact' WHERE id = 3403; -- スタンドアップタクト
UPDATE bike_models SET slug = 'stream' WHERE id = 492; -- ストリーム
UPDATE bike_models SET slug = 'spacy-100' WHERE id = 493; -- スペイシー100
UPDATE bike_models SET slug = 'spacy-125' WHERE id = 494; -- スペイシー125
UPDATE bike_models SET slug = 'spacy-50' WHERE id = 3404; -- スペイシー50
UPDATE bike_models SET slug = 'smart-dio' WHERE id = 3450; -- スマートディオ
UPDATE bike_models SET slug = 'tact' WHERE id = 500; -- タクティ
UPDATE bike_models SET slug = 'tact-basic' WHERE id = 3452; -- タクト ベーシック
UPDATE bike_models SET slug = 'dax-e' WHERE id = 499; -- ダックス イー
UPDATE bike_models SET slug = 'dunk' WHERE id = 504; -- ダンク
UPDATE bike_models SET slug = 'dio-2-stroke' WHERE id = 3454; -- ディオ (2サイクル)
UPDATE bike_models SET slug = 'dio-xr-baja' WHERE id = 3455; -- ディオ xr バハ
UPDATE bike_models SET slug = 'dio-4-stroke' WHERE id = 3456; -- ディオ(4サイクル)
UPDATE bike_models SET slug = 'dio-cesta' WHERE id = 3457; -- ディオチェスタ
UPDATE bike_models SET slug = 'dio-fit' WHERE id = 3458; -- ディオフィット
UPDATE bike_models SET slug = 'tina-110' WHERE id = 3477; -- ティナ110
UPDATE bike_models SET slug = 'today-f' WHERE id = 3459; -- トゥデイ f
UPDATE bike_models SET slug = 'today-special' WHERE id = 507; -- トゥデイ・スペシャル
UPDATE bike_models SET slug = 'today-deluxe' WHERE id = 4542; -- トゥデイ・デラックス
UPDATE bike_models SET slug = 'topic' WHERE id = 4543; -- トピック
UPDATE bike_models SET slug = 'dream-50' WHERE id = 510; -- ドリーム50
UPDATE bike_models SET slug = 'nighthawk-750' WHERE id = 511; -- ナイトホーク750
UPDATE bike_models SET slug = 'pacific-coast' WHERE id = 518; -- パシフィックコースト
UPDATE bike_models SET slug = 'humming' WHERE id = 519; -- ハミング
UPDATE bike_models SET slug = 'vario-150' WHERE id = 517; -- バリオ150
UPDATE bike_models SET slug = 'pal' WHERE id = 520; -- パル
UPDATE bike_models SET slug = 'pal-frey' WHERE id = 521; -- パルフレイ
UPDATE bike_models SET slug = 'beat' WHERE id = 523; -- ビート
UPDATE bike_models SET slug = 'people' WHERE id = 524; -- ピープル
UPDATE bike_models SET slug = 'vir' WHERE id = 522; -- ビア
UPDATE bike_models SET slug = 'phase' WHERE id = 525; -- フェイズ
UPDATE bike_models SET slug = 'phase-type-s' WHERE id = 526; -- フェイズ タイプs
UPDATE bike_models SET slug = 'phase-s' WHERE id = 3414; -- フェイズs
UPDATE bike_models SET slug = 'foresight' WHERE id = 527; -- フォーサイト
UPDATE bike_models SET slug = 'foresight-ex' WHERE id = 528; -- フォーサイトex
UPDATE bike_models SET slug = 'forza' WHERE id = 529; -- フォルツァ
UPDATE bike_models SET slug = 'forza-x' WHERE id = 3516; -- フォルツァ x
UPDATE bike_models SET slug = 'forza-z' WHERE id = 3517; -- フォルツァ z
UPDATE bike_models SET slug = 'forza-type-x' WHERE id = 530; -- フォルツァ タイプx
UPDATE bike_models SET slug = 'forza-s' WHERE id = 533; -- フォルツァs
UPDATE bike_models SET slug = 'fusion-type-x' WHERE id = 536; -- フュージョン タイプx
UPDATE bike_models SET slug = 'fusion-type-xx' WHERE id = 537; -- フュージョン タイプxx
UPDATE bike_models SET slug = 'fusion-se' WHERE id = 538; -- フュージョンse
UPDATE bike_models SET slug = 'fusion-x' WHERE id = 3417; -- フュージョンx
UPDATE bike_models SET slug = 'flash' WHERE id = 3419; -- フラッシュ
UPDATE bike_models SET slug = 'flash-s' WHERE id = 539; -- フラッシュs
UPDATE bike_models SET slug = 'freeway' WHERE id = 540; -- フリーウェイ
UPDATE bike_models SET slug = 'press-cub' WHERE id = 541; -- プレスカブ
UPDATE bike_models SET slug = 'press-cub-50' WHERE id = 3461; -- プレスカブ50
UPDATE bike_models SET slug = 'broad-50' WHERE id = 542; -- ブロード50
UPDATE bike_models SET slug = 'broad-90' WHERE id = 543; -- ブロード90
UPDATE bike_models SET slug = 'helix' WHERE id = 544; -- ヘリックス
UPDATE bike_models SET slug = 'benly' WHERE id = 545; -- ベンリィ
UPDATE bike_models SET slug = 'benly-pro' WHERE id = 3462; -- ベンリィ プロ
UPDATE bike_models SET slug = 'benly-110' WHERE id = 546; -- ベンリィ110
UPDATE bike_models SET slug = 'benly-110-pro' WHERE id = 547; -- ベンリィ110プロ
UPDATE bike_models SET slug = 'benly-50' WHERE id = 3420; -- ベンリィ50
UPDATE bike_models SET slug = 'benly-ei' WHERE id = 558; -- ベンリィe:i
UPDATE bike_models SET slug = 'benly-ei-pro' WHERE id = 559; -- ベンリィe:iプロ
UPDATE bike_models SET slug = 'vocal' WHERE id = 560; -- ボーカル
UPDATE bike_models SET slug = 'hawk' WHERE id = 561; -- ホーク
UPDATE bike_models SET slug = 'hawk-11' WHERE id = 515; -- ホーク11
UPDATE bike_models SET slug = 'hawk-2' WHERE id = 3422; -- ホーク2
UPDATE bike_models SET slug = 'hornet-2-0' WHERE id = 514; -- ホーネット2.0
UPDATE bike_models SET slug = 'magna-750' WHERE id = 3426; -- マグナ750
UPDATE bike_models SET slug = 'motocompo' WHERE id = 564; -- モトコンポ
UPDATE bike_models SET slug = 'motora' WHERE id = 565; -- モトラ
UPDATE bike_models SET slug = 'monkey' WHERE id = 566; -- モンキー
UPDATE bike_models SET slug = 'monkey-r' WHERE id = 568; -- モンキーr
UPDATE bike_models SET slug = 'monkey-rt' WHERE id = 569; -- モンキーrt
UPDATE bike_models SET slug = 'unicorn' WHERE id = 571; -- ユニコーン
UPDATE bike_models SET slug = 'live-dio' WHERE id = 3463; -- ライブディオ
UPDATE bike_models SET slug = 'live-dio-zx' WHERE id = 3464; -- ライブディオzx
UPDATE bike_models SET slug = 'lead' WHERE id = 582; -- リード
UPDATE bike_models SET slug = 'lead-ex' WHERE id = 3481; -- リード ex
UPDATE bike_models SET slug = 'lead-100' WHERE id = 584; -- リード100
UPDATE bike_models SET slug = 'lead-110' WHERE id = 3427; -- リード110
UPDATE bike_models SET slug = 'lead-50' WHERE id = 586; -- リード50
UPDATE bike_models SET slug = 'lead-90' WHERE id = 3429; -- リード90
UPDATE bike_models SET slug = 'little-cub' WHERE id = 587; -- リトルカブ
UPDATE bike_models SET slug = 'little-cub-la' WHERE id = 588; -- リトルカブラ
UPDATE bike_models SET slug = 'rebel' WHERE id = 3430; -- レブル
UPDATE bike_models SET slug = 'rebel-1100' WHERE id = 572; -- レブル1100
UPDATE bike_models SET slug = 'road-pal' WHERE id = 594; -- ロードパル
UPDATE bike_models SET slug = 'road-fox' WHERE id = 595; -- ロードフォックス
UPDATE bike_models SET slug = 'valkyrie' WHERE id = 596; -- ワルキューレ
UPDATE bike_models SET slug = 'valkyrie-rune' WHERE id = 597; -- ワルキューレルーン
UPDATE bike_models SET slug = 'axis-treet' WHERE id = 3791; -- アクシストリート
UPDATE bike_models SET slug = 'active' WHERE id = 1446; -- アクティブ
UPDATE bike_models SET slug = 'aprio' WHERE id = 3764; -- アプリオ
UPDATE bike_models SET slug = 'excel' WHERE id = 3279; -- エクセル
UPDATE bike_models SET slug = 'cuxi' WHERE id = 1447; -- キュート
UPDATE bike_models SET slug = 'grand-axis' WHERE id = 3781; -- グランドアクシス
UPDATE bike_models SET slug = 'grand-filano' WHERE id = 3285; -- グランドフィラーノ
UPDATE bike_models SET slug = 'grand-majesty-400' WHERE id = 1451; -- グランドマジェスティ400
UPDATE bike_models SET slug = 'saluto' WHERE id = 3287; -- サリアン
UPDATE bike_models SET slug = 'cygnus' WHERE id = 3792; -- シグナス
UPDATE bike_models SET slug = 'cygnus-gryphus' WHERE id = 1455; -- シグナス グリファス
UPDATE bike_models SET slug = 'cygnus-125' WHERE id = 3288; -- シグナス125
UPDATE bike_models SET slug = 'cygnus-x' WHERE id = 1456; -- シグナスx
UPDATE bike_models SET slug = 'cygnus-ray-zr-hybrid' WHERE id = 1458; -- シグナスレイzrハイブリッド
UPDATE bike_models SET slug = 'zippy-50' WHERE id = 3302; -- ジッピー50
UPDATE bike_models SET slug = 'jog-125' WHERE id = 1452; -- ジョグ125
UPDATE bike_models SET slug = 'jog-aprio' WHERE id = 3769; -- ジョグアプリオ
UPDATE bike_models SET slug = 'jog-aprio-type-2' WHERE id = 3770; -- ジョグアプリオ タイプ2
UPDATE bike_models SET slug = 'jog-deluxe' WHERE id = 3771; -- ジョグデラックス
UPDATE bike_models SET slug = 'jog-poche' WHERE id = 3772; -- ジョグポシェ
UPDATE bike_models SET slug = 'towny' WHERE id = 1467; -- タウニー
UPDATE bike_models SET slug = 'chappy' WHERE id = 3773; -- チャッピー
UPDATE bike_models SET slug = 'chappy-50' WHERE id = 1469; -- チャッピー50
UPDATE bike_models SET slug = 'chappy-80' WHERE id = 1470; -- チャッピー80
UPDATE bike_models SET slug = 'champ' WHERE id = 1471; -- チャンプ
UPDATE bike_models SET slug = 'champ-50' WHERE id = 3304; -- チャンプ50
UPDATE bike_models SET slug = 'champ-cx' WHERE id = 1472; -- チャンプcx
UPDATE bike_models SET slug = 'champ-rs' WHERE id = 1473; -- チャンプrs
UPDATE bike_models SET slug = 'touring-serow' WHERE id = 1474; -- ツーリングセロー
UPDATE bike_models SET slug = 'tenere-700' WHERE id = 1464; -- テネレ700
UPDATE bike_models SET slug = 'tenere-700-rally-edition' WHERE id = 1465; -- テネレ700ラリーエディション
UPDATE bike_models SET slug = 'drag-star-1100-classic' WHERE id = 1476; -- ドラッグスター1100クラシック
UPDATE bike_models SET slug = 'drag-star-250' WHERE id = 1477; -- ドラッグスター250
UPDATE bike_models SET slug = 'drag-star-400' WHERE id = 1478; -- ドラッグスター400
UPDATE bike_models SET slug = 'drag-star-400-classic' WHERE id = 1479; -- ドラッグスター400クラシック
UPDATE bike_models SET slug = 'tricity' WHERE id = 3797; -- トリシティ
UPDATE bike_models SET slug = 'tricity-125' WHERE id = 1481; -- トリシティ125
UPDATE bike_models SET slug = 'tricker' WHERE id = 1480; -- トリッカー
UPDATE bike_models SET slug = 'tracer-9' WHERE id = 1485; -- トレイサー9
UPDATE bike_models SET slug = 'news-mate' WHERE id = 3775; -- ニュースメイト
UPDATE bike_models SET slug = 'passola' WHERE id = 1492; -- パッソーラ
UPDATE bike_models SET slug = 'passol' WHERE id = 1493; -- パッソル
UPDATE bike_models SET slug = 'passol-ev' WHERE id = 1494; -- パッソルev
UPDATE bike_models SET slug = 'passol-ii' WHERE id = 1495; -- パッソルii
UPDATE bike_models SET slug = 'vino-125' WHERE id = 1497; -- ビーノ125
UPDATE bike_models SET slug = 'vino-dx' WHERE id = 1498; -- ビーノdx
UPDATE bike_models SET slug = 'vino-deluxe' WHERE id = 3777; -- ビーノデラックス
UPDATE bike_models SET slug = 'vino-bianco-r' WHERE id = 1499; -- ビーノビアンコr
UPDATE bike_models SET slug = 'vino-morphe' WHERE id = 1500; -- ビーノモルフェ
UPDATE bike_models SET slug = 'virago-125' WHERE id = 3313; -- ビラーゴ125
UPDATE bike_models SET slug = 'fascino' WHERE id = 1491; -- ファッシーノ
UPDATE bike_models SET slug = 'feather-25' WHERE id = 3320; -- フェザー25
UPDATE bike_models SET slug = 'feather-8' WHERE id = 3321; -- フェザー8
UPDATE bike_models SET slug = 'vogel' WHERE id = 1501; -- フォーゲル
UPDATE bike_models SET slug = 'beluga-80' WHERE id = 1502; -- ベルーガ80
UPDATE bike_models SET slug = 'box' WHERE id = 3778; -- ボックス
UPDATE bike_models SET slug = 'box-deluxe' WHERE id = 3779; -- ボックス デラックス
UPDATE bike_models SET slug = 'pocke' WHERE id = 1503; -- ポッケ
UPDATE bike_models SET slug = 'pop-gal' WHERE id = 1504; -- ポップギャル
UPDATE bike_models SET slug = 'bolt-c-spec' WHERE id = 3826; -- ボルト cスペック
UPDATE bike_models SET slug = 'maxam' WHERE id = 1505; -- マグザム
UPDATE bike_models SET slug = 'majesty' WHERE id = 1506; -- マジェスティ
UPDATE bike_models SET slug = 'majesty-125' WHERE id = 1507; -- マジェスティ125
UPDATE bike_models SET slug = 'majesty-150' WHERE id = 1508; -- マジェスティ150
UPDATE bike_models SET slug = 'majesty-250' WHERE id = 3329; -- マジェスティ250
UPDATE bike_models SET slug = 'majesty-c' WHERE id = 1509; -- マジェスティc
UPDATE bike_models SET slug = 'mint' WHERE id = 1512; -- ミント
UPDATE bike_models SET slug = 'mate' WHERE id = 1513; -- メイト
UPDATE bike_models SET slug = 'mate-50' WHERE id = 3333; -- メイト50
UPDATE bike_models SET slug = 'mate-90' WHERE id = 1514; -- メイト90
UPDATE bike_models SET slug = 'lanza' WHERE id = 1515; -- ランツァ
UPDATE bike_models SET slug = 'lyric' WHERE id = 1516; -- リリック
UPDATE bike_models SET slug = 'renaissa' WHERE id = 1517; -- ルネッサ
UPDATE bike_models SET slug = 'roadster-1600' WHERE id = 3337; -- ロードスター1600
UPDATE bike_models SET slug = 'roadster-1700' WHERE id = 3338; -- ロードスター1700
UPDATE bike_models SET slug = 'royal-star' WHERE id = 1518; -- ロイヤルスター
UPDATE bike_models SET slug = 'royal-star-tour-classic' WHERE id = 1519; -- ロイヤルスターツアークラシック
UPDATE bike_models SET slug = 'birdie-50-2-stroke' WHERE id = 1041; -- 2サイクルバーディー50
UPDATE bike_models SET slug = 'birdie-50-4-stroke' WHERE id = 1042; -- 4サイクルバーディー50
UPDATE bike_models SET slug = 'avenis-125' WHERE id = 1045; -- アヴェニス125
UPDATE bike_models SET slug = 'avenis-150' WHERE id = 3247; -- アヴェニス150
UPDATE bike_models SET slug = 'address' WHERE id = 1047; -- アドレス
UPDATE bike_models SET slug = 'address-way' WHERE id = 3244; -- アドレスウェイ
UPDATE bike_models SET slug = 'intruder-1400' WHERE id = 1063; -- イントルーダー1400
UPDATE bike_models SET slug = 'intruder-150' WHERE id = 1061; -- イントルーダー150
UPDATE bike_models SET slug = 'intruder-400' WHERE id = 1062; -- イントルーダー400
UPDATE bike_models SET slug = 'intruder-400-classic' WHERE id = 3252; -- イントルーダー400クラシック
UPDATE bike_models SET slug = 'intruder-750' WHERE id = 3255; -- イントルーダー750
UPDATE bike_models SET slug = 'intruder-lc' WHERE id = 1064; -- イントルーダーlc
UPDATE bike_models SET slug = 'intruder-classic' WHERE id = 1066; -- イントルーダークラシック
UPDATE bike_models SET slug = 'intruder-classic-800' WHERE id = 1067; -- イントルーダークラシック800
UPDATE bike_models SET slug = 'vekstar-125' WHERE id = 1068; -- ヴェクスター125
UPDATE bike_models SET slug = 'vekstar-150' WHERE id = 1069; -- ヴェクスター150
UPDATE bike_models SET slug = 'verde' WHERE id = 1070; -- ヴェルデ
UPDATE bike_models SET slug = 'epo' WHERE id = 1071; -- エポ
UPDATE bike_models SET slug = 'kana' WHERE id = 3258; -- カーナ
UPDATE bike_models SET slug = 'grass-tracker' WHERE id = 1073; -- グラストラッカー
UPDATE bike_models SET slug = 'grass-tracker-big-boy' WHERE id = 1074; -- グラストラッカー ビッグボーイ
UPDATE bike_models SET slug = 'gladius' WHERE id = 1075; -- グラディウス
UPDATE bike_models SET slug = 'gladius-400' WHERE id = 1076; -- グラディウス400
UPDATE bike_models SET slug = 'gladius-650' WHERE id = 3262; -- グラディウス650
UPDATE bike_models SET slug = 'colleda-50' WHERE id = 3263; -- コレダ50
UPDATE bike_models SET slug = 'colleda-scrambler' WHERE id = 1077; -- コレダスクランブラー
UPDATE bike_models SET slug = 'colleda-sport' WHERE id = 1078; -- コレダスポーツ
UPDATE bike_models SET slug = 'savage-400' WHERE id = 1079; -- サベージ400
UPDATE bike_models SET slug = 'savage-650' WHERE id = 1080; -- サベージ650
UPDATE bike_models SET slug = 'djebel-125' WHERE id = 1081; -- ジェベル125
UPDATE bike_models SET slug = 'djebel-200' WHERE id = 1082; -- ジェベル200
UPDATE bike_models SET slug = 'gemma' WHERE id = 1085; -- ジェンマ
UPDATE bike_models SET slug = 'gemma-125' WHERE id = 1086; -- ジェンマ125
UPDATE bike_models SET slug = 'gemma-250' WHERE id = 3270; -- ジェンマ250
UPDATE bike_models SET slug = 'gemma-50' WHERE id = 1087; -- ジェンマ50
UPDATE bike_models SET slug = 'gemma-50-quest' WHERE id = 1088; -- ジェンマ50クエスト
UPDATE bike_models SET slug = 'suzy' WHERE id = 3289; -- スージー
UPDATE bike_models SET slug = 'super-mollet' WHERE id = 1089; -- スーパーモレ
UPDATE bike_models SET slug = 'skywave-250' WHERE id = 1092; -- スカイウェイブ250
UPDATE bike_models SET slug = 'skywave-400' WHERE id = 1099; -- スカイウェイブ400
UPDATE bike_models SET slug = 'skywave-650' WHERE id = 1103; -- スカイウェイブ650
UPDATE bike_models SET slug = 'suzuki-others' WHERE id = 3760; -- スズキ その他
UPDATE bike_models SET slug = 'street-magic-110-2' WHERE id = 3284; -- ストリートマジック110 2
UPDATE bike_models SET slug = 'street-magic-50' WHERE id = 1106; -- ストリートマジック50
UPDATE bike_models SET slug = 'street-magic-50-2' WHERE id = 3286; -- ストリートマジック50 2
UPDATE bike_models SET slug = 'senior-car' WHERE id = 3290; -- セニアカー
UPDATE bike_models SET slug = 'sepia' WHERE id = 1109; -- セピア
UPDATE bike_models SET slug = 'sepia-zz' WHERE id = 3293; -- セピア zz
UPDATE bike_models SET slug = 'choinori' WHERE id = 1111; -- チョイノリ
UPDATE bike_models SET slug = 'choinori-ss' WHERE id = 3296; -- チョイノリss
UPDATE bike_models SET slug = 'desperado-400' WHERE id = 1112; -- デスペラード400
UPDATE bike_models SET slug = 'desperado-800' WHERE id = 1113; -- デスペラード800
UPDATE bike_models SET slug = 'desperado-winder' WHERE id = 1114; -- デスペラードワインダー
UPDATE bike_models SET slug = 'tempter' WHERE id = 1115; -- テンプター
UPDATE bike_models SET slug = 'burgman-125' WHERE id = 3305; -- バーグマン125
UPDATE bike_models SET slug = 'burgman-150' WHERE id = 1119; -- バーグマン150
UPDATE bike_models SET slug = 'burgman-200' WHERE id = 1120; -- バーグマン200
UPDATE bike_models SET slug = 'burgman-250' WHERE id = 3306; -- バーグマン250
UPDATE bike_models SET slug = 'burgman-400' WHERE id = 1121; -- バーグマン400
UPDATE bike_models SET slug = 'burgman-street' WHERE id = 3716; -- バーグマンストリート
UPDATE bike_models SET slug = 'burgman-street-125' WHERE id = 1117; -- バーグマンストリート125
UPDATE bike_models SET slug = 'birdie-80' WHERE id = 3311; -- バーディー80
UPDATE bike_models SET slug = 'birdie-90' WHERE id = 1123; -- バーディー90
UPDATE bike_models SET slug = 'hi-up' WHERE id = 1124; -- ハイup
UPDATE bike_models SET slug = 'hustler-125' WHERE id = 1125; -- ハスラー125
UPDATE bike_models SET slug = 'hustler-250' WHERE id = 1126; -- ハスラー250
UPDATE bike_models SET slug = 'hustler-400' WHERE id = 1127; -- ハスラー400
UPDATE bike_models SET slug = 'hustler-50' WHERE id = 3303; -- ハスラー50
UPDATE bike_models SET slug = 'vanvan-125' WHERE id = 1129; -- バンバン125
UPDATE bike_models SET slug = 'vanvan-200' WHERE id = 1130; -- バンバン200
UPDATE bike_models SET slug = 'vanvan-50' WHERE id = 1132; -- バンバン50
UPDATE bike_models SET slug = 'vanvan-75' WHERE id = 1133; -- バンバン75
UPDATE bike_models SET slug = 'vanvan-90' WHERE id = 1134; -- バンバン90
UPDATE bike_models SET slug = 'boulevard-400' WHERE id = 1135; -- ブルバード400
UPDATE bike_models SET slug = 'volty' WHERE id = 1139; -- ボルティー
UPDATE bike_models SET slug = 'marauder-125' WHERE id = 1144; -- マローダー125
UPDATE bike_models SET slug = 'marauder-250' WHERE id = 1145; -- マローダー250
UPDATE bike_models SET slug = 'ud-mini' WHERE id = 3316; -- ユーディーミニ
UPDATE bike_models SET slug = 'lets-2' WHERE id = 3319; -- レッツ2
UPDATE bike_models SET slug = 'lets-4' WHERE id = 1154; -- レッツ4
UPDATE bike_models SET slug = 'lets-4g' WHERE id = 1155; -- レッツ4g
UPDATE bike_models SET slug = 'lets-4-basket' WHERE id = 1157; -- レッツ4バスケット
UPDATE bike_models SET slug = 'lets-4-palette' WHERE id = 1156; -- レッツ4パレット
UPDATE bike_models SET slug = 'lets-5' WHERE id = 1158; -- レッツ5
UPDATE bike_models SET slug = 'lets-5g' WHERE id = 1159; -- レッツ5g
UPDATE bike_models SET slug = 'lets-g' WHERE id = 1160; -- レッツg
UPDATE bike_models SET slug = 'lets-ii' WHERE id = 1150; -- レッツii
UPDATE bike_models SET slug = 'lets-basket' WHERE id = 1161; -- レッツバスケット
UPDATE bike_models SET slug = 'z1r-tc' WHERE id = 828; -- 750ターボ
UPDATE bike_models SET slug = 'd-tracker' WHERE id = 607; -- dトラッカー
UPDATE bike_models SET slug = 'd-tracker-x' WHERE id = 606; -- dトラッカーx
UPDATE bike_models SET slug = 'estrella' WHERE id = 832; -- エストレヤ
UPDATE bike_models SET slug = 'estrella-rs' WHERE id = 833; -- エストレヤrs
UPDATE bike_models SET slug = 'estrella-rs-custom' WHERE id = 834; -- エストレヤrsカスタム
UPDATE bike_models SET slug = 'estrella-custom' WHERE id = 835; -- エストレヤカスタム
UPDATE bike_models SET slug = 'epsilon-250' WHERE id = 836; -- エプシロン250
UPDATE bike_models SET slug = 'eliminator' WHERE id = 3185; -- エリミネーター
UPDATE bike_models SET slug = 'eliminator-125' WHERE id = 837; -- エリミネーター125
UPDATE bike_models SET slug = 'eliminator-750' WHERE id = 845; -- エリミネーター750
UPDATE bike_models SET slug = 'eliminator-900' WHERE id = 846; -- エリミネーター900
UPDATE bike_models SET slug = 'eliminator-se' WHERE id = 3193; -- エリミネーターse
UPDATE bike_models SET slug = 'super-sherpa' WHERE id = 848; -- スーパーシェルパ
UPDATE bike_models SET slug = 'zephyr-x' WHERE id = 3634; -- ゼファーx
UPDATE bike_models SET slug = 'vulcan-1500' WHERE id = 850; -- バルカン1500
UPDATE bike_models SET slug = 'vulcan-1500-classic' WHERE id = 851; -- バルカン1500クラシック
UPDATE bike_models SET slug = 'vulcan-1500-drifter' WHERE id = 853; -- バルカン1500ドリフター
UPDATE bike_models SET slug = 'vulcan-400-classic' WHERE id = 858; -- バルカン400クラシック
UPDATE bike_models SET slug = 'vulcan-400-drifter' WHERE id = 859; -- バルカン400ドリフター
UPDATE bike_models SET slug = 'vulcan-800-classic' WHERE id = 860; -- バルカン800クラシック
UPDATE bike_models SET slug = 'vulcan-900-custom' WHERE id = 861; -- バルカン900カスタム
UPDATE bike_models SET slug = 'vulcan-900-classic' WHERE id = 862; -- バルカン900クラシック
UPDATE bike_models SET slug = 'vulcan-s' WHERE id = 863; -- バルカンs
UPDATE bike_models SET slug = 'voyager' WHERE id = 864; -- ボイジャー
UPDATE bike_models SET slug = 'autopet' WHERE id = 2787; -- オートペット
UPDATE bike_models SET slug = 'silver-pigeon-125' WHERE id = 2785; -- シルバーピジョン125
UPDATE bike_models SET slug = 'rabbit-125' WHERE id = 1524; -- ラビット125
UPDATE bike_models SET slug = 'rabbit-200' WHERE id = 1525; -- ラビット200
UPDATE bike_models SET slug = 'rabbit-90' WHERE id = 1526; -- ラビット90
UPDATE bike_models SET slug = 'rikuo' WHERE id = 2784; -- 陸王
UPDATE bike_models SET slug = 'bridgestone-other' WHERE id = 4515; -- ブリヂストン その他
UPDATE bike_models SET slug = 'ev-scooter' WHERE id = 2769; -- evスクーター
UPDATE bike_models SET slug = 'smart-ev' WHERE id = 2771; -- スマートev
UPDATE bike_models SET slug = 'aa-cargo-alpha' WHERE id = 1576; -- aa−カーゴ α
UPDATE bike_models SET slug = 'mobair-type-2' WHERE id = 1570; -- モバエール タイプ2
UPDATE bike_models SET slug = 'softail-thunder-250' WHERE id = 2762; -- ソフテイル サンダー250
UPDATE bike_models SET slug = 'hardtail-thunder-250' WHERE id = 2763; -- ハードテイル サンダー250
UPDATE bike_models SET slug = 'anchor-125' WHERE id = 2749; -- アンカー125
UPDATE bike_models SET slug = 'anchor-50' WHERE id = 2750; -- アンカー50
UPDATE bike_models SET slug = 'city' WHERE id = 2747; -- シティ
UPDATE bike_models SET slug = 'harley-davidson-other' WHERE id = 4512; -- ハーレーダビッドソン その他
UPDATE bike_models SET slug = 'pan-america-1250-special' WHERE id = 2743; -- パンアメリカ1250スペシャル
UPDATE bike_models SET slug = 'buell-other' WHERE id = 4397; -- ビューエル その他
UPDATE bike_models SET slug = 'lightning-s1' WHERE id = 2546; -- ライトニングs1
UPDATE bike_models SET slug = 'indian-other' WHERE id = 4383; -- インディアン その他
UPDATE bike_models SET slug = 'super-chief-dark-horse' WHERE id = 2521; -- スーパーチーフ ダークホース
UPDATE bike_models SET slug = 'super-chief-limited' WHERE id = 2520; -- スーパーチーフ リミテッド
UPDATE bike_models SET slug = 'scout-classic' WHERE id = 2515; -- スカウト クラシック
UPDATE bike_models SET slug = 'scout-classic-limited-tech' WHERE id = 2837; -- スカウト クラシック リミテッド +テック
UPDATE bike_models SET slug = 'scout-sixty-classic' WHERE id = 2513; -- スカウト シックスティ クラシック
UPDATE bike_models SET slug = 'scout-sixty-classic-limited' WHERE id = 2838; -- スカウト シックスティ クラシック リミテッド
UPDATE bike_models SET slug = 'scout-sixty-bobber' WHERE id = 2839; -- スカウト シックスティ ボバー
UPDATE bike_models SET slug = 'scout-bobber-limited-tech' WHERE id = 2840; -- スカウト ボバー リミテッド +テック
UPDATE bike_models SET slug = 'scout-rogue' WHERE id = 2514; -- スカウト ローグ
UPDATE bike_models SET slug = 'scout-100th-anniversary-edition' WHERE id = 2509; -- スカウト100周年アニバーサリーエディション
UPDATE bike_models SET slug = 'springfield-dark-horse' WHERE id = 2519; -- スプリングフィールド ダークホース
UPDATE bike_models SET slug = 'sport-scout-limited-tech' WHERE id = 2841; -- スポーツ スカウト リミテッド +テック
UPDATE bike_models SET slug = 'sport-chief-rt' WHERE id = 2523; -- スポーツチーフrt
UPDATE bike_models SET slug = 'chief' WHERE id = 2524; -- チーフ
UPDATE bike_models SET slug = 'chief-dark-horse' WHERE id = 2525; -- チーフ ダークホース
UPDATE bike_models SET slug = 'chief-bobber-dark-horse' WHERE id = 2526; -- チーフ ボバー ダークホース
UPDATE bike_models SET slug = 'chieftain-dark-horse' WHERE id = 2528; -- チーフテン ダークホース
UPDATE bike_models SET slug = 'chieftain-limited' WHERE id = 2529; -- チーフテン リミテッド
UPDATE bike_models SET slug = 'chief-roadmaster' WHERE id = 2532; -- チーフロードマスター
UPDATE bike_models SET slug = 'challenger-elite' WHERE id = 2535; -- チャレンジャー エリート
UPDATE bike_models SET slug = 'challenger-limited' WHERE id = 2534; -- チャレンジャー リミテッド
UPDATE bike_models SET slug = 'pursuit-limited' WHERE id = 2536; -- パースート リミテッド
UPDATE bike_models SET slug = 'victory-other' WHERE id = 4362; -- ヴィクトリー その他
UPDATE bike_models SET slug = 'can-am-spyder-c-evolution' WHERE id = 2377; -- cエヴォリューション
UPDATE bike_models SET slug = 'scorpion' WHERE id = 2372; -- スコーピオン
UPDATE bike_models SET slug = 'scorpion-tour' WHERE id = 2373; -- スコーピオンツアー
UPDATE bike_models SET slug = 'thunderbird' WHERE id = 2294; -- サンダーバード
UPDATE bike_models SET slug = 'thunderbird-900' WHERE id = 2922; -- サンダーバード900
UPDATE bike_models SET slug = 'scrambler' WHERE id = 2295; -- スクランブラー
UPDATE bike_models SET slug = 'street-triple-675' WHERE id = 2303; -- ストリートトリプル675
UPDATE bike_models SET slug = 'street-triple-675-85' WHERE id = 2304; -- ストリートトリプル675 85
UPDATE bike_models SET slug = 'speed-triple-r' WHERE id = 2312; -- スピードトリプルr
UPDATE bike_models SET slug = 'speedmaster' WHERE id = 2317; -- スピードマスター
UPDATE bike_models SET slug = 'speedmaster-1200' WHERE id = 2925; -- スピードマスター1200
UPDATE bike_models SET slug = 'speedmaster-865' WHERE id = 2926; -- スピードマスター865
UPDATE bike_models SET slug = 'thruxton-1200' WHERE id = 2320; -- スラクストン1200
UPDATE bike_models SET slug = 'tiger-1200-alpine-edition' WHERE id = 2347; -- タイガー1200アルパインエディション
UPDATE bike_models SET slug = 'tiger-1200-rally-explorer' WHERE id = 2344; -- タイガー1200ラリー エクスプローラー
UPDATE bike_models SET slug = 'tiger-800' WHERE id = 2329; -- タイガー800
UPDATE bike_models SET slug = 'tiger-900-alpine-edition' WHERE id = 2339; -- タイガー900アルパインエディション
UPDATE bike_models SET slug = 'tiger-900-desert-edition' WHERE id = 2340; -- タイガー900デザートエディション
UPDATE bike_models SET slug = 'tiger-explorer-xr' WHERE id = 2930; -- タイガーエクスプローラーxr
UPDATE bike_models SET slug = 'tiger-sport-660' WHERE id = 2328; -- タイガースポーツ660
UPDATE bike_models SET slug = 'tiger-sport-800' WHERE id = 2334; -- タイガースポーツ800
UPDATE bike_models SET slug = 'daytona-660' WHERE id = 2350; -- デイトナ660
UPDATE bike_models SET slug = 'daytona-675' WHERE id = 2351; -- デイトナ675
UPDATE bike_models SET slug = 'daytona-765-moto2-limited-edition' WHERE id = 2353; -- デイトナ765 モト2リミテッドエディション
UPDATE bike_models SET slug = 'triumph-other' WHERE id = 4126; -- トライアンフ その他
UPDATE bike_models SET slug = 'trophy' WHERE id = 2932; -- トロフィー
UPDATE bike_models SET slug = 'bonneville-speedmaster' WHERE id = 2364; -- ボンネビル スピードマスター
UPDATE bike_models SET slug = 'bonneville-790' WHERE id = 2360; -- ボンネビル790
UPDATE bike_models SET slug = 'bonneville-865' WHERE id = 2933; -- ボンネビル865
UPDATE bike_models SET slug = 'rocket-3' WHERE id = 2936; -- ロケット3
UPDATE bike_models SET slug = 'gold-star-650' WHERE id = 2281; -- ゴールドスター650
UPDATE bike_models SET slug = 'scrambler-650' WHERE id = 2282; -- スクランブラー650
UPDATE bike_models SET slug = 'bantam-350' WHERE id = 2283; -- バンタム350
UPDATE bike_models SET slug = 'commando-750' WHERE id = 2882; -- コマンド750
UPDATE bike_models SET slug = 'commando-961-cafe-racer' WHERE id = 2280; -- コマンド961カフェレーサー
UPDATE bike_models SET slug = 'rapid' WHERE id = 2278; -- ラパイド
UPDATE bike_models SET slug = 'akita-125' WHERE id = 2266; -- アキタ125
UPDATE bike_models SET slug = 'akita-250' WHERE id = 2265; -- アキタ250
UPDATE bike_models SET slug = 'sabas-250' WHERE id = 2267; -- サバス250
UPDATE bike_models SET slug = 'hilz-125' WHERE id = 2270; -- ヒルツ125
UPDATE bike_models SET slug = 'hilz-250' WHERE id = 2268; -- ヒルツ250
UPDATE bike_models SET slug = 'fat-sabas-125' WHERE id = 2269; -- ファットサバス125
UPDATE bike_models SET slug = 'mastiff-250' WHERE id = 2272; -- マスティフ250
UPDATE bike_models SET slug = 'mashman-250' WHERE id = 2273; -- マッシュマン250
UPDATE bike_models SET slug = 'mongrel-125' WHERE id = 2274; -- モングレル125
UPDATE bike_models SET slug = 'mongrel-250' WHERE id = 2271; -- モングレル250
UPDATE bike_models SET slug = 'razorback-125' WHERE id = 2276; -- レイザーバック125
UPDATE bike_models SET slug = 'razorback-250' WHERE id = 2275; -- レイザーバック250
UPDATE bike_models SET slug = '71-desert-scrambler-125' WHERE id = 2254; -- ’71 デザートスクランブラー125
UPDATE bike_models SET slug = 'scrambler-250' WHERE id = 2256; -- スクランブラー250
UPDATE bike_models SET slug = 'turismo-tecnica-125' WHERE id = 2246; -- ツーリズモテクニカ125
UPDATE bike_models SET slug = 'tecnica-125' WHERE id = 2245; -- テクニカ125
UPDATE bike_models SET slug = '1098' WHERE id = 2105; -- 1098
UPDATE bike_models SET slug = '1198' WHERE id = 2108; -- 1198
UPDATE bike_models SET slug = '1299-superleggera' WHERE id = 2111; -- 1299スーパーレッジェーラ
UPDATE bike_models SET slug = '848' WHERE id = 2127; -- 848
UPDATE bike_models SET slug = '959-panigale-corse' WHERE id = 2133; -- 959パニガーレコルセ
UPDATE bike_models SET slug = '996-monoposto' WHERE id = 2134; -- 996モノポスト
UPDATE bike_models SET slug = 'xdiavel' WHERE id = 2102; -- xディアベル
UPDATE bike_models SET slug = 'xdiavel-s' WHERE id = 2103; -- xディアベルs
UPDATE bike_models SET slug = 'supersport-950' WHERE id = 2157; -- スーパースポーツ950
UPDATE bike_models SET slug = 'scrambler-italia-independent' WHERE id = 2146; -- スクランブラーイタリアインディペンデント
UPDATE bike_models SET slug = 'scrambler-nightshift' WHERE id = 2150; -- スクランブラーナイトシフト
UPDATE bike_models SET slug = 'scrambler-flat-track-pro' WHERE id = 2151; -- スクランブラーフラットトラックプロ
UPDATE bike_models SET slug = 'scrambler-mach-2-0' WHERE id = 2153; -- スクランブラーマッハ2.0
UPDATE bike_models SET slug = 'streetfighter' WHERE id = 2164; -- ストリートファイター
UPDATE bike_models SET slug = 'streetfighter-848' WHERE id = 2167; -- ストリートファイター848
UPDATE bike_models SET slug = 'streetfighter-v4-suprema' WHERE id = 2163; -- ストリートファイターv4シュプリーム
UPDATE bike_models SET slug = 'streetfighter-v4-lamborghini' WHERE id = 2162; -- ストリートファイターv4ランボルギーニ
UPDATE bike_models SET slug = 'sport-1000' WHERE id = 2169; -- スポーツ1000
UPDATE bike_models SET slug = 'diavel-strada' WHERE id = 2175; -- ディアベル ストラーダ
UPDATE bike_models SET slug = 'diavel-titanium' WHERE id = 2177; -- ディアベル チタニウム
UPDATE bike_models SET slug = 'diavel-diesel' WHERE id = 2178; -- ディアベル ディーゼル
UPDATE bike_models SET slug = 'diavel-1260-lamborghini' WHERE id = 2181; -- ディアベル1260ランボルギーニ
UPDATE bike_models SET slug = 'desert-x' WHERE id = 2171; -- デザートx
UPDATE bike_models SET slug = 'hyperstrada' WHERE id = 2183; -- ハイパーストラーダ
UPDATE bike_models SET slug = 'hyperstrada-939' WHERE id = 2184; -- ハイパーストラーダ939
UPDATE bike_models SET slug = 'hypermotard' WHERE id = 2185; -- ハイパーモタード
UPDATE bike_models SET slug = 'hypermotard-1100' WHERE id = 2945; -- ハイパーモタード1100
UPDATE bike_models SET slug = 'hypermotard-796' WHERE id = 2187; -- ハイパーモタード796
UPDATE bike_models SET slug = 'hypermotard-820' WHERE id = 2946; -- ハイパーモタード820
UPDATE bike_models SET slug = 'hypermotard-939' WHERE id = 2188; -- ハイパーモタード939
UPDATE bike_models SET slug = 'panigale-v4-racing-replica' WHERE id = 2202; -- パニガーレv4 レーシング・レプリカ
UPDATE bike_models SET slug = 'panigale-v4-world-champion-replica' WHERE id = 2201; -- パニガーレv4 ワールドチャンピオン・レプリカ
UPDATE bike_models SET slug = 'panigale-v4-speciale' WHERE id = 2199; -- パニガーレv4スペチアーレ
UPDATE bike_models SET slug = 'multistrada-1200' WHERE id = 2212; -- ムルティストラーダ1200
UPDATE bike_models SET slug = 'multistrada-620' WHERE id = 2208; -- ムルティストラーダ620
UPDATE bike_models SET slug = 'multistrada-v4' WHERE id = 2949; -- ムルティストラーダv4
UPDATE bike_models SET slug = 'monster-plus' WHERE id = 2236; -- モンスター プラス
UPDATE bike_models SET slug = 'monster-1100' WHERE id = 2221; -- モンスター1100
UPDATE bike_models SET slug = 'monster-400' WHERE id = 2226; -- モンスター400
UPDATE bike_models SET slug = 'monster-696' WHERE id = 2227; -- モンスター696
UPDATE bike_models SET slug = 'monster-696-plus' WHERE id = 2228; -- モンスター696プラス
UPDATE bike_models SET slug = 'monster-750-dark' WHERE id = 2229; -- モンスター750ダーク
UPDATE bike_models SET slug = 'monster-796' WHERE id = 2230; -- モンスター796
UPDATE bike_models SET slug = 'monster-797' WHERE id = 2231; -- モンスター797
UPDATE bike_models SET slug = 'monster-797-plus' WHERE id = 2951; -- モンスター797プラス
UPDATE bike_models SET slug = 'monster-821' WHERE id = 2233; -- モンスター821
UPDATE bike_models SET slug = 'monster-821-stealth' WHERE id = 2234; -- モンスター821ステルス
UPDATE bike_models SET slug = 'monster-900' WHERE id = 2952; -- モンスター900
UPDATE bike_models SET slug = 'monster-sp' WHERE id = 2237; -- モンスターsp
UPDATE bike_models SET slug = '1100-sport' WHERE id = 2087; -- 1100スポルト
UPDATE bike_models SET slug = 'v7-cafe-classic' WHERE id = 2074; -- v7カフェクラシック
UPDATE bike_models SET slug = 'v7-classic' WHERE id = 2075; -- v7クラシック
UPDATE bike_models SET slug = 'v7-stone' WHERE id = 2076; -- v7ストーン
UPDATE bike_models SET slug = 'v7-stone-corsa' WHERE id = 2077; -- v7ストーン コルサ
UPDATE bike_models SET slug = 'v7-stone-ten' WHERE id = 2079; -- v7ストーン テン
UPDATE bike_models SET slug = 'v7-special' WHERE id = 2078; -- v7スペシャル
UPDATE bike_models SET slug = 'v7-sport' WHERE id = 2080; -- v7スポルト
UPDATE bike_models SET slug = 'v7-racer' WHERE id = 2081; -- v7レーサー
UPDATE bike_models SET slug = 'v9-bobber' WHERE id = 2084; -- v9ボバー
UPDATE bike_models SET slug = 'california-ev' WHERE id = 2089; -- カリフォルニアev
UPDATE bike_models SET slug = 'california-stone' WHERE id = 2812; -- カリフォルニアストーン
UPDATE bike_models SET slug = 'nevada-base' WHERE id = 2091; -- ネバダ・ベース
UPDATE bike_models SET slug = 'breva-750' WHERE id = 2092; -- ブレヴァ750
UPDATE bike_models SET slug = 'le-mans-850' WHERE id = 2094; -- ルマン850
UPDATE bike_models SET slug = 'atlantic-500' WHERE id = 2964; -- アトランティック500
UPDATE bike_models SET slug = 'caponord-1200' WHERE id = 2048; -- カポノルド1200
UPDATE bike_models SET slug = 'classic-50' WHERE id = 2049; -- クラシック50
UPDATE bike_models SET slug = 'tuono' WHERE id = 2051; -- トゥオーノ
UPDATE bike_models SET slug = 'tuono-660-factory' WHERE id = 2056; -- トゥオーノ660ファクトリー
UPDATE bike_models SET slug = 'dragster-125' WHERE id = 2024; -- ドラッグスター125
UPDATE bike_models SET slug = 'dragster-200' WHERE id = 2025; -- ドラッグスター200
UPDATE bike_models SET slug = 'dragster-50' WHERE id = 2027; -- ドラッグスター50
UPDATE bike_models SET slug = 'formula-125' WHERE id = 2028; -- フォーミュラ125
UPDATE bike_models SET slug = 'ciao' WHERE id = 2022; -- チャオ
UPDATE bike_models SET slug = 'my-mover' WHERE id = 2023; -- マイムーバー
UPDATE bike_models SET slug = 'supermono' WHERE id = 2018; -- スーパーモノ
UPDATE bike_models SET slug = 'cross-trainer-250' WHERE id = 2004; -- クロストレイナー250
UPDATE bike_models SET slug = 'sfida-1100' WHERE id = 2847; -- スフィーダ1100
UPDATE bike_models SET slug = 'imperiale-400' WHERE id = 1989; -- インペリアーレ400
UPDATE bike_models SET slug = 'tornado-900' WHERE id = 1990; -- トルネード900
UPDATE bike_models SET slug = 'leoncino-125' WHERE id = 1992; -- レオンチーノ125
UPDATE bike_models SET slug = 'leoncino-250' WHERE id = 1991; -- レオンチーノ250
UPDATE bike_models SET slug = 'saturno-500' WHERE id = 1977; -- サトゥルノ500
UPDATE bike_models SET slug = 'f3-serie-oro' WHERE id = 1931; -- f3 セリエ・オロ
UPDATE bike_models SET slug = 'f4-serie-oro' WHERE id = 1934; -- f4 セリエ・オロ
UPDATE bike_models SET slug = 'enduro-veloce' WHERE id = 1946; -- エンデューロヴェローチェ
UPDATE bike_models SET slug = 'super-veloce-800' WHERE id = 1947; -- スーパーヴェローチェ800
UPDATE bike_models SET slug = 'super-veloce-800-serie-oro' WHERE id = 1949; -- スーパーヴェローチェ800 セリエ・オロ
UPDATE bike_models SET slug = 'stradale-800' WHERE id = 1950; -- ストラダーレ800
UPDATE bike_models SET slug = 'turismo-veloce-800' WHERE id = 1951; -- ツーリズモヴェローチェ800
UPDATE bike_models SET slug = 'dragster-800-rosso' WHERE id = 1956; -- ドラッグスター800ロッソ
UPDATE bike_models SET slug = 'brutale-corsa' WHERE id = 1972; -- ブルターレ コルサ
UPDATE bike_models SET slug = 'brutale-1000-serie-oro' WHERE id = 1962; -- ブルターレ1000セリエ・オロ
UPDATE bike_models SET slug = 'brutale-800' WHERE id = 1963; -- ブルターレ800
UPDATE bike_models SET slug = 'brutale-800-rosso' WHERE id = 1966; -- ブルターレ800ロッソ
UPDATE bike_models SET slug = 'brutale-910' WHERE id = 2961; -- ブルターレ910
UPDATE bike_models SET slug = 'brutale-920' WHERE id = 1971; -- ブルターレ920
UPDATE bike_models SET slug = 'brutale-s' WHERE id = 1973; -- ブルターレs
UPDATE bike_models SET slug = 'rivale-800' WHERE id = 1974; -- リヴァーレ800
UPDATE bike_models SET slug = 'spartan-125' WHERE id = 1923; -- スパルタン125
UPDATE bike_models SET slug = 'pagani-125' WHERE id = 1925; -- パガーニ125
UPDATE bike_models SET slug = 'pagani-300' WHERE id = 1924; -- パガーニ300
UPDATE bike_models SET slug = 'piega-125' WHERE id = 1926; -- ピエガ125
UPDATE bike_models SET slug = 'flat-track-125' WHERE id = 1927; -- フラットトラック125
UPDATE bike_models SET slug = '100' WHERE id = 1897; -- 100
UPDATE bike_models SET slug = '100-vintage' WHERE id = 1898; -- 100ビンテージ
UPDATE bike_models SET slug = '125' WHERE id = 1899; -- 125
UPDATE bike_models SET slug = '125-primavera' WHERE id = 1901; -- 125プリマベラ
UPDATE bike_models SET slug = '150' WHERE id = 1902; -- 150
UPDATE bike_models SET slug = '150-sprint' WHERE id = 1904; -- 150スプリント
UPDATE bike_models SET slug = '946' WHERE id = 1909; -- 946
UPDATE bike_models SET slug = 'sprint-150-officina-8' WHERE id = 1913; -- スプリント150 オフィチナ8
UPDATE bike_models SET slug = 'sei-giorni' WHERE id = 1914; -- セイ ジョルニ
UPDATE bike_models SET slug = 'vespa-other' WHERE id = 4286; -- ベスパ その他
UPDATE bike_models SET slug = 'explorer-500' WHERE id = 1860; -- エクスプローラー500
UPDATE bike_models SET slug = 'enduro-250' WHERE id = 1861; -- エンデューロ250
UPDATE bike_models SET slug = 'caballero-scrambler-125' WHERE id = 1864; -- キャバレロ スクランブラー125
UPDATE bike_models SET slug = 'caballero-scrambler-250' WHERE id = 2852; -- キャバレロ スクランブラー250
UPDATE bike_models SET slug = 'caballero-scrambler-500' WHERE id = 1865; -- キャバレロ スクランブラー500
UPDATE bike_models SET slug = 'caballero-scrambler-500-deluxe' WHERE id = 1866; -- キャバレロ スクランブラー500デラックス
UPDATE bike_models SET slug = 'caballero-scrambler-700' WHERE id = 1867; -- キャバレロ スクランブラー700
UPDATE bike_models SET slug = 'caballero-flat-track-250' WHERE id = 1862; -- キャバレロ フラットトラック250
UPDATE bike_models SET slug = 'caballero-flat-track-500' WHERE id = 1863; -- キャバレロ フラットトラック500
UPDATE bike_models SET slug = 'caballero-rally-125' WHERE id = 1869; -- キャバレロ ラリー125
UPDATE bike_models SET slug = 'caballero-rally-500' WHERE id = 1868; -- キャバレロ ラリー500
UPDATE bike_models SET slug = 'fantic-other' WHERE id = 4188; -- ファンティック その他
UPDATE bike_models SET slug = 'granpasso-1200' WHERE id = 2846; -- グランパッソ1200
UPDATE bike_models SET slug = 'hyper-5' WHERE id = 2843; -- ハイパー5
UPDATE bike_models SET slug = 'montesa-other' WHERE id = 3965; -- モンテッサ その他
UPDATE bike_models SET slug = 'hunter' WHERE id = 1831; -- ハンター
UPDATE bike_models SET slug = 'other' WHERE id = 2789; -- 他車種
UPDATE bike_models SET slug = 'daytona-125' WHERE id = 1825; -- デイトナ125
UPDATE bike_models SET slug = 'tracker-125' WHERE id = 1826; -- トラッカー125
UPDATE bike_models SET slug = 'pilder-125' WHERE id = 1828; -- パイルダー125
UPDATE bike_models SET slug = 'vessel-125' WHERE id = 1829; -- ベッセル125
UPDATE bike_models SET slug = 'heritage-125' WHERE id = 1827; -- ヘリテイジ125
UPDATE bike_models SET slug = '1190-adventure' WHERE id = 1774; -- 1190アドベンチャー
UPDATE bike_models SET slug = '1290-super-adventure' WHERE id = 1779; -- 1290スーパーアドベンチャー
UPDATE bike_models SET slug = '200-duke' WHERE id = 2972; -- 200 デューク
UPDATE bike_models SET slug = '640-duke' WHERE id = 1802; -- 640デューク
UPDATE bike_models SET slug = '690-duke' WHERE id = 2985; -- 690 デューク
UPDATE bike_models SET slug = '990-super-duke' WHERE id = 2988; -- 990 スーパーデューク
UPDATE bike_models SET slug = '990-adventure' WHERE id = 1817; -- 990アドベンチャー
UPDATE bike_models SET slug = 'crossfire-500-sto' WHERE id = 1759; -- クロスファイア500ストー
UPDATE bike_models SET slug = 'classic-i' WHERE id = 1754; -- クラシックi
UPDATE bike_models SET slug = 'classic-ii' WHERE id = 1755; -- トモス クラシックii
UPDATE bike_models SET slug = 'citystar-125-smart-motion' WHERE id = 1748; -- シティスター125 スマートモーション
UPDATE bike_models SET slug = 'django-125-allure' WHERE id = 1746; -- ジャンゴ125 アリュール
UPDATE bike_models SET slug = 'django-125-eversion' WHERE id = 1744; -- ジャンゴ125 エバージョン
UPDATE bike_models SET slug = 'django-150-eversion' WHERE id = 1742; -- ジャンゴ150 エバージョン
UPDATE bike_models SET slug = 'django-50' WHERE id = 1749; -- ジャンゴ50
UPDATE bike_models SET slug = 'speedfight-100' WHERE id = 1750; -- スピードファイト100
UPDATE bike_models SET slug = 'speedfight-125' WHERE id = 1751; -- スピードファイト125
UPDATE bike_models SET slug = 'trial-250' WHERE id = 1738; -- トライアル250
UPDATE bike_models SET slug = 'nuda-900' WHERE id = 1733; -- ヌーダ900
UPDATE bike_models SET slug = 'norden-901-expedition' WHERE id = 1736; -- ノーデン901エクスペディション
UPDATE bike_models SET slug = 'super-meteor-650' WHERE id = 1686; -- スーパーメテオ650
UPDATE bike_models SET slug = 'super-meteor-650-tourer' WHERE id = 1687; -- スーパーメテオ650ツアラー
UPDATE bike_models SET slug = 'himalayan' WHERE id = 1699; -- ヒマラヤ
UPDATE bike_models SET slug = 'himalayan-450' WHERE id = 1698; -- ヒマラヤ450
UPDATE bike_models SET slug = 'bullet-350-army' WHERE id = 1693; -- ブリット350アーミー
UPDATE bike_models SET slug = 'bullet-500-army' WHERE id = 1696; -- ブリット500アーミー
UPDATE bike_models SET slug = 'bullet-535' WHERE id = 1697; -- ブリット535
UPDATE bike_models SET slug = '450-rally' WHERE id = 1659; -- 450ラリー
UPDATE bike_models SET slug = 'napoleon-bob-250' WHERE id = 1620; -- ナポレオンボブ250
UPDATE bike_models SET slug = 'orbit-three-125' WHERE id = 1591; -- オービットスリー125
UPDATE bike_models SET slug = 'simply-125' WHERE id = 1594; -- シンプリー125
UPDATE bike_models SET slug = 'fighter-150' WHERE id = 1596; -- ファイター150
UPDATE bike_models SET slug = 'mini-elite-150' WHERE id = 1568; -- ミニ エリート150
UPDATE bike_models SET slug = 'gunner-100' WHERE id = 1543; -- ガンナー100
UPDATE bike_models SET slug = 'gunner-125' WHERE id = 1544; -- ガンナー125
UPDATE bike_models SET slug = 'gunner-50' WHERE id = 2830; -- ガンナー50
UPDATE bike_models SET slug = 'trike-buggy' WHERE id = 1542; -- バギー
UPDATE bike_models SET slug = 'snowmobile-electric-scooter' WHERE id = 1541; -- 電動スクーター
UPDATE bike_models SET slug = 'snowbike-trike' WHERE id = 1540; -- トライク
UPDATE bike_models SET slug = 'snow-blower-snowmobile' WHERE id = 1539; -- スノーモービル
UPDATE bike_models SET slug = 'senior-car-snowbike-enduro-racer' WHERE id = 1538; -- スノーバイク エンデューロレーサー
UPDATE bike_models SET slug = 'senior-car-snowbike-motocrosser' WHERE id = 1537; -- スノーバイク モトクロッサー
UPDATE bike_models SET slug = 'imported-other-manufacturer-snow-blower' WHERE id = 1536; -- 除雪機
UPDATE bike_models SET slug = 'other-manufacturer-senior-car' WHERE id = 1535; -- シニアカー
UPDATE bike_models SET slug = 'snake-motors-america-other' WHERE id = 1531; -- アメリカ・他車種
UPDATE bike_models SET slug = 'snake-motors-uk-other' WHERE id = 1532; -- イギリス・他車種
UPDATE bike_models SET slug = 'snake-motors-swiss-other' WHERE id = 1533; -- スイス・他車種
UPDATE bike_models SET slug = 'snake-motors-germany-other' WHERE id = 1534; -- ドイツ・他車種
UPDATE bike_models SET slug = 'snake-motors-china-other' WHERE id = 1529; -- 中国・他車種
UPDATE bike_models SET slug = 'snake-motors-imported-manufacturer-other' WHERE id = 1530; -- 輸入車メーカー・他車種
UPDATE bike_models SET slug = 'road-hopper-other-manufacturer-other' WHERE id = 1528; -- その他メーカー・他車種
UPDATE bike_models SET slug = 'apaxx-power-s1-yoshimura' WHERE id = 3849; -- s1 (ヨシムラ)
UPDATE bike_models SET slug = 'boss-hoss-domestic-manufacturer-other' WHERE id = 4513; -- 国内メーカー その他
UPDATE bike_models SET slug = 'west-coast-choppers-boss-hoss-other' WHERE id = 4360; -- ボスホス その他
UPDATE bike_models SET slug = 'moto-guzzi-rodeo-motorcycle-other' WHERE id = 4359; -- ロデオモーターサイクル その他
UPDATE bike_models SET slug = 'vent-moto-guzzi-other' WHERE id = 4247; -- モトグッチ その他
UPDATE bike_models SET slug = 'moto-morini-f4' WHERE id = 4226; -- f4
UPDATE bike_models SET slug = 'daelim-hyosung-other' WHERE id = 3921; -- ヒョースン その他
UPDATE bike_models SET slug = 'sym-yamaha-other' WHERE id = 3905; -- ヤディア その他
UPDATE bike_models SET slug = 'overseas-manufacturer-other' WHERE id = 3858; -- 海外メーカー その他
UPDATE bike_models SET slug = 'other-other' WHERE id = 3850; -- その他

COMMIT;
