<?php

use Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) {
    return;
}

global $APPLICATION;

Loc::loadMessages(__DIR__ . '/index.php');

CAdminMessage::ShowMessage([
    'TYPE' => 'ERROR',
    'MESSAGE' => Loc::getMessage('SHEF_FIXMOIZVONKI_INSTALL_TITLE'),
    'DETAILS' => $APPLICATION->GetException()->GetString(),
    'HTML' => true,
]);
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="submit" value="<?= Loc::getMessage('SHEF_FIXMOIZVONKI_BACK') ?>">
</form>
