#!/bin/bash

usage()
{
    echo "Usage: minify [--help,-h] [--dry-run,-d] [--html <cmd>] [--css <cmd>] [--js <cmd>] [--tmp-ext <extension>] [--stat <filename>] [<filename 1> <filename 2> ...]"
}

for arg in "$@"; do
    shift
    case "$arg" in
	"--help")    set -- "$@" "-h" ;;
	"--dry-run") set -- "$@" "-d" ;;
	"--html")    set -- "$@" "-x" ;;
	"--css")     set -- "$@" "-c" ;;
	"--js")      set -- "$@" "-j" ;;
	"--tmp-ext") set -- "$@" "-e" ;;
	"--stat")    set -- "$@" "-s" ;;
	*)           set -- "$@" "$arg"
    esac
done

dry_run=0
html="htmlmin --remove-comments --remove-empty-space --keep-pre-attr"
css="yui-compressor --type=css"
js="uglifyjs --mangle --compress --"
tmp_ext="tmp"
stat="/dev/null"

OPTIND=1
while getopts "hdx:c:j:e:s:" opt
do
    case "$opt" in
	"h") usage; exit 0     ;;
	"d") dry_run=1         ;;
	"x") html=${OPTARG}    ;;
	"c") css=${OPTARG}     ;;
	"j") js=${OPTARG}      ;;
	"e") tmp_ext=${OPTARG} ;;
	"s") stat=${OPTARG}    ;;
	"?") usage >&2; exit 1 ;;
    esac
done
shift $(expr $OPTIND - 1)

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
	*)      cmd="cat" ;;
    esac

    echo "$cmd $filename > $filename.$tmp_ext"

    if [[ $dry_run == 0 ]]; then
	$cmd $filename > $filename"."$tmp_ext

	if [ $? -ne 0 ]; then
	    echo "cat $filename > $filename.$tmp_ext"
	    less $filename > $filename"."$tmp_ext
	fi

	size=$(stat -c %s "$filename")
	msize=$(stat -c %s "$filename.$tmp_ext")

	if [[ $msize < $size ]]; then
	    echo "$filename: $(ratio $size $msize) % (${size}B -> ${msize}B)" >> $stat
	fi

	total=$(($total + $size))
	mtotal=$(($mtotal + $msize))

	mv $filename"."$tmp_ext $filename
    fi

done

echo "Total: $(ratio $total $mtotal) % (${total}B -> ${mtotal}B)" >> $stat
