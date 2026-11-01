#!/bin/sh
set -e
cd /var/www/html

if [ ! -f .env ]; then
  echo ">> Creando .env desde .env.example"
  cp .env.example .env
fi

echo ">> Instalando dependencias (Composer)..."
composer install --no-interaction --prefer-dist --no-progress

if ! grep -q '^APP_KEY=base64:' .env; then
  echo ">> Generando APP_KEY"
  php artisan key:generate --force
fi

echo ">> Esperando a MySQL..."
ESTADO=""
INTENTOS=0
while [ -z "$ESTADO" ]; do
  ESTADO=$(php -r '
    try {
      $dsn = "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE");
      $pdo = new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
      echo ((int) $pdo->query("SELECT COUNT(*) FROM ROL")->fetchColumn() > 0) ? "con-datos" : "vacia";
    } catch (Throwable $e) {}
  ' 2>/dev/null) || true

  if [ -z "$ESTADO" ]; then
    INTENTOS=$((INTENTOS + 1))
    if [ "$INTENTOS" -ge 60 ]; then
      echo "!! No se pudo leer la base de datos. Revisa: docker compose logs mysql"
      exit 1
    fi
    sleep 2
  fi
done

if [ "$ESTADO" = "vacia" ]; then
  echo ">> Base vacia: cargando datos iniciales..."
  php artisan db:seed --force
fi

echo ">> Listo. Abre http://localhost:8000"
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload