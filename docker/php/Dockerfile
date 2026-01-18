# PHP 8.3 の公式イメージをベースにする
FROM php:8.3-fpm-bookworm

# システムのタイムゾーンを日本に設定
ENV TZ=Asia/Tokyo

# 依存パッケージのインストール (PHP用 + Python用)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    # --- ここから Python 実行環境の追加 ---
    python3 \
    python3-pip \
    python3-venv \
    build-essential \
    libssl-dev \
    libffi-dev \
    python3-dev \
    # ------------------------------------
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP 拡張のインストール (MySQL, GD等)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Composer のインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリの設定
WORKDIR /var/www

# Scraper の依存関係 (requirements.txt) を先にインストール
# backend フォルダと同じ階層にある scraper フォルダからコピー
COPY ./scraper/requirements.txt /tmp/requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /tmp/requirements.txt

# 不要な Playwright などの残骸があればここで削除設定を行えます