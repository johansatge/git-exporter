<?php

if (php_sapi_name() == 'cli')
{
    $exporter = new GitExporter($argv);
    $exporter->exec();
}

class GitExporter
{

    const NAME    = 'GitExporter';
    const VERSION = '0.1.1';

    private $userInput;
    private $exportDir;
    private $exportPath;
    private $separator;

    /**
     * Inits the tool
     * @param array $argv
     */
    function __construct($argv)
    {
        $this->userInput  = $this->parseCommandLineParameters($argv);
        $this->exportDir  = !empty($this->userInput['options']['dir']) ? $this->userInput['options']['dir'] : '.export';
        $this->exportPath = rtrim(getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->exportDir;
        $this->separator  = str_repeat('-', 40);
    }

    /**
     * Executes the script
     */
    public function exec()
    {
        if (in_array('help', array_keys($this->userInput['options'])))
        {
            $this->outputHelp();
        }
        else if (in_array('version', array_keys($this->userInput['options'])))
        {
            $this->output(self::NAME . ' version ' . self::VERSION);
        }
        else if ($this->userInput['command'] == 'diff')
        {
            $this->makeDiff();
        }
        else
        {
            $this->output('Nothing to do. See "--help".');
        }
    }

    /**
     * Outputs help message
     */
    private function outputHelp()
    {
        $this->output('usage:' . "\t" . 'gitexporter [--version] [--dir=<name>] <command> [<args>]');
        $this->output(' ');
        $this->output('The available commands are:');
        $this->output(' diff' . "\t" . 'Exports a diff between two commits');
        $this->output("\t" . 'and creates a changelog file including modified and deleted files');
    }

    /**
     * Builds the export folder
     * @return boolean
     */
    private function makeDiff()
    {
        // Checks parameters and repository status
        $since = !empty($this->userInput['params'][0]) ? $this->userInput['params'][0] : '';
        $until = !empty($this->userInput['params'][1]) ? $this->userInput['params'][1] : '';
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
        $commits = $this->executeCommand('git log ' . $since . '..' . $until . ' -m --pretty=format:%H');
        if ($commits['error'] !== false)
        {
            $this->output('An error occurred when getting the diff.');
            $this->output('(Error: ' . $commits['error'] . ')');
            return false;
        }

        // Gets the modified and deleted files lists for each commit found
        $this->output('Generating diff from "' . substr($since, 0, 6) . '" to "' . substr($until, 0, 6) . '"...');
        $modified_files = array();
        $deleted_files  = array();
        foreach ($commits['result'] as $commit)
        {
            $files = $this->executeCommand('git show ' . $commit . ' --name-status --pretty=format:');
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
        $modified_files_count = count($modified_files);
        $deleted_files_count  = count($deleted_files);
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
            $this->makeDirTreeForFile($file);
            $this->executeCommand('git show ' . $until . ':' . $file . ' > ' . $this->exportPath . DIRECTORY_SEPARATOR . $file);
            $line = 'Exporting file ' . ($index + 1) . ' of ' . $modified_files_count . ' (' . intval(($index + 1) * (100 / $modified_files_count)) . '%): ' . $file;
            $this->output($line . ($line_length - strlen($line) > 0 ? str_repeat(' ', $line_length - strlen($line)) : ''), false);
            $line_length = strlen($line);
        }
        $this->output(str_repeat(' ', $line_length), false);

        // Generates the changelog
        $changelog = array(
            'Diff from "' . $since . '" to "' . $until . '"',
            $this->separator,
            count($commits['result']) . ' commits',
            $modified_files_count . ' modified file(s)',
            $deleted_files_count . ' deleted file(s)',
            $this->separator,
            'Modified files:',
            implode("\r\n", $modified_files),
            $this->separator,
            'Deleted files:',
            implode("\r\n", $deleted_files)
        );
        file_put_contents($this->exportPath . DIRECTORY_SEPARATOR . '_changelog.txt', implode("\r\n", $changelog));

        $this->output('Export done. ' . count($commits['result']) . ' commits found, ' . $modified_files_count . ' modified file(s) and ' . $deleted_files_count . ' deleted file(s).');
        return true;
    }

    /**
     * Checks if the directory tree exists for the given file, or creates it
     * @param $file
     */
    private function makeDirTreeForFile($file)
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
                    if (!is_dir($dir))
                    {
                        $this->executeCommand('mkdir ' . $dir);
                    }
                }
            }
        }
    }

    /**
     * Asks permission to delete the export directory, if needed
     * @return boolean
     */
    private function checkExportDirectory()
    {
        if (is_readable($this->exportPath))
        {
            $this->output('The "' . $this->exportDir . '" directory exists. Do you want to remove it ? (y/n)');
            $user_input                 = fopen('php://stdin', 'r');
            $confirm_directory_deletion = strtolower(trim(fgets($user_input)));
            if (!in_array($confirm_directory_deletion, array('y', 'yes')))
            {
                $this->output('Aborted. Please delete the "' . $this->exportDir . '" directory before processing again.');
                return false;
            }
            $this->executeCommand('rm -rf ' . $this->exportPath);
            if (is_readable($this->exportPath))
            {
                $this->output('Aborted. The "' . $this->exportDir . '" directory could not be deleted.');
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
     * @param array $raw_user_input
     * @return array
     */
    private function parseCommandLineParameters($raw_user_input)
    {
        array_shift($raw_user_input);
        $user_input = array('command' => '', 'params' => array(), 'options' => array());
        foreach ($raw_user_input as $value)
        {
            if (strpos($value, '--') === 0)
            {
                $value                          = explode('=', str_replace('--', '', $value));
                $option                         = array_shift($value);
                $user_input['options'][$option] = implode('=', $value);
            }
            else if (empty($user_input['command']))
            {
                $user_input['command'] = $value;
            }
            else
            {
                $user_input['params'][] = $value;
            }
        }
        return $user_input;
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