# Domain Planning

## 1. Objetivo

Aplicação para controle financeiro de uma pequena empresa, permitindo ao usuário:

- cadastrar clientes e fornecedores;
- registrar contas a pagar e a receber;
- liquidar lançamentos;
- identificar lançamentos em aberto, quitados e em atraso;
- visualizar um resumo financeiro por período;
- receber avisos automáticos por e-mail;
- solicitar fechamento de período com envio de arquivo por e-mail.

Cada usuário acessa somente os próprios dados.

---

## 2. Entidades

### User

Representa o usuário autenticado da aplicação.

Campos principais:

- id
- name
- email
- password
- created_at
- updated_at

Relacionamentos:

- possui vários Contacts;
- possui várias Transactions;
- possui vários PeriodClosures.

---

### Contact

Representa um cliente, fornecedor ou ambos.

Campos principais:

- id
- user_id
- name
- email
- type
- created_at
- updated_at

Tipos:

- CUSTOMER
- SUPPLIER
- BOTH

Relacionamentos:

- pertence a um User;
- pode possuir várias Transactions.

Regra:

Um usuário nunca pode acessar ou alterar contatos pertencentes a outro usuário.

---

### Transaction

Representa uma conta a pagar ou a receber.

Campos principais:

- id
- user_id
- contact_id
- description
- type
- amount
- due_date
- paid_at
- created_at
- updated_at

Tipos:

- PAYABLE
- RECEIVABLE

Relacionamentos:

- pertence a um User;
- pertence a um Contact.

Regra de status:

O status não será armazenado diretamente no banco.

Será calculado da seguinte forma:

- se `paid_at` estiver preenchido: PAID;
- se `paid_at` estiver vazio e `due_date` for anterior à data atual: OVERDUE;
- caso contrário: OPEN.

Motivo:

O status de atraso depende da data atual. Armazenar OVERDUE no banco poderia deixar o dado desatualizado caso nenhuma rotina atualizasse o registro.

---

## 3. Liquidação

A liquidação será inicialmente integral.

Ao liquidar uma Transaction:

- o sistema verifica se ela pertence ao usuário autenticado;
- verifica se ainda está em aberto;
- preenche `paid_at` com a data e hora da liquidação.

Não será criada uma entidade Settlement nesta primeira versão.

Motivo:

Liquidação parcial é um requisito opcional. Para o escopo obrigatório, `paid_at` mantém a solução mais simples.

---

## 4. Visão financeira do período

O usuário poderá informar:

- data inicial;
- data final.

O backend calculará:

- total a pagar;
- total a receber;
- total já liquidado;
- total vencido.

As regras de cálculo ficarão no backend para manter a regra de negócio centralizada.

---

## 5. NotificationLog

Representa o registro de um aviso automático enviado.

Campos principais:

- id
- transaction_id
- type
- reference_date
- sent_at
- created_at
- updated_at

Tipos previstos:

- DUE_SOON
- OVERDUE

Objetivo:

Evitar o envio duplicado do mesmo aviso caso um job seja executado novamente.

Será criada uma restrição de unicidade apropriada no banco.

---

## 6. Avisos automáticos

Fluxo previsto:

Scheduler
→ procura Transactions próximas do vencimento ou vencidas
→ cria Jobs
→ Queue
→ Worker
→ envio de e-mail
→ registro em NotificationLog

O envio não será executado diretamente pelo Scheduler porque pode existir um grande volume de e-mails.

---

## 7. PeriodClosure

Representa uma solicitação de fechamento de período.

Campos principais:

- id
- user_id
- start_date
- end_date
- status
- file_path
- error_message
- completed_at
- created_at
- updated_at

Status previstos:

- PENDING
- PROCESSING
- COMPLETED
- FAILED

Fluxo:

Usuário solicita fechamento
→ backend cria PeriodClosure
→ responde imediatamente
→ Job processa o fechamento
→ gera arquivo CSV
→ envia por e-mail
→ atualiza o status.

Motivo:

A geração do arquivo pode demorar e não deve bloquear a requisição HTTP.

---

## 8. Arquivo de fechamento

Formato escolhido:

CSV.

Motivos:

- simples de gerar;
- fácil de validar;
- pode ser aberto em Excel ou outras ferramentas;
- atende ao requisito de envio de arquivo ao contador.

---

## 9. Endpoints previstos

### Authentication

- POST /api/register
- POST /api/login
- POST /api/logout
- GET /api/user

### Contacts

- GET /api/contacts
- POST /api/contacts
- GET /api/contacts/{id}
- PUT /api/contacts/{id}
- DELETE /api/contacts/{id}

### Transactions

- GET /api/transactions
- POST /api/transactions
- GET /api/transactions/{id}
- PUT /api/transactions/{id}
- DELETE /api/transactions/{id}
- POST /api/transactions/{id}/settle

### Financial Summary

- GET /api/financial-summary

Parâmetros previstos:

- start_date
- end_date

### Period Closures

- GET /api/period-closures
- POST /api/period-closures
- POST /api/period-closures/{id}/retry

---

## 10. Telas previstas

### Login

Autenticação do usuário.

### Dashboard

Resumo financeiro do período.

### Contacts

Cadastro e gerenciamento de clientes e fornecedores.

### Transactions

Cadastro e gerenciamento de contas a pagar e receber, incluindo liquidação.

### Period Closures

Solicitação e acompanhamento dos fechamentos de período.

---

## 11. Decisões de escopo

Ficam inicialmente de fora:

- juros;
- multa;
- liquidação parcial;
- categorias;
- relatórios por categoria;
- integração bancária;
- boleto;
- PIX;
- parcelamento;
- recorrência.

Motivo:

Priorizar os requisitos obrigatórios com qualidade e manter a solução simples e explicável.