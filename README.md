Git Exporter
============

## Presentation

GitExporter is a PHP CLI tool designed to export data from a Git repository.

![Screen capture](https://raw.github.com/johansatge/git-exporter/master/assets/css/images/screenshot.png)

## Installation

Create an alias to the tool in your `.bashrc` file.

    alias gitexporter="php '/Users/jsatge/www/git-exporter/classes/GitExporter.php'"

## Usage

Navigate to a Git repository:

    cd /Users/jsatge/www/sample-git-repository

Execute the script to export the diff between two commits:

    gitexporter diff 07840746ad77cbbfc580413eea46de19387f7ef0 9531fa7357d4f478f9d3fe9758d8985e0e7a45cb

Exported files are stored in a `.export` directory in the Git repository.

A `_changelog.txt` file will also be created in the `.export` directory, with a list of the modified and deleted files between the two commits.

## Options

You may specify your own export directory by using the `--dir` option:

    gitexporter diff 07840746ad77cbbfc580413eea46de19387f7ef0 HEAD --dir=my-export-dir

## Changelog

### 0.1.1

Refactors calls to `count()`, adds the `makeDirTreeForFile` function

### 0.1

Initial version