<?php

// detect debug
if (!defined('__DEBUG__')) {
    define('__DEBUG__', true);
}

// handle exception
set_exception_handler(function (Exception $e) {
    le_console('error', ((string) $e));
    exit(1);
});

/**
 * print string to console
 * 
 * @param string $type 
 * @param string $str 
 */
function le_console($type, $str) {
    $colors = [
        'info'      =>  '33',
        'done'      =>  '32',
        'error'     =>  '31',
        'debug'     =>  '36'
    ];

    if (!__DEBUG__ && $type == 'debug') {
        return;
    }

    $color = isset($colors[$type]) ? $colors[$type] : '37';

    $args = array_slice(func_get_args(), 2);
    array_unshift($args, $str);
    $str = call_user_func_array('sprintf', $args);

    echo "\033[{$color};1m[" . $type . "]\t\033[37;0m {$str}\n";
}

/**
 * trigger a fatal error 
 * 
 * @param string $str
 * @throws Exception
 */
function le_fatal($str) {
    $args = func_get_args();
    throw new Exception(call_user_func_array('sprintf', $args));
}

// init global vars
global $workflow, $context;
$workflow = [];
$context = new stdClass();

/**
 * get current caller file 
 * 
 * @return string
 */
function le_get_current_namespace() {
    static $file;

    $traces = debug_backtrace(!DEBUG_BACKTRACE_IGNORE_ARGS & !DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    $current = array_pop($traces);

    $file = isset($current['file']) ? $current['file'] : $file;

    return pathinfo($file, PATHINFO_FILENAME);
}

/**
 * get a dir recursive iterator  
 * 
 * @param string $dir 
 * @return RecursiveIteratorIterator
 */
function le_get_all_files($dir) {
    return !is_dir($dir) ? [] : new RecursiveIteratorIterator(new LEIgnorantRecursiveDirectoryIterator($dir,
        FilesystemIterator::KEY_AS_FILENAME
        | FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS));
}

class LEIgnorantRecursiveDirectoryIterator extends RecursiveDirectoryIterator { 
    function getChildren() { 
        try { 
            return new LEIgnorantRecursiveDirectoryIterator($this->getPathname(), FilesystemIterator::KEY_AS_FILENAME
                | FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS); 
        } catch(UnexpectedValueException $e) { 
            return new RecursiveArrayIterator(array()); 
        } 
    } 
}

/**
 * add workflow  
 * 
 * @param string $name 
 * @param mixed $func 
 */
function le_add_workflow($name, $func) {
    global $workflow;

    $ns = le_get_current_namespace();
    $workflow[$ns . '.' . $name] = $func;
}

/**
 * @param $name
 * @return mixed
 */
function le_do_workflow($name) {
    global $workflow, $context;
    
    $args = func_get_args();
    array_shift($args);

    $parts = explode('.', $name, 2);
    if (2 == count($parts)) {
        list ($ns) = $parts;
    } else {
        $ns = le_get_current_namespace();
        $name = $ns . '.' . $name;
    }

    require_once __DIR__ . '/../workflow/' . $ns . '.php';

    if (!isset($workflow[$name])) {
        le_fatal('can not find workflow "%s"', $name);
    }

    $desc = implode(', ', array_map(function ($arg) {
        return is_string($arg) ? mb_strimwidth(
            str_replace(["\r", "\n"], '', $arg)
            , 0, 10, '...', 'UTF-8') : '...';
    }, $args));

    le_console('debug', '%s%s', $name, empty($desc) ? '' : ': ' . $desc);
    return call_user_func_array($workflow[$name], $args);
}

