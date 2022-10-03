#!/usr/bin/env bash

set -e
set -x

CURRENT_BRANCH="main"

function split()
{
    SHA1=$(splitsh-lite --prefix="$1")
    git push "$2" "$SHA1:refs/heads/$CURRENT_BRANCH" -f
}

function remote()
{
    git remote add "$1" "$2" || true
}

git pull origin $CURRENT_BRANCH

remote core git@github.com:koansu-php/core.git
remote dependency-injection git@github.com:koansu-php/dependency-injection.git
remote testing git@github.com:koansu-php/testing.git

split 'src/Core' core
split 'src/DependencyInjection' dependency-injection
split 'src/Testing' testing

