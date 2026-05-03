<?php

return [
    'host' => env('MCP_SERVER_HOST', 'localhost'),
    'port' => env('MCP_SERVER_PORT', 8080),
    'query_endpoint' => env('MCP_QUERY_ENDPOINT', '/query'),
    'generate_title_endpoint' => env('MCP_GENERATE_TITLE_ENDPOINT', '/generate_title'),
    'vectorize_endpoint' => env('MCP_VECTORIZATION_ENDPOINT', '/vectorize'),
    'vector_search_endpoint' => env('MCP_VECTOR_SEARCH_ENDPOINT', '/vector_search'),
    'delete_document_endpoint' => env('MCP_DELETE_DOCUMENT_ENDPOINT', '/delete_document'),
    'edit_document_endpoint' => env('MCP_EDIT_ENDPOINT', '/edit'),
    'ai_interrogation_endpoint' => env('MCP_AI_INTERROGATION_ENDPOINT', '/ai_interrogation'),
];
