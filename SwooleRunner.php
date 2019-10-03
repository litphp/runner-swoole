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
use Zend\Diactoros\Stream;
use function Zend\Diactoros\marshalHeadersFromSapi;
use function Zend\Diactoros\marshalUriFromSapi;
use function Zend\Diactoros\normalizeServer;
use function Zend\Diactoros\normalizeUploadedFiles;

/**
 * swoole runner
 */
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
     *
     * @param Server                  $swooleServer   The swoole server object.
     * @param RequestHandlerInterface $requestHandler The request handler.
     */
    public function __construct(Server $swooleServer, RequestHandlerInterface $requestHandler)
    {
        $this->swooleServer = $swooleServer;
        $this->requestHandler = $requestHandler;
    }


    /**
     * run a bolt app with swoole.
     *
     * @param array $config The application configuration.
     */
    public static function run($config = [])
    {
        $container = $config instanceof ContainerInterface
            ? $config
            : BoltContainerConfiguration::createContainer($config + SwooleConfiguration::default());

        $container->get(static::class)->work();
    }

    protected static function emitResponse(Response $res, ResponseInterface $psrRes)
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

    protected static function makePsrRequest(Request $req)
    {
        $server = [];
        foreach ($req->server as $key => $value) {
            $server[strtoupper($key)] = $value;
        }
        $server = normalizeServer($server);

        $files = isset($req->files)
            ? normalizeUploadedFiles($req->files)
            : [];
        $cookies = isset($req->cookie) ? $req->cookie : [];
        $query = isset($req->get) ? $req->get : [];
        $body = isset($req->post) ? $req->post : [];

        $stream = new Stream('php://memory', 'wb+');
        $stream->write($req->rawContent());
        $stream->rewind();

        $headers = marshalHeadersFromSapi($server);
        $request = new ServerRequest(
            $server,
            $files,
            marshalUriFromSapi($server, $headers),
            $server['REQUEST_METHOD'] ?? 'GET',
            $stream,
            $headers
        );

        return $request
            ->withCookieParams($cookies)
            ->withQueryParams($query)
            ->withParsedBody($body);
    }

    protected function work()
    {
        $this->swooleServer->on('request', [$this, 'onRequest']);
        $this->swooleServer->start();
    }

    /**
     * swoole request event handler
     *
     * @param Request  $req The swoole reqeust.
     * @param Response $res The swoole response
     */
    public function onRequest(Request $req, Response $res)
    {
        $psrReq = static::makePsrRequest($req);
        $psrRes = $this->requestHandler->handle($psrReq);

        static::emitResponse($res, $psrRes);
    }
}
