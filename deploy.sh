#!/bin/bash

SOURCE_DIR="/home/wtejing/blog_github/blog-php-mysql"
TARGET_DIR="/var/www/html/blog-php-mysql"

# 备份 images 和 avatars 文件夹
if [ -d "$TARGET_DIR" ]; then
    echo "备份 images 和 avatars 文件夹..."
    sudo mkdir -p /tmp/images_backup
    sudo mkdir -p /tmp/avatars_backup
    
    # 如果目标目录存在相应文件夹，则进行备份
    if [ -d "$TARGET_DIR/images" ]; then
        sudo rsync -a "$TARGET_DIR/images/" /tmp/images_backup/
    fi
    
    if [ -d "$TARGET_DIR/avatars" ]; then
        sudo rsync -a "$TARGET_DIR/avatars/" /tmp/avatars_backup/
    fi
    
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

# 恢复 avatars 文件夹
if [ -d "/tmp/avatars_backup" ]; then
    echo "恢复 avatars 文件夹..."
    sudo mkdir -p "$TARGET_DIR/avatars"
    sudo rsync -a /tmp/avatars_backup/ "$TARGET_DIR/avatars/"
    sudo rm -rf /tmp/avatars_backup
fi

# 设置权限
echo "设置目录权限..."
sudo chown -R www-data:www-data "$TARGET_DIR"
sudo chmod -R 755 "$TARGET_DIR"

# 启动 Apache 服务
echo "启动 Apache 服务..."
sudo service apache2 restart

echo "操作完成！新版本已部署到 $TARGET_DIR"