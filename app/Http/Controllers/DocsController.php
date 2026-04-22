<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DocsController extends Controller
{
    public function swagger(): Response
    {
        $html = <<<'HTML'
        <!DOCTYPE html>
        <html>
        <head>
            <title>ZaakiyCRM API Documentation</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@4/swagger-ui.css">
            <style>
                html { box-sizing: border-box; overflow-y: scroll; }
                *, *:before, *:after { box-sizing: inherit; }
                body { margin: 0; padding: 0; }
            </style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@4/swagger-ui-bundle.js" defer></script>
            <script src="https://unpkg.com/swagger-ui-dist@4/swagger-ui-standalone-preset.js" defer></script>
            <script>
            function initSwagger() {
                if (typeof SwaggerUIBundle === 'undefined') {
                    setTimeout(initSwagger, 100);
                    return;
                }
                SwaggerUIBundle({
                    url: "/api/swagger-spec.json",
                    dom_id: '#swagger-ui',
                    presets: [SwaggerUIBundle.presets.apis],
                    layout: "BaseLayout"
                });
            }
            setTimeout(initSwagger, 500);
            </script>
        </body>
        </html>
        HTML;
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}
