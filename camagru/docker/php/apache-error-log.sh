#!/bin/sh
# Omit only normal lifecycle announcements; forward every other line immediately.
while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
        *"[mpm_prefork:notice]"*" AH00163:"*|*"[core:notice]"*" AH00094:"*|*"[mpm_prefork:notice]"*" AH00169:"*) ;;
        *) printf '%s\n' "$line" >&2 ;;
    esac
done
