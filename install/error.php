<?php

use Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) {
    return;
}

global $APPLICATION;

Loc::loadMessages(__DIR__ . '/index.php');

CAdminMessage::ShowMessage([
    'TYPE' => 'ERROR',
    'MESSAGE' => Loc::getMessage('shef.fixmoizvonki_INSTALL_TITLE'),
    'DETAILS' => $APPLICATION->GetException()->GetString(),
    'HTML' => true,
]);
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="submit" value="<?= Loc::getMessage('shef.fixmoizvonki_BACK') ?>">
</form>
