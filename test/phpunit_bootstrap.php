<?php
/**
 * Created by PhpStorm.
 * User: Sergio Flores Genis
 * Date: 2018-01-17T16:29
 */

require realpath(__DIR__) . '/../vendor/autoload.php';

function test_path($file = null)
{
    return realpath(__DIR__) . ($file ? '/' . $file : $file);
}