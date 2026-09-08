# Harmonika — Controle Financeiro

Aplicação web para controle financeiro de pequenas operações. Cada usuário gerencia seus próprios contatos, contas a pagar e receber, liquidações, indicadores por período, avisos de vencimento e fechamentos enviados por e-mail em CSV.

## Funcionalidades

- autenticação SPA com sessão e CSRF;
- contatos neutros, utilizáveis como cliente ou fornecedor conforme o lançamento;
- CRUD de contas a pagar e receber;
- liquidação integral e preservação de lançamentos pagos;
- dashboard e visão financeira por intervalo;
- avisos automáticos de vencimento e atraso;
- fechamento assíncrono do período com CSV enviado por e-mail;
- filas persistentes, retries, idempotência e reconciliação de trabalhos interrompidos.

## Stack

- Laravel 13 e PHP 8.4;
- React 19, TypeScript e Vite;
- PostgreSQL 17;
- Redis 7 para filas, locks e cache;
- Laravel Sanctum;
- Mailpit para inspeção local de e-mails;
- Docker Compose como ambiente oficial.

## Executando em uma máquina limpa

### Pré-requisitos

- Git;
- Docker Engine ou Docker Desktop com o comando `docker compose`;
- portas `5173`, `8000`, `8025`, `5432` e `6379` disponíveis.

Não é necessário instalar PHP, Composer, Node.js, PostgreSQL ou Redis no host.

### 1. Preparar o ambiente

```bash
git clone <url-do-repositorio>
cd test-harmonika-games
cp backend/.env.example backend/.env
```

No PowerShell, substitua o último comando por:

```powershell
Copy-Item backend/.env.example backend/.env
```

O Compose injeta `VITE_API_URL=http://localhost:8000` no frontend. O arquivo `frontend/.env.example` contém o mesmo valor para quem optar por executar o Vite fora do Compose.

### 2. Construir e preparar os serviços

```bash
docker compose build
docker compose up -d postgres redis mailpit
docker compose run --rm --no-deps backend php artisan key:generate
docker compose run --rm backend php artisan migrate --seed
docker compose up -d
```

Confira o estado:

```bash
docker compose ps
```

### 3. Acessar

| Serviço | URL |
| --- | --- |
| Frontend | http://localhost:5173 |
| API Laravel | http://localhost:8000 |
| Mailpit | http://localhost:8025 |

Credenciais da demonstração:

```text
E-mail: demo@harmonika.local
Senha: Harmonika@123
```

O seed é idempotente e usa datas relativas ao dia atual em `America/Sao_Paulo`. Ele cria dois contatos e cinco lançamentos cobrindo conta a pagar, conta a receber, pago, vencido e próximo do vencimento. Pode ser reaplicado com:

```bash
docker compose exec backend php artisan db:seed
```

Para interromper sem apagar os volumes:

```bash
docker compose down
```

Não use `docker compose down -v` se quiser preservar PostgreSQL e Redis.

## Validação

Todos os comandos usam os containers do projeto:

```bash
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
```

A suíte Laravel força `APP_ENV=testing`, SQLite `:memory:`, fila síncrona, sessão em memória e mailer `array`. Uma proteção adicional interrompe a suíte se outra conexão de banco estiver ativa.

## Fluxos automáticos

### Avisos de vencimento

O scheduler procura contas elegíveis a cada dez minutos e cria um Job independente por aviso. O seed sempre deixa uma conta vencida e outra dentro da janela de três dias, permitindo demonstração imediata:

```bash
docker compose exec backend php artisan transactions:send-reminders
docker compose logs -f queue
```

Para simular outra data de negócio:

```bash
docker compose exec backend php artisan transactions:send-reminders --date=AAAA-MM-DD
```

A data informada é propagada ao Job, tornando retries e demonstrações determinísticos. E-mails enviados aparecem no Mailpit.

### Fechamento do período

Na tela **Visão do período**, escolha as datas e selecione **Fechar período**. A API responde `202 Accepted`, registra o fechamento e envia o processamento para a fila `period-closings`. O frontend acompanha os estados `pending`, `processing`, `sent` e `failed`.

```bash
docker compose logs -f queue
docker compose exec backend php artisan period-closings:reconcile
```

O reconciliador também roda automaticamente a cada cinco minutos e recupera solicitações pendentes ou interrompidas consideradas stale. O e-mail final e seu CSV ficam disponíveis no Mailpit.

### Scheduler e worker

```bash
docker compose ps queue scheduler
docker compose restart queue scheduler
```

O Redis usa AOF e volume persistente. O worker consome as filas `reminders` e `period-closings`.

## Decisões técnicas

- **Contato neutro:** evita duplicar cadastros; o papel de cliente ou fornecedor é determinado pelo lançamento associado.
- **Atraso derivado:** `overdue` não é persistido. Uma conta está atrasada quando permanece `pending` e `due_date` é anterior ao dia atual.
- **Sanctum:** oferece autenticação de primeira parte por cookie, sessão e proteção CSRF para a SPA.
- **Redis e Jobs:** tarefas potencialmente lentas ou volumosas não bloqueiam requests HTTP.
- **Retry/backoff:** falhas temporárias de SMTP ou infraestrutura podem ser tentadas novamente com espera progressiva.
- **Idempotência:** registros persistentes e `ShouldBeUnique` reduzem double submit e reprocessamento concorrente.
- **`202 Accepted`:** o fechamento é aceito rapidamente e processado em background.
- **CSV:** formato simples, interoperável e adequado ao envio ao contador; campos textuais são neutralizados contra formula injection.
- **SQLite `:memory:`:** mantém a suíte rápida e isolada do PostgreSQL de desenvolvimento.
- **Paginação frontend:** suficiente para o volume esperado no teste; evita complexidade de contrato e paginação no backend.

## Trade-offs e limitações

- A visão do período calcula os totais no frontend, enquanto o fechamento recalcula no backend para gerar o arquivo confiável.
- Valores são convertidos para `Number` somente para apresentação e somas no frontend; aplicações financeiras maiores deveriam usar inteiros em centavos ou biblioteca decimal.
- O fechamento reflete os dados existentes quando o Job é processado, não um snapshot imutável do instante da solicitação.
- A idempotência reduz duplicações, mas exatamente-once de e-mail não pode ser garantido no intervalo entre o SMTP aceitar a mensagem e o banco registrar `sent_at`.
- A maior parte dos testes usa SQLite, portanto diferenças específicas do PostgreSQL ainda exigem testes de integração dedicados em um ambiente isolado.
- Ao excluir um contato, a foreign key usa `SET NULL`; o lançamento permanece, mas perde a identificação histórica daquele contato.
- A paginação ocorre somente no cliente e não é adequada a bases muito grandes.

## Uso de IA

IA foi usada como apoio para investigar o repositório, propor implementações, gerar testes, revisar consistência e acelerar tarefas repetitivas. Todo código produzido foi revisado, formatado e validado por testes automatizados e verificações manuais no ambiente Docker.

Durante o desenvolvimento, sugestões e diagnósticos iniciais da IA também introduziram ou deixaram passar problemas reais:

- divergência de comportamento entre SQLite e PostgreSQL;
- `RefreshDatabase` executando com configuração incorreta e atingindo o banco de desenvolvimento;
- `QUEUE_CONNECTION` diferente entre o processo HTTP e o worker;
- hipótese inicial incorreta sobre o comportamento de `ShouldBeUnique`;
- preenchimento de `dispatched_at` antes da confirmação efetiva do enqueue;
- scanner e Job de reminders usando datas de negócio diferentes;
- migration pendente mantendo uma unique key incompatível com o novo ciclo de reminders.

Esses casos foram investigados com logs dos containers, consultas ao PostgreSQL, inspeção de chaves e filas Redis, Tinker, chamadas HTTP reais, Mailpit e testes de regressão. As correções mantiveram o banco como fonte de verdade, reforçaram idempotência e isolaram a suíte em SQLite `:memory:`.
