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
    # ★追加: 画像処理(GD)用ライブラリ（フォント描画に必須）
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    # --- Python 実行環境 ---
    python3 \
    python3-pip \
    python3-venv \
    build-essential \
    libssl-dev \
    libffi-dev \
    python3-dev \
    # -----------------------
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ★追加: Node.js と npm のインストール (Node.js 20系)
# Laravel Breeze (Vite) のビルドに必要です
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# PHP 拡張のインストール (MySQL, GD等)
# ★修正: GDの設定(configure)を追加して、FreeTypeを有効にする
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd

# Composer のインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリの設定
WORKDIR /var/www

# 1. Scraper の依存関係 (requirements.txt) を先にインストール
COPY ./scraper/requirements.txt /tmp/requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /tmp/requirements.txt

# 2. Scraper のソースコードをイメージ内にコピー
COPY ./scraper /var/scraper

# 3. 権限設定
RUN chown -R www-data:www-data /var/scraper

# コンテナ起動時のデフォルト
EXPOSE 9000
CMD ["php-fpm"]