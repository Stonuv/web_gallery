<?php
if (function_exists('gd_info')) {
    var_dump(gd_info());
} else {
    echo "Функция gd_info не существует — GD, скорее всего, не подключена";
}