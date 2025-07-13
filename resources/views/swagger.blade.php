<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Madnezz API Documentation</title>
    <link rel="stylesheet" type="text/css" href="/vendor/swagger-ui/swagger-ui.css">
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #fafafa;
        }
    </style>
</head>
<body>
<div id="swagger-ui"></div>

<script src="/vendor/swagger-ui/swagger-ui-bundle.js" charset="UTF-8"></script>
<script src="/vendor/swagger-ui/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
<script>
    window.onload = function() {
        // Build a system
        const ui = SwaggerUIBundle({
            url: "/swagger-docs/api-docs.json",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout"
        });

        window.ui = ui;
    }
</script>
</body>
</html>