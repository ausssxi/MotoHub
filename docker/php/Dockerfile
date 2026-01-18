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

# PHP 拡張のインストール (MySQL, GD等)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Composer のインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリの設定
WORKDIR /var/www

# 1. Scraper の依存関係 (requirements.txt) を先にインストール
COPY ./scraper/requirements.txt /tmp/requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /tmp/requirements.txt

# 2. Scraper のソースコードをイメージ内にコピー
# エラーの原因箇所：パスを /var/scraper に修正しました
COPY ./scraper /var/scraper

# 3. 権限設定
# エラーの原因箇所：ここも /var/scraper に修正しました
RUN chown -R www-data:www-data /var/scraper

# コンテナ起動時のデフォルト
EXPOSE 9000
CMD ["php-fpm"]