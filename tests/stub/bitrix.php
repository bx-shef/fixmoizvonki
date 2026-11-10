<?php

// Заглушки ядра для проверки установщика. Таблица подписок b_module_to_module держится в памяти:
// ключ строится из тех же полей, по которым ядро ищет строку и при регистрации, и при снятии
// (источник, событие, модуль-получатель, класс, метод). Версия обработчика в ключ не входит,
// как и в SQL ядра (EventManager::unRegisterEventHandler).

namespace Bitrix\Main {
    class EventManager
    {
        public static array $table = [];
        private static ?self $instance = null;

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        private static function key($from, $event, $to, $class, $method): string
        {
            return strtolower("$from|$event|$to|$class|$method");
        }

        public function registerEventHandlerCompatible($from, $event, $to, $class = '', $method = '', $sort = 100)
        {
            self::$table[self::key($from, $event, $to, $class, $method)] = 'compatible';
        }

        public function registerEventHandler($from, $event, $to, $class = '', $method = '', $sort = 100)
        {
            self::$table[self::key($from, $event, $to, $class, $method)] = 'd7';
        }

        public function unRegisterEventHandler($from, $event, $to, $class = '', $method = '')
        {
            unset(self::$table[self::key($from, $event, $to, $class, $method)]);
        }
    }

    class ModuleManager
    {
        public static array $registered = [];

        public static function registerModule($id)
        {
            self::$registered[$id] = true;
        }

        public static function unRegisterModule($id)
        {
            unset(self::$registered[$id]);
        }

        public static function isModuleInstalled($id)
        {
            return true;
        }
    }

    class Application
    {
        public static array $jobs = [];
        public static bool $jobThrows = false;
        private static ?self $instance = null;

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        public function addBackgroundJob(callable $job, array $args = [])
        {
            if (self::$jobThrows) {
                throw new \RuntimeException('нельзя поставить фоновую задачу');
            }
            self::$jobs[] = [$job, $args];
        }

        public static function isUtfMode()
        {
            return true;
        }
    }
}

namespace Bitrix\Main\Localization {
    class Loc
    {
        public static function loadMessages($file)
        {
        }

        public static function getMessage($code, $replace = null)
        {
            return $code;
        }
    }
}

namespace {
    class CModule
    {
    }

    class CAgent
    {
        public static array $removedFor = [];
        public static array $calls = [];

        public static function AddAgent(...$args)
        {
            self::$calls[] = $args;

            return 1;
        }

        public static function RemoveModuleAgents($moduleId)
        {
            self::$removedFor[] = $moduleId;
        }
    }
}
