<?php

declare(strict_types=1);

namespace Lit\Runner\Swoole;

use Http\Factory\Diactoros\ResponseFactory;
use Lit\Air\Configurator as C;
use Lit\Bolt\BoltApp;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Server;

class SwooleConfiguration
{
    public static function default()
    {
        return [
            SwooleRunner::class => C::provideParameter([
                Server::class => C::alias(SwooleRunner::class, 'server'),
                RequestHandlerInterface::class => C::alias(SwooleRunner::class, 'handler'),
            ]),

            C::join(SwooleRunner::class, 'handler') => C::alias(BoltApp::class),
            C::join(SwooleRunner::class, 'server') => C::instance(Server::class, [
                C::alias(SwooleRunner::class, 'server', 'host'),
                C::alias(SwooleRunner::class, 'server', 'port'),
                C::alias(SwooleRunner::class, 'server', 'mode'),
                C::alias(SwooleRunner::class, 'server', 'type'),
            ]),
            C::join(SwooleRunner::class, 'server', 'host') => $_ENV['HOST'] ?? 'localhost',
            C::join(SwooleRunner::class, 'server', 'port') => $_ENV['PORT'] ?? '8080',
            C::join(SwooleRunner::class, 'server', 'mode') => SWOOLE_PROCESS,
            C::join(SwooleRunner::class, 'server', 'type') => SWOOLE_SOCK_TCP,
            ResponseFactoryInterface::class => C::instance(ResponseFactory::class),
        ];
    }
}
