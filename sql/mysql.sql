#
# XOOPS 1.3.x trackback SQL schema
#
# $Id: mysql.sql,v 1.3 2004/12/03 09:20:24 nobu Exp $
# --------------------------------------------------------
#
# master of tracking 
# handling from URI to track_id and so-on.
#
CREATE TABLE trackback (
    track_id  INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    track_uri VARCHAR(255)     NOT NULL DEFAULT '',
    since     INT(10) UNSIGNED NOT NULL DEFAULT '0',
    disable   INT(1) UNSIGNED  NOT NULL DEFAULT '0',
    PRIMARY KEY (track_id),
    KEY track_id (track_uri)
)
    ENGINE = ISAM;
# --------------------------------------------------------
#
# referer of tracking 
#
CREATE TABLE trackback_ref (
    ref_id     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    since      INT(10) UNSIGNED NOT NULL DEFAULT '0',
    track_from INT(10) UNSIGNED NOT NULL,
    ref_url    VARCHAR(255)     NOT NULL DEFAULT '',
    title      VARCHAR(255)     NOT NULL DEFAULT '',
    context    TINYTEXT         NOT NULL DEFAULT '',
    nref       INT(10) UNSIGNED NOT NULL DEFAULT '0',
    mtime      INT(10) UNSIGNED NOT NULL DEFAULT '0',
    linked     INT(1) UNSIGNED  NOT NULL DEFAULT '0',
    checked    INT(1) UNSIGNED  NOT NULL DEFAULT '0',
    PRIMARY KEY (ref_id),
    KEY ref_id (ref_url)
)
    ENGINE = ISAM;
# --------------------------------------------------------
#
# check for reload soon
#
CREATE TABLE trackback_log (
    log_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    atime  INT(10) UNSIGNED NOT NULL DEFAULT '0',
    tfrom  INT(10) UNSIGNED,
    rfrom  INT(10) UNSIGNED,
    ip     VARCHAR(15)      NOT NULL DEFAULT '',
    PRIMARY KEY (log_id)
)
    ENGINE = ISAM;
