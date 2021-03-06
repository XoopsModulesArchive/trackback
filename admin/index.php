<?php
// trackback module for XOOPS (admin side code)
// $Id: index.php,v 1.8 2004/12/11 05:09:43 nobu Exp $
include 'admin_header.php';
require_once '../functions.php';
$dir = $xoopsModule->dirname();
$op  = $_POST['op'] ?? $_GET['op'] ?? 'list';
$page = $_GET['page'] ?? 1;
$start = ($page > 1) ? ($page - 1) * $trackConfig['list_max'] : 0;
$myts = MyTextSanitizer::getInstance();
$tbl = $xoopsDB->prefix('trackback');
$tbr = $xoopsDB->prefix('trackback_ref');
$tblstyle = "border='0' cellspacing='1' cellpadding='3' class='bg2' width='100%'";
if ('list' == $op) {
    xoops_cp_header();

    OpenTable();

    echo "<h4 style='text-align:left;'>" . _AM_TRACKBACK_LIST . '</h4>';

    $result = $xoopsDB->query("SELECT count(ref_id) FROM $tbr WHERE checked=0");

    [$ck] = $xoopsDB->fetchRow($result);

    if ($ck) {
        echo "<p><a href='index.php?op=check'>" . sprintf(_AM_UNCHECKED, $ck) . '</a></p>';
    }

    $mode = $_GET['m'] ?? '';

    $opt = '';

    if ('all' == $mode) {
        $cond = '';

        $opt = '&m=all';
    } elseif ('dis' == $mode) {
        $cond = 'disable=1 AND';

        $opt = '&m=dis';
    } else {
        $cond = 'disable=0 AND';
    }

    echo "<form action='index.php' method='get'>" . _AM_ENTRY_VIEW . ' ' . myselect('m', ['' => _AM_ENTRY_ENABLE, 'dis' => _AM_ENTRY_DISABLE, 'all' => _AM_ENTRY_ALL], $mode) . " <input type='submit' value='" . _SUBMIT . "'>\n</form>";

    $result = $xoopsDB->query("SELECT count(track_id) FROM $tbl WHERE $cond 1");

    [$nrec] = $xoopsDB->fetchRow($result);

    $result = $xoopsDB->query("SELECT track_id, track_uri,count(ref_id),sum(linked), disable FROM $tbl,$tbr WHERE $cond track_id=track_from GROUP BY track_id ORDER BY track_uri", $trackConfig['list_max'], $start);

    if ($nrec) {
        $pctrl = make_page_index(_AM_PAGE, $nrec, $page, " <a href='index.php?page=%d$opt'>(%d)</a>");

        echo $pctrl;

        echo "<form action='index.php' method='post'>";

        echo "<table $tblstyle>\n";

        echo "<tr class='bg1'><th>" . _AM_DISABLE . '</th><th>' . _AM_TRACKBACK_PAGE . '</th><th>' . _AM_REF_SHOWS . '</th><th>' . _AM_REF_LINKS . "</th></tr>\n";

        $nc = 1;

        while (list($tid, $uri, $count, $nlink, $disable) = $xoopsDB->fetchRow($result)) {
            $bg = $disable ? 'dis' : $tags[($nc++ % 2)];

            echo "<tr class='$bg'>"
                 . "<td style='text-align: center'><input type='checkbox' name='disable[$tid]' "
                 . ($disable ? 'checked' : '')
                 . "><input type='hidden' name='trid[$tid]' value='1'></td>"
                 . "<td><a href='index.php?op=edit&tid=$tid'>"
                 . uri_to_name($uri)
                 . '</a></td>'
                 . "<td style='text-align: center'>$nlink</th><td style='text-align: center'>$count</td>"
                 . "</tr>\n";
        }

        echo "</table>\n";

        echo "<input type='hidden' name='op' value='disable'>" . "<input type='hidden' name='m' value='$mode'>" . "<input type='submit' value='" . _AM_SUBMIT_DISABLE . "'>" . "</form>\n";

        echo $pctrl;
    } else {
        echo _AM_TRACK_NODATA;
    }

    CloseTable();

    xoops_cp_footer();

    exit();
}
if ('edit' == $op) {
    $tid = $_GET['tid'];

    $result = $xoopsDB->query("SELECT track_uri, disable FROM $tbl WHERE track_id=$tid");

    [$uri, $disable] = $xoopsDB->fetchRow($result);

    xoops_cp_header();

    OpenTable();

    echo "<h4 style='text-align:left;'>" . _AM_TRACKBACK_PAGE . '</h4>';

    $result = $xoopsDB->query("SELECT count(ref_id) FROM $tbr WHERE track_from=$tid");

    [$nrec] = $xoopsDB->fetchRow($result);

    $result = $xoopsDB->query("SELECT * FROM $tbr WHERE track_from=$tid ORDER BY linked DESC, ref_url", $trackConfig['list_max'], $start);

    echo '<p>' . _AM_TRACK_TARGET . ": <a href='index.php'>" . _AM_TRACK_LIST . "</a> &gt;&gt; <a href='$uri'>" . uri_to_name($uri) . '</a>' . ($disable ? ' - ' . _AM_DISABLE_MODE : '') . '</p>';

    $pctrl = make_page_index(_AM_PAGE, $nrec, $page, " <a href='index.php?op=edit&tid=$tid&page=%d$opt'>(%d)</a>");

    echo $pctrl;

    if ($nrec) {
        echo "<form action='index.php' method='post'>";

        echo "<table $tblstyle>\n";

        echo "<tr class='bg1'><th>" . _AM_REF_URL . "</th></tr>\n";

        $nc = 1;

        while (false !== ($data = $xoopsDB->fetchArray($result))) {
            $bg = $tags[($nc++ % 2)];

            $rid = $data['ref_id'];

            $mkl = $data['linked'] ? 'checked' : '';

            $start++;

            $fbox = ($data['mtime'] > 10) ? ' ' . _AM_FLUSH_INFO . ":<input type='checkbox' name='flush[$rid]'>" : '';

            echo "<tr class='$bg'><td>" . "<input type='checkbox' name='link[$rid]' $mkl>" . "<input type='hidden' name='refid[$rid]' value='ok'>" . "$start. " . ($data['checked'] ? '' : ' (' . _AM_UNCHECK . ')') . make_track_item($data, $fbox) . '</td>' . "</tr>\n";
        }

        echo "</table>\n";

        echo "<input type='hidden' name='op' value='edit_update'>" . "<input type='hidden' name='tid' value='$tid'>" . "<input type='submit' value='" . _AM_SUBMIT_LINK . "'>" . "</form>\n";
    }

    echo $pctrl;

    CloseTable();

    xoops_cp_footer();

    exit();
}
if ('edit_update' == $op) {
    $tid = $_POST['tid'];

    $sets = '';

    $resets = '';

    $flushs = '';

    $link = $_POST['link'] ?? [];

    $flush = $_POST['flush'] ?? [];

    foreach ($_POST['refid'] as $i => $v) {
        if (isset($link[$i])) {
            $sets .= ('' == $sets ? '' : ' OR ') . "ref_id=$i";
        } else {
            $resets .= ('' == $resets ? '' : ' OR ') . "ref_id=$i";
        }

        if (isset($flush[$i])) {
            $flushs = ('' == $resets ? '' : ' OR ') . "ref_id=$i";
        }
    }

    if ('' != $resets) {
        $xoopsDB->query("UPDATE $tbr SET linked=0 WHERE ($resets) AND track_from=$tid");
    }

    if ('' != $sets) {
        $xoopsDB->query("UPDATE $tbr SET linked=1 WHERE ($sets) AND track_from=$tid");
    }

    if ('' != $flushs) {
        $xoopsDB->query("UPDATE $tbr SET mtime=1 WHERE ($sets) AND track_from=$tid");
    }

    redirect_header("index.php?op=edit&tid=$tid", 1, _AM_DBUPDATED);

    exit();
}
if ('check' == $op) {
    xoops_cp_header();

    OpenTable();

    echo "<h4 style='text-align:left;'>" . _AM_TRACKBACK_CHECK . '</h4>';

    $result = $xoopsDB->query("SELECT * FROM $tbl,$tbr WHERE track_from=track_id AND checked=0 ORDER BY ref_id");

    $n = $xoopsDB->getRowsNum($result);

    if ($n) {
        echo "<script>
<!--
function myCheckAll(formname, switchid, group) {
var ele = document.forms[formname].elements;
var switch_cbox = xoopsGetElementById(switchid);
for (var i = 0; i < ele.length; i++) {
var e = ele[i];
if ( (e.name != switch_cbox.name) && (e.id==group) && (e.type == 'checkbox') ) {
e.checked = switch_cbox.checked;
}
}
}
-->
</script>";

        $allbox = "<input name='allbox' id='allbox' onclick='myCheckAll(\"refchk\", \"allbox\", \"check\");' type='checkbox' value='Check All'>";

        echo "<form action='index.php' method='post' name='refchk'>";

        echo $allbox . ' ' . _AM_CHECKALL_CHECK;

        echo "<table $tblstyle>\n";

        echo "<tr class='bg1'><th nowrap>" . _AM_REF_CHECKED . '</th><th>' . _AM_REF_URL . "</th></tr>\n";

        $nc = 1;

        while (false !== ($data = $xoopsDB->fetchArray($result))) {
            $bg = $tags[($nc++ % 2)];

            $tid = $data['track_id'];

            $uri = $data['track_uri'];

            $rid = $data['ref_id'];

            $url = $data['ref_url'];

            $title = $data['title'];

            $uri = $data['track_uri'];

            $linkto = ' ' . _TB_LINKTO . " <a href='$uri'>" . uri_to_name($uri) . '</a>';

            $clickmark = "target='_blank' onclick='javascript:document.forms[\"refchk\"].elements[\"check[$rid]\"].checked=true;'";

            $mkl = $data['linked'] ? 'checked' : '';

            $start++;

            echo "<tr class='$bg'><td style='text-align:center;'><input type='checkbox' name='check[$rid]' id='check'></td>" . "<td>$start. " . "<input type='checkbox' name='link[$rid]' $mkl>" . "<input type='hidden' name='refid[$rid]' value='ok'> " . make_track_item(
                $data,
                $linkto,
                $clickmark
            ) . "</td></tr>\n";
        }

        echo "</table>\n";

        echo "<p><input type='hidden' name='op' value='check_update'>" . "<input type='submit' value='" . _SUBMIT . "'></p>" . "</form>\n";
    } else {
        echo _AM_NO_UNCHECKED;
    }

    CloseTable();

    xoops_cp_footer();

    exit();
}
if ('check_update' == $op) {
    if (isset($_POST['link'])) {
        $cond = '';

        foreach ($_POST['link'] as $i => $v) {
            if ('' == $cond) {
                $cond = "ref_id=$i";
            } else {
                $cond .= " OR ref_id=$i";
            }
        }

        $xoopsDB->query("UPDATE $tbr SET linked=0 WHERE checked=0");

        $xoopsDB->query("UPDATE $tbr SET linked=1 WHERE $cond");
    }

    if (isset($_POST['check'])) {
        $cond = '';

        foreach ($_POST['check'] as $i => $v) {
            if ('' == $cond) {
                $cond = "ref_id=$i";
            } else {
                $cond .= " OR ref_id=$i";
            }
        }

        $xoopsDB->query("UPDATE $tbr SET checked=1 WHERE ($cond)");
    }

    redirect_header('index.php?op=check', 1, _AM_DBUPDATED);

    exit();
}
if ('disable' == $op) {
    $disable = $_POST['disable'] ?? [];

    $resets = '';

    $sets = '';

    foreach ($_POST['trid'] as $tid => $v) {
        if (isset($disable[$tid])) {
            $sets .= ('' == $sets ? '' : ' OR ') . "track_id=$tid";
        } else {
            $resets .= ('' == $resets ? '' : ' OR ') . "track_id=$tid";
        }
    }

    if ('' != $resets) {
        $xoopsDB->query("UPDATE $tbl SET disable=0 WHERE $resets");
    }

    if ('' != $sets) {
        $xoopsDB->query("UPDATE $tbl SET disable=1 WHERE $sets");
    }

    redirect_header('index.php' . (empty($_POST['m']) ? '' : '?m=' . $_POST['m']), 1, _AM_DBUPDATED);

    exit();
}
if ('config' == $op) {
    xoops_cp_header();

    OpenTable();

    echo '<h4>' . _AM_TRACKBACK_CONFIG . "</h4>\n";

    $cfg = "$modbase/cache/config.php";

    if (!function_exists('getCache') && !is_writable($cfg)) {
        echo "<p style='color: red;'>" . sprintf(_MUSTWABLE, $cfg) . "</p>\n";
    }

    echo "<form action='index.php' method='post'>\n"
         . "<table>\n<tr><td class='nw'>"
         . _AM_TRACK_AUTOCHECK
         . '</td><td>'
         . myradio('autocheck', [1 => _AM_DO, 0 => _AM_DONT], $trackConfig['auto_check'])
         . "</td></tr>\n"
         . "<tr><td class='nw'>"
         . _AM_TRACK_BLOCKHIDE
         . '</td><td>'
         . myradio(
             'blockhide',
             [0 => _AM_DO, 1 => _AM_DONT],
             $trackConfig['block_hide']
         )
         . "</td></tr>\n"
         . "<tr><td class='nw'>"
         . _AM_TRACK_LIST_MAX
         . '</td><td>'
         . "<input size='4' name='listmax' value='"
         . $trackConfig['list_max']
         . "'></td></tr>\n"
         . "<tr><td class='nw'>"
         . _AM_TRACK_TITLELEN
         . '</td><td>'
         . "<input size='4' name='titlelen' value='"
         . $trackConfig['title_len']
         . "'></td></tr>\n"
         . "<tr><td class='nw'>"
         . _AM_TRACK_CTEXTLEN
         . '</td><td>'
         . "<input size='4' name='ctextlen' value='"
         . $trackConfig['ctext_len']
         . "'></td></tr>\n"
         . "<tr><td class='nw'>"
         . _AM_TRACK_EXPIREDAY
         . '</td><td>'
         . "<input size='4' name='expireday' value='"
         . $trackConfig['expire']
         . "'></td></tr>\n"
         . "</table>\n";

    echo '<p><b>'
         . _AM_TRACK_EXCLUDE
         . "</b></p>\n"
         . "<textarea name='exclude' rows='5' cols='60'>"
         . htmlspecialchars($trackConfig['exclude'], ENT_QUOTES | ENT_HTML5)
         . "</textarea>\n"
         . '<p><b>'
         . _AM_TRACK_INCLUDE
         . '</b></p>'
         . "<textarea name='include' rows='5' cols='60'>"
         . htmlspecialchars($trackConfig['include'], ENT_QUOTES | ENT_HTML5)
         . "</textarea>\n"
         . "<input type='hidden' name='op' value='config_update'>\n"
         . "<p><input type='submit' value='"
         . _SUBMIT
         . "'></p>\n"
         . "</form>\n";

    CloseTable();

    xoops_cp_footer();

    exit();
}
if ('config_update' == $op) {
    $config = "\$trackConfig['exclude']=\""
              . crlf2nl($_POST['exclude'])
              . "\";\n"
              . "\$trackConfig['include']=\""
              . crlf2nl($_POST['include'])
              . "\";\n"
              . "\$trackConfig['auto_check']="
              . $_POST['autocheck']
              . ";\n"
              . "\$trackConfig['block_hide']="
              . $_POST['blockhide']
              . ";\n"
              . "\$trackConfig['list_max']="
              . $_POST['listmax']
              . ";\n"
              . "\$trackConfig['title_len']="
              . $_POST['titlelen']
              . ";\n"
              . "\$trackConfig['ctext_len']="
              . $_POST['ctextlen']
              . ";\n"
              . "\$trackConfig['expire']="
              . $_POST['expireday']
              . ';';

    putCache($xoopsModule->dirname() . '/config.php', $config);

    redirect_header('index.php?op=config', 1, _AM_DBUPDATED);

    exit();
}
function myradio($name, $value, $def)
{
    $body = '';

    foreach ($value as $i => $v) {
        $ck = ($i == $def) ? ' checked' : '';

        $body .= "<input type='radio' name='$name' value='$i' $ck>&nbsp;$v&nbsp;";
    }

    return $body;
}

function myselect($name, $value, $def)
{
    $body = "<select name='$name'>\n";

    foreach ($value as $i => $v) {
        $ck = ($i == $def) ? ' selected' : '';

        $body .= "<option value='$i'$ck>$v</option>\n";
    }

    return $body . '</select>';
}
