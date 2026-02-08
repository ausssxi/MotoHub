from PIL import Image, ImageDraw, ImageFont
import os
import sys

# ==========================================
# 設定
# ==========================================
# Docker環境(Laravel)のルートパスを直接指定
BASE_DIR = '/var/www'

# 保存先: /var/www/public/images/
# (Laravelの公開ディレクトリは通常 /var/www/public です)
OUTPUT_DIR = os.path.join(BASE_DIR, 'public', 'images')
OUTPUT_FILE = "twitter_template.png"

# フォントパス
FONT_PATH = os.path.join(BASE_DIR, 'public', 'fonts', 'font.ttf')

# 画像サイズ
WIDTH, HEIGHT = 1200, 630

# 色定義 (R, G, B)
WHITE = (255, 255, 255)
GRAY_BG = (240, 242, 245)   # 右側の背景色
BLUE = (59, 130, 246)       # ロゴ色
DARK_GRAY = (55, 65, 81)    # アイコン色

def create_template():
    print(f"Generating template image...")
    print(f"Target Directory: {OUTPUT_DIR}")
    
    # 1. 白いキャンバスを作成
    img = Image.new("RGB", (WIDTH, HEIGHT), WHITE)
    draw = ImageDraw.Draw(img)

    # 2. 右側のデザインエリア (台形)
    # 右側 40% くらいをグレーにする
    right_width = 450
    poly_points = [
        (WIDTH - right_width, 0),           # 上辺左
        (WIDTH, 0),                         # 上辺右
        (WIDTH, HEIGHT),                    # 下辺右
        (WIDTH - right_width + 60, HEIGHT)  # 下辺左 (斜めにカット)
    ]
    draw.polygon(poly_points, fill=GRAY_BG)

    # 3. テキストの中心位置 (右側エリアの重心)
    center_x = WIDTH - (right_width / 2) + 20
    center_y = HEIGHT / 2

    # 4. フォント読み込み（なければデフォルト）
    try:
        # フォントがある場合
        font_logo = ImageFont.truetype(FONT_PATH, 70)
        font_sub = ImageFont.truetype(FONT_PATH, 24)
        has_font = True
    except IOError:
        print(f"⚠️  Font file not found at {FONT_PATH}. Using default font for logo.")
        font_logo = ImageFont.load_default() 
        font_sub = ImageFont.load_default()
        has_font = False

    # 5. アイコン (バイクのシルエット) の描画
    icon_y = center_y - 50
    icon_x = center_x
    
    # タイヤ (左)
    draw.ellipse((icon_x - 50, icon_y + 20, icon_x - 10, icon_y + 60), outline=DARK_GRAY, width=6)
    # タイヤ (右)
    draw.ellipse((icon_x + 10, icon_y + 20, icon_x + 50, icon_y + 60), outline=DARK_GRAY, width=6)
    # ボディ
    draw.line((icon_x - 30, icon_y + 40, icon_x, icon_y + 10), fill=DARK_GRAY, width=6)
    draw.line((icon_x, icon_y + 10, icon_x + 30, icon_y + 40), fill=DARK_GRAY, width=6)
    draw.line((icon_x - 30, icon_y + 40, icon_x + 30, icon_y + 40), fill=DARK_GRAY, width=6)
    # ハンドル
    draw.line((icon_x - 10, icon_y + 10, icon_x - 20, icon_y - 10), fill=DARK_GRAY, width=5)

    # 6. ロゴテキストの描画
    text_main = "MotoHub"
    
    if has_font:
        # フォントがあるなら綺麗に描画
        bbox = draw.textbbox((0, 0), text_main, font=font_logo)
        w_main = bbox[2] - bbox[0]
        h_main = bbox[3] - bbox[1]
        
        draw.text(
            (center_x - w_main / 2, center_y + 80),
            text_main,
            font=font_logo,
            fill=BLUE
        )
    else:
        draw.text((center_x - 20, center_y + 80), text_main, fill=BLUE)

    # 7. 保存
    if not os.path.exists(OUTPUT_DIR):
        try:
            os.makedirs(OUTPUT_DIR, exist_ok=True)
        except OSError as e:
            print(f"❌ Error: Cannot create directory {OUTPUT_DIR}: {e}")
            return

    save_path = os.path.join(OUTPUT_DIR, OUTPUT_FILE)
    try:
        img.save(save_path)
        print(f"✅ Success! Template image created at:\n   {save_path}")
    except Exception as e:
        print(f"❌ Error saving image: {e}")

if __name__ == "__main__":
    create_template()