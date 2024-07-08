<?php

$handle = fopen(".env", "r");

$content = fread($handle, 2048);

const REGEX = "/([a-z0-9]+)\s?=([a-z0-9]+)/i";

$groups = [];

preg_match(REGEX, $content, $groups);

$_ENV[$groups[1]] = $groups[2];
