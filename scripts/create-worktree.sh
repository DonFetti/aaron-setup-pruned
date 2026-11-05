#!/usr/bin/env bash
# Usage: ./scripts/create-worktree.sh <name>
# Uses an existing branch named <name>, or creates agent/<name> from HEAD.
# Copies ignored media from the main checkout.
set -euo pipefail

if [[ $# != 1 ]]; then
  echo "Usage: $0 <name>" >&2
  exit 1
fi

name=$1
if [[ ! $name =~ ^[a-zA-Z0-9][a-zA-Z0-9_-]*$ ]]; then
  echo "Name must contain only letters, numbers, underscores, and hyphens." >&2
  exit 1
fi

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
repo=$(git -C "$script_dir" rev-parse --show-toplevel)
# Always source media from the main checkout, even when invoked in a worktree.
common_dir=$(git -C "$repo" rev-parse --path-format=absolute --git-common-dir)
source_repo=$(dirname -- "$common_dir")
target="$(dirname -- "$source_repo")/aaron-worktrees/$name"
branch="agent/$name"

if [[ -e $target ]]; then
  echo "Destination already exists: $target" >&2
  exit 1
fi
mkdir -p -- "$(dirname -- "$target")"
if git -C "$repo" show-ref --verify --quiet "refs/heads/$name"; then
  branch=$name
  git -C "$repo" worktree add "$target" "$branch"
elif git -C "$repo" show-ref --verify --quiet "refs/heads/$branch"; then
  git -C "$repo" worktree add "$target" "$branch"
else
  git -C "$repo" worktree add -b "$branch" "$target" HEAD
fi

# NUL-delimited paths preserve spaces in filenames. Copy only ignored media;
# tracked gallery JSON/HTML belongs to the new branch. Never copy credentials.
count=0
while IFS= read -r -d '' relative; do
  case "${relative,,}" in
    *.jpg|*.jpeg|*.png|*.gif|*.svg|*.ico|*.webp|*.mp4|*.mp3|*.wav|*.ogg|*.flac|*.aac|*.heic)
      case "$relative" in
        node_modules/*|.git/*|.vscode/*|.cursor/*|.agents/*|.codex/*|.aws/*) continue ;;
      esac
      # Do not overwrite a path that is tracked on the target branch.
      if git -C "$target" ls-files --error-unmatch -- "$relative" >/dev/null 2>&1; then
        continue
      fi
      mkdir -p -- "$(dirname -- "$target/$relative")"
      cp --reflink=auto -- "$source_repo/$relative" "$target/$relative"
      count=$((count + 1))
      ;;
  esac
done < <(git -C "$source_repo" ls-files --others --ignored --exclude-standard -z)

echo "Copied $count ignored media files."
printf '\nWorktree ready: %s\nBranch: %s\n\nOpen it with:\n  cd %q\n' \
  "$target" "$branch" "$target"
