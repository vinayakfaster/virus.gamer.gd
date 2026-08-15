FROM php:8.2-apache

# curl ke liye zaroori packages install karo
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

EXPOSE 80
