# Website deployer

## `minify.sh`

The `minify.sh` script applies minification commands to every html, css and javascript files passed as parameters.

Default commands are:

* `htmlmin --remove-comments --remove-empty-space --keep-pre-attr` for html files;
* `yui-compressor --type=css` for css files; and
* `uglifyjs --mangle --compress --` for javascript files.

They can be changed with options `--html <cmd>`, `--css <cmd>` and `--js <cmd>`.

Minified files are first stored in a temporary file and then renamed as the original file. The default file temporary extension is `.tmp` and can be changed with the `--tmp-ext <extension>` option. Note that `<extension>` do not need a leading `.`.

Please note that the files will be modified in the executing folder.

If the `--stat <filename>` argument is supplied, compression statistics will be appended to `<filename>`.
The `--dry-run` option displays the commands the script would have run.

## `compress.sh`

The `compress.sh` script compresses each file passed on the command line.
If the compressed file is bigger than the original file, it is removed.

If the `--stat <filename>` argument is supplied, compression statistics will be appended to `<filename>`.

## `ssl.sh`

Fetch an SSL certificate from (Let's Encrypt)[https://letsencrypt.org/] by using Nginx and (Certbot)[https://certbot.eff.org/] for every domain passed as an argument.
