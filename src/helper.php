<?php
/**
 *+------------------
 * ingenious
 *+------------------
 * Copyright (c) https://gitcode.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Software Registration Number: 2024SR0694589
 * Official Website: https://madong.tech
 */



if (!function_exists('throw_if')) {
    /**
     * 按条件抛异常
     *
     * @template TValue
     * @template TException of \Throwable
     *
     * @param TValue            $condition
     * @param \Exception|string $exception
     * @param mixed             ...$parameters
     *
     * @return TValue
     * @throws \Exception
     */
    function throw_if($condition, Exception|string $exception='Exception', ...$parameters)
    {
        if ($condition) {
            throw (is_string($exception) ? new $exception(...$parameters) : $exception);
        }
        return $condition;
    }
}

if (!function_exists('throw_unless')) {
    /**
     * 按条件抛异常
     *
     * @template TValue
     * @template TException of \Throwable
     *
     * @param TValue            $condition
     * @param \Exception|string $exception
     * @param mixed             ...$parameters
     *
     * @return TValue
     * @throws \Exception
     */
    function throw_unless($condition, Exception|string $exception='Exception', ...$parameters)
    {
        if (!$condition) {
            throw (is_string($exception) ? new $exception(...$parameters) : $exception);
        }

        return $condition;
    }
}

if (!function_exists('class_basename')) {
    /**
     * 获取类名(不包含命名空间)
     *
     * @param mixed $class 类名
     * @return string
     */
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}
