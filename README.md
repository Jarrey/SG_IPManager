# 索格 IPManager / SG IPManager

## 简介 / Overview

`索格 IPManager` 是一个轻量级的 PHP 本地应用，专为家庭和小型办公网络的 DHCP 静态地址管理设计。它支持
- 用户认证与权限控制
- DHCP 静态地址表导入、导出
- 子网空闲 IP 可视化
- 设备在线/离线检测
- 本地数据文件存储与管理

SG IPManager is a lightweight PHP local application designed for DHCP static IP management in home and small office networks. It supports:
- user authentication and role management
- import/export of DHCP static IP lists
- subnet free IP visualization
- online/offline device checks
- local file-based storage and management

## 功能亮点 / Key Features

- 登录认证：默认账号 `admin/admin`，首次登录强制修改密码
- CSV 导入：兼容 `dhcp_static.csv` 格式，支持 RouterOS、MikroTik 以及爱快等常见路由器平台
- CSV 导出：生成标准 `dhcp_static.csv` 文件，可直接导入各种路由器系统
- 编码兼容：自动识别 UTF-8 与 GBK/GB2312 编码，避免中文备注乱码
- IP 管理：添加、编辑、删除 IP 绑定，支持启用/禁用开关
- 在线检测：单个 IP 检测和一键检测所有地址
- 子网视图：用色块直观显示已分配与空闲 IP
- 本地持久化：`data/` 目录保存用户、IP、设置与 ping 缓存数据

## 文件说明 / File Overview

- `index.php` - 登录页
- `app.php` - 主界面和前端页面
- `api.php` - JSON API 服务端接口
- `logout.php` - 注销入口
- `includes/` - 认证、数据存储、编码、ping 等辅助逻辑
- `assets/` - 前端样式与 JavaScript
- `data/` - 本地数据存储目录（包含 `.htaccess` 保护）
- `dhcp_static.csv` - 示例导入文件

## 导入/导出兼容性 / CSV Compatibility

该项目导入和导出的 CSV 格式兼容多种路由器平台，包括但不限于：
- RouterOS / MikroTik
- 爱快 (iKuai)
- OpenWrt / LEDE 兼容静态 DHCP 列表
- 其他采用 `dhcp_static.csv` 样式字段的系统

The import/export CSV format is compatible with multiple router platforms, including but not limited to:
- RouterOS / MikroTik
- iKuai
- OpenWrt / LEDE compatible static DHCP lists
- other systems using `dhcp_static.csv`-style fields

## 运行方式 / Run the App

1. 将项目放到 PHP 服务器环境下，例如 Apache 或 Nginx + PHP
2. 访问项目首页 `index.php`
3. 默认账号密码：`admin / admin`
4. 首次登录后请立即修改默认密码

## 其他说明 / Notes

- 所有用户和 IP 数据保存在 `data/` 目录中，确保该目录可写
- 在支持 `exec()` 的服务器上，Ping 检测采用系统 `ping` 命令；否则回退到 socket 检测
- 如果想添加更多子网，可在设置页中配置 `subnets`

## License

本项目采用 `LICENSE` 中定义的许可协议。
