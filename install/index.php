<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

if (class_exists('shef_fixmoizvonki')) {
    return;
}

Loc::loadMessages(__FILE__);

class shef_fixmoizvonki extends CModule
{
    public $MODULE_ID = 'shef.fixmoizvonki';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_SORT = 1003;
    public $MODULE_GROUP_RIGHTS = 'N';
    public $PARTNER_NAME;
    public $PARTNER_URI;

    private const PHP_MIN_VERSION = '8.2.0';

    /** Модули, без которых нечего прикреплять: дела CRM, звонки, Диск для файла записи. */
    private const NEED_MODULES = ['crm', 'voximplant', 'disk'];

    /** События CRM, после которых в описании дела может появиться ссылка на запись. */
    private const EVENTS = ['OnActivityAdd', 'OnActivityUpdate'];

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('shef.fixmoizvonki_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('shef.fixmoizvonki_MODULE_DESC');
        $this->PARTNER_NAME = Loc::getMessage('shef.fixmoizvonki_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('shef.fixmoizvonki_PARTNER_URI');
    }

    public function DoInstall()
    {
        $errors = $this->checkRequirements();
        if ($errors) {
            // Прервать установку можно только показом формы с ошибкой: return её не остановит.
            $GLOBALS['APPLICATION']->ThrowException(implode('<br>', $errors));
            $GLOBALS['APPLICATION']->IncludeAdminFile(
                Loc::getMessage('shef.fixmoizvonki_INSTALL_TITLE'),
                __DIR__ . '/error.php'
            );

            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        // События CRM шлются позиционными аргументами, поэтому Compatible.
        $manager = EventManager::getInstance();
        foreach (self::EVENTS as $event) {
            $manager->registerEventHandlerCompatible(
                'crm',
                $event,
                $this->MODULE_ID,
                '\Shef\FixMoizvonki\Handler',
                'onActivityChange'
            );
        }

        return true;
    }

    public function DoUninstall()
    {
        $manager = EventManager::getInstance();
        foreach (self::EVENTS as $event) {
            $manager->unRegisterEventHandler(
                'crm',
                $event,
                $this->MODULE_ID,
                '\Shef\FixMoizvonki\Handler',
                'onActivityChange'
            );
        }

        CAgent::RemoveModuleAgents($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    /** @return string[] */
    private function checkRequirements(): array
    {
        $errors = [];

        if (version_compare(PHP_VERSION, self::PHP_MIN_VERSION, '<')) {
            $errors[] = Loc::getMessage('shef.fixmoizvonki_ERR_PHP', ['#VERSION#' => self::PHP_MIN_VERSION]);
        }
        // Модуль поставляется в UTF-8: на портале в CP1251 получился бы мусор.
        if (!Application::isUtfMode()) {
            $errors[] = Loc::getMessage('shef.fixmoizvonki_ERR_UTF');
        }
        foreach (self::NEED_MODULES as $module) {
            if (!ModuleManager::isModuleInstalled($module)) {
                $errors[] = Loc::getMessage('shef.fixmoizvonki_ERR_MODULE', ['#MODULE#' => $module]);
            }
        }

        return $errors;
    }
}
