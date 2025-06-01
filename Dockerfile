FROM php:8.1-apache

# 把 fyp 文件夹内容复制到网页目录
COPY fyp/ /var/www/html/

# 启用 URL 重写模块
RUN a2enmod rewrite

# 安装 mysqli 扩展
RUN docker-php-ext-install mysqli

# 设置 Apache 默认首页为 index.php
RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

# 非常重要！让 Render 知道我们监听的是 80 端口
EXPOSE 80

# 设置工作目录
WORKDIR /var/www/html
