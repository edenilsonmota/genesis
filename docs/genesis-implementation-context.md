# Genesis — Contexto de domínio e implementação em Laravel

## 1. Objetivo deste documento

Este documento define o contexto para criar o Genesis do zero: um sistema de gerenciamento de igrejas construído como monólito Laravel, com PHP, PostgreSQL, Blade, Tailwind CSS e JavaScript.

Ele deve ser lido pelo Codex antes de criar migrations, Models Eloquent, Form Requests, Services, Policies, middleware, controllers ou telas relacionadas a membros, usuários, grupos de acesso e permissões.

O diagrama relacional correspondente está no arquivo `genesis-domain-model.mmd`.

Este é um projeto novo, sem banco legado, dados anteriores ou funcionalidades que precisem ser preservadas. As migrations versionadas serão a fonte de verdade do banco desde o primeiro commit.

## 1.1 Stack e arquitetura inicial

- Laravel como aplicação full-stack e monolítica;
- Blade para renderização das páginas no servidor;
- PostgreSQL como banco de dados;
- autenticação web por `username` e senha, baseada em sessão e cookies;
- Vite para compilar os assets do frontend;
- Tailwind CSS como padrão de estilização;
- JavaScript nativo organizado em módulos pequenos;
- Laravel Sail como opção padrão para o ambiente Docker local;
- Pest ou PHPUnit para testes automatizados, respeitando o padrão criado no projeto.

Não criar uma API REST para a comunicação interna do painel. Os controllers Laravel devem retornar views Blade ou redirecionamentos. Uma API deve ser criada somente quando surgir um consumidor externo real, como aplicativo móvel ou integração de terceiros.

### Organização Laravel

Usar os recursos nativos do framework antes de criar abstrações próprias:

- **Models Eloquent:** relações, casts, scopes e regras simples ligadas à entidade;
- **Form Requests:** autorização e validação de entrada;
- **Controllers:** coordenam a requisição e delegam casos de uso, sem concentrar regras complexas;
- **Services:** apenas para regras de negócio, fluxos com várias etapas, transações ou reutilização real;
- **Policies e Gates:** autorização por recurso e capacidades globais;
- **Middleware:** contexto da igreja ativa e exigência de troca de senha;
- **Views Blade:** páginas organizadas por módulo, layouts, componentes e partials reutilizáveis;
- **JavaScript:** comportamento progressivo e módulos pequenos carregados pelo Vite;
- **Jobs/Events/Listeners:** somente quando houver necessidade concreta de processamento assíncrono ou efeitos desacoplados.

Não criar camada de repository sobre o Eloquent no início. Ela só deve existir se aparecer uma necessidade concreta que o Model, query scopes e services não resolvam com clareza. Também não criar Services que apenas repassam chamadas ao Model sem conter regra de negócio.

### Organização do frontend

Organizar as views aproximadamente desta forma:

```text
resources/views/
├── layouts/
├── components/
├── auth/
├── dashboard/
├── members/
├── users/
└── access-roles/
```

Regras para o frontend:

- usar HTML semântico e Blade Components para elementos realmente reutilizáveis;
- utilizar Tailwind CSS por meio do Vite, sem CDN em produção;
- manter os estilos e tokens globais no arquivo principal de CSS;
- usar JavaScript nativo para menus, modais, confirmações, máscaras e interações simples;
- instalar bibliotecas pequenas somente quando resolverem uma necessidade concreta;
- evitar jQuery e frameworks SPA;
- manter validações e autorização reais no servidor, ainda que a interface também forneça feedback;
- preservar acessibilidade, mensagens de erro, valores antigos dos formulários e estados vazios.

## 2. Princípio central

O sistema deve separar quatro conceitos:

1. **Membro:** pessoa cadastrada na organização religiosa.
2. **Usuário:** credencial que permite autenticação no sistema.
3. **Cargo ou função:** posição real exercida pelo membro na igreja, como Pastor, Diácono, Coordenador ou Professor.
4. **Grupo de acesso:** conjunto de permissões do sistema, como Secretaria, Financeiro ou Administrador local.

Esses conceitos não são equivalentes.

- Um membro pode existir sem usuário e, portanto, sem `username`.
- Nem todo usuário precisa representar um membro; o administrador técnico global pode ser uma exceção.
- O que concede capacidade de autenticação é a existência de uma linha em `users`, e não um campo ou status em `members`.
- Um cargo ministerial não concede acesso automaticamente.
- Um grupo de acesso não transforma o usuário em ocupante de um cargo ministerial.
- Um membro pode participar de mais de uma igreja e exercer funções diferentes em cada uma.
- Um usuário pode ter grupos de acesso diferentes conforme a igreja ativa.

## 3. Organização do domínio

### 3.1 Área

Uma área é um agrupamento administrativo de igrejas. Ela não deve ser usada como endereço da igreja.

Uma área pode:

- agrupar várias igrejas;
- possuir um catálogo de cargos ou funções;
- possuir grupos de acesso reutilizados pelas igrejas da área;
- servir como escopo de acesso para usuários responsáveis por todas as igrejas daquela área.

### 3.2 Igreja

Cada igreja pertence a uma área e possui seu próprio endereço, incluindo `city_id`. A cidade da igreja não deve ser inferida pela área, porque futuramente uma área poderá conter igrejas de municípios diferentes.

Toda informação futura que pertença a uma igreja deve possuir `church_id` diretamente ou ser ligada a uma entidade que permita derivá-lo sem ambiguidade.

### 3.3 Departamento

Departamento é uma subdivisão de uma igreja, como Infantil, Jovens, Música ou EBD.

Um departamento pertence a exatamente uma igreja. Um departamento não é cargo e não é grupo de acesso.

## 4. Membros e vínculo com igrejas

### 4.1 `members`

`members` representa a pessoa. Não deve possuir `church_id` como fonte definitiva de pertencimento.

Dados principais:

- nome;
- CPF normalizado;
- e-mail de contato;
- telefone;
- nascimento;
- sexo;
- endereço;
- status geral.

O `members.email` é somente um contato pessoal. Ele não deve ser utilizado como credencial de autenticação.

### 4.2 `member_church_memberships`

O pertencimento do membro a uma igreja deve ser representado por `member_church_memberships`.

Esse vínculo permite:

- membro sem acesso ao sistema;
- participação em mais de uma igreja;
- indicação de igreja principal;
- transferência de membro sem apagar histórico;
- datas de entrada e encerramento;
- inativação sem exclusão física.

Regras:

- não pode existir mais de um vínculo ativo entre o mesmo membro e a mesma igreja;
- um membro pode possuir no máximo uma igreja principal ativa;
- `ended_at` não pode ser anterior a `joined_at`;
- ao encerrar o vínculo principal, outra igreja deve ser escolhida como principal quando houver outro vínculo ativo;
- encerrar o vínculo do membro não deve apagar seu histórico, seus cargos antigos nem os registros de auditoria.

Estados iniciais sugeridos: `active`, `inactive` e `transferred`.

## 5. Cargos e funções dos membros

### 5.1 `positions`

`positions` cataloga cargos e funções reais, como:

- Pastor;
- Diácono;
- Secretário;
- Coordenador;
- Professor.

O catálogo pode pertencer a uma área. Cargos fixos do sistema recebem `fixed = true` e não podem ser excluídos.

### 5.2 `member_position_assignments`

O cargo deve ser atribuído ao vínculo do membro com a igreja, e não diretamente ao usuário.

O vínculo aponta para:

- `member_church_membership_id`;
- `position_id`;
- `department_id`, quando a função for departamental;
- período de exercício;
- status.

Regras:

- o vínculo de membro com a igreja precisa estar ativo na data inicial da atribuição;
- o departamento informado deve pertencer à mesma igreja do vínculo do membro;
- o cargo deve pertencer à área da igreja ou ser um cargo global permitido;
- o mesmo cargo encerrado pode ser atribuído novamente no futuro;
- `ended_at` não pode ser anterior a `started_at`.

## 6. Usuários e autenticação

### 6.1 `users`

`users` representa somente a conta de acesso.

Campos essenciais:

- `member_id`, opcional e único;
- `display_name`;
- `username`, usado no login, obrigatório, único e comparado sem diferenciar maiúsculas e minúsculas;
- `password`, contendo somente o hash produzido pelo Laravel;
- `remember_token`, opcional;
- `status`;
- `must_change_password`;
- `last_login_at`;
- timestamps.

Um membro pode possuir no máximo um usuário.

O `username` deve existir somente em `users`. Assim:

- membro comum: possui registro em `members`, mas não possui registro em `users`;
- membro com acesso: possui registro em `members` e uma conta correspondente em `users` por meio de `users.member_id`;
- administrador técnico: pode possuir registro somente em `users`, com `member_id = null`.

Regras sugeridas para o `username`:

- normalizar com `trim` e letras minúsculas antes de validar e persistir;
- aceitar de 3 a 50 caracteres;
- aceitar letras sem acento, números, ponto, hífen e sublinhado;
- não permitir espaços;
- reservar nomes técnicos como `system`, `root` e `support`;
- impedir duplicidade sem diferenciar maiúsculas e minúsculas;
- nunca permitir alteração silenciosa do `username` sem autorização e auditoria.

Para usuários locais comuns:

- `member_id` é obrigatório pela regra de negócio;
- o membro precisa ter vínculo ativo com a igreja que receberá o acesso;
- a criação do usuário e do primeiro vínculo de acesso deve acontecer na mesma transação.

O administrador técnico global pode existir sem `member_id`.

O Seeder inicial deve criar esse administrador global de forma idempotente usando, no mínimo, `GENESIS_ADMIN_NAME`, `GENESIS_ADMIN_USERNAME` e `GENESIS_ADMIN_PASSWORD` definidos no ambiente. Não manter credenciais administrativas reais fixas no código-fonte.

Se algum scaffolding de autenticação utilizado vier preparado para e-mail, o fluxo deve ser adaptado integralmente: formulário Blade, validação, tentativa de autenticação, mensagens e testes devem usar `username`. Não adicionar verificação de e-mail ao Model `User`.

Como `users` não possui e-mail, a recuperação automática de senha por e-mail fica fora do primeiro MVP. Inicialmente, um administrador autorizado poderá gerar uma nova senha temporária e forçar a troca no próximo acesso. Uma recuperação automática poderá ser projetada depois sem transformar o e-mail em credencial de login.

### 6.2 Criação de acesso para um membro

O fluxo de “Adicionar usuário” deve:

1. selecionar um membro existente;
2. impedir a operação se ele já possuir usuário;
3. informar e validar um `username` disponível;
4. selecionar a igreja na qual o acesso será concedido;
5. confirmar que o membro possui vínculo ativo com essa igreja;
6. selecionar pelo menos um grupo de acesso;
7. criar a conta e os vínculos em uma transação;
8. marcar `must_change_password = true`;
9. registrar a operação na auditoria.

Não utilizar o CPF como senha definitiva. Preferir senha temporária aleatória ou convite com token e validade. Nunca armazenar senha ou token em texto puro.

Na primeira etapa, as ações de editar e excluir usuários podem ficar desabilitadas no frontend até que as regras de segurança estejam concluídas.

## 7. Grupos de acesso e permissões

### 7.1 `access_roles`

`access_roles` representa grupos de acesso ao sistema. Exemplos:

- Secretaria;
- Financeiro;
- Consulta pastoral;
- Administrador local;
- Administrador global.

O nome “grupo de acesso” deve ser utilizado nas telas. O nome técnico recomendado é `access_roles` para não confundir essa estrutura com cargos ministeriais.

Campos relevantes:

- nome;
- descrição;
- `area_id` opcional;
- `fixed`;
- `is_administrator`;
- status.

Regras:

- grupos comuns pertencem a uma área e podem ser utilizados nas igrejas dessa área;
- grupos globais possuem `area_id = null`, são fixos e somente podem ser atribuídos no escopo global;
- `fixed` significa que o grupo é protegido contra edição ou exclusão estrutural;
- `is_administrator` significa acesso administrativo total dentro do escopo da atribuição;
- alterar permissões de um grupo compartilhado por uma área afeta todos os usuários que utilizam esse grupo naquela área;
- um grupo inativo não pode receber novas atribuições.

### 7.2 `permission_modules`

Cada módulo funcional da aplicação deve ser registrado por uma chave estável.

Chaves iniciais:

- `dashboard`;
- `members`;
- `users`;
- `access_roles`;
- `positions`;
- `areas`;
- `churches`;
- `departments`;
- `audit`.

O módulo não representa apenas uma tela. Ele representa uma capacidade protegida no servidor e reutilizada pelo frontend.

### 7.3 `access_role_permissions`

Cada grupo pode receber um nível por módulo:

- ausência de registro: sem acesso;
- `read`: somente leitura;
- `write`: leitura e escrita.

A combinação `(access_role_id, permission_module_id)` deve ser única.

Quando um usuário possuir vários grupos válidos no mesmo contexto, prevalece o maior nível encontrado. `write` inclui `read`.

Para o MVP, não criar ações individuais como `create`, `update`, `delete`, `approve` e `export`. A separação poderá ser feita futuramente quando um caso real exigir granularidade maior.

## 8. Escopos de acesso

Existem três escopos explícitos. Eles não devem ser representados por uma relação polimórfica genérica.

### 8.1 Global

`user_global_access_roles` é reservado para administração de todo o sistema.

- somente grupos globais e fixos podem ser usados;
- um administrador global não depende de igreja ativa;
- a atribuição deve ser extremamente restrita e auditada.

### 8.2 Área

`user_area_access_roles` permite acesso a todas as igrejas pertencentes à área indicada.

Esse vínculo deve ser usado apenas quando a regra de negócio exigir responsabilidade regional, como um pastor ou supervisor de área. Usuários comuns não devem receber acesso de área.

### 8.3 Igreja

`user_church_access_roles` é o vínculo padrão para usuários locais.

Ele relaciona:

- usuário;
- igreja;
- grupo de acesso;
- departamento opcional;
- período;
- status.

`department_id` somente deve ser informado quando o acesso aos dados precisar ficar restrito àquele departamento. O simples fato de o membro servir em um departamento deve ser registrado em `member_position_assignments`, não nesta tabela.

## 9. Cálculo das permissões efetivas

Ao acessar uma igreja, a aplicação deve calcular as permissões nesta ordem:

1. validar se o usuário está ativo;
2. verificar grupos globais ativos;
3. verificar grupos de área ativos para a área da igreja atual;
4. verificar grupos ativos atribuídos diretamente à igreja atual;
5. descartar vínculos ainda não iniciados ou já encerrados;
6. unir as permissões dos grupos encontrados;
7. escolher o maior nível por módulo;
8. aplicar eventual restrição de departamento;
9. retornar o mapa de permissões efetivas.

Um grupo com `is_administrator = true` concede acesso total apenas dentro do seu escopo:

- global: todo o sistema;
- área: todas as igrejas da área;
- igreja: somente a igreja vinculada.

Exemplo de retorno:

```json
{
  "churchId": "uuid-da-igreja",
  "isAdministrator": false,
  "permissions": {
    "dashboard": "read",
    "members": "write",
    "users": null,
    "churches": "read"
  }
}
```

As views Blade podem usar `@can`, componentes autorizados e dados fornecidos pelo backend para ocultar menus e desabilitar ações. A autorização real deve continuar sendo executada no Laravel por Policies, Gates ou middleware.

## 10. Contexto da igreja ativa

Um usuário com acesso a várias igrejas deve selecionar a igreja ativa após o login.

Regras:

- o navegador nunca pode escolher livremente uma igreja sem validação;
- o Laravel deve confirmar que o usuário possui acesso global, por área ou diretamente à igreja solicitada;
- todas as consultas locais devem ser filtradas pela igreja ativa;
- o identificador da igreja ativa deve ser mantido na sessão depois de validado;
- receber `church_id` no body não é autorização suficiente;
- Models, query scopes, Policies e Services devem impedir leitura ou alteração entre igrejas diferentes;
- a igreja ativa e as permissões efetivas devem ser disponibilizadas às views Blade por middleware ou View Composer;
- não introduzir JWT ou autenticação por token para o painel web sem uma necessidade concreta.

## 11. Integridade e histórico

### 11.1 Índices parciais

Criar índices parciais para impedir duplicidades somente entre vínculos ativos.

Como esses índices possuem cláusula `WHERE`, as migrations PostgreSQL poderão usar `DB::statement()` quando o Schema Builder não expressar a restrição necessária. Criar também um índice único funcional em `LOWER(users.username)` para garantir login case-insensitive; esse índice é a restrição de unicidade real do `username`, evitando depender apenas de `$table->unique()`.

Exemplos conceituais:

```sql
UNIQUE (member_id, church_id)
WHERE status = 'active' AND ended_at IS NULL;

UNIQUE (member_id)
WHERE status = 'active' AND ended_at IS NULL AND is_primary = true;

UNIQUE (user_id, church_id, access_role_id)
WHERE status = 'active' AND ended_at IS NULL AND department_id IS NULL;

UNIQUE (user_id, church_id, access_role_id, department_id)
WHERE status = 'active' AND ended_at IS NULL AND department_id IS NOT NULL;
```

Aplicar a mesma ideia aos vínculos globais, de área e aos cargos dos membros.

### 11.2 Exclusões

- não excluir fisicamente membros, igrejas, áreas, usuários ou vínculos com histórico;
- utilizar status e data de encerramento;
- usar `RESTRICT` em referências históricas;
- usar `CASCADE` somente em dependências puramente técnicas, como permissões de um grupo removível;
- proteger o último administrador global ativo;
- proteger o último administrador local ativo de cada igreja;
- a remoção de acesso não deve excluir o membro.

### 11.3 Consistência de datas

Todos os vínculos temporais devem validar:

```text
ended_at IS NULL OR started_at IS NULL OR ended_at >= started_at
```

Um vínculo é efetivo somente quando:

```text
status = active
AND (started_at IS NULL OR started_at <= hoje)
AND (ended_at IS NULL OR ended_at >= hoje)
```

## 12. Segurança

- utilizar o `Hash` do Laravel com o algoritmo configurado no projeto;
- manter `password` e `remember_token` ocultos na serialização do Model `User`;
- autenticar exclusivamente por `username` e senha;
- normalizar o `username` antes da tentativa de login;
- utilizar rate limiting do Laravel nas tentativas de login;
- usar mensagens de autenticação que não revelem se o `username` existe;
- exigir troca da senha temporária antes de liberar as demais rotas;
- invalidar sessões quando o usuário for inativado;
- não confiar nas permissões enviadas pelo frontend;
- sanitizar senha, tokens, CPF e dados sensíveis da auditoria;
- executar criação de usuário e atribuição inicial com `DB::transaction()`;
- auditar concessão, alteração e encerramento de acessos.

## 13. Auditoria

`audit_logs` deve manter snapshots do ator para continuar legível mesmo que o usuário seja inativado.

Registrar pelo menos:

- ator;
- `username` do ator no momento da ação;
- ação;
- recurso;
- rota;
- registro afetado;
- escopo global, área ou igreja;
- IP;
- detalhes sanitizados;
- data e hora.

Não criar exclusão em cascata entre usuário e auditoria.

## 14. Fluxos fundamentais

### 14.1 Cadastrar membro

1. cadastrar a pessoa;
2. criar seu primeiro `member_church_membership`;
3. marcar a igreja principal;
4. não criar usuário automaticamente;
5. registrar auditoria.

### 14.2 Transformar membro em usuário

1. selecionar membro sem usuário;
2. definir um `username` válido e disponível;
3. selecionar igreja com vínculo ativo;
4. selecionar grupo de acesso válido para a área;
5. criar usuário e vínculo de acesso na mesma transação;
6. gerar senha temporária segura;
7. exigir troca no primeiro acesso;
8. registrar auditoria.

### 14.3 Transferir membro

1. encerrar ou alterar o vínculo anterior conforme a regra pastoral;
2. preservar cargos e histórico anteriores;
3. criar vínculo com a nova igreja;
4. atualizar a igreja principal;
5. revisar separadamente os acessos do usuário;
6. não transferir automaticamente permissões administrativas.

### 14.4 Encerrar acesso

1. encerrar o vínculo de acesso específico;
2. verificar se ainda restam outros acessos válidos;
3. inativar o usuário somente quando não houver mais necessidade de login;
4. preservar membro, vínculos e auditoria;
5. impedir a remoção do último administrador do escopo.

## 15. Matriz inicial de exemplo

| Módulo | Secretaria | Financeiro | Administrador local |
| --- | --- | --- | --- |
| Dashboard | Leitura | Leitura | Escrita |
| Membros | Escrita | Leitura | Escrita |
| Usuários | Sem acesso | Sem acesso | Escrita |
| Grupos de acesso | Sem acesso | Sem acesso | Escrita |
| Cargos | Leitura | Sem acesso | Escrita |
| Igrejas | Leitura | Leitura | Escrita |
| Auditoria | Sem acesso | Sem acesso | Escrita |

Essa matriz é somente um Seeder inicial e poderá ser configurada posteriormente.

## 16. Ordem recomendada de implementação

1. Criar o projeto Laravel com Blade, Vite, Tailwind CSS e JavaScript e configurar PostgreSQL.
2. Configurar o ambiente local, preferencialmente com Sail, e validar a aplicação, a compilação dos assets e os testes iniciais.
3. Criar enums PHP e convenções de status compartilhadas pelo domínio.
4. Criar migrations e Models de estados, cidades, áreas, igrejas e departamentos.
5. Criar migrations e Models de membros e `member_church_memberships`.
6. Criar cargos ministeriais e `member_position_assignments`.
7. Adaptar a migration inicial de usuários para conter `member_id`, `display_name`, `username`, `password`, `remember_token`, `status`, `must_change_password` e `last_login_at` e personalizar o login do starter kit.
8. Criar módulos de permissão, grupos de acesso e a matriz `access_role_permissions`.
9. Criar os vínculos de acesso global, por área e por igreja.
10. Implementar o serviço central de cálculo de permissões efetivas.
11. Implementar Policies, Gates e middleware de autorização.
12. Implementar seleção e persistência da igreja ativa na sessão.
13. Disponibilizar usuário, igreja ativa e permissões para as views Blade por middleware ou View Composer.
14. Implementar cadastro de membro sem usuário e o fluxo de transformar membro em usuário.
15. Implementar as páginas Blade de membros, usuários, grupos e matriz de permissões.
16. Implementar cargos, departamentos, auditoria e proteção dos últimos administradores.
17. Completar os testes de isolamento, autorização, integridade e fluxos transacionais.

Como o projeto começará do zero, gerar as migrations já com a estrutura final e na ordem correta de dependências. Não criar migrations de conversão, renomeação ou transporte de dados de um sistema anterior.

## 17. Critérios de aceite mínimos

- é possível cadastrar membro sem criar usuário;
- membro comum não possui `username` nem linha em `users`;
- um membro pode possuir vínculos históricos com igrejas;
- um membro possui no máximo um usuário;
- o login aceita `username` e senha, não e-mail;
- `username` não pode ser duplicado com diferença apenas de maiúsculas e minúsculas;
- um usuário local não recebe acesso a igreja sem vínculo ativo do membro;
- cargo ministerial não concede acesso automaticamente;
- usuário pode ter grupos diferentes em igrejas diferentes;
- usuário de uma igreja não consegue acessar dados de outra igreja;
- usuário de área acessa somente igrejas pertencentes à sua área;
- administrador local não recebe poderes globais;
- a união de grupos sempre utiliza o maior nível por módulo;
- vínculo encerrado deixa de conceder permissão;
- o mesmo grupo pode ser atribuído novamente depois do encerramento anterior;
- o último administrador ativo de um escopo não pode ser removido;
- permissões são verificadas no Laravel e não apenas pela visibilidade de elementos nas views Blade;
- operações críticas são transacionais e auditadas;
- as rotas web possuem autorização explícita e nomes consistentes;
- caso uma API pública seja criada futuramente, ela documenta autenticação, contexto da igreja e permissões exigidas com OpenAPI.

## 18. Testes obrigatórios

Criar testes unitários e, principalmente, Feature Tests para:

- membro sem usuário;
- tentativa de criar dois usuários para o mesmo membro;
- autenticação por `username` válido;
- rejeição de login por e-mail;
- normalização e unicidade case-insensitive do `username`;
- criação idempotente do administrador global com `username` definido no ambiente;
- tentativa de conceder acesso a igreja sem vínculo do membro;
- união de permissões de vários grupos;
- isolamento entre igrejas;
- acesso herdado da área;
- administrador local limitado à igreja;
- administrador global;
- vínculo com início futuro;
- vínculo expirado;
- reatribuição depois do encerramento;
- departamento pertencente a outra igreja;
- cargo pertencente a outra área;
- proteção do último administrador;
- rollback da criação de usuário quando a atribuição de acesso falhar.

## 19. Restrições para o Codex

- Considerar que o Genesis será criado do zero em Laravel; não procurar nem reaproveitar uma implementação anterior em NestJS.
- Antes de implementar cada etapa, inspecionar o esqueleto atual do projeto e apresentar um plano curto com os arquivos que serão criados ou alterados.
- Usar migrations, Models Eloquent, Form Requests, Services, Policies, middleware, controllers e views Blade nos papéis definidos neste documento.
- Preferir recursos nativos do Laravel e evitar camadas sem necessidade comprovada.
- Não criar API REST para o próprio painel quando o fluxo puder ser atendido por controllers, views Blade e formulários tradicionais.
- Usar autenticação web por sessão no painel administrativo.
- Personalizar o fluxo de autenticação para solicitar `username` no login e não utilizar `email` como identificador.
- Não criar uma tabela genérica polimórfica para todos os escopos.
- Não misturar cargo ministerial com grupo de acesso.
- Não criar `members.church_id`; o pertencimento deve existir somente em `member_church_memberships`.
- Não confiar somente em ocultação de botões no frontend.
- Não excluir histórico para resolver duplicidade de vínculo.
- Não implementar permissões novas fora do escopo sem justificar uma necessidade concreta.
- Manter nomes de tabelas e colunas em `snake_case`.
- Usar nomes convencionais do Laravel para Models, relações, Policies e rotas.
- Criar Seeders idempotentes para módulos e grupos fixos iniciais.
- Utilizar Blade Components para elementos reutilizáveis, sem transformar cada pequeno trecho em componente.
- Utilizar Tailwind CSS integrado ao Vite e evitar CSS inline ou dependência de CDN em produção.
- Preferir JavaScript nativo; instalar uma biblioteca somente quando houver benefício concreto.
- Não exigir Swagger ou OpenAPI para rotas web Blade; usar OpenAPI apenas se uma API real for criada.
- Ao final de cada etapa, listar migrations criadas, arquivos alterados, testes executados e pendências encontradas.
