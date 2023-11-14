<?php

namespace Antsstyle\NFTCryptoBlocker\Core;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Formatter\LineFormatter;

class LogManager {

    public static $cronLogger;
    public static $webLogger;

    public static function getLogger($channel) {
        $logger = new Logger($channel);
        $processor = new IntrospectionProcessor();
        $logger->pushProcessor($processor);
        $debugHandler = new StreamHandler('logs/debug.log', Logger::DEBUG);
        $errorHandler = new StreamHandler('logs/error.log', Logger::ERROR);
        $formatter = new LineFormatter("%datetime%: %channel%: %level_name%: %message% %context% %extra%\n", "Y-m-d H:i:s", true, true);
        $debugHandler->setFormatter($formatter);
        $errorHandler->setFormatter($formatter);
        $logger->pushHandler($debugHandler);
        $logger->pushHandler($errorHandler);
        return $logger;
    }

    public static function initialiseCronLogger() {
        self::$cronLogger = new Logger('cronjobslogger');
        $processor = new IntrospectionProcessor();
        self::$cronLogger->pushProcessor($processor);
        $debugHandler = new StreamHandler('logs/cronjobs.debug.log', Logger::DEBUG);
        $errorHandler = new StreamHandler('logs/cronjobs.error.log', Logger::ERROR);
        $formatter = new LineFormatter("%datetime%: %channel%: %level_name%: %message% %context% %extra%\n", "Y-m-d H:i:s", true, true);
        $debugHandler->setFormatter($formatter);
        $errorHandler->setFormatter($formatter);
        self::$cronLogger->pushHandler($debugHandler);
        self::$cronLogger->pushHandler($errorHandler);
    }

    public static function initialiseWebLogger() {
        self::$webLogger = new Logger('weblogger');
        $processor = new IntrospectionProcessor();
        self::$webLogger->pushProcessor($processor);
        $debugHandler = new StreamHandler('logs/web.debug.log', Logger::DEBUG);
        $errorHandler = new StreamHandler('logs/web.error.log', Logger::ERROR);
        $formatter = new LineFormatter("%datetime%: %channel%: %level_name%: %message% %context% %extra%\n", "Y-m-d H:i:s", true, true);
        $debugHandler->setFormatter($formatter);
        $errorHandler->setFormatter($formatter);
        self::$webLogger->pushHandler($debugHandler);
        self::$webLogger->pushHandler($errorHandler);
    }

}

LogManager::initialiseCronLogger();
LogManager::initialiseWebLogger();
