# Website deployer


## `minify.sh`

The `minify.sh` script applies minification commands to every html, css and javascript files found in the current directory.

Default commands are:

* `htmlmin --remove-comments --remove-empty-space --keep-pre-attr` for html files;
* `yui-compressor --type=css` for css files; and
* `uglifyjs --mangle --compress --` for javascript files.

They can be changed with options `--html <cmd>`, `--css <cmd>` and `--js <cmd>`.

Minified files are generated in the output location specified as the unique command argument. They are first stored in a temporary file and the renamed as the original file. The default file temporary extension is `.tmp` and can be changed with the `--tmp-ext <extension>` option. Note that `<extension>` do not need a leading `.`. Temporary files are excluded from the search and will not be processed.
Note that when the output directory is the same as the input directory, the content of a file will be replaced by its minified version.

If the `--stat <filename>` argument is supplied, compression statistics will be appended to `<filename>`.
The `--dry-run` option displays the commands the script would have run.

## `compress.sh`

The `compress.sh` script compresses each file on the command line if
* its extension is not `.gz`; and
* the compressed file is smaller than the original file.

It can simply be invoked as :
```bash
bash compress.sh `find -type f -not -name '*.php'`
```

If the `--stat <filename>` argument is supplied, compression statistics will be appended to `<filename>`.