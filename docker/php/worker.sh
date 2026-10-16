#!/bin/sh

echo "🐇 Iniciando worker da fila de pagamentos (RabbitMQ)..."

cd /var/www

# Garante dependências e autoload (o volume é compartilhado com o container back,
# mas o worker pode subir primeiro em um build limpo).
composer install --no-interaction --no-progress

# Consome a fila de pagamentos no RabbitMQ (CloudAMQP). Flags:
#  --tries/--backoff: as da própria Job prevalecem; aqui são o teto de segurança
#  --max-time: recicla o worker periodicamente para evitar vazamento de memória
#  --sleep: espera entre polls quando a fila está vazia
exec php artisan queue:work rabbitmq \
  --queue="${RABBITMQ_QUEUE:-payments}" \
  --tries=5 \
  --backoff=10 \
  --sleep=3 \
  --max-time=3600 \
  --verbose
