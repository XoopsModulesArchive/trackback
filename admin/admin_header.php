<?php

include '../../../mainfile.php';
require XOOPS_ROOT_PATH . '/modules/pollex/include/constants.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsmodule.php';
require XOOPS_ROOT_PATH . '/include/cp_functions.php';
//error_reporting(E_ALL);
if ($xoopsUser) {
    $xoopsModule = XoopsModule::getByDirname('trackback');

    if (!$xoopsUser->isAdmin($xoopsModule->mid())) {
        redirect_header($xoopsConfig['xoops_url'] . '/', 3, _NOPERM);

        exit();
    }
} else {
    redirect_header($xoopsConfig['xoops_url'] . '/', 3, _NOPERM);

    exit();
}
if (file_exists('../language/' . $xoopsConfig['language'] . '/admin.php')) {
    include '../language/' . $xoopsConfig['language'] . '/admin.php';
} else {
    include '../language/english/admin.php';
}
if (file_exists('../language/' . $xoopsConfig['language'] . '/main.php')) {
    include '../language/' . $xoopsConfig['language'] . '/main.php';
} else {
    include '../language/english/admin.php';
}
$modbase = XOOPS_ROOT_PATH . '/modules/' . $xoopsModule->dirname();
if (function_exists('getCache')) {
    eval(getCache($xoopsModule->dirname() . '/config.php'));
} else {
    include "$modbase/cache/config.php";

    function putCache($tag, $content)
    {
        $file = XOOPS_ROOT_PATH . '/modules/' . preg_replace('/\//', '/cache/', $tag);

        $fp = fopen($file, 'wb');

        if ($fp) {
            fwrite($fp, "<?php\n$content\n?>");

            fclose($fp);
        } else {
            redirect_header('index.php', 5, sprintf(_MUSTWABLE, $file));

            exit();
        }
    }
}
?>
