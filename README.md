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

Na sidebar, Administração contém **Usuários**, **Cargos e permissões**, **Departamentos** e **Auditoria**. Consulte [contexto de implementação](docs/genesis-implementation-context.md), [modelo de domínio](docs/genesis-domain-model.mmd), [plano de migrations](docs/migration-plan.md) e [identidade visual](docs/visual-identity.md).

A fundação do módulo Financeiro está documentada separadamente em [contexto financeiro](docs/finance/financial-implementation-context.md), [modelo financeiro](docs/finance/financial-domain-model.mmd) e [roadmap financeiro](docs/finance/financial-roadmap.md).

## Desenvolvimento

Consulte as [instruções de desenvolvimento](docs/instructions-dev.md) para
configurar o Sail, executar migrations, seeders e comandos frequentes.

## Catálogo de localidades

O Seeder lê exclusivamente o snapshot versionado `database/data/ibge-localities.json`. Para atualizá-lo explicitamente pela API oficial do IBGE:

```bash
./vendor/bin/sail artisan ibge:download-localities
```

Revise e versione o snapshot após a atualização. Migrations, Seeders e testes não acessam serviços externos..
