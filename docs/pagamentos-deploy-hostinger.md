# Pagamentos (EFI Pix) — Deploy na Hostinger (hospedagem compartilhada)

Como o fluxo de confirmação de pagamento funciona em produção, onde **não há**
Docker, worker persistente (`queue:work` daemon) nem cron via SSH — apenas o
**Cron Job do hPanel**.

## Visão geral do fluxo

```
EFI  --POST-->  /api/wallet/webhook/pix
                      │  (só registra + enfileira, responde 200 na hora)
                      ▼
             webhook_events (dedup/auditoria)  +  fila RabbitMQ (CloudAMQP)
                      │
   cron do hPanel ──► php artisan schedule:run  (1x por minuto)
                      │
      ┌───────────────┴───────────────┐
      ▼                                ▼
 drain-payments-queue           reconcile-pending-payments
 (queue:work --stop-when-empty)  (a cada 5 min, rede de segurança)
      │                                │
      └──────────► WalletService::settleCharge() ◄──────────┘
                   consulta o status REAL no EFI (checkStatus/txid)
                   credita o saldo de forma idempotente (lock + índice único)
```

Pontos-chave de segurança/robustez:
- **O payload do webhook nunca é confiado** para mover dinheiro. O crédito só
  ocorre após reconsultar o status no EFI pelo `txid` (`checkStatus`). Um POST
  forjado no endpoint aberto não credita nada.
- **Idempotência**: índice único em `wallet_transactions.gateway_reference` +
  `lockForUpdate` ao creditar. Webhooks duplicados não creditam em dobro.
- **Nada se perde**: se o webhook não chegar, a reconciliação (a cada 5 min)
  consulta o EFI e liquida.

## 1. Cron Job no hPanel

hPanel → **Avançado → Cron Jobs**. Crie **uma** entrada:

- Frequência: **a cada minuto** (`* * * * *`).
- Comando (ajuste o caminho real do projeto — é a pasta onde está o `artisan`):

```
php /home/SEU_USUARIO/domains/SEU_DOMINIO/artisan schedule:run >> /dev/null 2>&1
```

> Dica: confira o caminho absoluto no Gerenciador de Arquivos do hPanel. Em
> contas Hostinger costuma ser algo como
> `/home/u878239129/domains/seudominio.com.br/public_html` (se o Laravel estiver
> na raiz do site) — mas o `artisan` fica na RAIZ do projeto Laravel, que pode
> não ser a `public_html`. Aponte para onde está o `artisan`.

Essa única entrada dispara tudo: o drain da fila (todo minuto) e a
reconciliação (a cada 5 min).

## 2. Variáveis de ambiente (.env de produção)

Já existem no `.env.example`. Preencha no servidor:

```
# RabbitMQ (CloudAMQP) — buffer durável dos webhooks de pagamento.
RABBITMQ_HOST=jaragua.lmq.cloudamqp.com
RABBITMQ_PORT=5671
RABBITMQ_USER=msihdwcg
RABBITMQ_PASSWORD=********         # senha do CloudAMQP
RABBITMQ_VHOST=msihdwcg
RABBITMQ_QUEUE=payments
RABBITMQ_SSL=true
RABBITMQ_SSL_VERIFY_PEER=true
```

> A URL completa do CloudAMQP tem o formato
> `amqps://user:senha@host/vhost`. Host, user e vhost acima vêm dela; a porta
> `amqps` é 5671 (TLS).

O `QUEUE_CONNECTION` global permanece como está (não precisa ser `rabbitmq`): o
job de pagamento aponta explicitamente para a conexão `rabbitmq`.

## 3. Pré-requisitos de PHP no servidor

- **Extensão `sockets`** habilitada (o `php-amqplib` exige). No hPanel:
  **PHP Configuration → extensões**. Sem ela, a conexão com o CloudAMQP falha.
- `composer install` com o pacote novo
  `vladimir-yuldashev/laravel-queue-rabbitmq` (já está no `composer.json`).
- Certificado EFI (`.pem`) presente em `storage/app/private/` conforme as
  variáveis `EFI_*CERTIFICATE_PATH` (ver seção EFI do `.env`).

## 4. Registrar a URL do webhook no painel do EFI

Aponte o webhook Pix do EFI para:

```
https://SEU_DOMINIO/api/wallet/webhook/pix
```

(A API Pix do EFI acrescenta `/pix` à URL configurada; as duas rotas
`/api/wallet/webhook` e `/api/wallet/webhook/pix` mapeiam para o mesmo handler.)
Faça isso tanto em homologação quanto em produção, no ambiente correspondente.

## 5. Como validar depois de configurar

- Faça um depósito Pix de teste e pague. Em até ~1 minuto o saldo deve ser
  creditado (drain da fila via cron).
- Mesmo que o webhook não chegue, a reconciliação credita em até 5 min.
- Em caso de dúvida, verifique a tabela `webhook_events` (status `received` →
  `processed`) e `wallet_transactions` (status `pending` → `completed`).

## Ambiente de desenvolvimento (Docker)

No `docker-compose.yml` existe um container `worker` que roda o
`queue:work rabbitmq` como daemon — é só conveniência local, para o crédito ser
quase instantâneo. Em produção esse container não existe; quem drena a fila é o
cron do hPanel. Ambos usam o mesmo caminho idempotente, então não há conflito se
os dois rodarem.
