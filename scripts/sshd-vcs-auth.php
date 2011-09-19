#!/usr/bin/env php
<?php

$_SERVER['PHABRICATOR_ENV'] = 'custom/vault.phabricator.com';

require_once dirname(__FILE__).'/__init_script__.php';

phutil_require_module('phabricator', 'storage/queryfx');

$root = dirname(dirname(__FILE__));
$cmd = $root.'/bin/sshd-vcs-serve';

$cert = file_get_contents('php://stdin');

$user = null;
if ($cert) {
  $user_dao = new PhabricatorUser();
  $ssh_dao = new PhabricatorUserSSHKey();
  $conn = $user_dao->establishConnection('r');

  list($type, $body) = array_merge(
    explode(' ', $cert),
    array('', ''));

  $user = queryfx_one(
    $conn,
    'SELECT userName FROM %T u JOIN %T ssh ON u.phid = ssh.userPHID
      WHERE ssh.keyBody = %s AND ssh.keyType = %s',
    $user_dao->getTableName(),
    $ssh_dao->getTableName(),
    $body,
    $type);
  if ($user) {
    $user = idx($user, 'userName');
  }
}

if (!$user) {
  exit(1);
}

$options = array(
  'command="'.$cmd.' '.$user.'"',
  'no-port-forwarding',
  'no-X11-forwarding',
  'no-agent-forwarding',
  'no-pty',
);

echo implode(',', $options);
exit(0);
