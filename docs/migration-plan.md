# Plano de migrations do Genesis

O diagrama de domínio define a seguinte ordem topológica para a estrutura completa:

1. `states`;
2. `cities` (`state_id`);
3. `areas`;
4. restrição singleton de `areas`, por índice único sobre uma expressão constante;
5. `churches` (`area_id`, `city_id`);
6. códigos oficiais do IBGE em `states` e `cities`;
7. `departments` (`church_id`);
8. `members` (`city_id`);
9. `member_church_memberships` (`member_id`, `church_id`);
10. `positions` (`area_id` opcional);
11. `member_position_assignments` (`member_church_membership_id`, `position_id`, `department_id` opcional);
12. `users` (`member_id` opcional e único);
13. `access_roles` (`area_id` opcional);
14. `permission_modules`;
15. `access_role_permissions` (`access_role_id`, `permission_module_id`);
16. `user_global_access_roles` (`user_id`, `access_role_id`);
17. `user_area_access_roles` (`user_id`, `area_id`, `access_role_id`);
18. `user_church_access_roles` (`user_id`, `church_id`, `access_role_id`, `department_id` opcional);
19. `audit_logs` (referência lógica e snapshot do ator, sem cascade para `users`).

## Núcleo de autenticação

Esta etapa materializa somente `states`, `cities`, `areas`, `members`, `users`, `access_roles`, `permission_modules`, `access_role_permissions` e `user_global_access_roles`, além das tabelas técnicas do Laravel.

`states`, `cities` e `members` são necessários para que a foreign key opcional `users.member_id` já aponte para a entidade definitiva. `areas` é necessário para preservar desde já `access_roles.area_id`. O administrador técnico usa apenas o escopo global, então `churches` e as tabelas de vínculos locais/regionais não são necessárias para autenticação e ficam para a etapa correspondente dos CRUDs.

As migrations usam UUID nos identificadores previstos pelo diagrama, foreign keys explícitas com exclusão restrita para dados históricos, unicidade funcional em `LOWER(users.username)` e índice parcial para impedir duas atribuições globais ativas iguais.

## Área e igrejas

A etapa de organização acrescenta duas migrations incrementais, sem alterar ou reaplicar a migration versionada de `areas`:

1. `add_singleton_constraint_to_areas_table` cria `areas_singleton_unique` sobre a expressão constante `(true)`, permitindo no máximo uma área por instalação. O rollback remove somente esse índice e preserva o registro da área.
2. `create_churches_table` cria `churches` depois de `areas`, `states` e `cities`, com UUID, `area_id` e `city_id` obrigatórios, endereço, status e timestamps. O rollback remove `churches` antes que a restrição singleton seja retirada.

Relacionamentos desta etapa:

- `Area::churches()` / `Church::area()`;
- `Church::city()` / `City::churches()`;
- `City::state()`.

Índices de `churches`:

- `(area_id, status)` para listagem por área e situação;
- `(city_id, status)` para filtros geográficos e situação;
- índice funcional único `(area_id, LOWER(name))` para impedir nomes duplicados sem diferenciar maiúsculas e minúsculas.

As foreign keys usam `RESTRICT`, pois área, cidade e igreja preservam histórico. Não existe migration ou rota de exclusão física para a área ou para igrejas.

## Catálogo geográfico do IBGE

A migration incremental `add_ibge_codes_to_states_and_cities_tables` acrescenta `ibge_code` anulável e único a `states` e `cities`. A coluna começa anulável para que a alteração de esquema seja segura em instalações que já possuam localidades; o `IbgeLocalitiesSeeder` associa registros legados compatíveis e preenche os códigos oficiais.

O catálogo é obtido exclusivamente pelo comando `php artisan ibge:download-localities`. O comando consulta a API de Localidades do IBGE, normaliza e ordena os dados e substitui atomicamente `database/data/ibge-localities.json`. O snapshot versionado registra a data da consulta, as URLs exatas e as contagens recebidas.

Migrations, Seeders e testes não consultam a rede. O Seeder lê o snapshot local e usa `upsert()` por `ibge_code`, preservando os identificadores internos referenciados por membros e igrejas. Atualizar o catálogo é uma ação explícita de desenvolvimento; depois dela, o novo snapshot deve ser revisado e versionado.

## Membros e vínculos com igrejas

A migration incremental `create_member_church_memberships_table` materializa a relação entre `members` e `churches` somente depois que as duas tabelas existem. Ela utiliza UUID, foreign keys com `RESTRICT`, status, indicação da igreja principal, data de entrada, data opcional de encerramento e timestamps.

Relacionamentos desta etapa:

- `Member::memberships()` / `MemberChurchMembership::member()`;
- `Church::memberMemberships()` / `MemberChurchMembership::church()`;
- `Member::churches()` / `Church::members()`;
- `Member::primaryMembership()` para o vínculo atual marcado como principal.

Índices e garantias no banco:

- `(member_id, status)` para a consulta dos vínculos de um membro;
- `(church_id, status)` para consultas por igreja;
- `member_church_memberships_active_church_unique`, índice parcial único sobre `(member_id, church_id)` quando o vínculo está ativo e não encerrado;
- `member_church_memberships_active_primary_unique`, índice parcial único sobre `member_id` quando o vínculo está ativo, não encerrado e marcado como principal;
- `member_church_memberships_dates_check`, que impede `ended_at` anterior a `joined_at`.

O cadastro do membro e seu primeiro vínculo ativo e principal ocorre na mesma transação. Um membro ativo não pode receber vínculo duplicado com a mesma igreja, possuir duas igrejas principais nem encerrar seu único vínculo ativo. A troca da igreja principal remove a marcação anterior na mesma transação. A inativação do membro encerra todos os vínculos ativos, preserva o histórico e não altera uma eventual conta em `users`.

O rollback remove primeiro `member_church_memberships`, antes de qualquer rollback de `churches` ou `members`, em respeito às dependências das foreign keys. Nenhum vínculo é excluído pelos fluxos da aplicação; encerramento e inativação são mudanças de status com registro de `ended_at`.
