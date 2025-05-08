#!/bin/bash

# 定义项目路径
PROJECT_NAME="blog-php-mysql"
SOURCE_DIR="$HOME/blog_github/$PROJECT_NAME"
TARGET_DIR="/var/www/html/$PROJECT_NAME"

# 检查源目录是否存在
if [ ! -d "$SOURCE_DIR" ]; then
    echo "源目录 $SOURCE_DIR 不存在，请检查路径！"
    exit 1
fi

# 停止 Apache 服务
echo "停止 Apache 服务..."
sudo service apache2 stop

# 删除旧版本时保留 images 文件夹
if [ -d "$TARGET_DIR" ]; then
    echo "保留 images 文件夹..."
    sudo mkdir -p /tmp/images_backup
    sudo rsync -a "$TARGET_DIR/images/" /tmp/images_backup/
    echo "删除旧版本目录 $TARGET_DIR..."
    sudo rm -rf "$TARGET_DIR"
fi

# 复制新版本
echo "复制新版本到 $TARGET_DIR..."
sudo cp -r "$SOURCE_DIR" /var/www/html/

# 恢复 images 文件夹
if [ -d "/tmp/images_backup" ]; then
    echo "恢复 images 文件夹..."
    sudo mkdir -p "$TARGET_DIR/images"
    sudo rsync -a /tmp/images_backup/ "$TARGET_DIR/images/"
    sudo rm -rf /tmp/images_backup
fi

# 设置权限
echo "设置目录权限..."
sudo chown -R www-data:www-data "$TARGET_DIR"
sudo chmod -R 755 "$TARGET_DIR"

# 启动 Apache 服务
echo "启动 Apache 服务..."
sudo service apache2 start

echo "操作完成！新版本已部署到 $TARGET_DIR"