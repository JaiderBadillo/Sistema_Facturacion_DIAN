# Folio en Render: PHP 8.2 con la extensión soap. Render no tiene entorno nativo de PHP, por eso se usa Docker.
FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libxml2-dev \
    && docker-php-ext-install soap \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .
RUN mkdir -p data/facturas

# Render entrega el puerto en $PORT (por defecto 10000) y exige escuchar en 0.0.0.0.
ENV PORT=10000
EXPOSE 10000
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} router.php"]
