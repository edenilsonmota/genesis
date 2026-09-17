# Desenvolvimento e deploy

Genesis e uma aplicacao Laravel 13 em PHP 8.4.1 ou superior, com PostgreSQL 18, Redis,
Vite e Tailwind. O ambiente local oficial e o Laravel Sail, definido em
`compose.yaml`.

O Sail e destinado ao desenvolvimento. Ele monta o codigo-fonte no container,
expoe portas locais e usa imagens de runtime de desenvolvimento; portanto, nao
deve ser usado como a composicao de producao do subdominio.

## Desenvolvimento local com Sail

### Requisitos

- Docker Engine com Docker Compose v2;
- Composer e Node.js no host somente para instalar dependencias inicialmente.

### Primeiro uso

1. Crie o ambiente local antes de iniciar os containers:

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

O Seeder de localidades usa o snapshot versionado
`database/data/ibge-localities.json`. O comando de download consulta a API do
IBGE, devendo ser revisado e versionado antes de ser usado em uma entrega.

## Producao atras do reverse proxy central

O servidor ja hospeda sob outros subdominios. Assim, somente o reverse
proxy central (Caddy) pode publicar as portas `80` e `443`; Genesis deve usar
uma composicao de producao propria, conectada a rede Docker externa
`web-proxy` e sem portas publicadas.

Fluxo esperado:

```text
Internet -> Caddy :80/:443 -> genesis-nginx:80 -> PHP-FPM -> Laravel
```

O repositorio possui uma infraestrutura de producao separada do Sail:

- `Dockerfile`: imagens PHP-FPM e Nginx, com dependencias otimizadas e assets
  compilados;
- `docker-compose.prod.yml`: alternativa para um servidor que seja dono das
  portas `80` e `443`;
- `docker-compose.proxy.yml`: composicao apropriada para este servidor, que
  usa o Caddy central e a rede externa `web-proxy`;
- `.env.production.example`: modelo sem credenciais ou dominio real;
- `docker/nginx/proxy.conf.template`: Nginx interno HTTP para uso atras do
  Caddy.

O primeiro provisionamento sera feito passo a passo. Ate la, nao execute os
comandos de producao diretamente no servidor.

### Parametros que a composicao de producao deve respeitar

- A imagem deve instalar PHP 8.4.1 ou superior com extensoes para PostgreSQL e Redis, as
  dependencias Composer sem `require-dev`, e executar `npm ci` seguido de
  `npm run build`.
- O Nginx interno deve expor apenas a porta `80` na rede `web-proxy`, com o
  alias `genesis-nginx`.
- PostgreSQL e Redis devem ficar isolados na rede interna do projeto, com
  volumes nomeados exclusivos de Genesis. Nao reutilize os volumes, banco ou
  credenciais do CCT. PostgreSQL 18 deve montar seu volume em
  `/var/lib/postgresql`, conforme a imagem oficial.
- Os valores atuais de `QUEUE_CONNECTION=database` e `CACHE_STORE=database`
  nao exigem Redis para a aplicacao. Redis pode permanecer disponivel para uso
  futuro, mas nao e necessario criar worker de fila ou scheduler: o projeto
  ainda nao define jobs despachados nem tarefas agendadas.
- A imagem deve manter `storage` em volume persistente e criar o link
  `public/storage` durante o provisionamento.
- Migrations devem ser executadas explicitamente com `php artisan migrate
  --force` no deploy, nunca automaticamente em todo boot de container.

### Ambiente de producao

O arquivo de ambiente deve existir somente no servidor e nunca ser enviado ao
Git. Copie `.env.production.example` para `.env.production` no diretorio de
deploy e preencha os valores reais. Os valores minimos sao:

```dotenv
APP_NAME=Genesis
APP_ENV=production
APP_KEY=base64:gere-uma-chave-unica
APP_DEBUG=false
APP_URL=https://seu-subdominio.example.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=genesis
DB_USERNAME=genesis
DB_PASSWORD=uma-senha-forte-e-exclusiva

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

REDIS_HOST=redis
REDIS_PASSWORD=outra-senha-forte-e-exclusiva

GENESIS_ADMIN_NAME="Administrador Genesis"
GENESIS_ADMIN_USERNAME=genesis.admin
GENESIS_ADMIN_PASSWORD="uma-senha-inicial-forte"
```

Gere `APP_KEY` no ambiente da imagem de producao com
`php artisan key:generate --show` e copie somente o valor retornado. Depois do
primeiro seed, remova `GENESIS_ADMIN_PASSWORD` do arquivo de ambiente para nao
deixar uma senha inicial permanente no servidor.

Como Caddy termina o HTTPS, Laravel tambem deve confiar nos headers
`X-Forwarded-*` enviados pelo proxy. A implementacao da composicao de
producao deve incluir `TRUSTED_PROXIES=*` no ambiente **e** configurar
`trustProxies` em `bootstrap/app.php`; Genesis ainda nao possui essa
configuracao.

### Caddy central

Com a rede `web-proxy` ja criada no servidor, adicione este bloco ao Caddyfile
central e recarregue o Caddy pelo procedimento ja adotado no servidor:

```caddyfile
seu-subdominio.example.com {
    reverse_proxy genesis-nginx:80
}
```

Antes disso, crie o registro DNS `A` do subdominio apontando para o IP da VPS.
O Caddy obtera e renovara o certificado HTTPS; Genesis nao deve executar
Certbot nem publicar `80:80` ou `443:443`.

### GitHub Actions e deploy

O deploy deve seguir o mesmo padrao do CCT: um workflow acionado por push em
`main` conecta por SSH e executa um script local do servidor. Os secrets
necessarios no ambiente `production` do repositorio Genesis sao:

- `VPS_HOST`
- `VPS_PORT`
- `VPS_USER`
- `VPS_SSH_KEY`

O script de deploy deve, em ordem, atualizar o checkout com `git pull
--ff-only`, construir a imagem de producao, subir os servicos, executar
`php artisan migrate --force` e reconstruir os caches de configuracao, rotas e
views. Ele deve falhar imediatamente se qualquer etapa falhar.

Nao reutilize diretorio, containers, imagens, volumes ou aliases do CCT.
Genesis deve usar um diretorio de deploy proprio e o alias `genesis-nginx`.

## Verificacao apos o deploy

1. Confirme que os containers Genesis estao saudaveis e ligados a `web-proxy`.
2. Abra `https://seu-subdominio.example.com/up`; Laravel deve responder `200`.
3. Abra a pagina de login e valide que os redirects e cookies usam HTTPS.
4. Consulte os logs do Nginx, PHP e Caddy caso haja erro `502` ou redirect
   para HTTP.

## Seguranca e backup

- Nunca versione `.env`, `.env.production`, chaves SSH ou senhas.
- Mantenha `APP_DEBUG=false` em producao.
- Use credenciais de banco e Redis exclusivas para Genesis.
- Faça backup regular do volume do PostgreSQL e de `storage` antes de
  migrations de risco ou atualizacoes relevantes.
