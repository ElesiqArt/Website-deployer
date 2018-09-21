#!/bin/bash

function usage()
{
    echo "Usage: compress [--help,-h] [--stat <filename>] <outdir>"
}

for arg in "$@"; do
    shift
    case "$arg" in
	"--help") set -- "$@" "-h" ;;
	"--stat") set -- "$@" "-s" ;;
	*)        set -- "$@" "$arg"
    esac
done

stat="/dev/null"

OPTIND=1
while getopts "hx:c:j:s:" opt
do
  case "$opt" in
    "h") usage; exit 0  ;;
    "s") stat=${OPTARG} ;;
    "?") usage >&2; exit 1 ;;
  esac
done
shift $(expr $OPTIND - 1)

function ratio()
{
    if [[ $1 == 0 ]]; then
	echo "0"
    else
	printf "%.2f\n" "$(echo "scale=4; ($1 - $2) / $1 * 100" | bc)"
    fi
}

total=0
ctotal=0

for filename in `find . -type f -name '*' -not -name '*.gz'`; do
    echo "gzip --force --best --keep $filename"
    gzip --force --best --keep $filename

    size=$(stat -c %s "$filename")
    csize=$(stat -c %s $filename.gz)

    if [ $size -le $csize ]; then
	echo "rm $filename.gz"
	rm $filename.gz
	csize=$size
    else
	echo "$filename: $(ratio $size $csize) % (${size}B -> ${csize}B)" >> $stat
    fi

    total=$(($total + $size))
    ctotal=$(($ctotal + $csize))
done

echo "Total: $(ratio $total $ctotal) % (${total}B -> ${ctotal}B)" >> $stat
