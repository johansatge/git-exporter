<?php

if (php_sapi_name() != 'cli')
{
    $this->leave('This script needs PHP to be run in CLI.');
}

$exporter = new GitExporter($argv);
$exporter->exec();

class GitExporter
{

    const NAME       = 'GitExporter';
    const VERSION    = '1.0';
    const EXPORT_DIR = '_export';

    private $params;
    private $exportPath;
    private $separator;

    /**
     * Inits the tool
     * @param array $argv
     */
    function __construct($argv)
    {
        $this->params     = $this->parseCommandLineParameters($argv);
        $this->exportPath = rtrim(getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::EXPORT_DIR;
        $this->separator  = str_repeat('-', 40);
    }

    /**
     * Generates the diff
     */
    public function exec()
    {
        $this->output();
        if ($this->params['action'] == 'help')
        {
            $this->outputHelp();
        }
        else if ($this->params['action'] == 'version')
        {
            $this->outputVersion();
        }
        else if ($this->params['action'] == 'diff')
        {
            $this->makeDiff($this->params['options']);
        }
        else
        {
            $this->output('Nothing to do. See "--help".');
        }
        $this->output();
    }

    /**
     * Outputs help message
     */
    private function outputHelp()
    {
        echo '@todo write this';
    }

    /**
     * Outputs version
     */
    private function outputVersion()
    {
        $this->output(self::NAME . ' version ' . self::VERSION);
    }

    /**
     * Builds the export folder
     * @todo do not count() $modified_files and $commits['result'] multiple times
     * @param array $options
     * @return boolean
     */
    private function makeDiff($options)
    {
        // Checks parameters and repository status
        $since = !empty($options[0]) ? $options[0] : '';
        $until = !empty($options[1]) ? $options[1] : '';
        if (!$this->isRepository())
        {
            $this->output('No git repository found. See "--help".');
            return false;
        }
        if (empty($since) || empty($until))
        {
            $this->output('Missing ' . (empty($since) ? '<since>' : '<until>') . ' parameter. See "--help".');
            return false;
        }

        // Gets the commits list
        // @todo test this when using a tag name or the "HEAD" reference
        $commits = $this->executeCommand('git log ' . $since . '..' . $until . ' -m --pretty=format:%H', true);
        if ($commits['error'] !== false)
        {
            $this->output('An error occurred when getting the diff. (Error: ' . $commits['error'] . ')');
            return false;
        }

        // Gets the modified and deleted files lists for each commit found
        $this->output('Generating diff from "' . substr($since, 0, 6) . '" to "' . substr($until, 0, 6) . '"...');
        $modified_files = array();
        $deleted_files  = array();
        foreach ($commits['result'] as $commit)
        {
            $files = $this->executeCommand('git show ' . $commit . ' --name-status --pretty=format:', true);
            foreach ($files['result'] as $file)
            {
                $file_data = explode("\t", $file);
                $type      = !empty($file_data[0]) && preg_match('/^[AMD]{1}$/', $file_data[0]) ? $file_data[0] : false;
                $path      = !empty($file_data[1]) ? $file_data[1] : false;
                if (!empty($type) && !empty($path) && $type == 'D' && (!in_array($path, $deleted_files) && !in_array($path, $modified_files)))
                {
                    $deleted_files[] = $path;
                }
                if (!empty($type) && !empty($path) && ($type == 'M' || $type == 'A') && (!in_array($path, $deleted_files) && !in_array($path, $modified_files)))
                {
                    $modified_files[] = $path;
                }
            }
        }
        $this->output();

        // If the export directory exists, asks for deletion
        if (!$this->checkExportDirectory())
        {
            return false;
        }

        // Generates the files
        mkdir($this->exportPath);
        $line_length = 0;
        foreach ($modified_files as $index => $file)
        {
            $fileinfo = pathinfo($file);
            if (!is_dir($this->exportPath . DIRECTORY_SEPARATOR . $fileinfo['dirname']))
            {
                $subdirs = explode('/', $fileinfo['dirname']);
                $dir     = $this->exportPath . DIRECTORY_SEPARATOR;
                if (count($subdirs) > 0)
                {
                    foreach ($subdirs as $subdir)
                    {
                        $dir .= $subdir . DIRECTORY_SEPARATOR;
                        $this->executeCommand('mkdir ' . $dir);
                    }
                }
            }
            $this->executeCommand('git show ' . $until . ':' . $file . ' > ' . $this->exportPath . DIRECTORY_SEPARATOR . $file);
            $line = 'Exporting file ' . ($index + 1) . ' of ' . count($modified_files) . ' (' . intval(($index + 1) * (100 / count($modified_files))) . '%): ' . $file;
            $this->output($line . ($line_length - strlen($line) > 0 ? str_repeat(' ', $line_length - strlen($line)) : ''), false);
            $line_length = strlen($line);
        }
        $this->output(str_repeat(' ', $line_length), false);

        // Generates the changelog
        $changelog = array(
            'Diff from "' . $since . '" to "' . $until . '"',
            $this->separator,
            count($commits['result']) . ' commits',
            count($modified_files) . ' modified file(s)',
            count($deleted_files) . ' deleted file(s)',
            $this->separator,
            'Modified files:',
            implode("\r\n", $modified_files),
            $this->separator,
            'Deleted files:',
            implode("\r\n", $deleted_files)
        );
        file_put_contents($this->exportPath . DIRECTORY_SEPARATOR . '_changelog.txt', implode("\r\n", $changelog));

        $this->output('Export done. ' . count($commits['result']) . ' commits found, ' . count($modified_files) . ' modified file(s) and ' . count($deleted_files) . ' deleted file(s).');
        return true;
    }

    /**
     * Asks permission to delete the export directory, if needed
     * @return boolean
     */
    private function checkExportDirectory()
    {
        if (is_readable($this->exportPath))
        {
            $this->output('The "' . self::EXPORT_DIR . '" directory exists. Do you want to remove it ? (y/n)');
            $user_input                 = fopen('php://stdin', 'r');
            $confirm_directory_deletion = strtolower(trim(fgets($user_input)));
            if (!in_array($confirm_directory_deletion, array('y', 'yes')))
            {
                $this->output('Aborted. Please delete the "' . self::EXPORT_DIR . '" directory before processing again.');
                return false;
            }
            $this->executeCommand('rm -rf ' . $this->exportPath);
            if (is_readable($this->exportPath))
            {
                $this->output('Aborted. The "' . self::EXPORT_DIR . '" directory could not be deleted.');
                return false;
            }
            else
            {
                $this->output('Directory deleted: "' . $this->exportPath . '"');
                $this->output();
            }
        }
        return true;
    }

    /**
     * Parses command line parameters
     * @param array $raw_params
     * @return array
     */
    private function parseCommandLineParameters($raw_params)
    {
        array_shift($raw_params);
        $params = array('action' => '', 'options' => array());
        if (count($raw_params) > 0)
        {
            $params['action'] = str_replace('--', '', $raw_params[0]);
            array_shift($raw_params);
            $params['options'] = $raw_params;
        }
        return $params;
    }

    /**
     * Checks if the current directory is a git repository
     * @return boolean
     */
    private function isRepository()
    {
        $status = $this->executeCommand('git status');
        return $status['error'] === false;
    }

    /**
     * Executes a shell command and returns the result (or the error message if needed)
     * @param string $command
     * @return array
     */
    private function executeCommand($command)
    {
        exec($command . ' 2>&1', $output);
        $string_output = implode("\r\n", $output);
        return array(
            'error'  => strpos($string_output, 'fatal:') !== false ? (!empty($output[0]) ? str_replace('fatal: ', '', $output[0]) : '') : false,
            'result' => $output
        );
    }

    /**
     * Outputs a message
     * @param string $message
     * @param boolean $new_line
     */
    private function output($message = '', $new_line = true)
    {
        echo !empty($message) ? $message : $this->separator;
        echo $new_line ? "\r\n" : "\r";
    }

}