#!/usr/bin/env php
<?php

$user     = $argv[1];
$command  = getenv('SSH_ORIGINAL_COMMAND');

require_once '../libphutil/src/__phutil_library_init__.php';

if (empty($command)) {
  echo "Authenticated as {$user}. No interactive logins.\r\n";
  exit(1);
}

$svn_regexp = '/^svnserve/';
if (preg_match($svn_regexp, $command)) {
  $err = exec_svn_tunnel($user);
  exit($err);
}

$matches = null;
$git_regexp = '/^git(?:-| )(receive|upload)-pack (.*)$/';
if (preg_match($git_regexp, $command, $matches)) {

  $path = $matches[2];
  $path = trim($path, "'");
  if (!preg_match('#^[/a-zA-Z0-9@._-]+$#', $path)) {
    echo "Bad git path.\r\n";
    exit(1);
  }

  $err = exec_git_tunnel($user, $matches[1], $path);
  exit($err);
}

echo "'{$command}'? How about a dinosaur instead?\r\n\r\n";
echo dino();
exit(1);

function dino() {
  $dino = <<<EODINO
                            .       .
                           / `.   .' \
                   .---.  <    > <    >  .---.
                   |    \  \ - ~ ~ - /  /    |
                    ~-..-~             ~-..-~
                \~~~\.'                    `./~~~/
                 \__/                        \__/
                  /                  .-    .  \
           _._ _.-    .-~ ~-.       /       }   \/~~~/
       _.-'q  }~     /       }     {        ;    \__/
      {'__,  /      (       /      {       /      `. ,~~|   .     .
       `''''='~~-.__(      /_      |      /- _      `..-'   \\\\   //
                   / \   =/  ~~--~~{    ./|    ~-.     `-..__\\\\_//_.-'
                  {   \  +\         \  =\ (        ~ - . _ _ _..---~
                  |  | {   }         \   \_\
                 '---.o___,'       .o___,'


EODINO;
  return str_replace("\n", "\r\n", $dino);
}

function exec_git_tunnel($user, $op, $path) {
  $command = 'git-'.$op.'-pack';

  $future = new ExecFuture('cat');

  $stdin = fopen('php://stdin', 'r');
  $stdout = fopen('php://stdout', 'w');
  stream_set_blocking($stdin, false);
  stream_set_blocking($stdout, false);

  $in_bytes = 0;
  $out_bytes = 0;
  $duration = mt_rand();

  $out_buf = '';

  $future->write('', $keep_open = true);
  $future->isReady();

  do {


    $read = array();
    $write = array();

    if ($future) {
      $read = array_merge($read, $future->getReadSockets());
      $write = array_merge($write, $future->getWriteSockets());
    }

    if ($stdin) {
      $read[] = $stdin;
    }

    if (strlen($out_buf)) {
      $write[] = $stdout;
    }

    $s = microtime(true);
    Future::waitForSockets($read, $write);
    $e = microtime(true);

    if ($stdin) {
      do {
        $in = fread($stdin, 8192);
        $eof = feof($stdin);
        if ($future) {
          $future->write($in, $keep_open = !$eof);
        }
        if ($eof) {
          fclose($stdin);
          $stdin = null;
        }
        $in_bytes += strlen($in);
      } while (strlen($in));
    }

    if ($future) {
      $done = $future->isReady();
      list($cmd_stdout, $cmd_stderr) = $future->read();
      $future->discardBuffers();
      if (strlen($cmd_stdout)) {
        $out_buf .= $cmd_stdout;
      }
      if ($done) {
        $future = null;
      }
    }

    if (strlen($out_buf)) {
      $out = fwrite($stdout, $out_buf);
      $out_bytes += $out;
      if ($out) {
        $out_buf = substr($out_buf, $out);
      }
    }

    if (!$stdin && !$future && !$out_buf) {
      fclose($stdout);
      break;
    }
  } while (true);

  file_put_contents('php://stderr', "{$in_bytes}/{$out_bytes}/{$duration}\n");
}