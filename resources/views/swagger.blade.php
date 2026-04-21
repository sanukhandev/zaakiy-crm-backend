<!DOCTYPE html>
<html>
<head>
    <title>ZaakiyCRM API - Swagger Documentation</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.10.5/swagger-ui.min.css">
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *,
        *:before,
        *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/5.10.5/swagger-ui.min.js" integrity="sha512-YW0CjI9Wbl9BXjLgB7VQhfO/J3Ow3OzJ0jvWpKkLT/5XvwO0nPLDPF3yqL8WLFMvq8yiKtpDY4pAFe9BZj3Rw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            SwaggerUIBundle({
                url: "/api/swagger-spec.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout"
            });
        });
    </script>
</body>
</html>
