<?php

$translations = json_decode(file_get_contents(__DIR__.'/../admin-portal.json'), true, 512, JSON_THROW_ON_ERROR);
return array_map(fn ($values) => $values[1], $translations);
