<?php

declare(strict_types=1);

namespace Lit\Runner\Swoole;

use Lit\Bolt\BoltContainerConfiguration;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Stream;

class SwooleRunner
{
    protected const CHUNK_SIZE = 1048576;//1M
    /**
     * @var Server
     */
    protected $swooleServer;
    /**
     * @var RequestHandlerInterface
     */
    protected $requestHandler;

    /**
     * SwooleRunner constructor.
     * @param Server $swooleServer
     * @param RequestHandlerInterface $requestHandler
     */
    public function __construct(Server $swooleServer, RequestHandlerInterface $requestHandler)
    {
        $this->swooleServer = $swooleServer;
        $this->requestHandler = $requestHandler;
    }


    public static function run($config = [])
    {
        $container = $config instanceof ContainerInterface
            ? $config
            : BoltContainerConfiguration::createContainer($config + SwooleConfiguration::default());

        $container->get(static::class)->work();
    }

    public static function emitResponse(Response $res, ResponseInterface $psrRes)
    {
        $res->status($psrRes->getStatusCode());
        foreach ($psrRes->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $res->header($name, $value);
            }
        }

        $body = $psrRes->getBody();
        $body->rewind();
        if ($body->getSize() > static::CHUNK_SIZE) {
            while (!$body->eof()) {
                $res->write($body->read(static::CHUNK_SIZE));
            }
            $res->end();
        } else {
            $res->end($body->getContents());
        }
    }

    public static function makePsrRequest(Request $req)
    {
        $server = [];
        foreach ($req->server as $key => $value) {
            $server[strtoupper($key)] = $value;
        }
        $server = ServerRequestFactory::normalizeServer($server);

        $files = isset($req->files)
            ? ServerRequestFactory::normalizeFiles($req->files)
            : [];
        $cookies = isset($req->cookie) ? $req->cookie : [];
        $query = isset($req->get) ? $req->get : [];
        $body = isset($req->post) ? $req->post : [];

        $stream = new Stream('php://memory', 'wb+');
        $stream->write($req->rawContent());
        $stream->rewind();

        $headers = ServerRequestFactory::marshalHeaders($server);
        $request = new ServerRequest(
            $server,
            $files,
            ServerRequestFactory::marshalUriFromServer($server, $headers),
            ServerRequestFactory::get('REQUEST_METHOD', $server, 'GET'),
            $stream,
            $headers
        );

        return $request
            ->withCookieParams($cookies)
            ->withQueryParams($query)
            ->withParsedBody($body);
    }

    public function work()
    {
        $this->swooleServer->on('request', [$this, 'onRequest']);
        $this->swooleServer->start();
    }

    public function onRequest(Request $req, Response $res)
    {
        $psrReq = static::makePsrRequest($req);
        $psrRes = $this->requestHandler->handle($psrReq);

        static::emitResponse($res, $psrRes);
    }
}
