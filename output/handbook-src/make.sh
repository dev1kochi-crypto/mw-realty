#!/bin/bash
# make.sh <guide-basename> <kicker> <running header> <footer label>
S=/c/Users/User/AppData/Local/Temp/claude/c--Laravel-mw-realty/e2285a8f-4858-45a7-83e5-c35fa6db4434/scratchpad
W='C:/Users/User/AppData/Local/Temp/claude/c--Laravel-mw-realty/e2285a8f-4858-45a7-83e5-c35fa6db4434/scratchpad'
cd $S
MERMAID_SRC="file:///$W/render/mermaid.min.js" node build.mjs "guides/$1.md" "html/$1.html" "$2" "$3" >/dev/null || exit 1
HDR="<div style=\"width:100%;font-family:'DejaVu Sans',Arial,sans-serif;font-size:7pt;font-weight:700;letter-spacing:.12em;color:#7b8794;padding:0 17mm;text-transform:uppercase\">$3</div>"
FTR="<div style=\"width:100%;font-family:'DejaVu Sans',Arial,sans-serif;font-size:7pt;color:#7b8794;padding:0 17mm;display:flex;justify-content:space-between\"><span>$4</span><span>Page <span class=pageNumber></span> of <span class=totalPages></span></span></div>"
node cdp.mjs "file:///$W/html/$1.html" "$W/pdf/$1.pdf" pdf "$HDR" "$FTR"
