<?php
$mysqli = mysqli_connect('127.0.0.1', 'root', '', 'datachat');
        if (!$mysqli) {
            die('Ошибка подключения');
        }