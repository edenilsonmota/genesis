# Plano de migrations do Genesis

O projeto não possui dados legados a preservar. Após confirmação explícita, a refatoração adotou migrations limpas e `migrate:fresh`; não existe conversão semântica de estruturas antigas.

## Ordem de dependência

1. tabelas técnicas de cache e filas;
2. `states`;
3. `cities`;
4. `areas` e sua restrição singleton;
5. `members`;
6. `users`, incluindo sessões e o administrador global protegido;
7. `permission_modules`;
8. `churches`;
9. códigos IBGE de estados e cidades;
10. `member_church_memberships`;
11. `departments`, pertencentes à área;
12. `positions`, pertencentes à área e opcionalmente a um departamento;
13. `member_position_assignments`;
14. `position_permissions`;
15. `audit_logs`.
16. `financial_accounts`, com proprietário exclusivo entre área e igreja;
17. `financial_categories`, pertencentes à área.
18. `financial_transactions`, com o ciclo de status e referências gerenciais;
19. `financial_movements`, livro de entradas e saídas por conta;
20. `calendar_events`, pertencentes à área e opcionalmente a uma igreja, departamento e responsável.
21. `member_imports`, histórico de arquivos privados por igreja e usuários responsáveis.
22. `member_import_rows`, prévia normalizada e erros por linha da importação.

## Integridade

- UUIDs identificam entidades de domínio; localidades mantêm IDs numéricos internos e códigos oficiais únicos.
- A área possui índice único sobre uma expressão constante.
- Username, nomes de departamentos e nomes de cargos possuem índices funcionais case-insensitive.
- Vínculos de igreja e atribuições de cargo possuem índices parciais para impedir duplicidades ativas e constraints de datas.
- Relações históricas usam exclusão restrita. Somente linhas técnicas de permissão usam cascade a partir do cargo ou módulo.
- A matriz garante uma linha por combinação de cargo e módulo.
- Contas financeiras possuem constraint XOR de proprietário, índices funcionais de nome por área ou igreja e um único caixa padrão por escopo; categorias possuem unicidade funcional por área, tipo e nome.
- Eventos exigem término posterior ao início; visibilidade departamental exige departamento e toda relação histórica usa exclusão restrita.

O rollback de validação ocorre na ordem inversa: auditoria, matriz, atribuições, cargos, departamentos, vínculos, igrejas e restrição singleton. A suíte executa migrations em banco PostgreSQL de teste limpo.

## Seeders

`IbgeLocalitiesSeeder` consome `database/data/ibge-localities.json` com `upsert()` e sem rede. `PermissionModuleSeeder` mantém as chaves estáveis da sidebar. `GenesisAdminSeeder` cria ou restaura de forma idempotente a conta técnica configurada por `GENESIS_ADMIN_NAME`, `GENESIS_ADMIN_USERNAME` e `GENESIS_ADMIN_PASSWORD`; não cria cargos ou departamentos arbitrários.
