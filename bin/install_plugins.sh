#!/bin/sh

for plugin in plugins/*; do
    install="${plugin}/bin/install.sh"
    if [ -x "${install}" ]; then
        ( # subshell, to clear options/environment
            set -x
            "${install}"
        )
    fi
done
