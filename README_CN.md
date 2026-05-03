# 索格 IPManager

## 项目简介

`索格 IPManager` 是一个轻量级 PHP 本地应用，专为家庭和小型办公网络的 DHCP 静态 IP 地址管理设计。它提供了直观的 Web 控制面板，支持基础登录认证、IP 绑定管理、子网空闲地址可视化，以及在线状态检测。

## 主要功能

- 单用户认证模型（无管理员/普通用户权限划分）
- 支持在账号设置中修改用户名和密码
- DHCP 静态地址表 CSV 导入、导出
- 支持 `dhcp_static.csv` 格式，兼容 RouterOS、MikroTik、爱快等路由器平台
- 自动识别 UTF-8、GBK/GB2312 编码，避免中文备注乱码
- 可视化子网布局，显示已分配与空闲 IP
- 单条 IP 检测与一键检测所有地址
- 本地文件存储，所有数据保存在 `data/` 目录

## 界面截图

### 总览面板

![总览面板](img/overview.png)

展示核心统计信息、状态分布和快捷操作入口。

### IP 列表

![IP 列表](img/iplist.png)

用于维护静态地址记录，支持搜索、筛选、排序和状态检测。

### 网段视图

![网段视图](img/iprange.png)

直观展示网段内已分配与空闲地址分布。

### 导入导出

![导入导出](img/import_export.png)

支持 CSV 导入历史数据并导出标准 `dhcp_static.csv` 文件。

## 文件结构

- `index.php`：登录页面
- `app.php`：主界面与 SPA 应用入口
- `api.php`：JSON API 服务端接口
- `logout.php`：退出登录
- `includes/`：认证、数据、编码、ping 等后端逻辑
- `assets/`：样式和 JavaScript 文件
- `data/`：本地数据存储目录，包含 `.htaccess` 保护
- `dhcp_static.csv`：样例 CSV 导入文件

## CSV 导入 / 导出兼容性

本项目导入与导出的 CSV 文件格式兼容多种路由器系统，包括但不限于：

- RouterOS / MikroTik
- 爱快 (iKuai)
- OpenWrt / LEDE 兼容静态 DHCP 列表
- 其他采用 `dhcp_static.csv` 格式的系统

### 导入说明

- 支持拖拽或选择文件导入
- 支持 UTF-8 及 GBK/GB2312 编码
- 提供智能合并、追加、替换三种导入模式

### 导出说明

- 生成标准 `dhcp_static.csv`
- 可以直接下载并导入到兼容路由器中

## 使用说明

1. 将项目部署到支持 PHP 的服务器（Apache、Nginx 等）
2. 打开浏览器访问 `index.php`
3. 登录账户：`admin`，密码：`admin`
4. 进入设置页，按需修改用户名和密码

## 注意事项

- 确保 `data/` 目录可写
- 仅支持本地文件存储，不依赖数据库
- 若服务器禁用 `exec()`，Ping 检测将回退到 socket 方式

## 语言切换

查看英文说明请前往 [README.md](README.md)
