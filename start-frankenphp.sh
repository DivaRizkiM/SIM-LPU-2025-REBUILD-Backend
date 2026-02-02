#!/bin/bash

# Set custom PHP configuration
export PHPRC=/app/php-custom.ini

# Start FrankenPHP with Caddy
exec frankenphp run --config /app/Caddyfile
