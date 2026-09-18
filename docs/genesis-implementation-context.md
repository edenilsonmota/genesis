# Genesis — contexto de domínio e implementação

## Arquitetura

O Genesis é um monólito Laravel com PostgreSQL, Blade, Tailwind CSS e JavaScript nativo. A aplicação usa Models Eloquent, Form Requests, Controllers, Services transacionais, Policies/Gates e middleware. Não há API interna, repository genérico ou autorização no frontend como fonte de verdade.

A instalação possui no máximo uma área. A área agrupa igrejas e mantém os catálogos de departamentos e cargos.

## Pessoas, credenciais e organização

- `members` representa pessoas. Um membro não precisa ter acesso ao sistema.
- `users` representa credenciais. `users.member_id` é opcional e único; `username` existe somente em `users`.
- O administrador técnico é a única conta sem membro. Ele possui `is_global_administrator = true`, campo protegido contra mass assignment e mantido exclusivamente pelo Seeder.
- `member_church_memberships` guarda o vínculo e o histórico do membro em cada igreja.
- `departments` pertence à área e serve apenas para organização. Departamento nunca concede, restringe ou modifica autorização.
- `positions` pertence à área e pode ser categorizado por um departamento da mesma área.
- `member_position_assignments` atribui um cargo ao vínculo do membro com a igreja. Não possui `department_id`, pois o departamento é derivado do cargo.

Nomes de departamentos e cargos são normalizados e persistidos sempre em letras maiúsculas. Departamentos são únicos, sem diferenciar caixa, em toda a área; cargos são únicos, sem diferenciar caixa, na combinação área e departamento, incluindo o escopo sem departamento. Registros com histórico são inativados, nunca excluídos. Cargos fixos têm estrutura protegida.

## Acesso e permissões

Um cargo só participa da autenticação e da autorização quando todas estas condições são verdadeiras:

1. usuário, membro, igreja, vínculo, atribuição e cargo estão ativos;
2. as datas do vínculo e da atribuição incluem o dia atual;
3. cargo e igreja pertencem à mesma área;
4. `positions.grants_system_access = true`.

As permissões são ligadas diretamente ao cargo por `position_permissions`. A ausência de registro significa sem acesso, `read` significa leitura e `write` inclui leitura e escrita. A combinação cargo e módulo é única. Quando vários cargos são válidos na mesma igreja, prevalece o maior nível por módulo. Um cargo de uma igreja nunca concede direitos em outra igreja.

O `PermissionService` é a fonte central desse cálculo. Policies, Gates, middleware, Controllers e sidebar usam o resultado do serviço. O contexto da igreja ativa fica na sessão somente depois de validado. O middleware também revalida sessões abertas: remover um cargo, encerrar um vínculo, inativar uma entidade ou desmarcar a concessão de acesso produz revogação na requisição seguinte.

O administrador global ativo ignora o contexto e as permissões por cargo. Sua conta não depende de membro, igreja ou cargo e não pode ser alterada pelos fluxos web comuns.

### Contexto global e filtro de igrejas

O seletor do cabeçalho é compartilhado por todas as telas autenticadas e representa o contexto organizacional da sessão. Usuários comuns veem exclusivamente as igrejas para as quais possuem membro ativo, vínculo e atribuição de cargo válidos na data atual, com cargo que concede acesso ao sistema. Portanto, uma conta vinculada somente à Igreja X não recebe no seletor nem nas telas dados da Igreja Y.

O administrador global vê todas as igrejas ativas e dispõe também da opção **Visão geral**. Esse contexto não representa uma igreja: ele é reservado a consultas consolidadas, gráficos e telas administrativas futuras. Telas que exigem uma igreja específica devem solicitar ou impor esse escopo explicitamente; permissões e filtros do backend continuam sendo a fonte de verdade, jamais o seletor visual.

## Módulos e navegação

Cada módulo corresponde a uma tela ou capacidade real do backend. As categorias seguem a sidebar:

| Categoria | Chave | Tela/capacidade |
| --- | --- | --- |
| Principal | `dashboard` | Visão geral |
| Cadastros | `areas` | Área administrativa |
| Cadastros | `churches` | Igrejas |
| Cadastros | `members` | Membros e vínculos |
| Administração | `users` | Usuários |
| Administração | `positions` | Cargos e permissões |
| Administração | `departments` | Departamentos |
| Administração | `audit` | Auditoria |

As capacidades `finance.overview`, `finance.transactions` e `finance.tithes` possuem telas próprias na categoria Financeiro; **Visão financeira** é a primeira opção. `finance.reports` permanece reservada para evolução futura. O contexto ativo limita os dados à igreja selecionada, enquanto **Visão geral** consolida todas as áreas e igrejas exclusivamente para o administrador global. O domínio e a evolução do módulo estão em [`docs/finance`](finance/financial-implementation-context.md).

A categoria Administração exibe, nesta ordem: Usuários, Cargos e permissões e Departamentos. A matriz exibida em um cargo usa as mesmas categorias dos módulos cadastrados.

## Fluxos

### Atribuir cargo

O vínculo com a igreja deve estar ativo na data inicial. O cargo deve estar ativo e pertencer à área da igreja. A mesma combinação ativa não se repete. Encerrar preenche a data final e mantém o histórico; uma nova atribuição futura é permitida. O departamento é sempre derivado do cargo: um departamento inativo deixa de estar disponível para novos cargos, mas não invalida cargos e históricos já existentes nem participa da autorização.

### Criar usuário

O administrador escolhe um membro sem usuário que já possua cargo válido com acesso. O username é normalizado com `trim` e minúsculas, aceita 3 a 50 caracteres em `[a-z0-9._-]`, é único sem diferenciar caixa e rejeita `root`, `support` e `system`. A conta recebe uma senha temporária aleatória e `must_change_password = true`.

A senha temporária aparece somente no corpo da resposta de criação ou redefinição. Ela não entra em sessão, log ou auditoria; somente seu hash é persistido. Não há seletor de igreja, departamento, cargo ou permissão no formulário: tudo é herdado das atribuições válidas do membro.

### Revogar acesso

Desmarcar `grants_system_access` informa a quantidade de membros e usuários afetados, exige confirmação quando houver impacto e remove a matriz do cargo na mesma transação. A inativação de cargo e alterações que removem `users.write` também exigem confirmação quando aplicável. O sistema impede a remoção do último administrador local válido de uma igreja.

## Auditoria e segurança

`audit_logs` registra snapshots do ator, ação, recurso, rota, registro, escopo, IP e detalhes sanitizados. São auditadas alterações de área, igreja, membro, vínculo, departamento, cargo, matriz, atribuição, usuário, senha temporária e administrador global. Chaves relacionadas a senha, hash, token ou segredo são removidas defensivamente.

Foreign keys históricas usam `RESTRICT`; dependências técnicas da matriz usam `CASCADE`. Índices funcionais garantem unicidade case-insensitive e índices parciais garantem unicidade dos vínculos ativos. Validação de formulário não substitui constraints nem locks transacionais.

## Localidades

Estados e municípios usam `ibge_code` único. O comando `php artisan ibge:download-localities` consulta a API oficial do IBGE e atualiza o snapshot versionado. Migrations, Seeders e testes leem somente arquivos locais e nunca consultam serviços externos.
