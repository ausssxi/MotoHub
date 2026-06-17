# PHP 8.3 の公式イメージをベースにする
FROM php:8.3-fpm-bookworm

# システムのタイムゾーンを日本に設定
ENV TZ=Asia/Tokyo

# 依存パッケージのインストール
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    # ★追加: zip拡張に必要なライブラリ
    libzip-dev \
    # intl拡張に必要なライブラリ
    libicu-dev \
    # 画像処理(GD)用
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
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

# Node.js と npm のインストール (Node.js 20系)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# PHP 拡張のインストール (MySQL, GD, intl, zip等)
# ★修正: zip を追加しました
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd intl zip

# Redis 拡張のインストール
RUN pecl install redis && docker-php-ext-enable redis

# ImageMagick + libheif（iPhone の HEIC 写真を JPEG へデコードするため）と imagick 拡張。
# GD は HEIC 非対応のため、HEIC のみ Imagick でデコードして既存の再エンコード(EXIF全除去)パスへ合流する。
RUN apt-get update && apt-get install -y --no-install-recommends \
    libmagickwand-dev \
    imagemagick \
    libheif1 \
    libde265-0 \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer のインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHP設定（アップロードサイズ等）
COPY ./docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

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