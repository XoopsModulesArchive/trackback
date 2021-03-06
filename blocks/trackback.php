<?php

// $Id: trackback.php,v 1.8 2004/12/09 07:15:38 nobu Exp $
function b_trackback_log_show($options)
{
    global $xoopsDB, $trackConfig;

    $moddir = 'trackback';

    // ** trackback recoding **

    $ref = $_SERVER['HTTP_REFERER'] ?? '';

    $uri = $_SERVER['REQUEST_URI'];

    $uri = preg_replace('/\/index.php$/', '/', $uri);

    $uri = preg_replace('/&?PHPSESSID=[^&]*/', '', $uri);

    $uriq = addslashes($uri);

    $now = time();

    $tbl = $xoopsDB->prefix('trackback');

    $tbr = $xoopsDB->prefix('trackback_ref');

    $sql = "SELECT track_id,disable FROM $tbl WHERE track_uri='$uriq'";

    $result = $xoopsDB->query($sql);

    // referere self site, 2nd will be fake referer (Robots?).

    if (preg_match('/^' . preg_quote(XOOPS_URL, '/') . "\//", $ref)) {
        $ref = '';
    } elseif (preg_match('/^' . preg_quote(XOOPS_URL, '/') . '$/', $ref)) {
        $ref = '';
    }

    if ($xoopsDB->getRowsNum($result)) {
        [$tid, $disable] = $xoopsDB->fetchRow($result);
    } else {
        // new page register

        if ('' != $ref) {
            $xoopsDB->queryF("INSERT INTO $tbl(track_uri, since) VALUES('$uriq', $now)");

            $result = $xoopsDB->query($sql);

            [$tid, $disable] = $xoopsDB->fetchRow($result);
        } else {
            $disable = 0;

            $tid = 0;
        }
    }

    $block = [];

    if ($disable) {
        return $block;
    } // disable in this page
    if (function_exists('getCache')) { // for local hacks
        eval(getCache("$moddir/config.php"));
    } else {
        require_once XOOPS_ROOT_PATH . "/modules/$moddir/cache/config.php";
    }

    if ($tid && '' != $ref) {
        $refq = addslashes($ref);

        $result = $xoopsDB->query("SELECT ref_id,nref,mtime,checked FROM $tbr WHERE ref_url='$refq' AND track_from=$tid");

        $ip = $_SERVER['REMOTE_ADDR'];

        $log = $xoopsDB->prefix('trackback_log');

        $exreg = list_to_regexp($trackConfig['exclude']);

        $inreg = list_to_regexp($trackConfig['include']);

        $checked = 0;

        $linked = 0;

        if ($xoopsDB->getRowsNum($result)) {
            // already registered

            [$rid, $refno, $mtime, $checked] = $xoopsDB->fetchRow($result);

            // check valid reference. (is it not reload?)

            // remove expire entry

            $xoopsDB->queryF("DELETE FROM $log WHERE atime<" . ($now - 3600));

            $result = $xoopsDB->queryF("SELECT log_id FROM $log WHERE tfrom=$tid AND rfrom=$rid AND ip='$ip'");

            if ($xoopsDB->getRowsNum($result)) {
                [$lid] = $xoopsDB->fetchRow($result);

                $xoopsDB->queryF("UPDATE $log SET atime=$now WHERE log_id=$lid");
            } else {
                if ('' != $exreg && preg_match($exreg, $ref)) {
                    $checked = 1; // No link without check
                } elseif ('' != $inreg && preg_match($inreg, $ref)) {
                    $checked = 1;

                    $linked = 1; // Link without check
                } elseif ($trackConfig['auto_check']) {
                    // get fail or expire get text

                    if (!$checked
                        || ($now - $mtime) > $trackConfig['expire'] * 24 * 3600) {
                        [$title, $ctext, $linked, $checked] = trackback_get_details($ref, $uri);

                        if ($checked) { // keep old value when fail to get.
                            $title = addslashes($title);

                            $ctext = addslashes($ctext);

                            $xoopsDB->queryF("UPDATE $tbr SET checked=$checked, linked=$linked, title='$title', context='$ctext' WHERE ref_id=$rid");
                        }
                    }
                }

                $xoopsDB->queryF("UPDATE $tbr SET nref=nref+1, mtime='$now' WHERE ref_id=$rid");

                $refno++;

                $xoopsDB->queryF("INSERT INTO $log(atime, tfrom, rfrom, ip) VALUES($now, $tid, $rid, '$ip')");
            }
        } else {
            // new register

            $xoopsDB->queryF(
                "INSERT INTO $tbr(since,track_from,ref_url)" . " VALUES($now, $tid, '$refq')"
            );

            // check origin page, there is link exist?

            $title = '';

            $ctext = '';

            if ('' != $exreg && preg_match($exreg, $ref)) {
                $checked = 1; // No link without check
            } elseif ('' != $inreg && preg_match($inreg, $ref)) {
                $checked = 1;

                $linked = 1; // Link without check
            } elseif ($trackConfig['auto_check']) {
                [$title, $ctext, $linked, $checked] = trackback_get_details($ref, $uri);

                $title = addslashes($title);

                $ctext = addslashes($ctext);
            }

            $xoopsDB->queryF("UPDATE $tbr SET nref=nref+1, checked=$checked, linked=$linked, title='$title', context='$ctext', mtime='$now' WHERE track_from=$tid AND ref_url='$refq'");

            $result = $xoopsDB->query("SELECT ref_id FROM $tbr WHERE track_from=$tid AND ref_url='$refq'");

            [$rid] = $xoopsDB->fetchRow($result);

            $xoopsDB->queryF("INSERT INTO $log(atime, tfrom, rfrom, ip) VALUES($now, $tid, $rid, '$ip')");

            $refno = 1;
        }
    }

    // trackback show block build

    if ($trackConfig['block_hide']) {
        return $block;
    }

    $block['title'] = _MB_TRACKBACK_TITLE;

    $body = '';

    if ($tid) {
        $result = $xoopsDB->query("SELECT nref, ref_url, title FROM $tbr WHERE track_from=$tid AND linked=1 ORDER BY nref DESC");

        $nn = $xoopsDB->getRowsNum($result);

        if ($nn) {
            $n = $options[0];

            $l = $options[1];

            while ($n--
                   && list($nref, $url, $title) = $xoopsDB->fetchRow($result)) {
                if ('' == $title) {
                    $title = preg_replace('/\/.*$/', '', preg_replace('/^https?:\/\//', '', $url));
                }

                $alt = '';

                if (mb_strlen($title) > $l) {
                    $alt = " title='$title'";

                    $title = mysubstr($title, 0, $l) . '..';
                }

                $body .= "<a href='$url'$alt target='_blank'>$title</a> ($nref)<br>\n";
            }

            $nn -= $options[0];

            if ($nn > 0) {
                $body .= "<div style='text-align: center;'>" . sprintf(_MB_TRACKBACK_REST, $nn) . "</div>\n";
            }

            if (mod_allow_access($moddir)) {
                $body .= "<div style='text-align: right'><a href='" . XOOPS_URL . "/modules/$moddir/index.php?id=$tid'>" . _MB_TRACKBACK_MORE . "</a></div>\n";
            }
        }
    }

    if ('' == $body) {
        $body = '<div>' . _MB_TRACKBACK_NONE . '</div>';
    }

    $block['content'] = $body;

    return $block;
}

function b_trackback_log_edit($options)
{
    return _MB_TRACKBACK_MAX . "&nbsp;<input name='options[0]' value='" . $options[0] . "'><br>\n" . _MB_TRACKBACK_LEN . "&nbsp;<input name='options[1]' value='" . $options[1] . "'>\n";
}

function list_to_regexp($l)
{
    $l = trim($l);

    if ('' == $l) {
        return '';
    }

    return '/^https?:\/\/(' . preg_replace(['/\n*$/', '/\n\n+/', '/\r?\n/', '/\./', '/\*/', '/\//'], ['', "\n", '|', '\.', '.*', '\/'], $l) . ')/';
}

//
// block local functions
//
// substrings with support multibytes (--with-mbstring)
// duplicate in ../function.php - umm..
if (!function_exists('mysubstr')) {
    function mysubstr($s, $f, $l)
    {
        if (XOOPS_USE_MULTIBYTES && function_exists('mb_strcut')) {
            return mb_strcut($s, $f, $l, _CHARSET);
        }

        return mb_substr($s, $f, $l);
    }
}
function trackback_get_details($ref, $uri)
{
    global $trackConfig;

    $linked = 0;

    $checked = 0;

    $title = '';

    $ctext = '';

    // includes Snoopy class for remote file access

    if (file_exists(XOOPS_ROOT_PATH . '/class/snoopy.class.php')) {
        //xoops 1.3

        require_once XOOPS_ROOT_PATH . '/class/snoopy.class.php';
    } else {
        //xoops 2

        require_once XOOPS_ROOT_PATH . '/class/snoopy.php';
    }

    $snoopy = new Snoopy();

    //TIMEOUT

    $snoopy->read_timeout = 10;

    //URL

    $snoopy->fetch($ref);

    //GET DATA

    $page = $snoopy->results;

    if ($snoopy->error) {
        $page = '';
    }

    if ($page) {
        if (XOOPS_USE_MULTIBYTES && function_exists('mb_convert_encoding')) {
            $page = mb_convert_encoding($page, _CHARSET, 'EUC-JP,UTF-8,Shift_JIS,JIS');
        }

        if (preg_match("/<title>(.*)<\/title>/i", $page, $d)) {
            $title = $d[1];

            $len = 255;

            if (mb_strlen($title) > $len) {
                $title = mysubstr($title, 0, $len - 2) . '..';
            }

            $page = preg_replace("/<title>(.*)<\/title>/i", '', $page);
        }

        $anc = "/<a\\s+href=([\"'])?" . preg_quote(XOOPS_URL . $uri, '/') . "\\1(>|\s[^>]*>)/i";

        $root = "/<a\\s+href=([\"']?)" . preg_quote(XOOPS_URL . '/', '/') . "\\1(>|\s[^>]*>)/i";

        if (preg_match($anc, $page)) {
            $F = preg_preg_split($anc, $page, 2);

            $linked = 1;
        } elseif (preg_match($root, $page)) {
            $F = preg_preg_split($root, $page, 2);

            $linked = 1;
        }

        if ($linked) {
            // cut out text from orign page

            $l = min($trackConfig['ctext_len'], 255);

            $pre = ltrim(preg_replace('/\s+/', ' ', strip_tags($F[0])));

            [$a, $post] = preg_preg_split('/<\/a>/i', $F[1], 2);

            $post = rtrim(preg_replace('/\s+/', ' ', strip_tags($post)));

            $m = (int)($l / 2);

            $ctext = mysubstr($pre, max(mb_strlen($pre) - $m + 1, 0), $m) . '<u>' . strip_tags($a) . '</u>';

            $m = $l - mb_strlen($ctext);

            $ctext .= mysubstr($post, 0, min(mb_strlen($post), $m));
        }

        if (1 == $linked || '' != strip_tags($page)) {
            $checked = 1;
        }
    }

    return [$title, $ctext, $linked, $checked];
}

function mod_allow_access($dirname)
{
    global $xoopsUser;

    if (preg_match('/^XOOPS 2/', XOOPS_VERSION)) {
        $moduleHandler = xoops_getHandler('module');

        $mod = $moduleHandler->getByDirname($dirname);

        $handler = xoops_getHandler('groupperm');

        return (!empty($xoopsUser)
                && $handler->checkRight('module_read', $mod->getVar('mid'), $xoopsUser->getGroups())) || $handler->checkRight('module_read', $mod->getVar('mid'), XOOPS_GROUP_ANONYMOUS);
    }  

    $mod = XoopsModule::getByDirname($dirname);

    return (!empty($xoopsUser)
                && XoopsGroup::checkRight('module', $mod->mid(), $xoopsUser->groups())) || XoopsGroup::checkRight('module', $mod->mid(), 0);
}
?>
