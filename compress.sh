#!/bin/bash

function usage()
{
    echo "Usage: compress [--help,-h] [--stat <filename>] <filename 1> [<filename 2> ...]"
}

stat="/dev/null"

parsed_opts=$(getopt -o s: -l help,stat: -- "$@")
if [[ $? -ne 0 ]]; then usage >&2; exit 1; fi
eval "set -- $parsed_opts"
while true; do
    case "$1" in
	"--help") usage; exit 0       ;;
	"-s"|"--stat") shift; stat=$1 ;;
	--) shift; break              ;;
	*) usage >&2; exit 1          ;;
    esac
    shift
done

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

for filename in "$@"; do

    if [[ $filename == *.gz ]]; then
	continue;
    fi

    echo "gzip --force --best --keep $filename"
    gzip --force --best --keep $filename
    touch $filename

    size=$(stat -c %s "$filename")
    csize=$(stat -c %s $filename.gz)

    if [ $size -le $csize ]; then
	echo "rm $filename.gz"
	rm -f $filename.gz
	csize=$size
    else
	echo "$filename: $(ratio $size $csize) % (${size}B -> ${csize}B)" >> $stat
    fi

    total=$(($total + $size))
    ctotal=$(($ctotal + $csize))
done

echo "Total: $(ratio $total $ctotal) % (${total}B -> ${ctotal}B)" >> $stat
