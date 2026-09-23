#!/usr/bin/env bash
# ============================================================
#   acg-faka 一键部署脚本（Docker）
#   MIT License · Copyright (c) 2026 鼠宝财
#
#   本脚本安装的 acg-faka 是上游开源项目（github.com/lizhipay/acg-faka），
#   该项目版权归其原作者所有；原作者声明：仅限学习研究，未取得合法资质禁止商用。
# ============================================================
set -euo pipefail

exec < /dev/null
LOG_FILE=${LOG_FILE:-/var/log/acg-faka-install.log}
exec > >(tee -a "$LOG_FILE") 2>&1
export DEBIAN_FRONTEND=noninteractive
echo "===== $(date '+%F %T') 开始安装（日志同时写到 $LOG_FILE）====="

ACG_DIR=${ACG_DIR:-/opt/acg-faka}
ACG_PORT=${ACG_PORT:-8080}
REPO_URL=${ACG_REPO_URL:-https://github.com/lizhipay/acg-faka.git}
REPO_MIRROR=${ACG_REPO_MIRROR:-https://gitclone.com/github.com/lizhipay/acg-faka.git}
REGISTRY_MIRROR=${REGISTRY_MIRROR:-https://mirror.ccs.tencentyun.com}
SKIP_DOCKER_INSTALL=${SKIP_DOCKER_INSTALL:-0}
USE_MARIADB=${USE_MARIADB:-0}
SERVICE_NAME=acg-faka-app

c()   { printf '\033[1;36m%s\033[0m\n' "$*"; }
ok()  { printf '  \033[1;32m✓\033[0m %s\n' "$*"; }
warn(){ printf '  \033[1;33m!\033[0m %s\n' "$*"; }
die() { printf '  \033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" = "0" ] || die "请用 root 运行：sudo bash $0"

c "==> 1/7 检查环境"
. /etc/os-release 2>/dev/null || true
case "${ID:-}" in
  ubuntu|debian) ok "系统：${PRETTY_NAME:-$ID}" ;;
  *) warn "只在 Ubuntu/Debian 上测过，当前是 ${PRETTY_NAME:-未知}，继续但可能失败" ;;
esac
ARCH=$(dpkg --print-architecture 2>/dev/null || uname -m)
ok "架构：$ARCH"

if [ "$ARCH" != "amd64" ] && [ "$USE_MARIADB" != "1" ]; then
  USE_MARIADB=1
  warn "非 amd64 架构，自动改用 mariadb:10.6（避免 MySQL 5.7 走 qemu 模拟）"
fi

FREE_MB=$(df -Pm / | awk 'NR==2 {print $4}')
if [ "$FREE_MB" -lt 5120 ]; then
  warn "根分区只剩 ${FREE_MB}MB，构建 + 镜像大约要 3~4GB，可能不够"
else
  ok "磁盘可用 ${FREE_MB}MB"
fi

TOTAL_MB=$(free -m | awk 'NR==2 {print $2}')
SWAP_MB=$(free -m | awk 'NR==3 {print $2}')
if [ "$TOTAL_MB" -lt 4096 ] && [ "${SWAP_MB:-0}" -lt 1024 ]; then
  if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    ok "内存只有 ${TOTAL_MB}MB，已加 2GB swap（防止构建镜像时 OOM）"
  else
    swapon /swapfile 2>/dev/null || true
    ok "已有 /swapfile，已启用"
  fi
else
  ok "内存 ${TOTAL_MB}MB / swap ${SWAP_MB}MB，够用"
fi

for p in git curl; do
  command -v "$p" >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq "$p" >/dev/null; }
done
ok "git / curl 就绪"

if ss -lnt 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${ACG_PORT}\$"; then
  die "端口 ${ACG_PORT} 已被占用，换一个：ACG_PORT=9090 bash $0"
fi
ok "端口 ${ACG_PORT} 空闲"

c "==> 2/7 安装 Docker"
if command -v docker >/dev/null 2>&1 && [ "$SKIP_DOCKER_INSTALL" = "1" ]; then
  ok "已装 Docker：$(docker --version)，按要求跳过安装"
elif command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  ok "Docker 已在：$(docker --version) / $(docker compose version --short 2>/dev/null || echo compose)"
else
  apt-get update -qq || { warn "apt update 失败，重试一次"; sleep 3; apt-get update -qq; }
  apt-get install -y -qq ca-certificates curl gnupg >/dev/null

  DISTRO=${ID:-ubuntu}
  CODENAME=$(. /etc/os-release && echo "${VERSION_CODENAME:-jammy}")
  DOCKER_MIRRORS=${DOCKER_APT_MIRROR:-"https://mirrors.tencentyun.com/docker-ce/linux/$DISTRO
https://mirrors.cloud.tencent.com/docker-ce/linux/$DISTRO
https://mirrors.aliyun.com/docker-ce/linux/$DISTRO
https://mirrors.ustc.edu.cn/docker-ce/linux/$DISTRO
https://download.docker.com/linux/$DISTRO"}

  MIRROR_BASE=""
  for m in $DOCKER_MIRRORS; do
    if curl -fsSL --connect-timeout 8 -m 30 --retry 2 --retry-delay 2 "$m/gpg" -o /tmp/docker.gpg 2>/dev/null \
       && curl -fsS --connect-timeout 8 -m 20 -o /dev/null "$m/dists/$CODENAME/Release" 2>/dev/null; then
      MIRROR_BASE=$m
      break
    fi
    warn "镜像不可用，换下一个：$m"
  done
  [ -n "$MIRROR_BASE" ] || die "所有 Docker 软件源都连不上。可手动指定：DOCKER_APT_MIRROR=https://mirrors.aliyun.com/docker-ce/linux/$DISTRO bash $0"

  install -m 0755 -d /etc/apt/keyrings
  install -m 0644 /tmp/docker.gpg /etc/apt/keyrings/docker.asc
  rm -f /tmp/docker.gpg
  echo "deb [arch=$ARCH signed-by=/etc/apt/keyrings/docker.asc] $MIRROR_BASE $CODENAME stable" \
    > /etc/apt/sources.list.d/docker.list
  ok "Docker 软件源：$MIRROR_BASE"

  for i in 1 2 3; do
    apt-get update -qq && break || { warn "apt update 第 $i 次失败，重试"; sleep 3; }
  done
  for i in 1 2 3; do
    if apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin >/dev/null 2>&1; then
      break
    fi
    warn "Docker 安装第 $i 次失败，重试"
    sleep 5
    apt-get update -qq || true
  done
  command -v docker >/dev/null 2>&1 || die "Docker 安装失败，看上面报错"
  ok "已安装：$(docker --version)"
fi
systemctl enable --now docker >/dev/null 2>&1 || true
systemctl is-active docker >/dev/null || die "Docker 没能启动，journalctl -u docker 看看"
ok "docker 服务运行中，compose 插件版本 $(docker compose version --short 2>/dev/null || echo '?')"

if [ -n "$REGISTRY_MIRROR" ]; then
  mkdir -p /etc/docker
  cat > /etc/docker/daemon.json <<EOF
{
  "registry-mirrors": ["$REGISTRY_MIRROR"],
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" }
}
EOF
  if systemctl restart docker && sleep 2 && docker info >/dev/null 2>&1; then
    ok "已配置镜像加速：$REGISTRY_MIRROR（顺便限制了容器日志大小）"
  else
    warn "镜像加速配置后 docker 有问题，已回退"
    rm -f /etc/docker/daemon.json
    systemctl restart docker
  fi
else
  ok "按要求不使用镜像加速"
fi

c "==> 3/7 获取项目源码"
export GIT_TERMINAL_PROMPT=0
export GIT_HTTP_LOW_SPEED_LIMIT=1000 GIT_HTTP_LOW_SPEED_TIME=30

TARBALL_URLS=${ACG_TARBALL_URLS:-"https://ghfast.top/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.tar.gz
https://gh-proxy.com/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.tar.gz
https://ghproxy.net/https://github.com/lizhipay/acg-faka/archive/refs/heads/main.tar.gz
https://codeload.github.com/lizhipay/acg-faka/tar.gz/refs/heads/main
https://gitclone.com/github.com/lizhipay/acg-faka/archive/refs/heads/main.tar.gz"}

fetch_by_tarball() {
  local dest="$1" url tmp
  tmp=$(mktemp /tmp/acg-src-XXXXXX.tar.gz)
  for url in $TARBALL_URLS; do
    if curl -fsSL --connect-timeout 10 -m 180 -o "$tmp" "$url" 2>/dev/null \
       && tar tzf "$tmp" >/dev/null 2>&1; then
      rm -rf "$dest"; mkdir -p "$dest"
      tar xzf "$tmp" -C "$dest" --strip-components=1 && rm -f "$tmp"
      ok "已用加速代理下载源码：$(echo "$url" | sed 's|https://||' | cut -c1-46)…"
      return 0
    fi
    warn "下载失败，换下一个源：$(echo "$url" | sed 's|https://||' | cut -c1-40)…"
  done
  rm -f "$tmp"
  return 1
}

if [ -d "$ACG_DIR/.git" ]; then
  ok "已存在仓库，拉取更新"
  timeout 90 git -C "$ACG_DIR" pull --ff-only 2>&1 | tail -3 | sed 's/^/  /' \
    || warn "更新失败（网络或本地改动），继续用现有代码"
elif [ -e "$ACG_DIR" ] && [ ! -d "$ACG_DIR/.git" ]; then
  warn "已有非 git 目录（上次用 tarball 装的），删掉重新获取"
  rm -rf "$ACG_DIR"
fi

if [ ! -e "$ACG_DIR" ]; then
  mkdir -p "$(dirname "$ACG_DIR")"
  GOT=0
  if timeout 15 git ls-remote "$REPO_URL" HEAD >/dev/null 2>&1; then
    for attempt in 1 2; do
      if timeout 300 git clone --depth 1 "$REPO_URL" "$ACG_DIR" 2>/dev/null; then
        GOT=1
        ok "git clone 成功（GitHub 直连）"
        break
      fi
      rm -rf "$ACG_DIR"
      warn "git clone 第 $attempt 次失败/超时，重试"
      sleep 3
    done
  else
    warn "GitHub 探测不通，直接走国内加速代理"
  fi
  if [ "$GOT" = "0" ]; then
    fetch_by_tarball "$ACG_DIR" || die "源码获取失败。可指定源重试：ACG_TARBALL_URLS='<tarball 地址>' bash $0"
  fi
fi
[ -f "$ACG_DIR/docker-compose.yml" ] || die "$ACG_DIR 里没有 docker-compose.yml，仓库结构不对"
ok "代码就位：$(git -C "$ACG_DIR" log -1 --format='%h %cd' --date=short 2>/dev/null || echo '非 git 目录')"

c "==> 4/7 生成配置"
echo "ACG_HTTP_PORT=$ACG_PORT" > "$ACG_DIR/.env"
ok ".env：ACG_HTTP_PORT=$ACG_PORT（容器内仍是 80）"

if [ "$USE_MARIADB" = "1" ]; then
  if grep -q 'image: mysql:5.7' "$ACG_DIR/docker-compose.yml"; then
    sed -i 's|image: mysql:5\.7|image: mariadb:10.6|g' "$ACG_DIR/docker-compose.yml"
    sed -i '/platform: linux\/amd64/d' "$ACG_DIR/docker-compose.yml"
    ok "已把 MySQL 换成 mariadb:10.6 并去掉 amd64 限制（数据卷名不变，不影响使用）"
  else
    ok "compose 里已经不是 mysql:5.7，跳过替换"
  fi
fi

c "==> 5/7 构建加速补丁（国内必做）"
CN_DEBIAN_MIRROR=${CN_DEBIAN_MIRROR:-mirrors.cloud.tencent.com}
COMPOSER_MIRROR=${COMPOSER_MIRROR:-https://mirrors.tencent.com/composer/}
MIRROR_OK=""
if [ -n "$CN_DEBIAN_MIRROR" ] && curl -fsS -m 8 -o /dev/null "http://$CN_DEBIAN_MIRROR/debian/dists/bookworm/Release"; then
  MIRROR_OK="$CN_DEBIAN_MIRROR"
  ok "Debian 镜像可用：$MIRROR_OK"
else
  warn "国内 Debian 镜像不可用，只用「强制 IPv4」这一半补丁"
fi
if curl -fsS -m 8 -o /dev/null "${COMPOSER_MIRROR}packages.json"; then
  ok "Composer 镜像可用：$COMPOSER_MIRROR"
else
  warn "Composer 镜像不可用，构建时直连 packagist"
  COMPOSER_MIRROR=""
fi

if grep -q 'ForceIPv4' "$ACG_DIR/Dockerfile" && grep -q 'repo.packagist' "$ACG_DIR/Dockerfile"; then
  ok "Dockerfile 已有加速补丁，跳过"
else
  python3 - "$ACG_DIR/Dockerfile" "$MIRROR_OK" "$COMPOSER_MIRROR" <<'PY'
import sys
path, mirror, composer = sys.argv[1], sys.argv[2], sys.argv[3]
src = open(path, encoding='utf-8').read()

needle_apt = "    apt-get update; \\\n"
inject_apt = (
    "    printf 'Acquire::ForceIPv4 \"true\";\\n' > /etc/apt/apt.conf.d/99force-ipv4; \\\n"
)
if mirror:
    inject_apt += (
        "    sed -i 's|deb.debian.org|%s|g; s|security.debian.org|%s|g'"
        " /etc/apt/sources.list /etc/apt/sources.list.d/*.sources 2>/dev/null || true; \\\n" % (mirror, mirror)
    )

needle_composer = "    if [ ! -f ${ACG_HOME}/vendor/autoload.php ]; then \\\n"
inject_composer = ""
if composer:
    inject_composer = (
        "    composer config -g repo.packagist composer %s 2>/dev/null || true; \\\n" % composer
    )

changed = []
if needle_apt in src:
    src = src.replace(needle_apt, inject_apt + needle_apt, 1)
    changed.append("apt(ForceIPv4%s)" % ("+" + mirror if mirror else ""))
if needle_composer in src:
    src = src.replace(needle_composer, inject_composer + needle_composer, 1)
    changed.append("composer(%s)" % (composer or "官方源"))

if changed:
    open(path, 'w', encoding='utf-8').write(src)
    print("  ok 已注入构建加速补丁：" + "、".join(changed))
else:
    print("  warn Dockerfile 结构与预期不同，未注入；构建可能很慢")
PY
fi
grep -n -E 'ForceIPv4|repo.packagist' "$ACG_DIR/Dockerfile" | head -4 | sed 's/^/  /'

c "==> 6/7 构建并启动（首次要装 PHP 扩展，约 3~8 分钟）"
cd "$ACG_DIR"
for i in 1 2; do
  if docker compose up -d --build < /dev/null 2>&1 | tail -25 | sed 's/^/  /'; then
    break
  fi
  warn "构建/启动第 $i 次失败（多为拉镜像超时），重试"
  sleep 5
done

SECRET_VOL=$(docker volume ls -q \
  --filter "label=com.docker.compose.project=$(basename "$ACG_DIR")" \
  --filter "label=com.docker.compose.volume=acg_secrets" | head -1)
if [ -n "$SECRET_VOL" ]; then
  docker run --rm --entrypoint sh -v "$SECRET_VOL":/s redis:7.2-alpine \
    -c 'chmod 644 /s/mysql_app /s/mysql_root 2>/dev/null || true' >/dev/null 2>&1 \
    && ok "已修复内置数据库密钥权限（php-fpm 读得到，向导不会再报 using password: NO）" \
    || warn "密钥权限修正失败：若向导报 using password: NO，手动把该卷里的 mysql_app 改成 644"
else
  warn "没找到 acg_secrets 卷，跳过密钥权限修正"
fi

c "==> 7/7 等健康检查通过"
DEADLINE=$((SECONDS + 240))
STATUS="unknown"
while [ $SECONDS -lt $DEADLINE ]; do
  STATUS=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$SERVICE_NAME" 2>/dev/null || echo "missing")
  [ "$STATUS" = "healthy" ] && break
  printf '.'
  sleep 5
done
echo
if [ "$STATUS" = "healthy" ]; then
  ok "app 容器健康（/healthz 返回 pong）"
else
  warn "容器状态：$STATUS（未必是失败，可能还在初始化）"
fi

HTTP=$(curl -s -o /dev/null -w '%{http_code}' -m 10 "http://127.0.0.1:$ACG_PORT/" || echo 000)
ok "本机访问 http://127.0.0.1:$ACG_PORT/ → HTTP $HTTP"
echo
docker compose ps 2>/dev/null | sed 's/^/  /' || true
echo
echo "  镜像与卷："
docker images --format '    {{.Repository}}:{{.Tag}}  {{.Size}}' | grep -E 'acg|mysql|mariadb|redis|php' | sed 's/^/  /' || true
docker volume ls --format '    {{.Name}}' | grep acg | sed 's/^/  /' || true

PUBIP=$(curl -fsS --max-time 3 http://metadata.tencentyun.com/latest/meta-data/public-ipv4 2>/dev/null \
     || curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || echo "<服务器公网IP>")

cat <<EOF

============================================================
 部署完成 🎉
   安装向导：  http://$PUBIP:$ACG_PORT/
   后台地址：  http://$PUBIP:$ACG_PORT/admin   （会跳到 /admin/authentication/login，带验证码）
   项目目录：  $ACG_DIR
   数据卷：    acg_data（配置/上传/插件/模板/安装锁）、acg_mysql、acg_redis、acg_secrets

 接下来：浏览器打开安装向导 → 第 2 步数据库直接点「下一步」（密码留空，服务端自己取）
        → 第 3 步填管理员邮箱 / 昵称 / 密码 → 完成。

 已知问题（不影响安装结果）：
   * 向导停在「导入语言包」不动：此时安装已完成。程序先写安装锁、再导语言包，
     页面若重发请求会收到「您已经安装过了」而卡住。关闭向导，直接访问 /admin 登录即可。
   * 重新安装：删掉安装锁后重跑本脚本
       docker compose exec app rm -f /data/install/Lock
     连数据一起清空：docker compose down -v 后再跑本脚本。

 常用命令（都在 $ACG_DIR 下执行）：
   docker compose ps                查看容器状态
   docker compose logs -f app       查看应用日志
   docker compose restart app       重启应用
   docker compose down              停止（数据卷保留）
   docker compose up -d --build     更新代码后重建（git pull 之后跑）
   docker compose down -v           连数据一起删（危险，会清库）

 数据备份（配置文件、上传文件、数据库）：
   docker compose exec -T mysql sh -c 'exec mysqldump -uroot -p"\$(cat /secrets/mysql_root)" --databases acg_faka' > acg-\$(date +%F).sql
   docker run --rm -v acg_data:/data -v "\$PWD:/backup" alpine tar czf /backup/acg-data-\$(date +%F).tar.gz -C /data .

 查看随机生成的数据库密码：
   docker run --rm -v acg_secrets:/s -v "\$PWD:/out" alpine sh -c 'cp /s/mysql_app /out/mysql_app.pwd && chmod 600 /out/mysql_app.pwd'

 注意：
   1) 云服务器安全组需放行 $ACG_PORT 端口，否则外网访问不到。
   2) acg-faka 为 MIT 开源项目，原作者声明：仅限学习研究，未取得合法资质禁止商用。
   3) 需要 HTTPS 时，可在本机加一层 nginx + Let's Encrypt 反代到 $ACG_PORT。

 可选参数（写成环境变量放在命令前即可）：
   ACG_PORT=9090              换端口（默认 8080）
   ACG_DIR=/srv/acg           换安装目录（默认 /opt/acg-faka）
   USE_MARIADB=1              ARM 机器改用 mariadb:10.6（原生性能）
   SKIP_DOCKER_INSTALL=1      已装 Docker 时跳过安装步骤
   REGISTRY_MIRROR=           不用 Docker 镜像加速（默认腾讯云）
   DOCKER_APT_MIRROR=<url>    指定 Docker 软件源
   CN_DEBIAN_MIRROR=<host>    指定构建用 Debian 源
   COMPOSER_MIRROR=<url>      指定 composer 源
   ACG_TARBALL_URLS='<urls>'  指定源码下载源（默认 5 个国内加速代理）

 MIT License · Copyright (c) 2026 鼠宝财
 脚本项目仓库：https://github.com/shubaocai/sbc-acgfaka
============================================================
EOF
