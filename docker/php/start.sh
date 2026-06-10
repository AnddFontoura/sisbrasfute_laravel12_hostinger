#!/bin/sh

echo "🚀 Iniciando ambiente Laravel + Frontend..."

cd /var/www

echo "📦 Instalando dependências PHP (composer)..."
composer install


# Garante que o .env e chave da aplicação existam
if [ ! -f ".env" ]; then
  echo "⚙️ Copiando .env.example para .env"
  cp .env.example .env
  php artisan key:generate
fi

# Roda as migrations (opcional - descomente se quiser rodar sempre)
php artisan migrate
php artisan db:seed
php artisan storage:link

PORT="${LARAVEL_PORT:-8201}"
echo "🎯 Iniciando servidor Laravel na porta ${PORT}..."
php artisan serve --host=0.0.0.0 --port="${PORT}"
