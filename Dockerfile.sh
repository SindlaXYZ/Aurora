#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
ENV_FILE="${SCRIPT_DIR}/Dockerfile.env"
PROJECT_MOUNT="/srv/aurora"

source "${SCRIPT_DIR}/.docker/container/scripts/colors.sh"

ENV_KEYS=()

usage() {
    cat <<'USAGE'
Usage: ./Dockerfile.sh

Builds and manages the Aurora project Dev CLI container.
Configuration is read directly from Dockerfile.env. No *.local file is created.
USAGE
}

info() {
    printf '%s\n' "$*"
}

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

add_env_key() {
    local candidate="$1"
    local existing

    for existing in "${ENV_KEYS[@]}"; do
        [[ "$existing" == "$candidate" ]] && return 0
    done

    ENV_KEYS+=("$candidate")
}

collect_env_keys() {
    local line key

    ENV_KEYS=()

    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"

        [[ "$line" =~ ^[[:space:]]*$ ]] && continue
        [[ "$line" =~ ^[[:space:]]*# ]] && continue

        if [[ "$line" =~ ^[[:space:]]*([A-Za-z_][A-Za-z0-9_]*)= ]]; then
            key="${BASH_REMATCH[1]}"
            [[ "$key" == DKZ_* ]] && add_env_key "$key"
        fi
    done < "$ENV_FILE"
}

load_env() {
    [[ -f "$ENV_FILE" ]] || die "Missing ${ENV_FILE}"

    set -a
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a

    : "${DKZ_NAME:?DKZ_NAME is required in Dockerfile.env}"
    : "${DKZ_DIST:?DKZ_DIST is required in Dockerfile.env}"

    DKZ_SHARED_MEMORY="${DKZ_SHARED_MEMORY:-512m}"
    DKZ_RUN_RESTART="${DKZ_RUN_RESTART:-no}"

    collect_env_keys
    add_env_key "DKZ_SHARED_MEMORY"
    add_env_key "DKZ_RUN_RESTART"
}

check_docker() {
    command -v docker >/dev/null 2>&1 || die "Docker CLI is not available"
    docker info >/dev/null 2>&1 || die "Docker is not running or is not reachable"
}

image_exists() {
    docker image inspect "$DKZ_NAME" >/dev/null 2>&1
}

container_exists() {
    docker container inspect "$DKZ_NAME" >/dev/null 2>&1
}

container_running() {
    [[ "$(docker inspect -f '{{.State.Running}}' "$DKZ_NAME" 2>/dev/null || true)" == "true" ]]
}

docker_project_path() {
    if command -v cygpath >/dev/null 2>&1; then
        cygpath -am "$SCRIPT_DIR"
    else
        printf '%s\n' "$SCRIPT_DIR"
    fi
}

build_arg_args() {
    local key value

    BUILD_ARGS=()

    for key in "${ENV_KEYS[@]}"; do
        value="${!key:-}"
        BUILD_ARGS+=(--build-arg "${key}=${value}")
    done
}

runtime_env_args() {
    local key value

    RUNTIME_ENV_ARGS=()

    for key in "${ENV_KEYS[@]}"; do
        value="${!key:-}"
        RUNTIME_ENV_ARGS+=(-e "${key}=${value}")
    done

    RUNTIME_ENV_ARGS+=(-e "DKZ_PROJECT_DIR=${PROJECT_MOUNT}")
}

build_image() {
    build_arg_args

    info "Building image '${DKZ_NAME}' from ${SCRIPT_DIR}/Dockerfile..."

    (
        cd "$SCRIPT_DIR"
        DOCKER_BUILDKIT=1 docker build --no-cache -f Dockerfile -t "$DKZ_NAME" "${BUILD_ARGS[@]}" .
    )
}

run_container() {
    local project_path

    if container_exists; then
        die "Container '${DKZ_NAME}' already exists"
    fi

    runtime_env_args
    project_path="$(docker_project_path)"

    info "Creating container '${DKZ_NAME}' with ${project_path} mounted at ${PROJECT_MOUNT}..."

    MSYS_NO_PATHCONV=1 docker run -i -d \
        --name "$DKZ_NAME" \
        --hostname "$DKZ_NAME" \
        --shm-size "$DKZ_SHARED_MEMORY" \
        --restart "$DKZ_RUN_RESTART" \
        --workdir "$PROJECT_MOUNT" \
        -v "${project_path}:${PROJECT_MOUNT}:delegated" \
        "${RUNTIME_ENV_ARGS[@]}" \
        "$DKZ_NAME" >/dev/null

    info "Container '${DKZ_NAME}' is running."
}

ensure_container() {
    if container_exists; then
        return 0
    fi

    if ! image_exists; then
        build_image
    fi

    run_container
}

connect_to_container() {
    ensure_container

    if ! container_running; then
        info "Starting container '${DKZ_NAME}'..."
        docker start "$DKZ_NAME" >/dev/null
    fi

    info "Connecting to '${DKZ_NAME}'..."

    if command -v winpty >/dev/null 2>&1 && [[ -t 0 ]]; then
        winpty docker exec -it "$DKZ_NAME" bash
    else
        docker exec -it "$DKZ_NAME" bash
    fi
}

restart_container() {
    if container_exists; then
        if container_running; then
            info "Restarting container '${DKZ_NAME}'..."
            docker restart "$DKZ_NAME" >/dev/null
        else
            info "Starting container '${DKZ_NAME}'..."
            docker start "$DKZ_NAME" >/dev/null
        fi
        info "Container '${DKZ_NAME}' is running."
        return 0
    fi

    ensure_container
}

rebuild_image() {
    if container_exists; then
        info "Removing container '${DKZ_NAME}'..."
        docker rm -f "$DKZ_NAME" >/dev/null
    fi

    if image_exists; then
        info "Removing image '${DKZ_NAME}'..."
        docker image rm -f "$DKZ_NAME" >/dev/null
    fi

    build_image
    run_container
}

next_action() {
    local action

    if container_running; then
        echo -e "\n${CGREEN}Container ${DKZ_NAME} is running.${CDEF}"
    else
        echo -e "\n${CYELLOW}Container ${DKZ_NAME} exists but is stopped.${CDEF}"
    fi

    echo ""
    read -r -e -p "$(echo -e "${CYELLOW}[1]${CDEF} Exit\n${CYELLOW}[2]${CDEF} Rebuild image\n${CYELLOW}[3]${CDEF} Connect to container (bash)\n${CYELLOW}[4]${CDEF} Restart container\n> ")" action

    # Empty input (Enter) -> default to option 3
    [[ -z "$action" ]] && action="3"

    case "$action" in
        1)
            exit 0
            ;;
        2)
            rebuild_image
            next_action
            ;;
        3)
            connect_to_container
            next_action
            ;;
        4)
            restart_container
            next_action
            ;;
        *)
            next_action
            ;;
    esac
}

main() {
    case "${1:-}" in
        -h|--help)
            usage
            exit 0
            ;;
        "")
            ;;
        *)
            usage
            die "Unknown argument: $1"
            ;;
    esac

    load_env
    check_docker

    if container_exists || image_exists; then
        next_action
        exit 0
    fi

    build_image
    run_container
}

main "$@"
