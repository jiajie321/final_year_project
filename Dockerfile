FROM php:8.1-apache

# 将代码复制到 Apache 根目录
COPY fyp/ /var/www/html/

# 启用 Apache mod_rewrite
RUN a2enmod rewrite

# 安装 mysqli 扩展
RUN docker-php-ext-install mysqli

# 设置 index 文件
RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

# 设置工作目录
WORKDIR /var/www/html

# 👇 Render 需要你显式暴露端口
EXPOSE 80
