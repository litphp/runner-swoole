Bolt runner powered by Swoole
=============================

### Usage

+ Install [swoole](https://www.swoole.co.uk/) (with pecl: `pecl install swoole`)
+ Write a php file calling `SwooleRunner::run` with your configuration, then run it with php

(see _example)

By default, the listening host & port is read from environment variable `HOST` / `PORT`, and fallback to localhost:8080. This can be overridden via configuration
