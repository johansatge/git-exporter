Git Exporter
============

## Presentation

GitExporter is a PHP CLI tool designed to export data from a Git repository.

## Installation & usage

Create an alias to the tool in your `.bashrc` file.

    alias gitexporter="php '/Users/jsatge/www/git-exporter/GitExporter.php'"

Navigate to a Git repository:

    cd /Users/jsatge/www/sample-git-repository

Use the script to export the diff between two commits:

    gitexporter diff 07840746ad77cbbfc580413eea46de19387f7ef0 9531fa7357d4f478f9d3fe9758d8985e0e7a45cb

The first hash is the *start* commit and will not be included in the diff.

Exported files will be stored in a `.export` directory in the Git repository.

You may specify your own export directory by using the `--dir` option:

    gitexporter diff 07840746ad77cbbfc580413eea46de19387f7ef0 HEAD --dir=my-export-dir

## Changelog

### 0.1

Initial version