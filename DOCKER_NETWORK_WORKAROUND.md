# Docker 环境下的网络检测解决方案

## 问题描述

在 Docker 容器中运行 IPManager 时，容器内无法直接访问 Docker 宿主机的 IP 地址，导致这些 IP 检测状态始终显示为**离线**。这是 Docker 的网络隔离特性决定的。

例如：
- Docker 宿主机 IP: `192.168.2.6 - 192.168.2.10`
- 容器检测这些 IP 时，ICMP ping、TCP 连接、HTTP 探测都失败
- 结果：这些 IP 在列表中显示为离线

## 解决方案

### 方案 1: 应用层配置（推荐，无需改 Docker）⭐

在 IPManager 的**设置 > Docker 宿主 IP 范围**中配置宿主机 IP 范围。

**步骤：**
1. 打开 IPManager 界面 → **设置** 标签
2. 找到 **"Docker 宿主 IP 范围"** 输入框
3. 输入宿主机 IP 地址或范围：
   - **单个 IP**：`192.168.2.6`
   - **IP 范围**：`192.168.2.6-192.168.2.10`
   - **多个配置**（用逗号分隔）：`192.168.2.6-192.168.2.10,10.0.0.1`
4. 点击 **保存设置**

**效果：**
- 配置的 IP 地址检测时直接返回在线状态
- 检测方法显示为 `docker_host`
- 无需改 Docker 配置，无 Docker 安全影响

**示例配置：**
```
192.168.2.6-192.168.2.10
```

---

### 方案 2: Docker 容器网络配置（更全面）

使用 `--network host` 或 `host.docker.internal` 让容器访问宿主机网络。

**选项 A：host 网络模式** (最简单但降低容器隔离性)
```bash
docker run -d \
  --network host \
  --name ipmanager \
  -v /path/to/data:/app/data \
  jarrey/ipmanager:latest
```

**选项 B：bridge 网络 + host.docker.internal** (推荐，保持隔离)
```bash
docker run -d \
  --network bridge \
  --add-host=host.docker.internal:host-gateway \
  -p 8080:80 \
  --name ipmanager \
  -v /path/to/data:/app/data \
  jarrey/ipmanager:latest
```

如果使用 Docker Compose：
```yaml
services:
  ipmanager:
    image: jarrey/ipmanager:latest
    container_name: ipmanager
    ports:
      - "8080:80"
    volumes:
      - ./data:/app/data
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      - TZ=Asia/Shanghai
```

---

### 方案 3: 组合方案（最推荐）

1. **Docker 配置**：使用 `--add-host=host.docker.internal:host-gateway`
2. **应用配置**：在 IPManager 设置中配置 Docker 宿主 IP 范围

**优势：**
- ✅ 安全：容器网络仍然隔离
- ✅ 可靠：即使容器网络配置不完美，应用层也有 fallback
- ✅ 灵活：可针对不同 IP 配置不同的检测策略
- ✅ 透明：检测方法清晰可见，便于排查问题

---

## 配置细节

### 格式说明

**docker_host_ranges** 支持以下格式：

| 格式 | 示例 | 说明 |
|------|------|------|
| 单个 IP | `192.168.2.6` | 单个宿主机 IP |
| IP 范围 | `192.168.2.6-192.168.2.10` | 连续范围内的所有 IP |
| 多个项目 | `192.168.2.6,10.0.0.1` | 用逗号分隔多个 IP |
| 混合 | `192.168.2.6-192.168.2.10,10.0.0.5` | 范围和单个 IP 混合 |

### 工作原理

```
输入 IP → 检测流程
    ↓
检查是否在 docker_host_ranges 中？
    ↓ 是
  立即返回在线 (method: docker_host, time: 0ms)
    ↓ 否
  按照标准流程检测
  1. ICMP ping
  2. TCP 端口扫描 (15 个常用端口)
  3. HTTP HEAD 请求 (4 个常用端口)
```

### 在 HTML 中的显示

- **状态指示**：绿色在线图标 ✓
- **检测方法**：标记为 `docker_host`
- **响应时间**：0 ms（因为是本地配置，无实际网络延迟）

---

## 常见问题

### Q: 如何找到我的 Docker 宿主机 IP？

**在 Docker 宿主机上：**
```bash
# Linux/macOS
ifconfig | grep "inet "
# 或
ip addr show

# Windows
ipconfig
```

**从 Docker 容器内：**
```bash
# 查看网关地址（通常是宿主机）
ip route show
# 输出: default via 172.17.0.1 dev eth0
# 172.17.0.1 就是 Docker 网关

# 查看容器自己的 IP
hostname -I
```

### Q: 支持 IPv6 吗？

目前不支持。应用仅处理 IPv4 地址。

### Q: 如果我配置了无效的 IP 范围会怎样？

无效的 IP 地址会被忽略，不会影响其他配置或检测流程。

### Q: 配置后多久生效？

立即生效。已保存的新配置在下次检测时应用。

### Q: 可以配置 Docker 容器内的其他容器吗？

可以。只要 IP 能识别（有效的 IPv4 地址），就可以配置。

---

## 检测验证

### 验证配置是否生效

1. **配置 IP 范围**：在设置中输入 `192.168.2.6-192.168.2.10`
2. **打开 IP 列表**：确保有宿主机 IP 的记录
3. **检查状态**：
   - ✅ 状态应显示为 **在线** (绿色)
   - ✅ 检测方法应显示 **`docker_host`**
   - ✅ 响应时间应为 **0 ms**
4. **查看浏览器控制台**：F12 → Console，确保无错误信息

### 调试方法

如果配置后仍未生效：

1. **清除浏览器缓存**：Ctrl+F5 刷新
2. **检查设置是否保存**：设置 → 页面加载后，字段值应保留
3. **查看网络请求**：F12 → Network，检查 `api.php?action=ping_ip` 响应中的 `method` 字段

---

## Docker Compose 完整示例

```yaml
version: '3.8'

services:
  ipmanager:
    image: jarrey/ipmanager:latest
    container_name: ipmanager
    
    # 网络配置
    networks:
      - default
    extra_hosts:
      - "host.docker.internal:host-gateway"
    
    # 端口映射
    ports:
      - "8080:80"
    
    # 数据卷
    volumes:
      - ./data:/app/data
      - ./config:/app/includes/config  # 可选：共享配置
    
    # 环境变量
    environment:
      - TZ=Asia/Shanghai
      - PHP_MEMORY_LIMIT=512M
    
    # 重启策略
    restart: unless-stopped
    
    # 资源限制
    # deploy:
    #   resources:
    #     limits:
    #       cpus: '1'
    #       memory: 512M

# 可选：定义容器可访问的其他服务
networks:
  default:
    driver: bridge
```

### 启动方式

```bash
# 启动
docker-compose up -d

# 查看日志
docker-compose logs -f ipmanager

# 停止
docker-compose down
```

---

## 性能影响

- **应用层检测**：无性能影响，配置的 IP 直接返回结果 (O(1) 时间复杂度)
- **数据库查询**：无额外查询
- **网络开销**：0 字节

---

## 安全建议

✅ **推荐做法：**
- 使用 bridge 网络 + `--add-host` 而非 `--network host`
- 只配置实际的宿主机 IP，避免过宽范围
- 定期检查配置是否符合网络拓扑变化

⚠️ **避免：**
- 配置过大的 IP 范围（如整个子网）
- 使用 `--network host` 除非有特殊需求
- 暴露容器到公网时未配置防火墙

---

## 获取帮助

遇到问题？

1. 检查 `settings.json` 中 `docker_host_ranges` 的值是否正确格式
2. 使用 `docker logs ipmanager` 查看容器日志
3. 在 IPManager 界面 → 设置 → 清除缓存，然后重新检测
4. 提交 Issue：https://github.com/Jarrey/SG_IPManager/issues

---

**最后更新**：2026-05-03  
**适用版本**：1.0.0+
