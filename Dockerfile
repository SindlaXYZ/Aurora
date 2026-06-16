# syntax=docker/dockerfile:1

ARG DKZ_DIST=debian:13.5
FROM ${DKZ_DIST}

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

LABEL maintainer="Sindla"
LABEL description="Dockraft project Dev CLI container"

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV DKZ_PROJECT_DIR=/srv/dockraft
ENV PATH="/root/.local/bin:${PATH}"

ARG DKZ_NAME=dockraft
ENV DKZ_NAME=${DKZ_NAME}

ARG DKZ_PHP_VERSION_INSTALL=0
ENV DKZ_PHP_VERSION_INSTALL=${DKZ_PHP_VERSION_INSTALL}

ARG DKZ_NODEJS_VERSION_INSTALL=0
ENV DKZ_NODEJS_VERSION_INSTALL=${DKZ_NODEJS_VERSION_INSTALL}

ARG DKZ_PYTHON_VERSION_INSTALL=0
ENV DKZ_PYTHON_VERSION_INSTALL=${DKZ_PYTHON_VERSION_INSTALL}

ARG DKZ_AI_CLAUDE_CODE_INSTALL=0
ENV DKZ_AI_CLAUDE_CODE_INSTALL=${DKZ_AI_CLAUDE_CODE_INSTALL}

ARG DKZ_AI_CODEX_INSTALL=0
ENV DKZ_AI_CODEX_INSTALL=${DKZ_AI_CODEX_INSTALL}

ARG DKZ_AI_GEMINI_INSTALL=0
ENV DKZ_AI_GEMINI_INSTALL=${DKZ_AI_GEMINI_INSTALL}

ARG DKZ_SHARED_MEMORY=512m
ENV DKZ_SHARED_MEMORY=${DKZ_SHARED_MEMORY}

ARG DKZ_RUN_RESTART=no
ENV DKZ_RUN_RESTART=${DKZ_RUN_RESTART}

WORKDIR /
USER root

RUN apt-get -y update && \
    apt-get -y install --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        git \
        gnupg \
        htop \
        jq \
        less \
        lsof \
        nano \
        openssh-client \
        p7zip-full \
        procps \
        pv \
        ripgrep \
        rsync \
        screen \
        tree \
        unzip \
        wget \
        xz-utils \
        zip && \
    rm -rf /var/lib/apt/lists/*

RUN echo "" >> /etc/bash.bashrc && \
    echo "# Dockraft:" >> /etc/bash.bashrc && \
    echo "if [ \$# -eq 0 ]; then" >> /etc/bash.bashrc && \
    echo "  i=0; while [ \"\$((i+=1))\" -le 100 ]; do echo; done" >> /etc/bash.bashrc && \
    echo "fi" >> /etc/bash.bashrc

RUN cat > /usr/local/bin/dockraft-motd <<'EOF' && \
    chmod +x /usr/local/bin/dockraft-motd && \
    echo 'export LC_ALL=C.utf8' >> /etc/bash.bashrc && \
    echo 'export LANG=C.utf8' >> /etc/bash.bashrc && \
    echo 'alias screen="screen -U"' >> /etc/bash.bashrc && \
    echo 'alias ll='\''ls -alh --time-style="+%Y-%m-%d %T" --color=auto --group-directories-first'\''' >> /etc/bash.bashrc && \
    echo 'alias llt='\''ls -altr --time-style="+%Y-%m-%d %T" --color=auto --group-directories-first'\''' >> /etc/bash.bashrc && \
    echo 'alias motd='\''clear && dockraft-motd'\''' >> /etc/bash.bashrc && \
    echo 'case "$-" in *i*) dockraft-motd ;; esac' >> /etc/bash.bashrc
#!/usr/bin/env bash
set -Eeuo pipefail

CDEF='\033[0m'
CCYAN='\033[0;36m'

separator() {
    echo -e "\n${CCYAN}==================================================================================${CDEF}\n"
}

distribution="N/A"
if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    source /etc/os-release
    distribution="${PRETTY_NAME:-${NAME:-N/A}}"
fi

date_local="$(date +"%Y-%m-%d %H:%M:%S [%Z %z]")"
linux_kernel="$(uname -r 2>/dev/null || echo "N/A")"
host_name="$(hostname 2>/dev/null || echo "N/A")"
public_ip="$(curl -fsS --max-time 2 https://api.ipify.org 2>/dev/null || echo "N/A")"
disk_srv="$(df -h /srv 2>/dev/null | awk 'NR==2{print $3}')"
disk_available="$(df -h /srv 2>/dev/null | awk 'NR==2{print $4}')"
load_values="$(awk '{print $1", "$2", "$3}' /proc/loadavg 2>/dev/null || echo "N/A, N/A, N/A")"

if command -v free >/dev/null 2>&1; then
    memory_used="$(free -t -m | awk '/^Total:/{print $3" MB"}')"
    memory_total="$(free -m | awk '/^Mem:/{print $2" MB"}')"
    swap_used="$(free -m | awk '/^Swap:/{print $3" MB"}')"
else
    memory_used="N/A"
    memory_total="N/A"
    swap_used="N/A"
fi

separator
printf ' - Date/Time..................: %s\n' "${date_local}"
printf ' - Distribution...............: %s\n' "${distribution}"
printf ' - Linux kernel...............: %s\n' "${linux_kernel}"
printf ' - Hostname...................: %s\n' "${host_name}"
printf ' - Public IP..................: %s\n' "${public_ip}"
printf ' - Disk (/srv)................: %s / %s\n' "${disk_srv:-N/A}" "${disk_available:-N/A}"
printf ' - CPU load (1/5/15 min)......: %s\n' "${load_values}"
printf ' - Memory used................: %s / %s\n' "${memory_used:-N/A}" "${memory_total:-N/A}"
printf ' - Swap in use................: %s\n' "${swap_used:-N/A}"
separator

# -----------------------------------------------------------------------------
# AI CLI auto-update - adapted from the dockraft motd
# (_update_ai_clis). Runs `claude update` and `codex update` in the background
# on container connect. At most once per calendar day; flock guards against
# two shells connecting at the same time.
# Output goes to ${DKZ_PROJECT_DIR}/.docker/.logs/ai-update/<Y-m-d>.log.
# -----------------------------------------------------------------------------
update_ai_clis() {
    if [[ "${DKZ_AI_CLAUDE_CODE_INSTALL:-0}" == "0" && "${DKZ_AI_CODEX_INSTALL:-0}" == "0" ]]; then
        return 0
    fi

    local log_dir="${DKZ_PROJECT_DIR:-/srv/dockraft}/.docker/.logs/ai-update"
    local stamp="${log_dir}/last-run"
    mkdir -p "$log_dir"

    # Already ran today - skip (avoids re-updating on every shell connect)
    if [[ -f "$stamp" && "$(date -r "$stamp" +%Y-%m-%d)" == "$(date +%Y-%m-%d)" ]]; then
        return 0
    fi

    (
        flock -n 9 || exit 0
        touch "$stamp"
        export PATH="/root/.local/bin:${PATH}"

        if [[ "${DKZ_AI_CLAUDE_CODE_INSTALL:-0}" != "0" ]]; then
            if command -v claude >/dev/null 2>&1; then
                echo "[$(date '+%Y-%m-%d %H:%M:%S')] Updating Claude Code CLI ..."
                claude update || echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: claude update failed (non-critical)"
            else
                echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: claude not found in PATH - skipping claude update"
            fi
        fi

        if [[ "${DKZ_AI_CODEX_INSTALL:-0}" != "0" ]]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] Updating Codex CLI ..."
            # codex here is a native-installer build (self-update capable); fall back to re-running the installer, same as dockraft-start
            if command -v codex >/dev/null 2>&1 && codex update; then
                :
            else
                curl -fsSL https://chatgpt.com/codex/install.sh | CODEX_NON_INTERACTIVE=1 sh || echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: codex update failed (non-critical)"
            fi
        fi

        echo "[$(date '+%Y-%m-%d %H:%M:%S')] AI CLI update done."
    ) >> "${log_dir}/$(date +%Y-%m-%d).log" 2>&1 9>"${log_dir}/.lock" &
    disown 2>/dev/null || true
}
update_ai_clis
EOF

# Install PHP from the Sury repository. OPcache is a separate package on PHP <= 8.4
# ("php<ver>-opcache") but is bundled into the core binary on PHP >= 8.5, where that
# package no longer exists - so it is installed conditionally below to support both.
RUN set -e; \
    if [[ "${DKZ_PHP_VERSION_INSTALL:-0}" == "0" ]]; then exit 0; fi; \
    curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg; \
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(. /etc/os-release && echo "${VERSION_CODENAME}") main" > /etc/apt/sources.list.d/sury-php.list; \
    apt-get -y update; \
    apt-get -y install --no-install-recommends \
        "php${DKZ_PHP_VERSION_INSTALL}" \
        "php${DKZ_PHP_VERSION_INSTALL}-apcu" \
        "php${DKZ_PHP_VERSION_INSTALL}-bcmath" \
        "php${DKZ_PHP_VERSION_INSTALL}-curl" \
        "php${DKZ_PHP_VERSION_INSTALL}-dom" \
        "php${DKZ_PHP_VERSION_INSTALL}-fpm" \
        "php${DKZ_PHP_VERSION_INSTALL}-gd" \
        "php${DKZ_PHP_VERSION_INSTALL}-gmp" \
        "php${DKZ_PHP_VERSION_INSTALL}-intl" \
        "php${DKZ_PHP_VERSION_INSTALL}-mbstring" \
        "php${DKZ_PHP_VERSION_INSTALL}-mysql" \
        "php${DKZ_PHP_VERSION_INSTALL}-pgsql" \
        "php${DKZ_PHP_VERSION_INSTALL}-soap" \
        "php${DKZ_PHP_VERSION_INSTALL}-xml" \
        "php${DKZ_PHP_VERSION_INSTALL}-xmlwriter" \
        "php${DKZ_PHP_VERSION_INSTALL}-zip"; \
    if apt-cache show "php${DKZ_PHP_VERSION_INSTALL}-opcache" > /dev/null 2>&1; then \
        apt-get -y install --no-install-recommends "php${DKZ_PHP_VERSION_INSTALL}-opcache"; \
    fi; \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    if [[ -f "/etc/php/${DKZ_PHP_VERSION_INSTALL}/fpm/pool.d/www.conf" ]]; then \
        sed -i "s|^listen = .*|listen = 127.0.0.1:9000|" "/etc/php/${DKZ_PHP_VERSION_INSTALL}/fpm/pool.d/www.conf"; \
    fi; \
    rm -rf /var/lib/apt/lists/*

RUN set -e; \
    _node_version="${DKZ_NODEJS_VERSION_INSTALL:-0}"; \
    if [[ "$_node_version" == "0" || -z "$_node_version" ]]; then \
        if [[ "${DKZ_AI_GEMINI_INSTALL:-0}" != "0" ]]; then \
            _node_version="22"; \
        else \
            exit 0; \
        fi; \
    fi; \
    curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash; \
    export NVM_DIR="/root/.nvm"; \
    . "$NVM_DIR/nvm.sh"; \
    nvm install "$_node_version"; \
    nvm alias default "$_node_version"; \
    echo 'export NVM_DIR="/root/.nvm"' > /etc/profile.d/nvm.sh; \
    echo '[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"' >> /etc/profile.d/nvm.sh; \
    echo 'source /etc/profile.d/nvm.sh' >> /etc/bash.bashrc

RUN set -e; \
    if [[ "${DKZ_PYTHON_VERSION_INSTALL:-0}" == "0" ]]; then exit 0; fi; \
    apt-get -y update; \
    apt-get -y install --no-install-recommends python3 python3-pip python3-venv; \
    if [[ "${DKZ_PYTHON_VERSION_INSTALL}" != "3" ]]; then \
        apt-get -y install --no-install-recommends "python${DKZ_PYTHON_VERSION_INSTALL}" 2>/dev/null || true; \
    fi; \
    rm -rf /var/lib/apt/lists/*; \
    if command -v "python${DKZ_PYTHON_VERSION_INSTALL}" > /dev/null 2>&1; then \
        PYTHON_BIN="python${DKZ_PYTHON_VERSION_INSTALL}"; \
    else \
        PYTHON_BIN="python3"; \
    fi; \
    ln -sf "/usr/bin/${PYTHON_BIN}" /usr/bin/python; \
    EXTERNALLY_MANAGED="$(${PYTHON_BIN} -c 'import sys; print(f"/usr/lib/python{sys.version_info.major}.{sys.version_info.minor}/EXTERNALLY-MANAGED")')"; \
    rm -f "${EXTERNALLY_MANAGED}"

RUN set -e; \
    if [[ "${DKZ_AI_CODEX_INSTALL:-0}" == "0" ]]; then exit 0; fi; \
    curl -fsSL https://chatgpt.com/codex/install.sh | CODEX_NON_INTERACTIVE=1 sh

RUN set -e; \
    if [[ "${DKZ_AI_GEMINI_INSTALL:-0}" == "0" ]]; then exit 0; fi; \
    export NVM_DIR="/root/.nvm"; \
    if [[ ! -s "$NVM_DIR/nvm.sh" ]]; then echo "ERROR: Gemini requires Node.js"; exit 1; fi; \
    . "$NVM_DIR/nvm.sh"; \
    npm i -g @google/gemini-cli

RUN set -e; \
    if [[ "${DKZ_AI_CLAUDE_CODE_INSTALL:-0}" == "0" ]]; then exit 0; fi; \
    _installed=false; \
    for _i in 1 2 3; do \
        if curl -fsSL https://claude.ai/install.sh | bash; then \
            _installed=true; \
            break; \
        fi; \
        if [[ "$_i" -lt 3 ]]; then \
            echo "Claude Code install attempt ${_i} failed, retrying in 15s..."; \
            sleep 15; \
        fi; \
    done; \
    [[ "$_installed" == "true" ]]; \
    echo 'export PATH="$HOME/.local/bin:${PATH}"' > /etc/profile.d/claude.sh; \
    echo 'source /etc/profile.d/claude.sh' >> /etc/bash.bashrc

RUN cat > /usr/local/bin/aurora-start <<'EOF' && \
    chmod +x /usr/local/bin/aurora-start
#!/usr/bin/env bash
set -Eeuo pipefail

export PATH="/root/.local/bin:${PATH}"

# Shares the daily stamp + lock with aurora-motd's update_ai_clis(): whichever of the two
# updates first today marks last-run and the other skips - avoids a redundant second update
# on the first shell connect after a container start.
log_dir="${DKZ_PROJECT_DIR:-/srv/aurora}/.docker/.logs/ai-update"
stamp="${log_dir}/last-run"

if [[ "${DKZ_AI_CLAUDE_CODE_INSTALL:-0}" != "0" || "${DKZ_AI_CODEX_INSTALL:-0}" != "0" ]]; then
    mkdir -p "$log_dir"
    if [[ -f "$stamp" && "$(date -r "$stamp" +%Y-%m-%d)" == "$(date +%Y-%m-%d)" ]]; then
        echo "AI CLIs already updated today (${stamp}) - skipping"
    else
        (
            flock -n 9 || exit 0
            touch "$stamp"

            if [[ "${DKZ_AI_CLAUDE_CODE_INSTALL:-0}" != "0" ]]; then
                if command -v claude >/dev/null 2>&1; then
                    echo "Updating Claude Code CLI ..."
                    claude update || echo "WARNING: claude update failed (non-critical)" >&2
                else
                    echo "WARNING: claude not found in PATH - skipping claude update" >&2
                fi
            fi

            if [[ "${DKZ_AI_CODEX_INSTALL:-0}" != "0" ]]; then
                echo "Updating Codex CLI ..."
                curl -fsSL https://chatgpt.com/codex/install.sh | CODEX_NON_INTERACTIVE=1 sh || echo "WARNING: codex update failed (non-critical)" >&2
            fi
        ) 9>"${log_dir}/.lock"
    fi
fi

exec sleep infinity
EOF

RUN mkdir -p /srv/aurora

WORKDIR /srv/aurora

CMD ["/usr/local/bin/aurora-start"]
