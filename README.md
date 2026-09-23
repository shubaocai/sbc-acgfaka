# sbc-acgfaka

> 鼠宝财 · 一键部署 [acg-faka](https://github.com/lizhipay/acg-faka) 发卡商城（Docker）

一条命令在 Linux 服务器上把 [acg-faka（异次元店铺系统）](https://github.com/lizhipay/acg-faka) 跑起来：自动检测并安装 Docker、自动生成编排配置、构建应用镜像（nginx + PHP-FPM）、拉起 MySQL 与 Redis、修复容器密钥权限、等健康检查通过，最后打印安装向导地址与运维命令。

[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
![Platform](https://img.shields.io/badge/platform-Linux%20%7C%20Docker-blue)
![Upstream](https://img.shields.io/badge/acg--faka-3.7.9-orange)

---

## 目录

- [特性](#特性)
- [环境要求](#环境要求)
- [快速开始](#快速开始)
- [安装向导](#安装向导)
- [使用示例](#使用示例)
- [环境变量](#环境变量)
- [默认值](#默认值)
- [执行流程](#执行流程)
- [部署后的目录结构](#部署后的目录结构)
- [常用运维命令](#常用运维命令)
- [数据备份与恢复](#数据备份与恢复)
- [常见问题](#常见问题)
- [卸载](#卸载)
- [合规提示](#合规提示)
- [参考文档](#参考文档)
- [License](#license)

---

## 特性

- **全自动**：服务器没装 Docker 就自动装（官方源 + 国内镜像多源探测，`download.docker.com` 被墙也能装上）；项目源码、镜像构建、容器启动、健康检查一条龙。
- **国内网络友好**：Docker 软件源五源回退、源码 GitHub 直连失败自动切加速代理、构建时自动换国内 Debian 源与 Composer 源，并强制 apt 走 IPv4（容器默认没有 IPv6 出口，否则 apt 会长时间卡住）。
- **幂等可重跑**：Docker 已装则跳过安装；源码目录已是 git 仓库则 `git pull` 更新；加速补丁已打则跳过；镜像层有缓存时重建只需几十秒。
- **小内存自保**：内存低于 4GB 且没有 swap 时，自动加 2GB swap，避免构建 PHP 扩展时被 OOM 杀掉。
- **免配置数据库**：MySQL 的 root / 应用密码在首次启动时随机生成并写入 Docker 卷 `acg_secrets`，安装向导自动读取，数据库信息不用手填，密码也不经过浏览器。
- **自动修坑**：修正内置数据库密钥文件权限（否则 PHP-FPM 读不到密码，向导会报 `using password: NO`）。
- **部署即交付**：彩色中文日志、实时进度、结束时打印访问地址、后台路径、数据卷、备份与重装命令；全过程同时写日志到 `/var/log/acg-faka-install.log`。
- **MIT 协议**，可自由修改分发。

## 环境要求

| 项目 | 要求 |
| --- | --- |
| 系统 | Linux（需 systemd），Ubuntu / Debian 已实测；其它发行版未验证 |
| 权限 | **root**（或 `sudo`） |
| Shell | **bash** |
| 依赖 | `git`、`curl`（脚本自动补装）、`python3`（Ubuntu / Debian 默认自带，用于给上游 Dockerfile 打加速补丁） |
| 架构 | `x86_64`；`arm64` 会自动把 MySQL 换成 `mariadb:10.6`（MySQL 5.7 无 arm64 官方镜像） |
| 内存 | 建议 ≥ 2GB；低于 4GB 且无 swap 时脚本自动加 2GB swap |
| 磁盘 | 建议 ≥ 5GB 可用（镜像与构建缓存约 3~4GB） |
| 端口 | `8080`（可改）需空闲，并在云厂商安全组放行 |
| 网络 | 能访问外网（装 Docker、拉镜像、拉源码） |

已在 **Ubuntu 22.04.5 LTS（x86_64，2 vCPU / 2GB 内存）** 实测：从零开始装 Docker + 构建镜像 + 起服务，约 **9~12 分钟**（其中一半以上时间花在容器内编译 PHP 扩展，2 核机器上这段是纯 CPU 活）；镜像构建完成后再跑本脚本，走缓存约 1 分钟。

## 快速开始

```bash
# 1. 克隆
git clone https://github.com/shubaocai/sbc-acgfaka.git
cd sbc-acgfaka

# 2. 执行（默认端口 8080、安装目录 /opt/acg-faka）
sudo bash install_acgfaka.sh
```

也可以不克隆，把脚本下载到 `/tmp` 再执行（推荐这种写法：脚本先落盘，可以自己看一眼再跑，也避免 `curl | bash` 直接管道执行）：

```bash
curl -fsSL -o /tmp/acgfaka.sh https://raw.githubusercontent.com/shubaocai/sbc-acgfaka/main/install_acgfaka.sh && sudo bash /tmp/acgfaka.sh
```

> 如果 `raw.githubusercontent.com` 访问不稳定，可以换 jsDelivr 镜像：
>
> ```bash
> curl -fsSL -o /tmp/acgfaka.sh https://cdn.jsdelivr.net/gh/shubaocai/sbc-acgfaka@main/install_acgfaka.sh && sudo bash /tmp/acgfaka.sh
> ```

跑完后浏览器访问：

```
http://<服务器IP>:8080/
```

## 安装向导

首次访问会进入安装向导，三步走完即可：

| 步骤 | 要做什么 |
| --- | --- |
| 1. 环境检测 | 页面自动检测，全绿直接下一步 |
| 2. 数据库 | **什么都不用改**，密码框留空，直接点「下一步」（服务端自己从密钥卷取） |
| 3. 管理员 | 填邮箱、昵称、登录密码，完成安装 |

完成后前台是商城首页，后台地址：

```
http://<服务器IP>:8080/admin        # 会跳到 /admin/authentication/login，登录带验证码
```

> **已知现象**：向导可能停在「导入语言包」不动。此时**安装其实已经完成**——程序先写安装锁、再导语言包，页面若重发请求会收到「您已经安装过了」而卡住。关掉向导，直接访问 `/admin` 用刚才的邮箱密码登录即可。

## 使用示例

```bash
# 默认：端口 8080，目录 /opt/acg-faka
sudo bash install_acgfaka.sh

# 换端口
sudo ACG_PORT=9090 bash install_acgfaka.sh

# 换安装目录
sudo ACG_DIR=/srv/acgfaka bash install_acgfaka.sh

# 已经装好 Docker，跳过安装步骤
sudo SKIP_DOCKER_INSTALL=1 bash install_acgfaka.sh

# 不用 Docker Hub 镜像加速
sudo REGISTRY_MIRROR= bash install_acgfaka.sh

# 手动指定 Docker 软件源 / 构建用 Debian 源 / Composer 源
sudo DOCKER_APT_MIRROR=https://mirrors.aliyun.com/docker-ce/linux/ubuntu \
     CN_DEBIAN_MIRROR=mirrors.aliyun.com \
     COMPOSER_MIRROR=https://mirrors.aliyun.com/composer/ \
     bash install_acgfaka.sh

# 手动指定源码下载地址（GitHub 完全不通时）
sudo ACG_TARBALL_URLS='https://ghfast.top/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.tar.gz' \
     bash install_acgfaka.sh
```

> 用 `sudo` 传环境变量记得加 `-E`（`sudo -E ACG_PORT=9090 bash install_acgfaka.sh`），否则变量会被 sudo 丢掉。

## 环境变量

| 变量 | 说明 | 默认值 |
| --- | --- | --- |
| `ACG_PORT` | 对外端口 | `8080` |
| `ACG_DIR` | 安装目录（源码、`docker-compose.yml`、`.env` 都在这里） | `/opt/acg-faka` |
| `USE_MARIADB` | 用 `mariadb:10.6` 替代 `mysql:5.7` | 非 amd64 自动为 `1` |
| `SKIP_DOCKER_INSTALL` | 已装 Docker 时跳过安装步骤 | `0` |
| `REGISTRY_MIRROR` | Docker Hub 加速地址；留空表示不配置 | `https://mirror.ccs.tencentyun.com` |
| `DOCKER_APT_MIRROR` | 指定 Docker 软件源（跳过自动探测） | 自动探测五源 |
| `CN_DEBIAN_MIRROR` | 构建时使用的 Debian 源主机名 | `mirrors.cloud.tencent.com` |
| `COMPOSER_MIRROR` | 构建时使用的 Composer 源 | `https://mirrors.tencent.com/composer/` |
| `ACG_REPO_URL` | 上游源码仓库地址 | `https://github.com/lizhipay/acg-faka.git` |
| `ACG_REPO_MIRROR` | 备用 clone 地址 | `https://gitclone.com/github.com/lizhipay/acg-faka.git` |
| `ACG_TARBALL_URLS` | 源码 tarball 下载源（空格/换行分隔，按顺序尝试） | 5 个国内加速代理 |
| `LOG_FILE` | 安装日志路径 | `/var/log/acg-faka-install.log` |

## 默认值

| 项目 | 默认值 |
| --- | --- |
| 应用镜像 | `acg-faka:local`（由脚本在上游 Dockerfile 基础上现场构建） |
| 数据库 | `mysql:5.7`（非 amd64 自动换 `mariadb:10.6`） |
| 缓存 | `redis:7.2-alpine` |
| 应用容器 | `acg-faka-app`（nginx + PHP-FPM 8.2，supervisord 托管） |
| 端口 | 主机 `8080` → 容器 `80` |
| 安装目录 | `/opt/acg-faka` |
| 数据卷 | `acg_data`、`acg_mysql`、`acg_redis`、`acg_secrets` |

## 执行流程

1. **环境检查**：校验 root、读取 `/etc/os-release`、检测架构与磁盘，内存不足时自动加 2GB swap，补齐 `git` / `curl`，确认端口未被占用。
2. **安装 Docker**：已有 Docker + `docker compose` 则跳过；否则依次探测腾讯云内网 / 腾讯云公网 / 阿里云 / 中科大 / 官方源（要求 `gpg` 与 `dists/<codename>/Release` 都能取到），写入软件源并安装 `docker-ce` 全家桶，配置 Docker Hub 加速与容器日志上限。
3. **获取源码**：先探测 GitHub（15 秒），能通就 `git clone`（保留 `.git`，便于日后 `git pull`）；不通则依次尝试 5 个加速代理下载 tarball。已有仓库则 `git pull`。
4. **生成配置**：写入 `.env`（`ACG_HTTP_PORT`）；非 amd64 架构把 compose 里的 MySQL 换成 MariaDB 并去掉 `platform: linux/amd64`。
5. **构建加速补丁**：给上游 `Dockerfile` 注入「apt 强制 IPv4 + 国内 Debian 源」和「Composer 国内源」，已打过则跳过。
6. **构建并启动**：`docker compose up -d --build`（失败自动重试一次），随后修正 `acg_secrets` 卷内密钥文件权限，让 PHP-FPM 能读到数据库密码。
7. **等待健康检查**：轮询 `acg-faka-app` 容器健康状态（最多 4 分钟）并请求一次首页，最后打印访问地址、后台路径、数据卷与运维命令。

## 部署后的目录结构

```
/opt/acg-faka
├── docker-compose.yml      # 上游编排文件（非 amd64 时脚本会替换 MySQL 镜像）
├── Dockerfile              # 上游镜像构建文件（脚本会注入两行加速配置）
├── .env                    # 对外端口（由脚本生成）
└── 上游源码目录…
```

数据不在安装目录里，全部落在 Docker 命名卷：

| 卷 | 内容（**务必备份**） |
| --- | --- |
| `acg_data` | 站点配置、上传文件、插件、模板、运行时、安装锁 |
| `acg_mysql` | MySQL 数据目录 |
| `acg_redis` | Redis 持久化文件 |
| `acg_secrets` | 随机生成的数据库 root / 应用密码 |

容器与网络：

| 名称 | 说明 |
| --- | --- |
| `acg-faka-app` | 应用容器（nginx + PHP-FPM），映射 `主机端口:80` |
| `acg-faka-mysql` | MySQL 5.7 容器（非 amd64 为 MariaDB 10.6） |
| `acg-faka-redis` | Redis 7.2 容器 |
| `acg-faka-secrets-init` | 一次性容器，生成随机数据库密码后退出（`Exited (0)` 属正常） |
| `acg-faka_default` | 上述容器之间的内部网络 |

## 常用运维命令

在安装目录（默认 `/opt/acg-faka`）下执行：

```bash
cd /opt/acg-faka

docker compose ps                 # 查看容器状态
docker compose logs -f app        # 实时看应用日志
docker compose restart app        # 重启应用
docker compose down               # 停止并删除容器（数据卷保留）
docker compose up -d              # 启动
docker compose up -d --build      # 更新代码后重建（git pull 之后跑）

# 查看随机生成的数据库密码
docker run --rm -v acg_secrets:/s -v "$PWD:/out" alpine sh -c 'cp /s/mysql_app /out/mysql_app.pwd && chmod 600 /out/mysql_app.pwd'
cat mysql_app.pwd
```

## 数据备份与恢复

```bash
cd /opt/acg-faka

# 1. 数据库
docker compose exec -T mysql sh -c 'exec mysqldump -uroot -p"$(cat /secrets/mysql_root)" --databases acg_faka' > acg-$(date +%F).sql

# 2. 站点数据卷（配置、上传、插件、模板、安装锁）
docker run --rm -v acg_data:/data -v "$PWD:/backup" alpine tar czf /backup/acg-data-$(date +%F).tar.gz -C /data .
```

恢复：

```bash
cd /opt/acg-faka

# 数据卷
docker run --rm -v acg_data:/data -v "$PWD:/backup" alpine sh -c 'tar xzf /backup/acg-data-2026-01-01.tar.gz -C /data'

# 数据库（容器起来之后导入）
docker compose up -d mysql
docker compose exec -T mysql sh -c 'exec mysql -uroot -p"$(cat /secrets/mysql_root)"' < acg-2026-01-01.sql
docker compose up -d
```

## 常见问题

### 向导停在「导入语言包」

安装其实已经完成（程序先写安装锁、再导语言包，页面重发请求会撞上「您已经安装过了」这个保护）。关闭向导页，直接访问 `http://<服务器IP>:8080/admin`，用向导第 3 步填的邮箱和密码登录。

### 数据库那一步报 `Access denied ... (using password: NO)`

说明 PHP-FPM 读不到随机生成的数据库密码文件。脚本第 6 步已自动修正（把密钥卷里的 `mysql_app` / `mysql_root` 权限改为 644）；如果手动部署时遇到，自己修一下即可：

```bash
# 密钥卷名通常是 <安装目录名>_acg_secrets
docker volume ls -q | grep acg_secrets

docker run --rm --entrypoint sh -v <上一步查到的卷名>:/s redis:7.2-alpine \
  -c 'chmod 644 /s/mysql_app /s/mysql_root'
```

### 装 Docker 时提示连不上 `download.docker.com`

这是国内常见情况（`OpenSSL SSL_connect: Connection reset by peer`）。脚本会自动改用国内镜像源（腾讯云 → 阿里云 → 中科大 → 官方）。要手动指定：

```bash
sudo DOCKER_APT_MIRROR=https://mirrors.aliyun.com/docker-ce/linux/ubuntu bash install_acgfaka.sh
```

### 源码拉不下来（GitHub 超时 / 卡住）

脚本会先探测 GitHub，不通就走加速代理下载 tarball；并给 git 设了「低速 30 秒自动断开」，不会无限挂住。全都不通时手动指定一个可用地址：

```bash
sudo ACG_TARBALL_URLS='https://ghfast.top/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.tar.gz' bash install_acgfaka.sh
```

### 构建很慢，或者构建到一半失败

首次构建要在容器里编译 PHP 扩展（gd / zip / bcmath / opcache / pdo_mysql 以及 pecl 的 redis），2 核机器上通常 3~6 分钟，这是纯 CPU 消耗，不是卡死。用 `tail -f /var/log/acg-faka-install.log` 可以实时看进度。

内存不足导致的 OOM 由脚本自动加 swap 兜住；镜像拉取偶发超时会自动重试一次。

### 浏览器打不开

按顺序排查：

1. **云厂商安全组**：控制台把 `8080`（或自定义端口）加入入站规则——这是最常见的原因。
2. **本机防火墙**：`ufw allow 8080/tcp` 或 `firewall-cmd --add-port=8080/tcp`。
3. **服务是否健康**：`cd /opt/acg-faka && docker compose ps` 看 `acg-faka-app` 是否 `healthy`；`curl -I http://127.0.0.1:8080/` 看本机是否正常。

### 想换端口

`.env` 里的 `ACG_HTTP_PORT` 决定映射端口，改完重建容器：

```bash
cd /opt/acg-faka
sed -i 's|^ACG_HTTP_PORT=.*|ACG_HTTP_PORT=9090|' .env
docker compose up -d
```

### 想重新安装 / 彻底重来

```bash
cd /opt/acg-faka

# 只重跑安装向导（保留数据）
docker compose exec app rm -f /data/install/Lock

# 连数据一起清空（危险）
docker compose down -v
```

## 卸载

```bash
# 1. 停止并删除容器与数据卷（数据会一起删掉，请先备份）
cd /opt/acg-faka && docker compose down -v --remove-orphans
rm -rf /opt/acg-faka

# 2. 卸载 Docker 本体（Ubuntu/Debian）
systemctl disable --now docker.socket docker.service containerd.service
apt-get purge -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin docker-ce-rootless-extras
apt-get autoremove --purge -y
rm -rf /var/lib/docker /var/lib/containerd /etc/docker /etc/containerd /opt/containerd
rm -f /etc/apt/sources.list.d/docker.list /etc/apt/keyrings/docker.asc
groupdel docker 2>/dev/null

# 3. 如果脚本给你加过 swap
swapoff /swapfile && rm -f /swapfile && sed -i '\#^/swapfile#d' /etc/fstab
```

## 合规提示

acg-faka 是 MIT 协议的开源项目，作者在仓库 README 中明确声明：**本程序初衷是提供学习和研究机会，未取得合法资质，严禁用于任何商业用途，尤其是禁止利用本程序搭建平台进行商品销售。** 部署与使用前请自行确认合规性。

## 参考文档

- 上游项目：<https://github.com/lizhipay/acg-faka>
- 官方文档：<https://faka.wiki>
- 在线演示：后台 <https://demo.faka.wiki/admin>（`demo@demo.com` / `123456`）、前台 <https://demo.faka.wiki>
- 本脚本仓库：<https://github.com/shubaocai/sbc-acgfaka>

## License

[MIT](LICENSE) © 2026 鼠宝财
