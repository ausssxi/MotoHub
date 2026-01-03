import asyncio
import os
import datetime
import re
import unicodedata
from dotenv import load_dotenv
from playwright.async_api import async_playwright
from sqlalchemy import create_engine, Column, BigInteger, String, Integer, DateTime, ForeignKey, func
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 環境変数の読み込み
load_dotenv()
if not os.getenv("DB_DATABASE"):
    load_dotenv(dotenv_path='../.env')

# データベース接続設定
user = os.getenv("DB_USERNAME", "sail")
password = os.getenv("DB_PASSWORD", "password")
host = os.getenv("DB_HOST", "db")
port = os.getenv("DB_PORT", "3306")
database = os.getenv("DB_DATABASE", "motohub")
DATABASE_URL = f"mysql+pymysql://{user}:{password}@{host}:{port}/{database}"

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, index=True, autoincrement=True)
    manufacturer_id = Column(BigInteger)
    name = Column(String(255), nullable=False, unique=True)
    displacement = Column(Integer, nullable=True)
    updated_at = Column(DateTime, default=datetime.datetime.now, onupdate=datetime.datetime.now)

def robust_normalize(text):
    """
    文字のゆれを徹底的に排除する
    1. NFKC正規化（全角英数字を半角へ）
    2. 大文字化
    3. 各種ハイフン・ダッシュ類を半角ハイフンに統一
    4. 前後の空白削除
    """
    if not text:
        return ""
    # NFKC正規化
    text = unicodedata.normalize('NFKC', text)
    # 大文字化
    text = text.upper()
    # ハイフン類の統一 (長音、全角ハイフン、各種ダッシュを半角ハイフンへ)
    text = re.sub(r'[ー－―—‐-]', '-', text)
    # 前後の空白削除
    return text.strip()

async def collect_displacement():
    async with async_playwright() as p:
        print("BDS排気量コレクター（強化マッチ版）を起動しています...")
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        db = SessionLocal()

        # 巡回するメーカー（全メーカーを追加）
        maker_list_raw = [
            {"slug": "honda", "name": "ホンダ"}, {"slug": "suzuki", "name": "スズキ"},
            {"slug": "yamaha", "name": "ヤマハ"}, {"slug": "kawasaki", "name": "カワサキ"},
            {"slug": "daihatsu", "name": "ダイハツ"}, {"slug": "bridgestone", "name": "ブリジストン"},
            {"slug": "meguro", "name": "メグロ"}, {"slug": "rodeo", "name": "ロデオ"},
            {"slug": "plot", "name": "プロト"}, {"slug": "bmw", "name": "BMW"},
            {"slug": "ktm", "name": "KTM"}, {"slug": "aprilia", "name": "アプリリア"},
            {"slug": "mv_agusta", "name": "MVアグスタ"}, {"slug": "gilera", "name": "ジレラ"},
            {"slug": "ducati", "name": "ドゥカティ"}, {"slug": "triumph", "name": "トライアンフ"},
            {"slug": "norton", "name": "ノートン"}, {"slug": "harley_davidson", "name": "ハーレーダビッドソン"},
            {"slug": "husqvarna", "name": "ハスクバーナ"}, {"slug": "bimota", "name": "ビモータ"},
            {"slug": "buell", "name": "ビューエル"}, {"slug": "vespa", "name": "ベスパ"},
            {"slug": "moto_guzzi", "name": "モトグッツィ"}, {"slug": "royal_enfield", "name": "ロイヤルエンフィールド"},
            {"slug": "daelim", "name": "DAELIM"}, {"slug": "gg", "name": "GG"},
            {"slug": "pgo", "name": "PGO"}, {"slug": "sym", "name": "SYM"},
            {"slug": "italjet", "name": "イタルジェット"}, {"slug": "gasgas", "name": "ガスガス"},
            {"slug": "kymco", "name": "キムコ"}, {"slug": "krauser", "name": "クラウザー"},
            {"slug": "sachs", "name": "ザックス"}, {"slug": "derbi", "name": "デルビ"},
            {"slug": "tomos", "name": "トモス"}, {"slug": "piaggio", "name": "ピアジオ"},
            {"slug": "bsa", "name": "ビーエスエー"}, {"slug": "fantic", "name": "ファンティック"},
            {"slug": "peugeot", "name": "プジョー"}, {"slug": "beta", "name": "ベータ"},
            {"slug": "benelli", "name": "ベネリ"}, {"slug": "magni", "name": "マーニ"},
            {"slug": "moto_morini", "name": "モトモリーニ"}, {"slug": "mondial", "name": "モンディアル"},
            {"slug": "montesa", "name": "モンテッサ"}, {"slug": "lambretta", "name": "ランブレッタ"},
            {"slug": "adiva", "name": "アディバ"}, {"slug": "megelli", "name": "メガリ"},
            {"slug": "indian", "name": "インディアン"}, {"slug": "gpx", "name": "GPX"},
            {"slug": "phoenix", "name": "PHOENIX"}, {"slug": "leonart", "name": "レオンアート"},
            {"slug": "brp", "name": "BRP"}, {"slug": "brixton", "name": "BRIXTON"},
            {"slug": "mutt", "name": "MUTT"},
        ]

        try:
            for m in maker_list_raw:
                m_url = f"https://www.bds-bikesensor.net/bike/maker/{m['slug']}"
                print(f"\n--- {m['name']} の解析 ---")
                
                try:
                    await page.goto(m_url, wait_until="domcontentloaded", timeout=60000)
                    model_items = await page.query_selector_all(".model_item")
                except Exception as e:
                    print(f"  [エラー] メーカーページにアクセスできませんでした: {m['name']}")
                    continue
                
                for item in model_items:
                    m_link = await item.query_selector("a.c-bike_image")
                    if not m_link: continue
                    
                    # サイト上の名前を取得
                    raw_site_name = (await m_link.get_attribute("title") or "").strip()
                    # 徹底的に正規化
                    normalized_site_name = robust_normalize(raw_site_name)
                    href = await m_link.get_attribute("href")
                    
                    if not normalized_site_name or not href: continue

                    # DB検索も正規化を考慮して行う
                    # 1. 完全一致で探す
                    model_record = db.query(BikeModel).filter(BikeModel.name == raw_site_name).first()
                    
                    # 2. 見つからない場合、大文字小文字・ハイフンを無視して探す
                    if not model_record:
                        model_record = db.query(BikeModel).filter(func.upper(BikeModel.name) == normalized_site_name).first()

                    # すでに排気量がある（0より大きい）ならスキップ
                    if model_record and model_record.displacement and model_record.displacement > 0:
                        continue

                    # 検索ページURLの生成 (絶対パスか相対パスかを判定して結合)
                    if href.startswith('http'):
                        search_page_url = href
                    else:
                        search_page_url = "https://www.bds-bikesensor.net" + (href if href.startswith('/') else '/' + href)

                    print(f"  >> 照合中: {normalized_site_name} (URL: {search_page_url})")
                    
                    sub_page = None
                    try:
                        sub_page = await context.new_page()
                        # wait_untilをdomcontentloadedに早める
                        await sub_page.goto(search_page_url, wait_until="domcontentloaded", timeout=30000)
                        
                        # 排気量情報の待機
                        try:
                            await sub_page.wait_for_selector(".c-search_status_col", timeout=5000)
                        except:
                            print(f"    [SKIP] 車両データが描画されませんでした: {normalized_site_name}")
                            await sub_page.close()
                            continue

                        status_cols = await sub_page.query_selector_all(".c-search_status_col")
                        disp_val = None
                        for col in status_cols:
                            head = await col.query_selector(".c-search_status_head")
                            if head and "排気量" in (await head.inner_text()):
                                val_el = await col.query_selector(".c-search_status_title01")
                                if val_el:
                                    m_digit = re.search(r'(\d+)', await val_el.inner_text())
                                    if m_digit: disp_val = int(m_digit.group(1))
                                    break
                        
                        if disp_val and model_record:
                            model_record.displacement = disp_val
                            db.commit()
                            print(f"    [更新] {model_record.name} -> {disp_val}cc")
                        else:
                            print(f"    [情報] 排気量情報が見つかりませんでした: {normalized_site_name}")
                        
                        await sub_page.close()
                    except Exception as e:
                        print(f"    [エラー] 解析中に問題が発生しました ({normalized_site_name}): {str(e)}")
                        if sub_page: await sub_page.close()

                await asyncio.sleep(0.5)

        finally:
            db.close()
            await browser.close()

if __name__ == "__main__":
    asyncio.run(collect_displacement())