<?php

return [
    'host' => env('MCP_SERVER_HOST', 'localhost'),
    'port' => env('MCP_SERVER_PORT', 8080),
    'query_endpoint' => env('MCP_QUERY_ENDPOINT', '/query'),
    'vectorize_endpoint' => env('MCP_VECTORIZATION_ENDPOINT', '/vectorize'),
    'delete_document_endpoint' => env('MCP_DELETE_DOCUMENT_ENDPOINT', '/delete_document'),
];