<?php

/*
 * Loosely based on:
 * https://github.com/wasinger/simplelogger/blob/master/Wa72/SimpleLogger/FileLogger.php
 * https://github.com/wasinger/simplelogger/blob/master/Wa72/SimpleLogger/AbstractSimpleLogger.php
 */

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

abstract class myAbstractLogger extends AbstractLogger
{
    protected $levels = [
	    LogLevel::DEBUG, LogLevel::INFO, LogLevel::NOTICE,
	    LogLevel::WARNING, LogLevel::ERROR, LogLevel::CRITICAL,
	    LogLevel::ALERT, LogLevel::EMERGENCY
	];
    protected $min_level = LogLevel::DEBUG;

    protected function min_level_reached($level) {
        return \array_search($level, $this->levels) >= \array_search($this->min_level, $this->levels);
    }

    protected function interpolate($message, array $context) {
        if (false === strpos($message, '{')) {
            return $message;
        }

        $replacements = array();
        foreach ($context as $key => $val) {
            if (null === $val || is_scalar($val) || (\is_object($val) && method_exists($val, '__toString'))) {
                $replacements["{{$key}}"] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
            } elseif (\is_object($val)) {
                $replacements["{{$key}}"] = '[object '.\get_class($val).']';
            } else { $replacements["{{$key}}"] = '['.\gettype($val).']'; }
        }

        return strtr($message, $replacements);
    }

    protected function format($level, $message, $context, $timestamp = null) {
        if ($timestamp === null) { $timestamp = date('Y-m-d H:i:s'); }
        return '[' . $timestamp . '] ' . strtoupper($level) . ': ' . $this->interpolate($message, $context) . "\n";
    }
}

class myLogger extends myAbstractLogger
{
    protected $logfile;

    public function __construct($logfile, $min_level = LogLevel::DEBUG) {
	if (strncmp($logfile, 'php://std', 9) !== 0) {
	    if (! file_exists($logfile)) {
		if (! touch($logfile)) {
		    throw new \InvalidArgumentException('Log file ' . $logfile . ' cannot be created');
		}
	    }
	    if (!is_writable($logfile)) {
		//throw new \InvalidArgumentException('Log file ' . $logfile . ' is not writeable');
		throw new \InvalidArgumentException('Log file ' . $logfile . ' is not writeable' .
		 'strncmp('.$logfile.', php://std, 9) returned: '. strncmp($logfile, 'php://std', 9));
	    }
	}
        $this->logfile = $logfile;
        $this->min_level = $min_level;
    }

    public function log($level, $message, array $context = array()) {
        if (!$this->min_level_reached($level)) { return; }
        $logline = $this->format($level, $message, $context);
	if (strncmp($this->logfile, 'php://std', 9) !== 0 && strncmp($this->logfile, '/dev/null', 9) !== 0) {
	    file_put_contents($this->logfile, $logline, FILE_APPEND | LOCK_EX);
	} else { file_put_contents($this->logfile, $logline); }
    }
}
