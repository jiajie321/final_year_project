FROM php:8.1-apache

# 复制代码
COPY fyp/ /var/www/html/

# 开启 rewrite 模块
RUN a2enmod rewrite

# 安装 mysqli
RUN docker-php-ext-install mysqli

# ✅ 正确设置首页为 index.php
RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

# 设置工作目录
WORKDIR /var/www/html
