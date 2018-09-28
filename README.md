# Website deployer

## `compress.sh`

The `compress.sh` script compresses each file on the command line if
* its extension is not `.gz`; and
* the compressed file is smaller than the original file.

It can simply be invoked as :
```bash
bash compress.sh `find -type f -not -name '*.php'`
```

If the `--stat <filename>` argument is supplied, compression statistics will be appended to `<filename>`.