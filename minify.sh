#!/bin/bash

usage()
{
    echo "Usage: minify [--help,-h] [--dry-run,-d] [--html <cmd>] [--css <cmd>] [--js <cmd>] [--tmp-ext <extension>] [--stat <filename>] <filename 1> [<filename 2> ...]"
}

dry_run=0
html="htmlmin --remove-comments --remove-empty-space --keep-pre-attr"
css="yui-compressor --type=css"
js="uglifyjs --mangle --compress --"
tmp_ext="tmp"
stat="/dev/null"

parsed_opts=$(getopt -o dh:c:j:e:s: -l help,dry-run,html:,css:,js:,ext:,stat: -- "$@")
if [[ $? -ne 0 ]]; then usage >&2; exit 1; fi
eval "set -- $parsed_opts"
while true; do
    case "$1" in
	"--help") usage; exit 0         ;;
	"-d"|"--dry-run") dry_run=1     ;;
	"-h"|"--html") shift; html=$1   ;;
	"-c"|"--css") shift; css=$1     ;;
	"-j"|"--js") shift; js=$1       ;;
	"-e"|"--ext") shift; tmp_ext=$1 ;;
	"-s"|"--stat") shift; stat=$1   ;;
	--) shift; break                ;;
	*) usage >&2; exit 1            ;;
    esac
    shift
done

total=0
mtotal=0

function ratio()
{
    if [[ $1 == 0 ]]; then
	echo "0"
    else
	printf "%.2f\n" "$(echo "scale=4; ($1 - $2) / $1 * 100" | bc)"
    fi
}

for filename in "$@"; do

    if [[ $filename == *.$tmp_ext ]]; then
       continue
    fi

    case "$filename" in
	*.html) cmd=$html ;;
	*.css)  cmd=$css  ;;
	*.js)   cmd=$js   ;;
	*)      continue  ;;
    esac

    echo "$cmd $filename"

    if [[ $dry_run == 1 ]]; then
	continue
    fi

    $cmd $filename > $filename"."$tmp_ext

    if [ $? -ne 0 ]; then
	rm -f $filename > $filename"."$tmp_ext
    fi

    size=$(stat -c %s "$filename")
    msize=$(stat -c %s "$filename.$tmp_ext")

    if [[ $msize < $size ]]; then
	echo "$filename: $(ratio $size $msize) % (${size}B -> ${msize}B)" >> $stat
    fi

    total=$(($total + $size))
    mtotal=$(($mtotal + $msize))

    mv $filename"."$tmp_ext $filename

done

echo "Total: $(ratio $total $mtotal) % (${total}B -> ${mtotal}B)" >> $stat
