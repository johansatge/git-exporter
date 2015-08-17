![Git Exporter](logo.png)

A PHP CLI tool designed to export commits from a Git repository.

---

* [Installation](#installation)
* [Usage](#usage)
* [Changelog](#changelog)
* [License](#license)

## Installation

Checkout the project or download the tool directly from Github:

```bash
curl https://raw.githubusercontent.com/johansatge/git-exporter/v0.2.0/GitExporter.php > gitexporter
chmod +x gitexporter
sudo mv gitexporter /usr/local/bin
```

## Usage

### Check the installation

```bash
gitexporter --version
> GitExporter version 0.2.0
```

### Export the changes between two commits

```bash
cd /path/to/some-git-repository
gitexporter diff 07840746ad77cbbfc580413eea46de19387f7ef0 HEAD
```

This will export all modified files between `0784074` (non-inclusive) and `HEAD` (inclusive) in a `.export` directory, in the root of the project.

The directory will also contain a `_changelog.txt` file with the list of the modified and deleted files.

### Specify an output directory

```bash
cd /path/to/some-git-repository
gitexporter diff 07840746ad77cbbfc580413eea46de19387f7ef0 HEAD --dir=some-export-dir
```

This will create the `some-export-dir` directory in the root of the Git project.

## Changelog

| Version | Date | Notes |
| --- | --- | --- |
| `0.2.0` | January 04th, 2015 | Adds a shebang and make installation more simple |
| `0.1.1` | July 25th, 2014 | Refactors calls to count(), adds the makeDirTreeForFile() function |
| `0.1` | January 21th, 2014 | Initial version |

## License

This project is released under the [MIT License](LICENSE).
