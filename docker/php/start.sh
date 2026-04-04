#!/bin/sh

echo "🚀 Iniciando ambiente Laravel + Frontend..."

cd /var/www

# Instala dependências PHP se a pasta vendor não existir
if [ ! -d "vendor" ]; then
  echo "📦 Instalando dependências PHP (composer)..."
  composer install
else
  echo "✅ Dependências PHP já instaladas."
fi


# Garante que o .env e chave da aplicação existam
if [ ! -f ".env" ]; then
  echo "⚙️ Copiando .env.example para .env"
  cp .env.example .env
  php artisan key:generate
fi

# Roda as migrations (opcional - descomente se quiser rodar sempre)
php artisan migrate
php artisan db:seed

echo "🎯 Iniciando servidor Laravel na porta 8101..."
php artisan serve --host=0.0.0.0 --port=8101

php artisan storage:link
