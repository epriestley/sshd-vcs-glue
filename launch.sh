#!/bin/sh

set -x
set -e

ROOT=$PWD

if [ -f $ROOT/tmp/sshd-vcs.pid ]
then
  echo "Killing running daemon.."
  kill `cat $ROOT/tmp/sshd-vcs.pid`
fi

sed s@{ROOT}@$ROOT@g conf/sshd_config > $ROOT/tmp/sshd_config

sshd-vcs -f $ROOT/tmp/sshd_config \
  -h $ROOT/key/host_dsa_key \
  -h $ROOT/key/host_rsa_key

tail -f /var/log/secure.log
