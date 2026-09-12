# Plano de migrations do Genesis

O diagrama de domínio define a seguinte ordem topológica para a estrutura completa:

1. `states`;
2. `cities` (`state_id`);
3. `areas`;
4. `churches` (`area_id`, `city_id`);
5. `departments` (`church_id`);
6. `members` (`city_id`);
7. `member_church_memberships` (`member_id`, `church_id`);
8. `positions` (`area_id` opcional);
9. `member_position_assignments` (`member_church_membership_id`, `position_id`, `department_id` opcional);
10. `users` (`member_id` opcional e único);
11. `access_roles` (`area_id` opcional);
12. `permission_modules`;
13. `access_role_permissions` (`access_role_id`, `permission_module_id`);
14. `user_global_access_roles` (`user_id`, `access_role_id`);
15. `user_area_access_roles` (`user_id`, `area_id`, `access_role_id`);
16. `user_church_access_roles` (`user_id`, `church_id`, `access_role_id`, `department_id` opcional);
17. `audit_logs` (referência lógica e snapshot do ator, sem cascade para `users`).

## Núcleo desta entrega

Esta etapa materializa somente `states`, `cities`, `areas`, `members`, `users`, `access_roles`, `permission_modules`, `access_role_permissions` e `user_global_access_roles`, além das tabelas técnicas do Laravel.

`states`, `cities` e `members` são necessários para que a foreign key opcional `users.member_id` já aponte para a entidade definitiva. `areas` é necessário para preservar desde já `access_roles.area_id`. O administrador técnico usa apenas o escopo global, então `churches` e as tabelas de vínculos locais/regionais não são necessárias para autenticação e ficam para a etapa correspondente dos CRUDs.

As migrations usam UUID nos identificadores previstos pelo diagrama, foreign keys explícitas com exclusão restrita para dados históricos, unicidade funcional em `LOWER(users.username)` e índice parcial para impedir duas atribuições globais ativas iguais.
