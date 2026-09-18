# Desenvolvimento

Genesis e uma aplicacao Laravel 13 em PHP 8.4.1 ou superior, PostgreSQL 18,
Redis, Vite e Tailwind. O ambiente local oficial e o Laravel Sail, definido em
`compose.yaml`.

## Desenvolvimento local com Sail

### Requisitos

- Docker Engine com Docker Compose v2;
- Composer e Node.js no host somente para instalar dependencias inicialmente.

### Primeiro uso

1. Crie o ambiente local:

   ```bash
   cp .env.example .env
   ```

2. Ajuste no `.env`, se necessario, as credenciais tecnicas usadas pelo
   seeder:

   ```dotenv
   GENESIS_ADMIN_NAME="Administrador Genesis"
   GENESIS_ADMIN_USERNAME=genesis.admin
   GENESIS_ADMIN_PASSWORD="uma-senha-segura"
   ```

3. Instale as dependencias PHP e inicie os servicos:

   ```bash
   composer install
   ./vendor/bin/sail up -d
   ```

4. Gere a chave, aplique as migrations e instale o frontend:

   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail npm install
   ```

5. Para desenvolvimento dos assets, execute em outro terminal:

   ```bash
   ./vendor/bin/sail npm run dev
   ```

   A aplicacao fica em `http://localhost` e o Vite usa a porta `5173`.

Para gerar assets sem servidor de desenvolvimento:

```bash
./vendor/bin/sail npm run build
```

### Comandos frequentes

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan tinker
./vendor/bin/sail artisan ibge:download-localities
./vendor/bin/sail composer install
./vendor/bin/sail npm run build
./vendor/bin/sail logs -f
./vendor/bin/sail down
```

`migrate:fresh --seed` apaga o banco local; nunca o use contra producao.

O frontend usa IMask para valores monetários e Flatpickr para datas. Ambos são dependências npm compiladas pelo Vite; não use CDN em produção nem calendários dependentes da localidade do navegador.

O Seeder de localidades usa o snapshot versionado
`database/data/ibge-localities.json`. O comando de download consulta a API do
IBGE, devendo ser revisado e versionado antes de ser usado em uma entrega.

## Dicas para producao

- Mantenha o Sail apenas para desenvolvimento; use imagens e Compose de
  producao separados.
- Nunca versione `.env.production`, senhas ou chaves SSH. Use
  `.env.production.example` como modelo.
- Execute `php artisan migrate --force` explicitamente no deploy e rode
  seeders apenas no primeiro provisionamento ou quando forem necessarios.
- Atras de proxy HTTPS, configure `APP_URL` e proxies confiaveis no backend.
- Preserve o armazenamento e banco em volumes persistentes, com credenciais e
  volumes exclusivos por projeto.
- Valide o health check e a pagina de login apos cada deploy.

Para um roteiro genérico reutilizável de VPS, Docker, Caddy, HTTPS e GitHub
Actions, mantenha um guia local fora do Git.
