# Genesis+

Sistema web Laravel para gestão de igrejas, membros, cargos e acesso por permissões herdadas.

## Modelo de acesso

- membro é uma pessoa e pode existir sem usuário;
- usuário é somente a credencial vinculada ao membro;
- cargos são atribuídos ao vínculo do membro com uma igreja;
- somente cargos ativos com “concede acesso ao sistema” permitem login;
- permissões de leitura e escrita pertencem diretamente ao cargo e são calculadas por igreja;
- departamentos pertencem à área e servem apenas para categorizar cargos;
- o administrador global é uma conta técnica protegida criada pelo Seeder.

Na sidebar, Administração contém **Usuários**, **Cargos e permissões** e **Departamentos**. Consulte [contexto de implementação](docs/genesis-implementation-context.md), [modelo de domínio](docs/genesis-domain-model.mmd), [plano de migrations](docs/migration-plan.md) e [identidade visual](docs/visual-identity.md).

A fundação do módulo Financeiro está documentada separadamente em [contexto financeiro](docs/finance/financial-implementation-context.md), [modelo financeiro](docs/finance/financial-domain-model.mmd) e [roadmap financeiro](docs/finance/financial-roadmap.md).

## Instalação local

Instale as dependências PHP e suba os containers do Sail:

```bash
composer install
./vendor/bin/sail up -d
```

Crie o arquivo de ambiente caso ainda não exista e configure as credenciais técnicas:

```bash
cp .env.example .env
```

```dotenv
GENESIS_ADMIN_NAME="Administrador Genesis"
GENESIS_ADMIN_USERNAME=genesis.admin
GENESIS_ADMIN_PASSWORD="uma-senha-segura"
```

Gere a chave da aplicação e execute as migrations:

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

Instale as dependências do frontend e mantenha o Vite em execução durante o desenvolvimento:

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Com o Sail e o Vite rodando, acesse `http://localhost`.

Para preparar os assets sem manter o Vite aberto, use:

```bash
./vendor/bin/sail npm run build
```

## Catálogo de localidades

O Seeder lê exclusivamente o snapshot versionado `database/data/ibge-localities.json`. Para atualizá-lo explicitamente pela API oficial do IBGE:

```bash
./vendor/bin/sail artisan ibge:download-localities
```

Revise e versione o snapshot após a atualização. Migrations, Seeders e testes não acessam serviços externos.
