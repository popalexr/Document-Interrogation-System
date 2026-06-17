<?php

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class McpStreamClient
{
    public function postStream(string $url, array $payload): ResponseInterface
    {
        $client = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);

        return $client->post($url, [
            'stream' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ],
            'body' => json_encode($payload),
        ]);
    }
}
