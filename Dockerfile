FROM php:8.2-apache

# Instalar extensões necessárias
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install curl \
    && docker-php-ext-install mbstring || true

# Ativar mod_rewrite do Apache
RUN a2enmod rewrite

# Configurar diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos do projeto
COPY . .

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html

# Expor porta 80
EXPOSE 80
