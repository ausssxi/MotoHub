# 安定版の Debian Bookworm ベースに変更
FROM python:3.11-slim-bookworm

WORKDIR /app

# 依存関係解決に必要なツールをインストール
RUN apt-get update && apt-get install -y \
    wget \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# 先に requirements.txt をコピーしてライブラリを入れる
COPY scraper/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Playwright本体のインストール
RUN playwright install chromium

# 安定版 OS なので、Playwright の依存関係インストールが正常に動作します
RUN playwright install-deps chromium

COPY scraper/ .

CMD ["python", "main.py"]
