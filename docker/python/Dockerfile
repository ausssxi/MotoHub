# 軽量なPython 3.11の安定版を使用
FROM python:3.11-slim-bookworm

# システムのタイムゾーンを日本に設定
ENV TZ=Asia/Tokyo

WORKDIR /app

# 依存関係のインストールに必要な最小限のツール
# (mysql+pymysqlを使用するため、基本的なビルドツールが必要になる場合があります)
RUN apt-get update && apt-get install -y \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# requirements.txtを先にコピーしてキャッシュを利用
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# クローラースクリプト一式をコピー
COPY . .

# 実行権限の付与（必要に応じて）
RUN chmod +x *.py

# デフォルトのコマンド（通常はスケジューラからexecされるため、コンテナ維持用かダミー）
CMD ["python", "main.py"]